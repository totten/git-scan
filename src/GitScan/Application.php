<?php
namespace GitScan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   */
  public static function main($binDir) {
    $application = new Application('git-scan', '@package_version@');
    $application->run();
  }

  public function __construct($name = 'UNKNOWN', $version = 'UNKNOWN') {
    parent::__construct($name, $version);
    $this->setCatchExceptions(TRUE);
    $this->addCommands($this->createCommands());
  }

  /**
   * Construct command objects
   *
   * @return array of Symfony Command objects
   */
  public function createCommands() {
    $commands = array();
    $commands[] = new \GitScan\Command\AutoMergeCommand();
    $commands[] = new \GitScan\Command\BranchCommand();
    $commands[] = new \GitScan\Command\DiffCommand();
    $commands[] = new \GitScan\Command\ExportCommand();
    $commands[] = new \GitScan\Command\ForeachCommand();
    $commands[] = new \GitScan\Command\HashCommand();
    //$commands[] = new \GitScan\Command\ImportCommand();
    $commands[] = new \GitScan\Command\LsCommand();
    $commands[] = new \GitScan\Command\StatusCommand();
    $commands[] = new \GitScan\Command\TagCommand();
    $commands[] = new \GitScan\Command\UpdateCommand();
    return $commands;
  }

}
