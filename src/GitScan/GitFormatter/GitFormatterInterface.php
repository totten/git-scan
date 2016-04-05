<?php
namespace GitScan\GitFormatter;

interface GitFormatterInterface {
  /**
   * @param array $details
   *  - path: string, local path
   *  - remotes: array (string $name => string $fetchUrl)
   *  - commit: string, the name of the checked-out HEAD commit
   *  - localBranch: string|NULL, the name of the checked-out branch (if applicable)
   *  - upstreamBranch: string|NULL, the name of he checked-out branch's upstream counterpart
   * @return string
   */
  public function formatRef($details);

  /**
   * @param array $from
   *  - path: string, local path
   *  - remotes: array (string $name => string $fetchUrl)
   *  - commit: string, the name of the checked-out HEAD commit
   *  - localBranch: string|NULL, the name of the checked-out branch (if applicable)
   *  - upstreamBranch: string|NULL, the name of he checked-out branch's upstream counterpart
   * @param array $to
   *  - path: string, local path
   *  - remotes: array (string $name => string $fetchUrl)
   *  - commit: string, the name of the checked-out HEAD commit
   *  - localBranch: string|NULL, the name of the checked-out branch (if applicable)
   *  - upstreamBranch: string|NULL, the name of he checked-out branch's upstream counterpart
   * @return string
   */
  public function formatComparison($from, $to);

}
