<?php
namespace GitScan\Command;

class ForeachCommandTest extends \GitScan\GitScanTestCase {
  public function setup() {
    parent::setup();
    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1');
    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1/repo-1b');
    $this->createExampleRepo($this->fixturePath . '/example-2/repo-2');
  }

  /**
   * If --command has no output, then the overall program has no output
   */
  public function testForeach_noOutput() {
    $commandTester = $this->createCommandTester(array(
      'command' => 'foreach',
      'path' => array($this->fixturePath),
      '--command' => 'touch foreach-output.txt'
    ));
    $this->assertEquals('', trim($commandTester->getDisplay(TRUE)));
    $this->assertTrue(file_exists($this->fixturePath . '/example-1/repo-1/foreach-output.txt'));
    $this->assertTrue(file_exists($this->fixturePath . '/example-1/repo-1/repo-1b/foreach-output.txt'));
    $this->assertTrue(file_exists($this->fixturePath . '/example-2/repo-2/foreach-output.txt'));
  }

  /**
   * If --command echos output, then the output is repeated for each
   * repo.
   */
  public function testForeach_output() {
    $commandTester = $this->createCommandTester(array(
      'command' => 'foreach',
      'path' => array($this->fixturePath),
      '--command' => 'echo "found $toplevel: $path"'
    ));

    $expected = ""
      . "found " . realpath($this->fixturePath) . ": example-1/repo-1/\n"
      . "found " . realpath($this->fixturePath) . ": example-1/repo-1/repo-1b/\n"
      . "found " . realpath($this->fixturePath) . ": example-2/repo-2/\n";
    $this->assertSameLines($expected, $commandTester->getDisplay(TRUE));
  }

  /**
   * Ensure that the correct chdir() was called before executing each command
   */
  public function testForeach_cwd() {
    $commandTester = $this->createCommandTester(array(
      'command' => 'foreach',
      'path' => array($this->fixturePath),
      '--command' => 'pwd'
    ));

    $expected = ""
      . realpath($this->fixturePath) . "/example-1/repo-1\n"
      . realpath($this->fixturePath) . "/example-1/repo-1/repo-1b\n"
      . realpath($this->fixturePath) . "/example-2/repo-2\n";
    $this->assertSameLines($expected, $commandTester->getDisplay(TRUE));
  }

  /**
   * If there is even one error on any of the repos, ensure
   * that it is reported.
   */
  public function testForeach_singleError() {
    $commandTester = $this->createCommandTester(array(
      'command' => 'foreach',
      'path' => array($this->fixturePath),
      '--command' => 'if [ "$path" = "example-1/repo-1/" ]; then echo "found $toplevel: $path" > /dev/stderr; exit 2; fi'
    ));

    $expected = ""
      . "found " . realpath($this->fixturePath) . ": example-1/repo-1/\n"
      . '[[ ' . realpath($this->fixturePath) . "/example-1/repo-1: exit code = 2 ]]\n";
    $this->assertEquals(trim($expected), trim($commandTester->getDisplay(TRUE)));
  }

  protected function assertSameLines($expected, $actual) {
    $actualLines = explode("\n", trim($actual));
    $expectedLines = explode("\n", trim($expected));
    sort($actualLines);
    sort($expectedLines);
    $this->assertEquals($expectedLines, $actualLines);
  }
}