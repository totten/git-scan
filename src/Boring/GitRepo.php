<?php
namespace Boring;
use Symfony\Component\Filesystem\Filesystem;

class GitRepo {
  /**
   * @var Filesystem
   */
  private $fs;

  /**
   * @var string the bath in which "git" commands are executed
   */
  private $path;

  /**
   * @var string command-line output from "git status --porcelain"
   */
  private $porcelain;

  function __construct($path) {
    $this->fs = new Filesystem();
    $this->path = $path;
  }

  /* --------------- Main interfaces --------------- */

  /**
   * Determine the name of the local branch
   *
   * @return string|NULL the name of the local branch (eg "master"); NULL if detached
   * @throws \RuntimeException
   */
  public function getLocalBranch() {
    $process = $this->command("git symbolic-ref -q HEAD");
    $process->run();
    $symbolicRef = trim($process->getOutput());
    if (empty($symbolicRef)) {
      return NULL;
    }
    if (preg_match(":^refs/heads/(.*)$:", $symbolicRef, $matches)) {
      return $matches[1];
    } else {
      throw new \RuntimeException("Unrecognized symbolic ref [$symbolicRef]");
    }
  }

  public function getTrackingBranch() {
    $process = $this->command("git rev-parse --abbrev-ref @{upstream}");
    $process->run();
    $symbolicRef = trim($process->getOutput());
    if (!$process->isSuccessful() && "@{upstream}" == $symbolicRef) {
      return NULL;
    }
    if (preg_match(":[a-zA-Z0-9\_\.\/]+:", $symbolicRef)) {
      return $symbolicRef;
    } else {
      throw new \RuntimeException("Failed to determine tracking branch");
    }
  }

  public function hasUncommittedChanges($fresh = FALSE) {
    return $this->getPorcelain($fresh) ? TRUE : FALSE;
  }

  /* --------------- Helpers to facilitate testing --------------- */

  public function getPorcelain($fresh = FALSE) {
    if (!$this->porcelain) {
      $process = ProcessUtils::runOk($this->command("git status --porcelain"));
      $this->porcelain = $process->getOutput();
    }
    return $this->porcelain;
  }

  /**
   * @return bool TRUE if new one created; FALSE if already initialized
   */
  public function init() {
    if (!$this->fs->exists($this->path)) {
      $this->fs->mkdir($this->path);
    }
    if (!$this->fs->exists($this->path . DIRECTORY_SEPARATOR . '.git')) {
      ProcessUtils::runOk($this->command("git init"));
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Prepare a command to run in the repo's directory
   *
   * @param $command
   * @return \Symfony\Component\Process\Process
   */
  public function command($command) {
    $process = new \Symfony\Component\Process\Process($command);
    $process->setWorkingDirectory($this->getPath());
    return $process;
  }

  /**
   * @param string $relPath
   * @return string file content
   */
  public function readFile($relPath) {
    return file_get_contents($this->path . DIRECTORY_SEPARATOR . $relPath);
  }

  /**
   * @param string $relPath
   * @param string $content
   */
  public function writeFile($relPath, $content) {
    $this->fs->dumpFile($this->path . DIRECTORY_SEPARATOR . $relPath, $content);
  }

  /**
   * @param string $relPath
   * @param string $content
   * @param string|null $commitMessage
   */
  public function commitFile($relPath, $content, $commitMessage = NULL) {
    if ($commitMessage === NULL) {
      $commitMessage = "Update $relPath";
    }
    $this->writeFile($relPath, $content);
    ProcessUtils::runOk($this->command("git add " . escapeshellarg($relPath)));
    ProcessUtils::runOk($this->command("git commit " . escapeshellarg($relPath) . ' -m ' . escapeshellarg($commitMessage)));
  }

  /* --------------- Boiler plate --------------- */

  /**
   * @param string $path
   */
  public function setPath($path) {
    $this->path = $path;
  }

  /**
   * @return string
   */
  public function getPath() {
    return $this->path;
  }

}