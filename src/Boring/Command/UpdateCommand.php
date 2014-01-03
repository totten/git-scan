<?php
namespace Boring\Command;

use Boring\Application;
use Boring\GitRepo;
use Boring\Util\ArrayUtil;
use Boring\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class UpdateCommand extends BaseCommand {

  /**
   * @var Filesystem
   */
  var $fs;

  /**
   * @param string|null $name
   * @param array $parameters list of configuration parameters to accept ($key => $label)
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('update')
      ->setDescription('Execute routine updates on any boring repositories')
      ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'The local base path to search', getcwd());
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $root = $this->fs->toAbsolutePath($input->getOption('root'));
    if (!$this->fs->exists($root)) {
      throw new \Exception("Failed to locate root: " . $root);
    }
    else {
      $input->setOption('root', $root);
    }
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $statusCode = 0;

    $output->writeln("<info>[[ Finding repositories ]]</info>");
    $scanner = new \Boring\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getOption('root'));

    $output->writeln("<info>[[ Fast-forwarding ]]</info>");
    foreach ($gitRepos as $gitRepo) {
      /** @var \Boring\GitRepo $gitRepo */
      $path = rtrim($this->fs->makePathRelative($gitRepo->getPath(), $input->getOption('root')), '/');
      if ($gitRepo->getUpstreamBranch() === NULL) {
        $output->writeln("<comment>Skip $path: No upstream tracking branch</comment>");
      }
      elseif (!$gitRepo->isLocalFastForwardable()) {
        $output->writeln("<comment>Skip $path: Cannot be fast-forwarded</comment>");
      }
      else {
        $output->writeln("<comment>Fast-forward $path ({$gitRepo->getLocalBranch()} <= {$gitRepo->getUpstreamBranch()})...</comment>");
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