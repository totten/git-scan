<?php
namespace GitScan\Command;

use GitScan\Util\ArrayUtil;
use GitScan\Util\Filesystem;
use GitScan\Util\Process as ProcessUtil;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends BaseCommand {

  const DISPLAY_ALL_THRESHOLD = 10;

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
      ->setName('status')
      ->setAliases(array('st'))
      ->setDescription('Show the status of any nested git repositories')
      ->setHelp("Show the status of any nested git repositories.\n\nNote: This will fetch upstream repositories to help determine the status (unless you specify --offline mode).")
      ->addArgument('path', InputArgument::IS_ARRAY, 'The local base path to search', array(getcwd()))
      ->addOption('max-depth', NULL, InputOption::VALUE_REQUIRED, 'Limit the depth of the search', -1)
      ->addOption('status', NULL, InputOption::VALUE_REQUIRED, 'Filter table output by repo statuses ("all","novel","boring","auto")', 'auto')
      ->addOption('fetch', NULL, InputOption::VALUE_NONE, 'Fetch latest data about remote repositories. (Slower but more accurate statuses.)');
    //->addOption('scan', 's', InputOption::VALUE_NONE, 'Force an immediate scan for new git repositories before doing anything')
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setArgument('path', $this->fs->toAbsolutePaths($input->getArgument('path')));
    $this->fs->validateExists($input->getArgument('path'));
  }

  protected function execute(InputInterface $input, OutputInterface $output): int {
    $output->writeln("<comment>[[ Finding repositories ]]</comment>");
    $scanner = new \GitScan\GitRepoScanner();
    $gitRepos = $scanner->scan($input->getArgument('path'), $input->getOption('max-depth'));

    if ($input->getOption('status') == 'auto') {
      $input->setOption('status', count($gitRepos) > self::DISPLAY_ALL_THRESHOLD ? 'novel' : 'all');
    }

    $output->writeln($input->getOption('fetch')
        ? "<comment>[[ Fetching statuses ]]</comment>"
        : "<comment>[[ Checking statuses ]]</comment>"
    );
    /** @var \Symfony\Component\Console\Helper\ProgressHelper $progress */
    $progress = new ProgressBar($output);
    $progress->start(1 + count($gitRepos));
    $progress->advance();
    $rows = array();
    $hiddenCount = 0;

    foreach ($gitRepos as $gitRepo) {
      /** @var \GitScan\GitRepo $gitRepo */
      if ($input->getOption('fetch') && $gitRepo->getUpstreamBranch() !== NULL) {
        ProcessUtil::runOk($gitRepo->command('git fetch'));
      }
      if ($gitRepo->matchesStatus($input->getOption('status'))) {
        $rows[] = array(
          $gitRepo->getStatusCode(),
          $this->fs->formatPrettyPath($gitRepo->getPath(), $input->getArgument('path')),
          $gitRepo->getLocalBranch(),
          $gitRepo->getUpstreamBranch(),
          $gitRepo->getOriginUrl(),
        );
      }
      else {
        $hiddenCount++;
      }
      $progress->advance();
    }
    $progress->finish();

    $output->writeln("<comment>[[ Results ]]</comment>\n");
    if (!empty($rows)) {
      $table = new Table($output);
      $table
        ->setHeaders(array('Status', 'Path', 'Local Branch / Tag', 'Remote Branch', 'Remote URL'))
        ->setRows($rows);
      $table->render();

      $chars = $this->getUniqueChars(ArrayUtil::collect($rows, 0));
      foreach ($chars as $char) {
        switch ($char) {
          case ' ':
            break;

          case 'M':
            $output->writeln("[M] Modifications have not been committed");
            break;

          case 'N':
            $output->writeln("[N] New files have not been committed");
            break;

          case 'F':
            $output->writeln("[F] Fast-forwards are not possible");
            break;

          case 'B':
            $output->writeln("[B] Branch names are suspiciously different");
            break;

          case 'S':
            $output->writeln("[S] Stash contains data");
            break;

          default:
            throw new \RuntimeException("Unrecognized status code [$char]");
        }
      }
    }
    else {
      $output->writeln("No repositories to display.");
    }

    if ($hiddenCount > 0) {
      switch ($input->getOption('status')) {
        case 'novel':
          $output->writeln("NOTE: Omitted information about $hiddenCount boring repo(s). To display all, use --status=all.");
          break;

        case 'boring':
          $output->writeln("NOTE: Omitted information about $hiddenCount novel repo(s). To display all, use --status=all.");
          break;

        default:
          $output->writeln("NOTE: Omitted information about $hiddenCount repo(s). To display all, use --status=all.");
      }
    }
    return 0;
  }

  public function getUniqueChars($items) {
    $chars = array();
    foreach ($items as $item) {
      foreach (str_split($item) as $char) {
        $chars[$char] = 1;
      }
    }
    ksort($chars);
    return array_keys($chars);
  }

}
