<?php
namespace GitScan\Command;

use GitScan\Exception\ProcessErrorException;
use GitScan\GitRepo;
use GitScan\Util\Process as ProcessUtil;

class AutoMergeCommandTest extends \GitScan\GitScanTestCase {
  public function setUp(): void {
    parent::setUp();
  }

  /**
   * Rebuild the branch with clean history (based on upstream).
   *
   * Ex: git scan automerge --rebuild ;upstreamurlregex;/path/to/patchfile
   */
  public function testAutoMergeRebuild() {
    $upstream = $this->createUpstreamRepo();

    mkdir("{$this->fixturePath}/subdir");
    ProcessUtil::runOk($this->command("", "git clone file://{$upstream->getPath()} {$this->fixturePath}/subdir/downstream"));
    $downstream = new GitRepo($this->fixturePath . '/subdir/downstream');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));
    $this->assertEquals("master", $downstream->getLocalBranch());

    $patchFile = $this->createPatchFile($upstream, 'mypatch');

    $this->assertNotRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));

    $downstream->commitFile("example.txt", "some unrelated local changes");
    $this->assertRegExp('/some unrelated local changes/', $downstream->readFile('example.txt'));

    // Run it the first time

    $commandTester = $this->createCommandTester(array(
      'command' => 'automerge',
      '--path' => $this->fixturePath,
      '--rebuild' => 1,
      'url' => array(";/upstream;$patchFile"),  // Find the dir based on upstream remote.
    ));
    $this->assertStringContainsString(
      'In "subdir/downstream/", rename "master" to "backup-master-',
      $commandTester->getDisplay(FALSE));
    $this->assertStringContainsString(
      'In "subdir/downstream/", apply',
      $commandTester->getDisplay(FALSE));
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertEquals("master", $downstream->getLocalBranch());
    $this->assertRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));

    // Rebuilt from upstream. Local changes were lost.
    $this->assertNotRegExp('/some unrelated local changes/', $downstream->readFile('example.txt'));
  }

  /**
   * Merge a patch into the currently-active branch.
   *
   * Ex: git scan automerge ;upstreamurlregex;/path/to/patchfile
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

    $downstream->commitFile("example.txt", "some unrelated local changes");
    $this->assertRegExp('/some unrelated local changes/', $downstream->readFile('example.txt'));

    $commandTester = $this->createCommandTester(array(
      'command' => 'automerge',
      '--path' => $this->fixturePath,
      '--keep' => 1,
      'url' => array(";/upstream;$patchFile"),  // Find the dir based on upstream remote.
    ));
    $this->assertStringContainsString(
      'In "subdir/downstream/", keep the current branch "master"',
      $commandTester->getDisplay(FALSE));
    $this->assertStringContainsString(
      'In "subdir/downstream/", apply',
      $commandTester->getDisplay(FALSE));
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertEquals("master", $downstream->getLocalBranch()); // because --branch=current
    $this->assertRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));

    // Preserved local changes.
    $this->assertRegExp('/some unrelated local changes/', $downstream->readFile('example.txt'));
  }

  /**
   * Merge a patch into the currently-active branch.
   *
   * Ex: git scan automerge ;upstreamurlregex;/path/to/patchfile
   */
  public function testAutoMergeCurrentConflict() {
    $upstream = $this->createUpstreamRepo();

    mkdir("{$this->fixturePath}/subdir");
    ProcessUtil::runOk($this->command("", "git clone file://{$upstream->getPath()} {$this->fixturePath}/subdir/downstream"));
    $downstream = new GitRepo($this->fixturePath . '/subdir/downstream');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));
    $this->assertEquals("master", $downstream->getLocalBranch());

    $patchFile = $this->createPatchFile($upstream, 'mypatch');
    $this->assertNotRegExp('/the future has been patched/', $downstream->readFile('changelog.txt'));

    $downstream->commitFile("example.txt", "some unrelated local changes");
    $this->assertRegExp('/some unrelated local changes/', $downstream->readFile('example.txt'));

    // Introduce a conflict
    $downstream->commitFile("changelog.txt", "this is bad! it has  conflict!");

    try {
      $commandTester = $this->createCommandTester(array(
        'command' => 'automerge',
        '--path' => $this->fixturePath,
        '--keep' => 1,
        'url' => array(";/upstream;$patchFile"),  // Find the dir based on upstream remote.
      ));
      $this->fail("Expected ProcessErrorException");
    } catch (ProcessErrorException $e) {
      $this->assertTrue($e->getProcess()->getExitCode() > 0);
      $this->assertStringContainsString(
        'patch failed',
        $e->getProcess()->getErrorOutput());
    }
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