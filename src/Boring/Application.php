<?php
namespace Boring;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Application extends \Symfony\Component\Console\Application {

  /**
   * Primary entry point for execution of the standalone command.
   *
   * @return
   */
  public static function main($binDir) {
    $application = new Application('boring', '@package_version@');
    $application->run();
  }

  public function __construct($name, $version) {
    parent::__construct($name, $version);
    $this->setCatchExceptions(FALSE);
    $this->addCommands($this->createCommands());
  }

  /**
   * Construct command objects
   *
   * @return array of Symfony Command objects
   */
  public function createCommands() {
    $commands = array();
    $commands[] = new \Boring\Command\StatusCommand();
    $commands[] = new \Boring\Command\UpdateCommand();
    return $commands;
  }
}