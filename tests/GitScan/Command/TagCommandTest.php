<?php
namespace GitScan\Command;

use GitScan\GitRepo;
use GitScan\Util\Process;

class TagCommandTest extends \GitScan\GitScanTestCase {
  /**
   * @var GitRepo
   */
  protected $repo1, $repo1b, $repo2;

  public function setUp(): void {
    parent::setUp();

    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1');
    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1/repo-1b');
    $this->createExampleRepo($this->fixturePath . '/example-2/repo-2');
    $this->repo1 = new GitRepo($this->fixturePath . '/example-1/repo-1');
    $this->repo1b = new GitRepo($this->fixturePath . '/example-1/repo-1/repo-1b');
    $this->repo2 = new GitRepo($this->fixturePath . '/example-2/repo-2');
  }

  /**
   * Make tags, but only for matching branches.
   */
  public function testTag_plain() {
    Process::runOk($this->repo1->command("git branch -m master 3.0"));
    Process::runOk($this->repo1b->command("git branch -m master 1.x-3.0"));
    Process::runOk($this->repo2->command("git branch -m master 7.x-3.0"));

    $this->assertNotContains('3.0.1', $this->repo1->getTags());
    $this->assertNotContains('1.x-3.0.1', $this->repo1b->getTags());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getTags());

    $commandTester = $this->createCommandTester(array(
      'command' => 'tag',
      '--path' => $this->fixturePath,
      '--prefix' => 0,
      'tagName' => '3.0.1',
      'head' => '3.0',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertContains('In "example-1/repo-1/", make tag "3.0.1" from "3.0"', $output);
    $this->assertNotContains('In "example-1/repo-1/repo-1b/", make tag "1.x-3.0.1" from "1.x-3.0"', $output);
    $this->assertNotContains('In "example-2/repo-2/", make tag "7.x-3.0.1" from "7.x-3.0"', $output);

    $this->assertContains('3.0.1', $this->repo1->getTags());
    $this->assertNotContains('1.x-3.0.1', $this->repo1b->getTags());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getTags());
  }

  /**
   * Make tags. Auto-adjust for prefixes. Note that all the repos have matches.
   */
  public function testTag_prefix() {
    Process::runOk($this->repo1->command("git branch -m master 3.0"));
    Process::runOk($this->repo1b->command("git branch -m master 1.x-3.0"));
    Process::runOk($this->repo2->command("git branch -m master 7.x-3.0"));

    $this->assertNotContains('3.0.1', $this->repo1->getTags());
    $this->assertNotContains('1.x-3.0.1', $this->repo1b->getTags());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getTags());

    $commandTester = $this->createCommandTester(array(
      'command' => 'tag',
      '--path' => $this->fixturePath,
      '--prefix' => 1,
      'tagName' => '3.0.1',
      'head' => '3.0',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertContains('In "example-1/repo-1/", make tag "3.0.1" from "3.0"', $output);
    $this->assertContains('In "example-1/repo-1/repo-1b/", make tag "1.x-3.0.1" from "1.x-3.0"', $output);
    $this->assertContains('In "example-2/repo-2/", make tag "7.x-3.0.1" from "7.x-3.0"', $output);

    $this->assertContains('3.0.1', $this->repo1->getTags());
    $this->assertContains('1.x-3.0.1', $this->repo1b->getTags());
    $this->assertContains('7.x-3.0.1', $this->repo2->getTags());
  }

  /**
   * Make tags. Auto-adjust for prefixes. Note that only repo has a match.
   */
  public function testTag_prefix2() {
    Process::runOk($this->repo1b->command("git branch -m master 1.x-3.0"));

    $this->assertNotContains('3.0.1', $this->repo1->getTags());
    $this->assertNotContains('1.x-3.0.1', $this->repo1b->getTags());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getTags());

    $commandTester = $this->createCommandTester(array(
      'command' => 'tag',
      '--path' => $this->fixturePath,
      '--prefix' => 1,
      'tagName' => '3.0.1',
      'head' => '3.0',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertNotContains('In "example-1/repo-1/", make tag "3.0.1" from "3.0"', $output);
    $this->assertContains('In "example-1/repo-1/repo-1b/", make tag "1.x-3.0.1" from "1.x-3.0"', $output);
    $this->assertNotContains('In "example-2/repo-2/", make tag "7.x-3.0.1" from "7.x-3.0"', $output);

    $this->assertNotContains('3.0.1', $this->repo1->getTags());
    $this->assertContains('1.x-3.0.1', $this->repo1b->getTags());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getTags());
  }

  public function testTag_delete() {
    Process::runOk($this->repo1b->command("git branch -m master 1.x-master"));
    Process::runOk($this->repo1b->command("git tag 1.x-1.0 1.x-master"));

    $commandTester = $this->createCommandTester(array(
      'command' => 'tag',
      '--path' => $this->fixturePath,
      '--delete' => 1,
      '--prefix' => 1,
      '--dry-run' => 1,
      'tagName' => '1.0',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertContains('In "example-1/repo-1/repo-1b/", delete tag "1.x-1.0"', $output);
    $this->assertContains("repo-1b'\n$ git tag -d '1.x-1.0'", $output);
  }

}