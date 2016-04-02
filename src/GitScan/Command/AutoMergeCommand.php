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
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
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
      of pull-requests that you wish to test.

      git clone https://github.com/example/foo.git mylocalbuild/foo
      git clone https://github.com/example/bar.git mylocalbuild/bar
      git scan automerge https://github.com/example/foo/pull/1234 https://github.com/example/bar/pull/5678

      The URLs passed to automerge may be Github PR URLs.
      If Github is not available, you may use expressions like:

      git scan automerge ;upstream-url-regex;patchfile
      git scan automerge ;foo-bar;http://example.com/foo-bar/my.patch
      ')
      ->addOption('rebuild', 'r', InputOption::VALUE_NONE, 'When applying patches, rebuild a clean history based on upstream. Destroy local changes.')
      ->addOption('keep', NULL, InputOption::VALUE_NONE, 'When applying patches, keep the current branch. Preserve local changes.')
      ->addOption('new', NULL, InputOption::VALUE_NONE, 'When applying patches, create a new merge branch.')
      ->addOption('path', NULL, InputOption::VALUE_REQUIRED, 'The local base path to search', getcwd())
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

          $output->writeln("In \"<info>{$relPath}</info>\", apply \"<info>{$rule->getExpr()}</info>\".");
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
   */
  protected function checkoutAutomergeBranch(InputInterface $input, OutputInterface $output, GitRepo $gitRepo, $repoName) {
    if ($gitRepo->hasUncommittedChanges(TRUE)) {
      throw new \RuntimeException("Cannot apply patch");
    }

    $localBranch = $gitRepo->getLocalBranch();
    $upstreamBranch = $gitRepo->getUpstreamBranch();
    $newLocalBranch = "merge-{$localBranch}-" . date('YmdHis');
    $mode = $this->getAutomergeMode($input, $output, $repoName, $localBranch, $upstreamBranch, $newLocalBranch);

    switch ($mode) {
      case 'keep':
        $output->writeln("In \"<info>$repoName</info>\", keep the current branch \"<info>$localBranch</info>\".");
        return; // OK, nothing to do.

      case 'rebuild':
        $backupBranch = $localBranch . '-' . date('YmdHis') . '-' . rand(0,100);
        $output->writeln("In \"<info>$repoName</info>\", rename \"<info>$localBranch</info>\" to \"<info>$backupBranch</info>\".");
        Process::runOk($gitRepo->command("git branch -m $localBranch $backupBranch"));
        $output->writeln("In \"<info>$repoName</info>\", create \"<info>$localBranch</info>\" using \"<info>$upstreamBranch</info>\".");
        Process::runOk($gitRepo->command("git checkout $upstreamBranch -b $localBranch"));
        return;

      case 'new':
        $output->writeln("In \"<info>$repoName</info>\", create \"<info>$newLocalBranch</info>\" using \"<info>$upstreamBranch</info>\".");
        Process::runOk($gitRepo->command("git checkout $upstreamBranch -b $newLocalBranch"));
        return;

      case 'abort':
        // Pass through...

      default:
        throw new \RuntimeException("Could not decide how to base local branch.");
    }
  }

  public function getAutomergeMode(InputInterface $input, OutputInterface $output, $repoName, $localBranch, $upstreamBranch, $newLocalBranch) {
    if ($input->getOption('rebuild')) {
      return 'rebuild';
    }
    if ($input->getOption('keep')) {
      return 'keep';
    }
    if (!$input->isInteractive()) {
      return 'abort';
    }

    $helper = $this->getHelper('question');
    $question = new ChoiceQuestion(
      "In \"<info>$repoName</info>\", the current branch is \"<info>$localBranch</info>\". What would you like to do it?",
      array(
        'keep' => "Keep the current branch \"<info>$localBranch</info>\" along with any local changes. Apply patches on top.",
        'rebuild' => "Rebuild the branch \"<info>$localBranch</info>\" based on \"<info>$upstreamBranch</info>\". Destroy any local changes. Apply changes on top.",
        'new' => "Create a new branch \"<info>$newLocalBranch</info>\" based on \"<info>$upstreamBranch</info>\". Apply changes on top.",
        'abort' => "Abort the auto-merge process. (default)",
      ),
      'abort'
    );

    return $helper->ask($input, $output, $question);
  }

}
