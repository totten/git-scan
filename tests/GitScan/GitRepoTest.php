<?php
namespace GitScan;
use GitScan\Util\Process as ProcessUtil;

class GitRepoTest extends GitScanTestCase {

  public function testLocalOnly_Empty() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();

    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getUpstreamBranch());
    $this->assertEquals(FALSE, $gitRepo->hasUncommittedChanges());
    $this->assertEquals(FALSE, $gitRepo->hasUntrackedFiles());
  }

  public function testLocalOnly_AllCommitted() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $gitRepo->commitFile('example.txt', 'first');

    $this->assertIsCommit($gitRepo->getCommit());
    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getUpstreamBranch());
    $this->assertEquals(FALSE, $gitRepo->hasUncommittedChanges());
    $this->assertEquals(FALSE, $gitRepo->hasUntrackedFiles());
  }

  public function testLocalOnly_ModifiedFile() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $gitRepo->commitFile('example.txt', 'first');
    $gitRepo->writeFile('example.txt', 'second');

    $this->assertIsCommit($gitRepo->getCommit());
    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getUpstreamBranch());
    $this->assertEquals(TRUE, $gitRepo->hasUncommittedChanges());
    $this->assertEquals(FALSE, $gitRepo->hasUntrackedFiles());
  }

  public function testLocalOnly_NewFile() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $gitRepo->commitFile('example.txt', 'first');
    $gitRepo->writeFile('example-2.txt', 'second');

    $this->assertIsCommit($gitRepo->getCommit());
    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getUpstreamBranch());
    $this->assertEquals(FALSE, $gitRepo->hasUncommittedChanges());
    $this->assertEquals(TRUE, $gitRepo->hasUntrackedFiles());
  }

  public function testLocalOnly_NewDir() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $gitRepo->commitFile('example.txt', 'first');
    $gitRepo->writeFile('newdir/example.txt', 'second');

    $this->assertIsCommit($gitRepo->getCommit());
    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getUpstreamBranch());
    $this->assertEquals(FALSE, $gitRepo->hasUncommittedChanges());
    $this->assertEquals(TRUE, $gitRepo->hasUntrackedFiles());
  }

  public function testLocalOnly_NewAndModifiedFiles() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $gitRepo->commitFile('example.txt', 'first');
    $gitRepo->writeFile('example.txt', 'second');
    $gitRepo->writeFile('newdir/example.txt', 'second');

    $this->assertIsCommit($gitRepo->getCommit());
    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getUpstreamBranch());
    $this->assertEquals(TRUE, $gitRepo->hasUncommittedChanges());
    $this->assertEquals(TRUE, $gitRepo->hasUntrackedFiles());
  }

  public function testClonedDefaultBranch_Fresh() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream);
    $this->assertEquals("example text", $downstream->readFile("example.txt"));

    $this->assertIsCommit($downstream->getCommit());
    $this->assertEquals($upstream->getCommit(), $downstream->getCommit());
    $this->assertEquals('master', $downstream->getLocalBranch());
    $this->assertEquals('origin/master', $downstream->getUpstreamBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
    $this->assertEquals(FALSE, $downstream->hasUntrackedFiles());

    $this->assertEquals(array(), $upstream->getRemotes());
    $this->assertEquals(array('origin'), $downstream->getRemotes());
    $this->assertEquals("file://{$upstream->getPath()}", $downstream->getRemoteUrl('origin'));
    $this->assertEquals(array('origin' => "file://{$upstream->getPath()}"), $downstream->getRemoteUrls());
  }

  public function testClonedMasterBranch_Fresh() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream, 'master');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));

    $this->assertIsCommit($downstream->getCommit());
    $this->assertEquals($upstream->getCommit(), $downstream->getCommit());
    $this->assertEquals('master', $downstream->getLocalBranch());
    $this->assertEquals('origin/master', $downstream->getUpstreamBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
    $this->assertEquals(FALSE, $downstream->hasUntrackedFiles());

    $this->assertEquals(array(), $upstream->getRemotes());
    $this->assertEquals(array('origin'), $downstream->getRemotes());
    $this->assertEquals("file://{$upstream->getPath()}", $downstream->getRemoteUrl('origin'));
    $this->assertEquals(array('origin' => "file://{$upstream->getPath()}"), $downstream->getRemoteUrls());
  }

  public function testClonedTag() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream);
    $this->assertEquals("example text", $downstream->readFile("example.txt"));
    ProcessUtil::runOk($downstream->command("git checkout 0.1"));

    $this->assertIsCommit($downstream->getCommit());
    $this->assertNotEquals($upstream->getCommit(), $downstream->getCommit());
    $this->assertEquals('tags/0.1', $downstream->getLocalBranch());
    $this->assertEquals(NULL, $downstream->getUpstreamBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
    $this->assertEquals(FALSE, $downstream->hasUntrackedFiles());

    $this->assertEquals(array(), $upstream->getRemotes());
    $this->assertEquals(array('origin'), $downstream->getRemotes());
  }

  public function testClonedMyFeatureBranch_Fresh() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream, 'my-feature');
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));

    $this->assertIsCommit($downstream->getCommit());
    $this->assertEquals('my-feature', $downstream->getLocalBranch());
    $this->assertEquals('origin/my-feature', $downstream->getUpstreamBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
    $this->assertEquals(FALSE, $downstream->hasUntrackedFiles());

    $this->assertEquals(array(), $upstream->getRemotes());
    $this->assertEquals(array('origin'), $downstream->getRemotes());
    $this->assertEquals("file://{$upstream->getPath()}", $downstream->getRemoteUrl('origin'));
    $this->assertEquals(array('origin' => "file://{$upstream->getPath()}"), $downstream->getRemoteUrls());
  }

  public function testCloned_CheckoutMyFeature_Fresh() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream);
    ProcessUtil::runOk($downstream->command("git checkout my-feature"));
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));

    $this->assertIsCommit($downstream->getCommit());
    $this->assertEquals('my-feature', $downstream->getLocalBranch());
    $this->assertEquals('origin/my-feature', $downstream->getUpstreamBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
    $this->assertEquals(FALSE, $downstream->hasUntrackedFiles());

    $this->assertEquals(array(), $upstream->getRemotes());
    $this->assertEquals(array('origin'), $downstream->getRemotes());
    $this->assertEquals("file://{$upstream->getPath()}", $downstream->getRemoteUrl('origin'));
    $this->assertEquals(array('origin' => "file://{$upstream->getPath()}"), $downstream->getRemoteUrls());
  }

  public function testCloned_CheckoutMyFeature_Modified() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream);
    ProcessUtil::runOk($downstream->command("git checkout my-feature"));
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));
    $downstream->writeFile("example.txt", "ch-ch-changes");

    $this->assertIsCommit($downstream->getCommit());
    $this->assertEquals('my-feature', $downstream->getLocalBranch());
    $this->assertEquals('origin/my-feature', $downstream->getUpstreamBranch());
    $this->assertEquals(TRUE, $downstream->hasUncommittedChanges());
    $this->assertEquals(FALSE, $downstream->hasUntrackedFiles());

    $this->assertEquals(array(), $upstream->getRemotes());
    $this->assertEquals(array('origin'), $downstream->getRemotes());
    $this->assertEquals("file://{$upstream->getPath()}", $downstream->getRemoteUrl('origin'));
    $this->assertEquals(array('origin' => "file://{$upstream->getPath()}"), $downstream->getRemoteUrls());
  }

  public function testCloned_CheckoutMyFeature_Newfile() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream);
    ProcessUtil::runOk($downstream->command("git checkout my-feature"));
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));
    $downstream->writeFile("example-2.txt", "second");

    $this->assertIsCommit($downstream->getCommit());
    $this->assertEquals('my-feature', $downstream->getLocalBranch());
    $this->assertEquals('origin/my-feature', $downstream->getUpstreamBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
    $this->assertEquals(TRUE, $downstream->hasUntrackedFiles());

    $this->assertEquals(array(), $upstream->getRemotes());
    $this->assertEquals(array('origin'), $downstream->getRemotes());
    $this->assertEquals("file://{$upstream->getPath()}", $downstream->getRemoteUrl('origin'));
    $this->assertEquals(array('origin' => "file://{$upstream->getPath()}"), $downstream->getRemoteUrls());
  }

  public function testIsFastForwardable_upstreamChanged() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream, 'master');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));

    // note: messages may appear different before/after fetching
    $this->assertIsCommit($downstream->getCommit());
    $upstream->commitFile('example-from-upstream.txt', 'upstream change example');
    $this->assertEquals(TRUE, $downstream->isLocalFastForwardable(TRUE));
    ProcessUtil::runOk($downstream->command("git fetch"));
    $this->assertEquals(TRUE, $downstream->isLocalFastForwardable(TRUE));
  }

  public function testIsFastForwardable_downstreamChanged() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream, 'master');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));

    $downstream->commitFile('example-from-downstream.txt', 'downstream change example');
    $this->assertEquals(FALSE, $downstream->isLocalFastForwardable(TRUE));

    $this->assertIsCommit($upstream->getCommit());
    $this->assertIsCommit($downstream->getCommit());
  }

  public function testIsFastForwardable_bothChanged() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream, 'master');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));

    $upstream->commitFile('example-from-upstream.txt', 'upstream change example');
    $downstream->commitFile('example-from-downstream.txt', 'downstream change example');
    $this->assertEquals(FALSE, $downstream->isLocalFastForwardable(TRUE));

    $this->assertIsCommit($upstream->getCommit());
    $this->assertIsCommit($downstream->getCommit());
    $this->assertNotEquals($upstream->getCommit(), $downstream->getCommit());
  }

  public function testHasStash() {
    $upstream = $this->createUpstreamRepo();

    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream, 'master');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));

    $this->assertEquals(FALSE, $downstream->hasStash());
    $downstream->writeFile('example.txt', 'example of stashed change');
    ProcessUtil::runOk($downstream->command("git stash save"));
    $this->assertEquals(TRUE, $downstream->hasStash());
    ProcessUtil::runOk($downstream->command("git stash pop"));
    $this->assertEquals(FALSE, $downstream->hasStash());
  }


  /**
   * @param string $checkout the treeish to checkout
   * @return GitRepo the upstream repo has:
   *  - a master branch
   *  - a new (unmerged) feature branch (my-feature)
   *  - a tag of after the merge of my-feature-1 (0.2)
   */
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
}
