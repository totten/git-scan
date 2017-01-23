<?php
namespace GitScan\GitFormatter;

class HtmlFormatter implements GitFormatterInterface {

  const ABBREV_LENGTH = 8;

  /**
   * @param array $details
   *  - path: string, local path
   *  - remotes: array (string $name => string $fetchUrl)
   *  - commit: string, the name of the checked-out HEAD commit
   *  - localBranch: string|NULL, the name of the checked-out branch (if applicable)
   *  - upstreamBranch: string|NULL, the name of he checked-out branch's upstream counterpart
   * @return string
   */
  public function formatRef($details) {
    // eg https://github.com/civicrm/civicrm-core/commit/{$commit}
    return sprintf("%s (%s)", $details['localBranch'] ? $details['localBranch'] : '', $this->toAbbrev($details));
  }

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
  public function formatComparison($from, $to) {
    // eg https://github.com/civicrm/civicrm-core/compare/{$commit}...{$commit}
    return sprintf('[RUN: git log %s...%s]', $this->toAbbrev($from), $this->toAbbrev($to));
  }

  /**
   * @param array $details
   * @return string
   */
  public function toAbbrev($details) {
    return substr($details['commit'], 0, self::ABBREV_LENGTH);
  }

}
