<?php
namespace GitScan;

use GitScan\Exception\ProcessErrorException;
use GitScan\Util\PatchUtil;
use GitScan\Util\Process;

/**
 * Class GithubAutoMergeRule
 * @package GitScan
 *
 */
class AutoMergeRule {

  /**
   * @var string
   * Ex: "https://github.com/civicrm/civicrm-core/pull/8022".
   */
  protected $expr;

  /**
   * @var string
   */
  protected $patch;

  /**
   * AutoMergeRule constructor.
   * @param string $expr
   *   Ex: "https://github.com/civicrm/civicrm-core/pull/8022".
   */
  public function __construct($expr) {
    $this->expr = $expr;
  }

  public function fetch($force = FALSE) {
    if ($this->patch && !$force) {
      return;
    }

    if (preg_match(';^http.*github.com/([^/]+)/([^/]+)/pull/([0-9]+)(|.diff|.patch);', $this->expr, $matches)) {
      $owner = $matches[1];
      $repoName = $matches[2];
      $prNum = $matches[3];

      $this->patch = file_get_contents("https://github.com/{$owner}/{$repoName}/pull/{$prNum}.patch");
    }
    elseif ($this->expr{0} == ';') {
      list (, $regex, $patchFile) = explode(';', $this->expr);
      $this->patch = file_get_contents($patchFile);
    }
    else {
      throw new \RuntimeException("Failed to recognize auto-merge rule: {$this->expr}");
    }

    if (!PatchUtil::isWellFormed($this->patch)) {
      throw new \RuntimeException("Malformed patch ({$this->expr}): {$this->patch}");
    }
  }

  public function isMatch(GitRepo $gitRepo) {
    if (preg_match(';github.com/([^/]+)/([^/]+)/pull/([0-9]+)(|.diff|.patch);', $this->expr, $matches)) {
      $owner = $matches[1];
      $repoName = $matches[2];
      $prNum = $matches[3];

      foreach ($gitRepo->getRemoteUrls() as $remote => $remoteUrl) {
        $baseUrl = preg_replace(';\.git$;', '', $remoteUrl);
        if ($baseUrl === "https://github.com/$owner/$repoName") {
          return TRUE;
        }
        if ($baseUrl === "https://github.com/$owner/$repoName/") {
          return TRUE;
        }
        if ($baseUrl === "https://github.com/$owner/$repoName.git") {
          return TRUE;
        }
        if ($baseUrl === "git@github.com:$owner/$repoName") {
          return TRUE;
        }
      }
    }
    elseif ($this->expr{0} == ';') {
      list (, $regex, $patchFile) = explode(';', $this->expr);
      foreach ($gitRepo->getRemoteUrls() as $remote => $remoteUrl) {
        if (preg_match(";$regex;", $remoteUrl)) {
          return TRUE;
        }
      }
      return FALSE;
    }
    else {
      throw new \RuntimeException("Failed to recognize auto-merge rule: {$this->expr}");
    }
  }

  /**
   * @return string
   */
  public function getExpr() {
    return $this->expr;
  }

  /**
   * @return string
   */
  public function getPatch() {
    return $this->patch;
  }

}
