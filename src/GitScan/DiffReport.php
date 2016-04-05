<?php
namespace GitScan;

use GitScan\GitFormatter\GitFormatterInterface;

/**
 * Class DiffReport
 * @package GitScan
 *
 * Prepare a table which summarizes the changes between two source trees
 */
class DiffReport {

  const REMOVED = '-';
  const ADDED = '+';
  const UNMODIFIED = ' ';
  const MODIFIED = 'M';

  /**
   * @var CheckoutDocument
   */
  protected $from, $to;

  /**
   * @var GitFormatter\GitFormatterInterface
   */
  protected $fmt;

  public function __construct(CheckoutDocument $from, CheckoutDocument $to, GitFormatterInterface $fmt) {
    $this->from = $from;
    $this->to = $to;
    $this->fmt = $fmt;
  }

  public function getRows() {
    $rows = array();

    $paths = array_unique(array_merge($this->from->getPaths(), $this->to->getPaths()));
    sort($paths);
    foreach ($paths as $path) {
      $from = $this->from->getDetails($path);
      $to = $this->to->getDetails($path);

      if ($from && !$to) {
        $status = self::REMOVED;
        $changes = '(Removed repository)';
      }
      elseif ($to && !$from) {
        $status = self::ADDED;
        $changes = '(Added repository)';
      }
      elseif ($from['commit'] === $to['commit']) {
        $status = self::UNMODIFIED;
        $changes = '';
      }
      else {
        $status = self::MODIFIED;
        $count = $this->getChangeCount($from, $to);
        $changes = sprintf("%s [%s]",
          $count ? $count : '?',
          $this->fmt->formatComparison($from, $to)
        );
      }

      $row = array(
        'path' => $path,
        'status' => $status,
        'from' => $from ? $this->fmt->formatRef($from) : '',
        'to' => $to ? $this->fmt->formatRef($to) : '',
        'changes' => $changes,
      );

      $rows[] = $row;
    }

    return $rows;
  }

  /**
   * @param array $from
   * @param array $to
   * @return int|null
   */
  public function getChangeCount($from, $to) {
    if ($from['commit'] == $to['commit']) {
      return 0;
    }
    $gitRepo = $this->findRepo(array('to', 'from'), $to);
    if (!$gitRepo) {
      return NULL;
    }
    $process = $gitRepo->command(
      sprintf("git log --pretty=oneline %s...%s", escapeshellarg($from['commit']), escapeshellarg($to['commit']))
    );
    $process->run();
    if ($process->isSuccessful()) {
      return count(explode("\n", trim($process->getOutput(), "\n")));
    }
    else {
      return NULL;
    }
  }

  /**
   * @param array $rootNames list of possible root paths, in order of preference; e.g. array('to','from') or array('from','to')
   * @param array $details
   * @return GitRepo|null
   */
  public function findRepo($rootNames, $details) {
    foreach ($rootNames as $rootName) {
      $root = $this->{$rootName}->getRoot();
      if ($root && is_dir($root . DIRECTORY_SEPARATOR . $details['path'] . DIRECTORY_SEPARATOR . '.git')) {
        return new GitRepo($root . DIRECTORY_SEPARATOR . $details['path']);
      }
    }
    return NULL;
  }

}
