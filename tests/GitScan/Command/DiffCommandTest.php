<?php
namespace GitScan\Command;

use GitScan\GitRepo;
use GitScan\Util\Process as ProcessUtil;

class DiffCommandTest extends \GitScan\GitScanTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testDiff_empty_json() {
    $commandTester = $this->createCommandTester(array(
      'command' => 'diff',
      'from' => $this->fixturePath,
      'to' => $this->fixturePath,
      '--format' => 'json',
    ));
    $json = $commandTester->getDisplay(FALSE);
    $actualDoc = json_decode($json, TRUE);
    $this->assertEquals(array(), $actualDoc);
  }

  public function testDiff_empty_text() {
    $commandTester = $this->createCommandTester(array(
      'command' => 'diff',
      'from' => $this->fixturePath,
      'to' => $this->fixturePath,
    ));
    $lines = explode("\n", trim($commandTester->getDisplay(FALSE)));
    $this->assertRegExp('/Compare .* to .*/', $lines[0]);
    $this->assertEquals('+---+------+------+----+---------+', $lines[1]);
    $this->assertEquals('|   | Path | From | To | Changes |', $lines[2]);
    $this->assertEquals('+---+------+------+----+---------+', $lines[3]);
    $this->assertEquals(4, count($lines));
  }

  public function testDiff_multiCase_json() {
    $this->createExampleRepo($this->fixturePath . '/orig/foo/repo-change-me');
    $this->createExampleRepo($this->fixturePath . '/orig/bar/repo-keep-me');
    $this->createExampleRepo($this->fixturePath . '/orig/whiz/repo-delete-me');
    ProcessUtil::runOk($this->command($this->fixturePath, "cp -r orig changed"));
    ProcessUtil::runOk($this->command($this->fixturePath, "rm -rf changed/whiz/repo-delete-me"));
    $this->createExampleRepo($this->fixturePath . '/changed/bang/repo-add-me');

    $changeMe = new GitRepo($this->fixturePath . '/changed/foo/repo-change-me');
    ProcessUtil::runOk($changeMe->command("git checkout -b feature-branch"));
    $changeMe->commitFile("new-stuff.txt", "This is a change to the repo!");

    $commandTester = $this->createCommandTester(array(
      'command' => 'diff',
      'from' => "{$this->fixturePath}/orig",
      'to' => "{$this->fixturePath}/changed",
      '--format' => 'json',
    ));
    $json = $commandTester->getDisplay(FALSE);
    $actualDoc = json_decode($json, TRUE);
    $expectRegexp = array(
      array(
        'path' => ':^bang/repo-add-me$:',
        'status' => '/^\+$/',
        'from' => '/^$/',
        'to' => '/^master \([0-9a-f]+\)$/',
        'changes' => '/^\(Added repository\)$/',
      ),
      array(
        'path' => ':^bar/repo-keep-me$:',
        'status' => '/^ $/',
        'from' => '/^master \([0-9a-f]+\)$/',
        'to' => '/^master \([0-9a-f]+\)$/',
        'changes' => '/^$/',
      ),
      array(
        'path' => ':^foo/repo-change-me$:',
        'status' => '/^M$/',
        'from' => '/^master \([0-9a-f]+\)$/',
        'to' => '/^feature-branch \([0-9a-f]+\)$/',
        'changes' => '/^1 \[.*\]$/',
      ),
      array(
        'path' => ':^whiz/repo-delete-me$:',
        'status' => '/^-$/',
        'from' => '/^master \([0-9a-f]+\)$/',
        'to' => '/^$/',
        'changes' => '/^\(Removed repository\)$/',
      ),
    );
    $this->assertEquals(count($actualDoc), count($expectRegexp), "Unexpected number of rows: " . print_r($actualDoc, TRUE));
    foreach ($expectRegexp as $offset => $expectRow) {
      foreach ($expectRow as $key => $pattern) {
        $this->assertRegExp($pattern, $actualDoc[$offset][$key]);
      }
    }
  }

  // TODO: Test cases wherein the underlying repository doesn't actually exist (eg comparing JSON exports)
  // TODO: More tests of output formatting
}
