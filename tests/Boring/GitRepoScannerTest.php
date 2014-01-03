<?php
namespace Boring;

use Boring\Util\Process as ProcessUtil;
use Symfony\Component\Filesystem\Filesystem;

class GitRepoScannerTest extends BoringTestCase {

  public function testScan() {
    // Make some superfluous files and directories
    foreach (array('/modules', '/modules/extra-1', '/themes/extra-1', '/sites/all/modules/extra-2') as $subdir) {
      $dir = $this->fixturePath . $subdir;
      $this->fs->mkdir($dir);
      $this->fs->dumpFile($dir . '/example.txt', 'hello');
    }

    // Make git repos with files
    foreach (array('', '/sites/all/modules/real-1', '/sites/default/real-2') as $subdir) {
      $dir = $this->fixturePath . $subdir;
      $this->fs->mkdir($dir);
      $this->fs->dumpFile($dir . '/example.txt', "hello $subdir");
      ProcessUtil::runOk($this->command($dir, "git init"));
      ProcessUtil::runOk($this->command($dir, "git add example.txt"));
      ProcessUtil::runOk($this->command($dir, "git commit -m Import example.txt"));
    }

    $scanner = new GitRepoScanner();
    $gitRepos = $scanner->scan($this->fixturePath);
    $actuals = array();
    foreach ($gitRepos as $actual) {
      $actuals[] = $actual->getPath();
    }
    sort($actuals);

    $expecteds = array(
      $this->fixturePath,
      $this->fixturePath . '/sites/all/modules/real-1',
      $this->fixturePath . '/sites/default/real-2'
    );
    $this->assertEquals($expecteds, $actuals);
  }

}