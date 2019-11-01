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
   * @var array|NULL
   *   Ex: ['src' => ..., 'vars' => ['!1' =>...]]
   */
  protected $registeredSource;

  /**
   * AutoMergeRule constructor.
   * @param string $expr
   *   Ex: "https://github.com/civicrm/civicrm-core/pull/8022".
   * @param array $registeredSources
   */
  public function __construct($expr, $registeredSources) {
    $this->expr = $expr;
    $this->registeredSource = self::findRegisteredSource($registeredSources, $this->expr);
  }

  public function fetch($force = FALSE) {
    if ($this->patch && !$force) {
      return;
    }

    if ($this->registeredSource) {
      $patchUrl = strtr($this->registeredSource['src']['patch_url'], $this->registeredSource['vars']);
      $this->patch = file_get_contents($patchUrl);
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
    if ($this->registeredSource) {
      foreach ($this->registeredSource['src']['match_remote'] as $regRemotePat) {
        $expectedRemoteUrlPat = strtr($regRemotePat, $this->registeredSource['vars']);
        foreach ($gitRepo->getRemoteUrls() as $remoteUrl) {
          if (preg_match($expectedRemoteUrlPat, $remoteUrl)) {
            return TRUE;
          }
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

  /**
   * Search the list of $registeredSources and determine if the $prUrl
   * matches any of them.
   *
   * @param array $registeredSources
   *   Each source has these properties:
   *   - match_pr: string (regex)
   *   - match_remote: string[] (regex)
   *   - patch_url: string
   * @param string $prUrl
   * @return array|NULL
   *   - src: array (the matching source)
   *   - vars: array (the list of regex substrings/groups extracted from $prUrl/match_pr')
   */
  protected static function findRegisteredSource($registeredSources, $prUrl) {
    foreach ($registeredSources as $registeredSource) {
      if (preg_match($registeredSource['match_pr'], $prUrl, $m)) {
        $vars = [];
        foreach ($m as $num => $val) {
          $vars['!' . $num] = $val;
        }
        return ['src' => $registeredSource, 'vars' => $vars];
      }
    }
    return NULL;
  }

}
