<?php
namespace GitScan\GitFormatter;

class PlainFormatter implements GitFormatterInterface {

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
  function formatRef($details) {
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
  function formatComparison($from, $to) {
    return sprintf('[RUN: git log %s...%s]', $this->toAbbrev($from), $this->toAbbrev($to));
  }

  /**
   * @param array $details
   * @return string
   */
  function toAbbrev($details) {
    return substr($details['commit'], 0, self::ABBREV_LENGTH);
  }
}