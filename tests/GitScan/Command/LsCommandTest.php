<?php
namespace GitScan\Command;

class LsCommandTest extends \GitScan\GitScanTestCase {
  public function setup() {
    parent::setup();
  }

  /**
   * If the fixturePath has no git repos, then the export lists no details.
   */
  public function testLs_noOutput() {
    $commandTester = $this->createCommandTester(array(
      'command' => 'ls',
      'path' => array($this->fixturePath),
    ));
    $this->assertEmpty($commandTester->getDisplay(FALSE));
  }

  /**
   * If the fixutrePath has multiple repos, then ls reports each of them.
   */
  public function testLs_output_relative() {
    $this->createExampleRepo($this->fixturePath);
    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1');
    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1/repo-1b');
    $this->createExampleRepo($this->fixturePath . '/example-2/repo-2');

    $commandTester = $this->createCommandTester(array(
      'command' => 'ls',
      'path' => array($this->fixturePath),
    ));
    $actualPaths = explode("\n", trim($commandTester->getDisplay(FALSE)));
    $expectPaths = array(
      '.',
      'example-1/repo-1',
      'example-1/repo-1/repo-1b',
      'example-2/repo-2',
    );
    sort($actualPaths);
    sort($expectPaths);
    $this->assertEquals($expectPaths, $actualPaths);
  }

  /**
   * If the fixutrePath has multiple repos, then ls reports each of them.
   */
  public function testLs_output_absolute() {
    $fp = realpath($this->fixturePath);

    $this->createExampleRepo($fp);
    $this->createExampleRepo($fp . '/example-1/repo-1');
    $this->createExampleRepo($fp . '/example-1/repo-1/repo-1b');
    $this->createExampleRepo($fp . '/example-2/repo-2');

    $commandTester = $this->createCommandTester(array(
      'command' => 'ls',
      '--absolute' => TRUE,
      'path' => array($this->fixturePath),
    ));
    $actualPaths = explode("\n", trim($commandTester->getDisplay(FALSE)));
    $expectPaths = array(
      $fp,
      $fp . '/example-1/repo-1',
      $fp . '/example-1/repo-1/repo-1b',
      $fp . '/example-2/repo-2',
    );
    sort($actualPaths);
    sort($expectPaths);
    $this->assertEquals($expectPaths, $actualPaths);
  }

}
