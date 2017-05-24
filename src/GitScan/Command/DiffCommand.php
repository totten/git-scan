<?php
namespace GitScan\Command;

use GitScan\CheckoutDocument;
use GitScan\DiffReport;
use GitScan\GitFormatter\PlainFormatter;
use GitScan\GitFormatter\HtmlFormatter;
use GitScan\Util\Filesystem;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class DiffCommand extends BaseCommand {

  /**
   * @var Filesystem
   */
  var $fs;

  /**
   * @param string|NULL $name
   */
  public function __construct($name = NULL) {
    $this->fs = new Filesystem();
    parent::__construct($name);
  }

  protected function configure() {
    $this
      ->setName('diff')
      ->setDescription('Compare the commits/revisions in different source trees')
      ->setHelp('Compare the commits/revisions in different source trees')
      ->addArgument('from', InputArgument::REQUIRED, 'Path to the project folder or JSON export')
      ->addArgument('to', InputArgument::REQUIRED, 'Path to the project folder or JSON export')
      ->addOption('format', NULL, InputOption::VALUE_REQUIRED, 'Output format (text|html|json)', 'text');
  }

  protected function initialize(InputInterface $input, OutputInterface $output) {
    $input->setArgument('from', $this->fs->toAbsolutePath($input->getArgument('from')));
    $this->fs->validateExists($input->getArgument('from'));

    $input->setArgument('to', $this->fs->toAbsolutePath($input->getArgument('to')));
    $this->fs->validateExists($input->getArgument('to'));
  }

  protected function execute(InputInterface $input, OutputInterface $output) {
    $fromDoc = $this->getCheckoutDocument($input->getArgument('from'));
    $toDoc = $this->getCheckoutDocument($input->getArgument('to'));

    $report = new DiffReport(
      $fromDoc,
      $toDoc,
      $input->getOption('format') == 'html' ? new HtmlFormatter() : new PlainFormatter()
    );

    switch ($input->getOption('format')) {
      case 'json':
        $output->write(json_encode($report->getRows()));
        break;

      case 'text':
        $output->writeln(sprintf("Compare <info>%s</info> to <info>%s</info>",
          $input->getArgument('from'),
          $input->getArgument('to')
        ));
        $rows = array();
        foreach ($report->getRows() as $row) {
          $rows[] = array($row['status'], $row['path'], $row['from'], $row['to'], $row['changes']);
        }
        $table = $this->getApplication()->getHelperSet()->get('table');
        $table
          ->setHeaders(array(' ', 'Path', 'From', 'To', 'Changes'))
          ->setRows($rows);
        $table->render($output);
        break;

      case 'html':
        // TODO
      default:
        $output->writeln('<error>Unsupported output format</error>');
        return 1;
    }
  }

  /**
   * @param string $path path to a directory or JSON file
   * @return CheckoutDocument
   */
  protected function getCheckoutDocument($path) {
    if (is_dir($path)) {
      $scanner = new \GitScan\GitRepoScanner();
      $gitRepos = $scanner->scan($path);

      return CheckoutDocument::create($path)
        ->importRepos($gitRepos);
    }
    else {
      $json = file_get_contents($path);
      return CheckoutDocument::create(NULL)
        ->importJson($json);
    }
  }

}
