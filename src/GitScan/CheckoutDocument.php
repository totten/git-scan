<?php
namespace GitScan;

/**
 * A "CheckoutDocument" is list of git repositories and the checkout (commit/branch)
 * of each.
 */
class CheckoutDocument {

  /**
   * @var string
   */
  protected $root;

  /**
   * @var array (string $path => array $details)
   */
  protected $details;

  /**
   * @var \GitScan\Util\FileSystem
   */
  protected $fs;

  /**
   * @param string $root the base dir which contains all the repos
   * @return CheckoutDocument
   */
  public static function create($root) {
    $checkout = new CheckoutDocument();
    $checkout->root = $root;
    $checkout->fs = new \GitScan\Util\Filesystem();
    $checkout->details = array();
    return $checkout;
  }

  /**
   * @param string $json
   * @return CheckoutDocument
   */
  public function importJson($json) {
    $jsonDoc = json_decode($json, TRUE);
    if ($this->root === NULL) {
      $this->root = $jsonDoc['root'];
    }
    foreach ($jsonDoc['details'] as $details) {
      $this->details[$details['path']] = $details;
    }
    return $this;
  }

  /**
   * @param array $gitRepos GitRepo instances
   * @return CheckoutDocument
   */
  public function importRepos($gitRepos) {
    foreach ($gitRepos as $gitRepo) {
      /** @var GitRepo $gitRepo */
      $path = rtrim($this->fs->makePathRelative($gitRepo->getPath(), $this->root), '/');
      $remotes = array();
      foreach ($gitRepo->getRemotes() as $remote) {
        $remotes[$remote] = array(
          'name' => $remote,
          'url' => $gitRepo->getRemoteUrl($remote),
        );
      }
      $this->details[$path] = array(
        'path' => $path,
        'remotes' => $remotes,
        'commit' => $gitRepo->getCommit(),
        'localBranch' => $gitRepo->getLocalBranch(),
        'upstreamBranch' => $gitRepo->getUpstreamBranch(),
        'uncommittedChanges' => $gitRepo->hasUncommittedChanges(),
      );
    }
    return $this;
  }

  public function getRoot() {
    return $this->root;
  }

  public function getPaths() {
    return array_keys($this->details);
  }

  /**
   * @param string $path
   * @return array|NULL with these keys:
   *   - path: string, local path
   *   - remotes: array (string $name => string $fetchUrl)
   *   - commit: string, the name of the checked-out HEAD commit
   *   - localBranch: string|NULL, the name of the checked-out branch (if applicable)
   *   - upstreamBranch: string|NULL, the name of he checked-out branch's upstream counterpart
   *   - modified: bool, whether there are uncommitted, local modifications
   */
  public function getDetails($path) {
    return isset($this->details[$path]) ? $this->details[$path] : NULL;
  }

  public function toJson() {
    return json_encode(array(
      'timestamp' => date(DATE_RFC2822),
      'root' => $this->root,
      'details' => array_values($this->details),
    ));
  }

}
