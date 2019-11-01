<?php
namespace GitScan\Command;

use GitScan\GitRepo;
use GitScan\Util\Filesystem;
use GitScan\Util\ProcessBatch;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class BranchCommand extends BaseCommand {

  /**
   * @var \GitScan\Util\Filesystem
   */
  public $fs;

  /**
   * @param string|NULL $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('branch')
      ->setDescription('Create branches across repos')
      ->addOption('path', NULL, InputOption::VALUE_REQUIRED, 'The local base path to search', getcwd())
      ->addOption('prefix', 'p', InputOption::VALUE_NONE, 'Autodetect prefixed variations')
      ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete fully merged branches')
      ->addOption('force-delete', 'D', InputOption::VALUE_NONE, 'Delete branch (even if not merged)')
      ->addOption('dry-run', 'T', InputOption::VALUE_NONE, 'Display what would be done')
      ->addArgument('branchName', InputArgument::REQUIRED, 'The name of the new branch(es)')
      ->addArgument('head', InputArgument::OPTIONAL, 'The name of the head(s) to use for new branch(s). *Must* be a branch name.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setOption('path', $this->fs->toAbsolutePath($input->getOption('path')));
    $this->fs->validateExists($input->getOption('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    if ($input->getOption('delete') || $input->getOption('force-delete')) {
      return $this->executeDelete($input, $output);
    }
    else {
      return $this->executeCreate($input, $output);
    }
  }

  protected function executeCreate(InputInterface $input, OutputInterface $output) {
    if (!$input->getArgument('head')) {
      throw new \RuntimeException("Missing argument \"head\". Please specify the name of original base branch.");
    }

    $helper = $this->getHelper('question');
    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getOption('path'));
    $batch = new ProcessBatch('Creating branch(es)...');
    $self = $this;

    $g = new \GitScan\GitBranchGenerator($gitRepos);
    $g->generate(
      $input->getArgument('head'),
      $input->getArgument('branchName'),
      $input->getOption('prefix'),
      function (GitRepo $gitRepo, $oldBranch, $newBranch) use ($input, $output, $helper, &$batch, $self) {
        $relPath = $self->fs->makePathRelative($gitRepo->getPath(), $input->getOption('path'));

        $question = new ChoiceQuestion("\n<comment>In \"<info>{$relPath}</info>\", found existing branch \"<info>$oldBranch</info>\". Create a new branch \"<info>$newBranch</info>\"?</comment>",
          array("y" => "yes (default)", "n" => "no", "c" => "customize"),
          "y"
        );
        $mode = $helper->ask($input, $output, $question);
        if ($mode === 'n') {
          return;
        }
        if ($mode === 'c') {
          $newBranch = $helper->ask($input, $output, new Question("<comment>Enter the new branch name:</comment> "));
          if (!$newBranch) {
            $output->writeln("Skipped");
            return;
          }
        }

        $label = "In \"<info>{$relPath}</info>\", make branch \"<info>$newBranch</info>\" from \"<info>$oldBranch</info>\"";
        $batch->add($label, $gitRepo->command(sprintf(
          "git branch %s %s",
          escapeshellarg($newBranch),
          escapeshellarg($oldBranch)
        )));
      });

    $batch->runAllOk($output, $input->getOption('dry-run'));
  }

  protected function executeDelete(InputInterface $input, OutputInterface $output) {
    $helper = $this->getHelper('question');
    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getOption('path'));
    $batch = new ProcessBatch('Deleting branch(es)...');

    $branchName = $input->getArgument('branchName');
    $branchQuoted = preg_quote($branchName, '/');

    foreach ($gitRepos as $gitRepo) {
      /** @var \GitScan\GitRepo $gitRepo */
      $relPath = $this->fs->makePathRelative($gitRepo->getPath(), $input->getOption('path'));

      $branches = $gitRepo->getBranches();
      $matches = array();
      if ($input->getOption('prefix')) {
        $matches = preg_grep("/[-_]$branchQuoted\$/", $branches);
      }
      if (in_array($branchName, $branches)) {
        $matches[] = $branchName;
      }

      // TODO: Verify that user wants to delete these.

      foreach ($matches as $match) {
        $label = "In \"<info>{$relPath}</info>\", delete branch \"<info>$match</info>\" .";
        $modifier = $input->getOption('force-delete') ? '-D' : '-d';
        $batch->add($label, $gitRepo->command("git branch $modifier " . escapeshellarg($match)));
      }
    }

    $batch->runAllOk($output, $input->getOption('dry-run'));
  }

}
