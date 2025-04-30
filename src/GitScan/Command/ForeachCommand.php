<?php
namespace GitScan\Command;

use GitScan\Util\Env;
use GitScan\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ForeachCommand extends BaseCommand {

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
      ->setName('foreach')
      ->setDescription('Execute a shell command on all nested repositories')
      ->setHelp(""
        . "Execute a shell command on all tested repositories\n"
        . "\n"
        . "The following shell variables can be used within a command:\n"
        . " * \$path - The relative path of the git repository\n"
        . " * \$toplevel - The absolute path of the directory being searched\n"
        . "\n"
        . "Examples:\n"
        . "   foreach -c 'echo Examine \$path in \$toplevel'\n"
        . "   foreach -c 'echo Examine \$path in \$toplevel' --status=boring\n"
        . "   foreach /home/me/download /home/me/src -c 'echo Examine \$path in \$toplevel'\n"
        . "\n"
        . "Important: The example uses single-quotes to escape the $'s\n"
      )
      ->addArgument('path', InputArgument::IS_ARRAY, 'The local base path to search', array(getcwd()))
      ->addOption('command', 'c', InputOption::VALUE_REQUIRED, 'The command to execute')
      ->addOption('status', NULL, InputOption::VALUE_REQUIRED, 'Filter table output by repo statuses ("all","novel","boring")', 'all');
  }

  //public function getSynopsis() {
  //    return $this->getName() . ' [--status="..."] [--path="..."] [command]';
  //}

  /**
   * @inheritDoc
   */
  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setArgument('path', $this->fs->toAbsolutePaths($input->getArgument('path')));
    $this->fs->validateExists($input->getArgument('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if (!$input->getOption('command')) {
      $output->writeln("<error>Missing required option: --command</error>");
      return 1;
    }

    $statusCode = 0;

    if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
      $output->writeln("<comment>[[ Finding repositories ]]</comment>");
    }
    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getArgument('path'));

    foreach ($gitRepos as $gitRepo) {
      /** @var \GitScan\GitRepo $gitRepo */
      if (!$gitRepo->matchesStatus($input->getOption('status'))) {
        continue;
      }

      $topLevel = $this->fs->findFirstParent($gitRepo->getPath(), $input->getArgument('path'));

      if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
        $output->writeln("<comment>[[ <info>{$gitRepo->getPath()}</info> ]]</comment>");
      }
      $process = \Symfony\Component\Process\Process::fromShellCommandline($input->getOption('command'));
      $process->setWorkingDirectory($gitRepo->getPath());
      // $process->setEnv(...); sucks in Debian/Ubuntu
      Env::set('path', $this->fs->makePathRelative($gitRepo->getPath(), $topLevel));
      Env::set('toplevel', $topLevel);
      $errorOutput = $output;
      if (is_callable($output, 'getErrorOutput') && $output->getErrorOutput()) {
        $errorOutput = $output->getErrorOutput();
      }
      $process->run(function ($type, $buffer) use ($output, $errorOutput) {
        if (\Symfony\Component\Process\Process::ERR === $type) {
          if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $errorOutput->write("<error>STDERR</error> ");
          }
          $errorOutput->write($buffer, FALSE, OutputInterface::OUTPUT_RAW);
        }
        else {
          if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
            $output->write("<comment>STDOUT</comment> ");
          }
          $output->write($buffer, FALSE, OutputInterface::OUTPUT_RAW);
        }
      });
      if (!$process->isSuccessful()) {
        $errorOutput->writeln("<error>[[ {$gitRepo->getPath()}: exit code = {$process->getExitCode()} ]]</error>");
        $statusCode = 2;
      }
    }
    Env::remove('path');
    Env::remove('toplevel');

    return $statusCode;
  }

}
