<?php
namespace Boring;

class GitRepoTest extends BoringTestCase {

  public function testLocalOnly_Empty() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getTrackingBranch());
    $this->assertEquals(FALSE, $gitRepo->hasUncommittedChanges());
  }

  public function testLocalOnly_AllCommitted() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $gitRepo->commitFile('example.txt', 'first');

    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getTrackingBranch());
    $this->assertEquals(FALSE, $gitRepo->hasUncommittedChanges());
  }

  public function testLocalOnly_ModifiedFile() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $gitRepo->commitFile('example.txt', 'first');
    $gitRepo->writeFile('example.txt', 'second');

    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getTrackingBranch());
    $this->assertEquals(TRUE, $gitRepo->hasUncommittedChanges());
  }

  public function testLocalOnly_NewFile() {
    $gitRepo = new GitRepo($this->fixturePath);
    $gitRepo->init();
    $gitRepo->commitFile('example.txt', 'first');
    $gitRepo->writeFile('example-2.txt', 'second');

    $this->assertEquals('master', $gitRepo->getLocalBranch());
    $this->assertEquals(NULL, $gitRepo->getTrackingBranch());
    $this->assertEquals(TRUE, $gitRepo->hasUncommittedChanges());
  }

  public function testClonedDefaultBranch_Fresh() {
    $upstream = $this->createUpstreamRepo();

    ProcessUtils::runOk($this->command("", "git clone file://{$upstream->getPath()} downstream"));
    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));

    $this->assertEquals('master', $downstream->getLocalBranch());
    $this->assertEquals('origin/master', $downstream->getTrackingBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
  }

  public function testClonedMasterBranch_Fresh() {
    $upstream = $this->createUpstreamRepo();

    ProcessUtils::runOk($this->command("", "git clone file://{$upstream->getPath()} downstream"));
    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $this->assertEquals("example text", $downstream->readFile("example.txt"));

    $this->assertEquals('master', $downstream->getLocalBranch());
    $this->assertEquals('origin/master', $downstream->getTrackingBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
  }

  public function testClonedMyFeatureBranch_Fresh() {
    $upstream = $this->createUpstreamRepo();

    ProcessUtils::runOk($this->command("", "git clone file://{$upstream->getPath()} downstream -b my-feature"));
    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));

    $this->assertEquals('my-feature', $downstream->getLocalBranch());
    $this->assertEquals('origin/my-feature', $downstream->getTrackingBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
  }

  public function testCloned_CheckoutMyFeature_Fresh() {
    $upstream = $this->createUpstreamRepo();

    ProcessUtils::runOk($this->command("", "git clone file://{$upstream->getPath()} downstream"));
    $downstream = new GitRepo($this->fixturePath . '/downstream');
    ProcessUtils::runOk($downstream->command("git checkout my-feature"));
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));

    $this->assertEquals('my-feature', $downstream->getLocalBranch());
    $this->assertEquals('origin/my-feature', $downstream->getTrackingBranch());
    $this->assertEquals(FALSE, $downstream->hasUncommittedChanges());
  }

  public function testCloned_CheckoutMyFeature_Modified() {
    $upstream = $this->createUpstreamRepo();

    ProcessUtils::runOk($this->command("", "git clone file://{$upstream->getPath()} downstream"));
    $downstream = new GitRepo($this->fixturePath . '/downstream');
    ProcessUtils::runOk($downstream->command("git checkout my-feature"));
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));
    $downstream->writeFile("example.txt", "ch-ch-changes");

    $this->assertEquals('my-feature', $downstream->getLocalBranch());
    $this->assertEquals('origin/my-feature', $downstream->getTrackingBranch());
    $this->assertEquals(TRUE, $downstream->hasUncommittedChanges());
  }

  public function testCloned_CheckoutMyFeature_Newfile() {
    $upstream = $this->createUpstreamRepo();

    ProcessUtils::runOk($this->command("", "git clone file://{$upstream->getPath()} downstream"));
    $downstream = new GitRepo($this->fixturePath . '/downstream');
    ProcessUtils::runOk($downstream->command("git checkout my-feature"));
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));
    $downstream->writeFile("example-2.txt", "second");

    $this->assertEquals('my-feature', $downstream->getLocalBranch());
    $this->assertEquals('origin/my-feature', $downstream->getTrackingBranch());
    $this->assertEquals(TRUE, $downstream->hasUncommittedChanges());
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
    ProcessUtils::runOk($gitRepo->command("git tag 0.1"));

    ProcessUtils::runOk($gitRepo->command("git checkout -b my-feature"));
    $gitRepo->commitFile("example.txt", "example text plus my feature");

    // Validate
    ProcessUtils::runOk($gitRepo->command("git checkout master"));
    $this->assertEquals("example text", $gitRepo->readFile("example.txt"));
    ProcessUtils::runOk($gitRepo->command("git checkout my-feature"));
    $this->assertEquals("example text plus my feature", $gitRepo->readFile("example.txt"));

    // Wrap up
    ProcessUtils::runOk($gitRepo->command("git checkout $checkout"));
    return $gitRepo;
  }
}