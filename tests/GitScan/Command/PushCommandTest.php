<?php
namespace GitScan\Command;

use GitScan\GitRepo;
use GitScan\Util\Process as ProcessUtil;

class PushCommandTest extends \GitScan\GitScanTestCase {

  public function setUp(): void {
    parent::setUp();
  }

  public function testInvalidRemote() {
    $upstream = $this->createUpstreamRepo();
    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream, '1.x-master');
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));

    $commandTester = $this->createCommandTester(array(
      'command' => 'push',
      '--path' => $this->fixturePath,
      '--prefix' => 1,
      '--dry-run' => 1,
      'remote' => 'theremote',
      'refspec' => 'master',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(1, $commandTester->getStatusCode());
    $this->assertStringContainsString("does not have remote \"theremote\"", $output);
  }

  public function testOK() {
    $upstream = $this->createUpstreamRepo();
    $downstream = new GitRepo($this->fixturePath . '/downstream');
    $downstream->initClone($upstream, '1.x-master');
    $this->assertEquals("example text plus my feature", $downstream->readFile("example.txt"));

    $commandTester = $this->createCommandTester(array(
      'command' => 'push',
      '--path' => $this->fixturePath . '/downstream',
      '--prefix' => 1,
      '--dry-run' => 1,
      'remote' => 'origin',
      'refspec' => 'master',
    ));
    $output = $commandTester->getDisplay(FALSE);
    $this->assertEquals(0, $commandTester->getStatusCode());
    $this->assertStringContainsString("/downstream'\n\$ git push 'origin' '1.x-master'", $output);
  }

  /**
   * @param string $checkout
   *  the treeish to checkout
   * @return \GitScan\GitRepo
   *   A new upstream repo which includes these artifacts:
   *   - a master branch
   *   - a new (unmerged) feature branch (1.x-master)
   *   - a tag of after the merge of 1.x-master-1 (0.2)
   */
  protected function createUpstreamRepo($checkout = 'master') {
    $gitRepo = new GitRepo($this->fixturePath . '/upstream');
    if (!$gitRepo->init()) {
      throw new \RuntimeException("Error: Repo already exists!");
    }

    $gitRepo->commitFile("example.txt", "example text");
    ProcessUtil::runOk($gitRepo->command("git tag 0.1"));

    $gitRepo->commitFile("changelog.txt", "new in v0.2: don't know yet!");

    ProcessUtil::runOk($gitRepo->command("git checkout -b 1.x-master"));
    $gitRepo->commitFile("example.txt", "example text plus my feature");

    // Validate
    ProcessUtil::runOk($gitRepo->command("git checkout master"));
    $this->assertEquals("example text", $gitRepo->readFile("example.txt"));
    ProcessUtil::runOk($gitRepo->command("git checkout 1.x-master"));
    $this->assertEquals("example text plus my feature", $gitRepo->readFile("example.txt"));

    // Wrap up
    ProcessUtil::runOk($gitRepo->command("git checkout $checkout"));
    return $gitRepo;
  }

}
