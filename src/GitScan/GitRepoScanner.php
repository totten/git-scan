<?php
namespace GitScan;

use GitScan\Util\Filesystem;
use Symfony\Component\Finder\Finder;

class GitRepoScanner {

  /**
   * @var FileSystem
   */
  protected $fs;

  /**
   * @var \GitScan\Config
   */
  protected $config;

  /**
   * @param FileSystem $fs
   * @param \GitScan\Config|NULL $config
   */
  public function __construct($fs = NULL, \GitScan\Config $config = NULL) {
    $this->fs = $fs ?: new Filesystem();
    $this->config = $config ?: Config::load();
  }

  /**
   * Find a list of all GitRepos within a
   * given base dir.
   *
   * @param string|array $basedir
   * @param int $depth The maximum directory depth to scan.
   * @param int $start The directory depth to start the scan from.
   * @return array of GitRepo
   */
  public function scan($basedir, $depth = null, $start = null) {
    $gitRepos = array();
    $finder = new Finder();
    $finder->in($basedir)
      ->ignoreUnreadableDirs()
    // Specifically looking for .git files!
      ->ignoreVCS(FALSE)
      ->ignoreDotFiles(FALSE)
      ->exclude($this->config->excludes)
      ->directories()
      ->name('.git');

    if ($depth) $finder->depth("< {$depth}");
    if ($start) $finder->depth("> {$start}");

    foreach ($finder as $file) {
      $path = dirname($file);
      $gitRepos[(string) $path] = new GitRepo($path);
    }
    return $gitRepos;
  }

  /**
   * Compute a hash to identify the list of repos/checkouts
   * within a given base dir.
   *
   * @param string $basedir
   * @return string
   */
  public function hash($basedir) {
    $gitRepos = $this->scan($basedir);
    $buf = '';
    foreach ($gitRepos as $gitRepo) {
      $path = rtrim($this->fs->makePathRelative($gitRepo->getPath(), $basedir), '/');
      /** @var $gitRepo GitRepo */
      $buf .= ';;' . $path;
      $buf .= ';;' . $gitRepo->getCommit();
    }
    return md5($buf);
  }

}
