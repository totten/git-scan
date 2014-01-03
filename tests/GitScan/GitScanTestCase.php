<?php
namespace GitScan;

use GitScan\Util\Process as ProcessUtil;
use GitScan\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class GitScanTestCase extends \PHPUnit_Framework_TestCase {
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

  public function createExampleFile($path) {
    $dir = dirname($path);
    if ($dir) {
      $this->fs->mkdir($dir);
    }
    $this->fs->dumpFile($path, "hello from $path");
  }

  public function createExampleRepo($dir) {
    $this->createExampleFile("$dir/example.txt");
    ProcessUtil::runOk($this->command($dir, "git init"));
    ProcessUtil::runOk($this->command($dir, "git add example.txt"));
    ProcessUtil::runOk($this->command($dir, "git commit -m Import example.txt"));
  }

  /**
   * Create a helper for executing command-tests in our application.
   *
   * @param array $args; must include key "command"
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  public function createCommandTester($args) {
    if (!isset($args['command'])) {
      throw new \RuntimeException("Missing mandatory argument: command");
    }
    $application = new Application();
    $command = $application->find($args['command']);
    $commandTester = new CommandTester($command);
    $commandTester->execute($args);
    return $commandTester;
  }

}