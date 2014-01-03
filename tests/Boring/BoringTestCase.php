<?php
namespace Boring;

use Symfony\Component\Filesystem\Filesystem;

class BoringTestCase extends \PHPUnit_Framework_TestCase {
  /**
   * @var string
   */
  protected $fixturePath;

  /**
   * @var Filesystem
   */
  protected $fs;

  /**
   * @var string
   */
  private $originalCwd;

  public function setup() {
    $runtimeClass = get_class($this);
    $this->originalCwd = getcwd();
    $this->fixturePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9_]/', '', $runtimeClass);
    $this->fs = new Filesystem();
    if ($this->fs->exists($this->fixturePath)) {
      $this->fs->remove(new \FilesystemIterator($this->fixturePath));
    }
    $this->fs->mkdir($this->fixturePath);
    chdir($this->fixturePath);
  }

  public function tearDown() {
    chdir($this->originalCwd);
    // could remove, but when using --stop-on-failure it might be useful to keep this around
    //if ($this->fixturePath) {
    //  $this->fs->remove(new \FilesystemIterator($this->fixturePath));
    // }
  }

  /**
   * @param string $subdir absolute path, or path relative to $this->fixturePath
   * @param string $command
   */
  protected function command($subdir, $command) {
    $process = new \Symfony\Component\Process\Process($command);
    $process->setWorkingDirectory($subdir);
    return $process;
  }
}