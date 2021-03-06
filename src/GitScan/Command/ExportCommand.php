<?php
namespace GitScan\Command;

use GitScan\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ExportCommand extends BaseCommand {

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
      ->setName('export')
      ->setDescription('Show the status of any nested git repositories')
      ->setHelp("Export the current checkout information to JSON format")
      ->addArgument('path', InputArgument::IS_ARRAY, 'The local base path to search', array(getcwd()));
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setArgument('path', $this->fs->toAbsolutePaths($input->getArgument('path')));
    $this->fs->validateExists($input->getArgument('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $scanner = new \GitScan\GitRepoScanner();
    $paths = $input->getArgument('path');
    if (count($paths) != 1) {
      $output->writeln('<error>Expected only one root path</error>');
      return;
    }

    $gitRepos = $scanner->scan($paths);
    $output->writeln(
      \GitScan\CheckoutDocument::create($paths[0])
        ->importRepos($gitRepos)
        ->toJson()
    );
  }

}
