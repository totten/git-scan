<?php
namespace GitScan;

/**
 * Class Config
 * @package GitScan
 *
 * A collection of persistent configuration options.
 */
class Config {

  /**
   * @var array|string
   * @see Finder::exclude()
   */
  public $excludes = array('.svn', '_svn', 'CVS', '_darcs', '.arch-params', '.monotone', '.bzr', '.hg', '.repo');

  /**
   * @return Config
   */
  public static function load() {
    $config = new Config();

    $f = getenv('HOME') . '/.git-scan.json';
    if (file_exists($f)) {
      $options = json_decode(file_get_contents($f), TRUE);
      if (!is_array($options)) {
        throw new \RuntimeException("Configuration file is malformed: $f");
      }
      foreach ($options as $key => $value) {
        switch ($key) {
          case 'excludes':
            $config->excludes = array_unique(array_merge($config->excludes, $value));
            break;

          default:
            throw new \RuntimeException("Configuration file \"$f\" includes unrecognized key \"$key\"");
        }
      }
    }

    return $config;
  }

}
