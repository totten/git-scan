<?php
namespace GitScan\Command;

use GitScan\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class HashCommand extends BaseCommand {

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
      ->setName('hash')
      ->setDescription('Generate a hash')
      ->setHelp("Generate a cumulative hash code for the current checkouts")
      ->addOption('max-depth', NULL, InputOption::VALUE_REQUIRED, 'Limit the depth of the search', -1)
      ->addArgument('path', InputArgument::IS_ARRAY, 'The local base path to search', array(getcwd()));
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setArgument('path', $this->fs->toAbsolutePaths($input->getArgument('path')));
    $this->fs->validateExists($input->getArgument('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $scanner = new \GitScan\GitRepoScanner();
    $paths = $input->getArgument('path');
    if (count($paths) != 1) {
      $output->writeln('<error>Expected only one root path</error>');
      return 1;
    }

    $output->writeln($scanner->hash($paths[0], $input->getOption('max-depth')));
    return 0;
  }

}
