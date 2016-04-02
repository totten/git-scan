<?php
namespace GitScan\Util;

class PatchUtil {
  /**
   * @param string $patch
   * @return bool
   */
  public static function isWellFormed($patch) {
    return
      !empty($patch)
      && preg_match('/^From [a-f0-9]+ /', $patch)
      && strpos($patch, 'diff --git') !== FALSE;
  }

}