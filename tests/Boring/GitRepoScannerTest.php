<?php
namespace Boring;

use Boring\Util\Process as ProcessUtil;
use Symfony\Component\Filesystem\Filesystem;

class GitRepoScannerTest extends BoringTestCase {

  public function testScan_string() {
    // Make a mix of git repos and superfluous files
    $this->createFile($this->fixturePath . '/modules/example.txt');
    $this->createFile($this->fixturePath . '/modules/extra-1/example.txt');
    $this->createFile($this->fixturePath . '/themes/extra-1/example.txt');
    $this->createFile($this->fixturePath . '/sites/all/modules/extra-2/example.txt');
    $this->createExampleRepo($this->fixturePath);
    $this->createExampleRepo($this->fixturePath . '/sites/all/modules/real-1');
    $this->createExampleRepo($this->fixturePath . '/sites/default/real-2');

    $scanner = new GitRepoScanner();
    $gitRepos = $scanner->scan($this->fixturePath);

    $this->assertRepos($gitRepos, array(
      $this->fixturePath,
      $this->fixturePath . '/sites/all/modules/real-1',
      $this->fixturePath . '/sites/default/real-2'
    ));
  }

  public function testScan_array() {
    // Make a mix of git repos and superfluous files
    $this->createFile($this->fixturePath . '/modules/example.txt');
    $this->createFile($this->fixturePath . '/sites/all/modules/extra-2/example.txt');
    $this->createExampleRepo($this->fixturePath . '/sites/all/themes/ignore-1');
    $this->createExampleRepo($this->fixturePath . '/sites/all/modules/real-1');
    $this->createExampleRepo($this->fixturePath . '/sites/default/real-2');

    $scanner = new GitRepoScanner();
    $gitRepos = $scanner->scan(array(
      $this->fixturePath . '/sites/all/modules',
      $this->fixturePath . '/sites/default',
    ));

    $this->assertRepos($gitRepos, array(
      $this->fixturePath . '/sites/all/modules/real-1',
      $this->fixturePath . '/sites/default/real-2'
    ));
  }


  public function createFile($path) {
    $dir = dirname($path);
    if ($dir) {
      $this->fs->mkdir($dir);
    }
    $this->fs->dumpFile($path, "hello from $path");
  }

  public function createExampleRepo($dir) {
    $this->createFile("$dir/example.txt");
    ProcessUtil::runOk($this->command($dir, "git init"));
    ProcessUtil::runOk($this->command($dir, "git add example.txt"));
    ProcessUtil::runOk($this->command($dir, "git commit -m Import example.txt"));
  }

  public function assertRepos($gitRepos, $expecteds) {
    $actuals = array();
    foreach ($gitRepos as $actual) {
      $actuals[] = $actual->getPath();
    }
    sort($actuals);
    $this->assertEquals($expecteds, $actuals);
  }

}