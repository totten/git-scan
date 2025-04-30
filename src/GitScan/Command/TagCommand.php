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

class TagCommand extends BaseCommand {

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
      ->setName('tag')
      ->setDescription('Create tags across repos')
      ->addOption('path', NULL, InputOption::VALUE_REQUIRED, 'The local base path to search', getcwd())
      ->addOption('prefix', 'p', InputOption::VALUE_NONE, 'Autodetect prefixed variations')
      ->addOption('delete', 'd', InputOption::VALUE_NONE, 'Delete fully merged branches')
      ->addOption('dry-run', 'T', InputOption::VALUE_NONE, 'Display what would be done')
      ->addArgument('tagName', InputArgument::REQUIRED, 'The name of the new tag(s)')
      ->addArgument('head', InputArgument::OPTIONAL, 'The name of the head(s) to use for new tag(s). *Must* be a branch name.');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setOption('path', $this->fs->toAbsolutePath($input->getOption('path')));
    $this->fs->validateExists($input->getOption('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    if ($input->getOption('delete')) {
      return $this->executeDelete($input, $output);
    }
    else {
      return $this->executeCreate($input, $output);
    }
  }

  protected function executeCreate(InputInterface $input, OutputInterface $output): int {
    if (!$input->getArgument('head')) {
      throw new \RuntimeException("Missing argument \"head\". Please specify the name of original base branch.");
    }

    $helper = $this->getHelper('question');
    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getOption('path'));
    $batch = new ProcessBatch('Creating tag(s)...');
    $self = $this;

    $g = new \GitScan\GitBranchGenerator($gitRepos);
    $g->generate(
      $input->getArgument('head'),
      $input->getArgument('tagName'),
      $input->getOption('prefix'),
      function (GitRepo $gitRepo, $oldBranch, $newTag) use ($input, $output, $helper, $batch, $self) {
        $relPath = $self->fs->makePathRelative($gitRepo->getPath(), $input->getOption('path'));

        $question = new ChoiceQuestion("\n<comment>In \"<info>{$relPath}</info>\", found existing branch \"<info>$oldBranch</info>\". Create a new tag \"<info>$newTag</info>\"?</comment>",
          array("y" => "yes (default)", "n" => "no", "c" => "customize"),
          "y"
        );
        $mode = $helper->ask($input, $output, $question);
        if ($mode === 'n') {
          return;
        }
        if ($mode === 'c') {
          $newTag = $helper->ask($input, $output, new Question("<comment>Enter the new tag name:</comment> "));
          if (!$newTag) {
            $output->writeln("Skipped");
            return;
          }
        }

        $label = "In \"<info>{$relPath}</info>\", make tag \"<info>$newTag</info>\" from \"<info>$oldBranch</info>\"";
        $batch->add($label, $gitRepo->command(sprintf(
          "git tag %s %s",
          escapeshellarg($newTag),
          escapeshellarg($oldBranch)
        )));
      });

    $batch->runAllOk($output, $input->getOption('dry-run'));
    return 0;
  }

  protected function executeDelete(InputInterface $input, OutputInterface $output): int {
    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getOption('path'));
    $batch = new ProcessBatch('Deleting branch(es)...');

    $tagName = $input->getArgument('tagName');
    $tagQuoted = preg_quote($tagName, '/');

    foreach ($gitRepos as $gitRepo) {
      /** @var \GitScan\GitRepo $gitRepo */
      $relPath = $this->fs->makePathRelative($gitRepo->getPath(), $input->getOption('path'));

      $tags = $gitRepo->getTags();
      $matches = array();
      if ($input->getOption('prefix')) {
        $matches = preg_grep("/[-_]$tagQuoted\$/", $tags);
      }
      if (in_array($tagName, $tags)) {
        $matches[] = $tagName;
      }

      // TODO: Verify that user wants to delete these.

      foreach ($matches as $match) {
        $label = "In \"<info>{$relPath}</info>\", delete tag \"<info>$match</info>\" .";
        $batch->add($label, $gitRepo->command("git tag -d " . escapeshellarg($match)));
      }
    }

    $batch->runAllOk($output, $input->getOption('dry-run'));
    return 0;
  }

}
