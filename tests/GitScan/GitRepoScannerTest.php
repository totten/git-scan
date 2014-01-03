<?php
namespace GitScan;

class GitRepoScannerTest extends GitScanTestCase {

  public function testScan_string() {
    // Make a mix of git repos and superfluous files
    $this->createExampleFile($this->fixturePath . '/modules/example.txt');
    $this->createExampleFile($this->fixturePath . '/modules/extra-1/example.txt');
    $this->createExampleFile($this->fixturePath . '/themes/extra-1/example.txt');
    $this->createExampleFile($this->fixturePath . '/sites/all/modules/extra-2/example.txt');
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
    $this->createExampleFile($this->fixturePath . '/modules/example.txt');
    $this->createExampleFile($this->fixturePath . '/sites/all/modules/extra-2/example.txt');
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

  public function assertRepos($gitRepos, $expecteds) {
    $actuals = array();
    foreach ($gitRepos as $actual) {
      $actuals[] = $actual->getPath();
    }
    sort($actuals);
    $this->assertEquals($expecteds, $actuals);
  }

}