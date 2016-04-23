<?php
namespace GitScan;

class GitBranchGenerator {

  private $gitRepos;

  /**
   * GitBranchGenerator constructor.
   * @param array $gitRepos
   */
  public function __construct($gitRepos) {
    $this->gitRepos = $gitRepos;
  }

  /**
   * Walk through list of repos and branches. Based on the matching
   * branches, generating a new branch or tag.
   *
   * @param string $headName
   * @param string $tagName
   * @param bool $prefix
   * @param callable $callback
   */
  public function generate($headName, $tagName, $prefix, $callback) {
    $headQuoted = preg_quote($headName, '/');
    $headRegex = "/^(.+[-_])$headQuoted\$/";

    foreach ($this->gitRepos as $gitRepo) {
      /** @var GitRepo $gitRepo */
      $branches = $gitRepo->getBranches();

      if (in_array($headName, $branches)) {
        call_user_func($callback, $gitRepo, $headName, $tagName);
      }

      if ($prefix) {
        foreach ($branches as $branch) {
          if (preg_match($headRegex, $branch, $matches)) {
            $tag = $matches[1] . $tagName;
            call_user_func($callback, $gitRepo, $branch, $tag);
          }
        }
      }
    }
  }

}