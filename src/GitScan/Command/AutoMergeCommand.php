<?php
namespace GitScan\Command;

use GitScan\AutoMergeRule;
use GitScan\GitRepo;
use GitScan\Util\ArrayUtil;
use GitScan\Util\Filesystem;
use GitScan\Util\Process as ProcessUtil;
use GitScan\Util\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;


class AutoMergeCommand extends BaseCommand {

  /**
   * @var Filesystem
   */
  var $fs;

  /**
   * @param string|null $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('automerge')
      ->setDescription('Automatically match pull-requests to repos and merge them')
      ->setHelp('
      Suppose you have local build with a handful of repos, and you have a list
      list of PRs that you wish to test.

      git clone https://github.com/example/foo.git mylocalbuild/foo
      git clone https://github.com/example/bar.git mylocalbuild/bar
      git scan automerge https://github.com/example/foo/pull/1234 https://github.com/example/bar/pull/5678

      If the branch mode is "current", then changes will be applied on the current branches.
      If the branch mode is "upstream", then a new branch (e.g. "master-automerge") will be
      created based on the upstream-tracking branch.

      The URLs passed to automerge may be Github PR URLs.
      If Github is not available, you may use expressions like:

      git scan automerge ;upstream-url-regex;patchfile
      git scan automerge ;foo-bar;http://example.com/foo-bar/my.patch
      ')
      ->addOption('branch', NULL, InputOption::VALUE_REQUIRED, 'How to handle branching (current|upstream)', 'upstream')
      ->addOption('suffix', NULL, InputOption::VALUE_REQUIRED, 'The name to append when making new branches', '-automerge')
      ->addOption('path', NULL, InputOption::VALUE_REQUIRED, 'The local base path to search', getcwd())
      ->addOption('force', 'f', InputOption::VALUE_NONE, 'If necessary, destroy local branches')
      ->addArgument('url', InputArgument::IS_ARRAY, 'The URL(s) of any PRs to merge');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setOption('path', $this->fs->toAbsolutePath($input->getOption('path')));
    $this->fs->validateExists($input->getOption('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $rules = array();
    foreach ($input->getArgument('url') as $url) {
      $rule = new AutoMergeRule($url);
      $rules[] = $rule;
    }

    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getOption('path'));

    $checkouts = array(); // array(string $absDir => TRUE)

    foreach ($gitRepos as $gitRepo) {
      /** @var GitRepo $gitRepo */
      $relPath = $this->fs->makePathRelative($gitRepo->getPath(), $input->getOption('path'));

      foreach (array_keys($rules) as $ruleId) {
        /** @var AutoMergeRule $rule */
        $rule = $rules[$ruleId];
        $rule->fetch();
        if ($rule->isMatch($gitRepo)) {
          unset($rules[$ruleId]);

          if (!isset($checkouts[$gitRepo->getPath()])) {
            $this->checkoutAutomergeBranch($input, $output, $gitRepo, $relPath);
            $checkouts[$gitRepo->getPath()] = 1;
          }

          $output->writeln("<info>In {$relPath}, merge \"{$rule->getExpr()}\".</info>");
          $process = $gitRepo->applyPatch($rule->getPatch());
          $output->writeln($process->getOutput());
        }
      }
    }

    foreach ($rules as $ruleId => $rule) {
      $output->writeln("<error>Failed to match {$rule->getExpr()} to a local repo. Ensure that one of the repos has the proper remote URL.</error>");
    }

    if (!empty($rules)) {
      return 1;
    }
  }

  /**
   * Ensure that we've checked out a branch where we can do merges.
   *
   * @param \GitScan\GitRepo $gitRepo
   * @return string
   *   Local branch name
   */
  protected function checkoutAutomergeBranch(InputInterface $input, OutputInterface $output, GitRepo $gitRepo, $repoName) {
    $mode = $input->getOption('branch');
    $suffix = $input->getOption('suffix');

    $localBranch = $gitRepo->getLocalBranch();

    switch ($mode) {
      case 'current':
        $output->writeln("<info>In {$repoName}, keeping current branch \"$localBranch\".</info>");
        return;

      case 'upstream':
        $upstreamBranch = $gitRepo->getUpstreamBranch();
        if (!$upstreamBranch) {
          throw new \RuntimeException("Cannot automerge. In {$gitRepo->getPath()}, failed to find upstream branch for \"$localBranch\"");
        }
        $suffixedBranchName = basename($upstreamBranch) . $suffix;

        Process::runOk($gitRepo->command("git fetch " . dirname($upstreamBranch)));

        if (!in_array($suffixedBranchName, $gitRepo->getBranches())) {
          $output->writeln("<info>In {$repoName}, create branch \"$suffixedBranchName\" using \"$upstreamBranch\".</info>");
        }
        else {
          $output->writeln("<error>In {$repoName}, the branch \"$suffixedBranchName\" already exists.</error>");
          $output->writeln("<error>To proceed with automerge, we must destroy \"$suffixedBranchName\" and recreate it (based on $upstreamBranch).</error>");

          $helper = $this->getHelper('question');
          $question = new ConfirmationQuestion("<question>Proceed with re-creating \"$suffixedBranchName\"? [y/n]</question> ", false);
          if ($input->getOption('force')) {
            $output->writeln($question->getQuestion() . "y");
          }
          elseif (!$helper->ask($input, $output, $question)) {
            throw new \RuntimeException("In {$repoName}, the branch \"$suffixedBranchName\" already exists.");
          }
          $commit = $gitRepo->getCommit();
          Process::runOk($gitRepo->command("git checkout $commit"));
          $gitRepo->command("git branch -D $suffixedBranchName")->run();
        }

        Process::runOk($gitRepo->command("git checkout $upstreamBranch -b $suffixedBranchName"));

        return $suffixedBranchName;

      default:
        throw new \RuntimeException("Unrecognized checkout mode: $mode");
    }
  }

}
