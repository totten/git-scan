<?php
namespace GitScan;

use GitScan\Util\Process as ProcessUtil;
use GitScan\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class GitScanTestCase extends \PHPUnit\Framework\TestCase {
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

  public function setUp(): void {
    $runtimeClass = get_class($this);
    $this->originalCwd = getcwd();
    $this->fixturePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR
      . preg_replace('/[^A-Za-z0-9_]/', '', $runtimeClass)
      . '_'
      . rand(0, 1000000);
    $this->fs = new Filesystem();
    if ($this->fs->exists($this->fixturePath)) {
      $this->fs->remove(new \FilesystemIterator($this->fixturePath));
    }
    $this->fs->mkdir($this->fixturePath);
    chdir($this->fixturePath);
  }

  public function tearDown(): void {
    chdir($this->originalCwd);
    if ($this->fixturePath) {
      if (!getenv('GITSCAN_KEEP_TMP')) {
        $this->fs->remove(new \FilesystemIterator($this->fixturePath));
        $this->fs->remove($this->fixturePath);
      }
    }
  }

  /**
   * @param string $subdir absolute path, or path relative to $this->fixturePath
   * @param string $command
   */
  protected function command($subdir, $command) {
    $process = new \Symfony\Component\Process\Process($command);
    if ($subdir !== NULL && $subdir !== "") {
      $process->setWorkingDirectory($subdir);
    }
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
   * @param array $args must include key "command"
   * @return \Symfony\Component\Console\Tester\CommandTester
   */
  public function createCommandTester($args) {
    if (!isset($args['command'])) {
      throw new \RuntimeException("Missing mandatory argument: command");
    }
    $application = new Application();
    $command = $application->find($args['command']);
    $commandTester = new CommandTester($command);
    $options = array('interactive' => FALSE);
    $commandTester->execute($args, $options);
    return $commandTester;
  }

  /**
   * Assert that $commit looks like a real commit.
   *
   * @param string $commit
   */
  public function assertIsCommit($commit) {
    $this->assertTrue(\GitScan\Util\Commit::isValid($commit));
  }

  public static function assertRegExp(string $pattern, string $string, string $message = ''): void {
    // Why would you want phpunit to be stable when it's clearly better for upstream to arbitrarily rename things? What could go wrong?
    if (is_callable([static::CLASS, 'assertMatchesRegularExpression'])) {
      static::assertMatchesRegularExpression($pattern, $string, $message);
    }
    else {
      parent::assertRegExp($pattern, $string, $message);
    }
  }

  public static function assertNotRegExp(string $pattern, string $string, string $message = ''): void {
    // Why would you want phpunit to be stable when it's clearly better for upstream to arbitrarily rename things? What could go wrong?
    if (is_callable([static::CLASS, 'assertDoesNotMatchRegularExpression'])) {
      static::assertDoesNotMatchRegularExpression($pattern, $string, $message);
    }
    else {
      parent::assertNotRegExp($pattern, $string, $message);
    }
  }

}
