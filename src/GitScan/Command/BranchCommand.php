<?php
namespace GitScan\Command;

use GitScan\GitRepo;
use GitScan\Util\ArrayUtil;
use GitScan\Util\Filesystem;
use GitScan\Util\Process as ProcessUtil;
use GitScan\Util\Process;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class BranchCommand extends BaseCommand {

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
      ->setName('branch')
      ->setDescription('Create branches across repos')
      ->addOption('path', NULL, InputOption::VALUE_REQUIRED, 'The local base path to search', getcwd())
      ->addOption('prefix', 'p', InputOption::VALUE_NONE, 'Autodetect prefixed variations')
      ->addOption('dry-run', NULL, InputOption::VALUE_NONE, 'Display what would be done')
      ->addArgument('branchName', InputArgument::REQUIRED, 'The name of the new branch(es)')
      ->addArgument('head', InputArgument::REQUIRED, 'The name of the head(s) to use for new branch(s). *Must* be a branch name.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setOption('path', $this->fs->toAbsolutePath($input->getOption('path')));
    $this->fs->validateExists($input->getOption('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getOption('path'));

    $g = new \GitScan\GitBranchGenerator($gitRepos);
    $g->generate(
      $input->getArgument('head'),
      $input->getArgument('branchName'),
      $input->getOption('prefix'),
      function (GitRepo $gitRepo, $oldBranch, $newBranch) use ($input, $output) {
        $relPath = $this->fs->makePathRelative($gitRepo->getPath(), $input->getOption('path'));
        $output->writeln("In \"<info>{$relPath}</info>\", make branch \"<info>$newBranch</info>\" from \"<info>$oldBranch</info>\"");
        if (!$input->getOption('dry-run')) {
          Process::runOk($gitRepo->command(sprintf(
            "git branch %s %s",
            escapeshellarg($newBranch),
            escapeshellarg($oldBranch)
          )));
        }
      });
  }

}
