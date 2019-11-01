<?php
namespace GitScan\Command;

use GitScan\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class LsCommand extends BaseCommand {

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
      ->setName('ls')
      ->setDescription('List any nested git repositories')
      ->setHelp('Create a list of git repos. This may be useful for piping and shell scripting.

      Example: git scan ls | while read dir; do ls -la $dir ; done
      ')
      ->addOption('absolute', 'A', InputOption::VALUE_NONE, 'Output absolute paths')
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
    foreach ($gitRepos as $gitRepo) {
      /** @var \GitScan\GitRepo $gitRepo */
      $path = $input->getOption('absolute')
        ? $gitRepo->getPath()
        : $this->fs->makePathRelative($gitRepo->getPath(), $paths[0]);
      $path = rtrim($path, '/');
      $output->writeln($path);
    }
  }

}
