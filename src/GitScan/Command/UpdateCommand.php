<?php
namespace GitScan\Command;

use GitScan\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends BaseCommand {

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
      ->setName('update')
      ->setAliases(array('up'))
      ->setDescription('Execute fast-forward merges on all nested repositories')
      ->setHelp('Execute fast-forward merges on all nested repositories (which are already amenable to fast-forwarding)')
      ->addArgument('path', InputArgument::IS_ARRAY, 'The local base path to search', array(getcwd()));
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setArgument('path', $this->fs->toAbsolutePaths($input->getArgument('path')));
    $this->fs->validateExists($input->getArgument('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $statusCode = 0;

    $output->writeln("<comment>[[ Finding repositories ]]</comment>");
    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getArgument('path'));

    $output->writeln("<comment>[[ Fast-forwarding ]]</comment>");
    foreach ($gitRepos as $gitRepo) {
      /** @var \GitScan\GitRepo $gitRepo */
      $path = $this->fs->formatPrettyPath($gitRepo->getPath(), $input->getArgument('path'));
      if ($gitRepo->getUpstreamBranch() === NULL) {
        $output->writeln("<comment>Skip <info>$path</info>: No upstream tracking branch</comment>");
      }
      elseif (!$gitRepo->isLocalFastForwardable()) {
        $output->writeln("<comment>Skip <info>$path</info>: Cannot be fast-forwarded</comment>");
      }
      else {
        $output->writeln("<comment>Fast-forward <info>$path</info> (<info>{$gitRepo->getLocalBranch()}</info> <= <info>{$gitRepo->getUpstreamBranch()}</info>)...</comment>");
        $process = $gitRepo->command('git pull --ff-only');
        $process->run();
        if (!$process->isSuccessful()) {
          $output->writeln("<error>Failed to update {$gitRepo->getPath()}/<error>");
          if ($process->getOutput()) {
            $output->writeln("//---------- BEGIN STDOUT ----------\\\\");
            $output->writeln($process->getOutput(), OutputInterface::OUTPUT_RAW);
            $output->writeln("\\\\----------- END STDOUT -----------//");
          }
          if ($process->getErrorOutput()) {
            $output->writeln("//---------- BEGIN STDERR ----------\\\\");
            $output->writeln($process->getErrorOutput(), OutputInterface::OUTPUT_RAW);
            $output->writeln("\\\\----------- END STDERR -----------//");
          }
          $statusCode = 1;
        }
      }

    }
    return $statusCode;
  }

}
