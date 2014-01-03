<?php
namespace Boring\Util;

class Filesystem extends \Symfony\Component\Filesystem\Filesystem {
  /**
   * @param string $path
   * @return string updated $path
   */
  public function toAbsolutePath($path) {
    if (empty($path)) {
      $res = getcwd();
    }
    elseif ($this->isAbsolutePath($path)) {
      $res = $path;
    }
    else {
      $res = getcwd() . DIRECTORY_SEPARATOR . $path;
    }
    if (is_dir($res)) {
      return realpath($res);
    } else {
      return $res;
    }
  }

}