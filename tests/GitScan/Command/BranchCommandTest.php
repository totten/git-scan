<?php
namespace GitScan\Command;

use GitScan\GitRepo;
use GitScan\Util\Process;

class BranchCommandTest extends \GitScan\GitScanTestCase {
  /**
   * @var GitRepo
   */
  protected $repo1, $repo1b, $repo2;

  public function setup() {
    parent::setup();

    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1');
    $this->createExampleRepo($this->fixturePath . '/example-1/repo-1/repo-1b');
    $this->createExampleRepo($this->fixturePath . '/example-2/repo-2');
    $this->repo1 = new GitRepo($this->fixturePath . '/example-1/repo-1');
    $this->repo1b = new GitRepo($this->fixturePath . '/example-1/repo-1/repo-1b');
    $this->repo2 = new GitRepo($this->fixturePath . '/example-2/repo-2');
  }

  /**
   * make branchs, but only for matching branches.
   */
  public function testTag_plain() {
    Process::runOk($this->repo1->command("git branch -m master 3.0"));
    Process::runOk($this->repo1b->command("git branch -m master 1.x-3.0"));
    Process::runOk($this->repo2->command("git branch -m master 7.x-3.0"));

    $this->assertNotContains('3.0.1', $this->repo1->getBranches());
    $this->assertNotContains('1.x-3.0.1', $this->repo1b->getBranches());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getBranches());

    $commandTester = $this->createCommandTester(array(
      'command' => 'branch',
      '--path' => $this->fixturePath,
      '--prefix' => 0,
      'branchName' => '3.0.1',
      'head' => '3.0',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertContains('In "example-1/repo-1/", make branch "3.0.1" from "3.0"', $output);
    $this->assertNotContains('In "example-1/repo-1/repo-1b/", make branch "1.x-3.0.1" from "1.x-3.0"', $output);
    $this->assertNotContains('In "example-2/repo-2/", make branch "7.x-3.0.1" from "7.x-3.0"', $output);

    $this->assertContains('3.0.1', $this->repo1->getBranches());
    $this->assertNotContains('1.x-3.0.1', $this->repo1b->getBranches());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getBranches());
  }

  /**
   * make branchs. Auto-adjust for prefixes. Note that all the repos have matches.
   */
  public function testTag_prefix() {
    Process::runOk($this->repo1->command("git branch -m master 3.0"));
    Process::runOk($this->repo1b->command("git branch -m master 1.x-3.0"));
    Process::runOk($this->repo2->command("git branch -m master 7.x-3.0"));

    $this->assertNotContains('3.0.1', $this->repo1->getBranches());
    $this->assertNotContains('1.x-3.0.1', $this->repo1b->getBranches());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getBranches());

    $commandTester = $this->createCommandTester(array(
      'command' => 'branch',
      '--path' => $this->fixturePath,
      '--prefix' => 1,
      'branchName' => '3.0.1',
      'head' => '3.0',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertContains('In "example-1/repo-1/", make branch "3.0.1" from "3.0"', $output);
    $this->assertContains('In "example-1/repo-1/repo-1b/", make branch "1.x-3.0.1" from "1.x-3.0"', $output);
    $this->assertContains('In "example-2/repo-2/", make branch "7.x-3.0.1" from "7.x-3.0"', $output);

    $this->assertContains('3.0.1', $this->repo1->getBranches());
    $this->assertContains('1.x-3.0.1', $this->repo1b->getBranches());
    $this->assertContains('7.x-3.0.1', $this->repo2->getBranches());
  }

  /**
   * make branchs. Auto-adjust for prefixes. Note that only repo has a match.
   */
  public function testTag_prefix2() {
    Process::runOk($this->repo1b->command("git branch -m master 1.x-3.0"));

    $this->assertNotContains('3.0.1', $this->repo1->getBranches());
    $this->assertNotContains('1.x-3.0.1', $this->repo1b->getBranches());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getBranches());

    $commandTester = $this->createCommandTester(array(
      'command' => 'branch',
      '--path' => $this->fixturePath,
      '--prefix' => 1,
      'branchName' => '3.0.1',
      'head' => '3.0',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertNotContains('In "example-1/repo-1/", make branch "3.0.1" from "3.0"', $output);
    $this->assertContains('In "example-1/repo-1/repo-1b/", make branch "1.x-3.0.1" from "1.x-3.0"', $output);
    $this->assertNotContains('In "example-2/repo-2/", make branch "7.x-3.0.1" from "7.x-3.0"', $output);

    $this->assertNotContains('3.0.1', $this->repo1->getBranches());
    $this->assertContains('1.x-3.0.1', $this->repo1b->getBranches());
    $this->assertNotContains('7.x-3.0.1', $this->repo2->getBranches());
  }

}