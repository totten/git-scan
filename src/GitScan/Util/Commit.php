<?php
namespace GitScan\Util;

class Commit {

  public static function isValid($commit) {
    return preg_match('/^[0-9a-f]+$/', $commit) && 40 == strlen($commit);
  }

}
