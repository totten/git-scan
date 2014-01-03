<?php
namespace Boring\Util;
class Process {

  /**
   * Helper which synchronously runs a command and verifies that it doesn't generate an error.
   *
   * @param \Symfony\Component\Process\Process $process
   * @return \Symfony\Component\Process\Process
   * @throws \RuntimeException
   */
  public static function runOk(\Symfony\Component\Process\Process $process) {
    $process->run();
    if (!$process->isSuccessful()) {
      $report = "Process failed:
[[ COMMAND: {$process->getCommandLine()} ]]
[[ CWD: {$process->getWorkingDirectory()} ]]
[[ EXIT CODE: {$process->getExitCode()} ]]
[[ STDOUT ]]
{$process->getOutput()}
[[ STDERR ]]
{$process->getErrorOutput()}
      ";
      throw new \RuntimeException($report);
    }
    return $process;
  }
}