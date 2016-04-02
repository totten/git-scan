<?php
namespace GitScan\Command;

use GitScan\GitRepo;
use GitScan\Util\Process as ProcessUtil;

class AutoMergeCommandTest extends \GitScan\GitScanTestCase {
  public function setup() {
    parent::setup();
  }

  /**
   * Ex: git scan automerge ;upstreamurlregex;/path/to/patchfile
   */
  public function testAutoMergeUpstream() {
    $upstream = $this->createUpstreamRepo();

    mkdir("{$this->fixturePath}/subdir");
    ProcessUtil::runOk($this->command("", "git clone file://{$upstream->getPath()} {$this->fixturePath}/subdir/downstream"));
    $downstream = new GitRepo($this->fixturePath . '/subdir/downstream');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));
    $this->assertEquals("master", $downstream->getLocalBranch());

    $patchFile = $this->createPatchFile($upstream, 'mypatch');

    $this->assertNotRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));

    // Run it the first time

    $commandTester = $this->createCommandTester(array(
      'command' => 'automerge',
      '--path' => $this->fixturePath,
      'url' => array(";/upstream;$patchFile"),  // Find the dir based on upstream remote.
    ));
    $this->assertContains(
      'In subdir/downstream/, create branch "master-automerge" using "origin/master".',
      $commandTester->getDisplay(FALSE));
    $this->assertContains(
      'In subdir/downstream/, merge',
      $commandTester->getDisplay(FALSE));
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertEquals("master-automerge", $downstream->getLocalBranch()); // because --branch=upstream
    $this->assertRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));

    // Run it a second time. On the second, it has to work harder to reset
    // the old branch.

    $commandTester = $this->createCommandTester(array(
      'command' => 'automerge',
      '--path' => $this->fixturePath,
      '--force' => 1,
      'url' => array(";/upstream;$patchFile"),  // Find the dir based on upstream remote.
    ));
    $this->assertContains(
      'Proceed with re-creating "master-automerge"? [y/n] y',
      $commandTester->getDisplay(FALSE));
    $this->assertContains(
      'In subdir/downstream/, merge',
      $commandTester->getDisplay(FALSE));
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertEquals("master-automerge", $downstream->getLocalBranch()); // because --branch=upstream
    $this->assertRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));
  }

  /**
   * Ex: git scan automerge --branch=current ;upstreamurlregex;/path/to/patchfile
   */
  public function testAutoMergeCurrent() {
    $upstream = $this->createUpstreamRepo();

    mkdir("{$this->fixturePath}/subdir");
    ProcessUtil::runOk($this->command("", "git clone file://{$upstream->getPath()} {$this->fixturePath}/subdir/downstream"));
    $downstream = new GitRepo($this->fixturePath . '/subdir/downstream');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));
    $this->assertEquals("master", $downstream->getLocalBranch());

    $patchFile = $this->createPatchFile($upstream, 'mypatch');

    $this->assertNotRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));

    $commandTester = $this->createCommandTester(array(
      'command' => 'automerge',
      '--path' => $this->fixturePath,
      '--branch' => 'current',
      'url' => array(";/upstream;$patchFile"),  // Find the dir based on upstream remote.
    ));
    $this->assertContains(
      'In subdir/downstream/, keeping current branch "master"',
      $commandTester->getDisplay(FALSE));
    $this->assertContains(
      'In subdir/downstream/, merge',
      $commandTester->getDisplay(FALSE));
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertEquals("master", $downstream->getLocalBranch()); // because --branch=current
    $this->assertRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));
  }

  protected function createUpstreamRepo($checkout = 'master') {
    $gitRepo = new GitRepo($this->fixturePath . '/upstream');
    if (!$gitRepo->init()) {
      throw new \RuntimeException("Error: Repo already exists!");
    }

    $gitRepo->commitFile("example.txt", "example text");
    ProcessUtil::runOk($gitRepo->command("git tag 0.1"));

    $gitRepo->commitFile("changelog.txt", "new in v0.2: don't know yet!");

    ProcessUtil::runOk($gitRepo->command("git checkout -b my-feature"));
    $gitRepo->commitFile("example.txt", "example text plus my feature");

    // Validate
    ProcessUtil::runOk($gitRepo->command("git checkout master"));
    $this->assertEquals("example text", $gitRepo->readFile("example.txt"));
    ProcessUtil::runOk($gitRepo->command("git checkout my-feature"));
    $this->assertEquals("example text plus my feature", $gitRepo->readFile("example.txt"));

    // Wrap up
    ProcessUtil::runOk($gitRepo->command("git checkout $checkout"));
    return $gitRepo;
  }

  /**
   * @param \GitScan\GitRepo $gitRepo
   * @param $name
   * @return string
   *   Path to the patch file.
   */
  protected function createPatchFile(GitRepo $gitRepo, $name) {
    $file = $this->fixturePath . '/' . $name . '.patch';

    ProcessUtil::runOk($gitRepo->command("git checkout master -b tmp"));
    $gitRepo->commitFile("changelog.txt", "new in v0.3: the future has been patched!");
    ProcessUtil::runOk($gitRepo->command("git format-patch --stdout HEAD~1 > $file"));
    ProcessUtil::runOk($gitRepo->command("git checkout master"));
    return $file;
  }

}