<?php

#### Find primary autoloader
$autoloaders = array(
  implode(DIRECTORY_SEPARATOR, array(dirname(__DIR__), 'vendor', 'autoload.php')),
  implode(DIRECTORY_SEPARATOR, array(dirname(dirname(dirname(dirname(__DIR__)))), 'vendor', 'autoload.php')),
);
foreach ($autoloaders as $autoloader) {
  if (file_exists($autoloader)) {
    $loader = require $autoloader;
    break;
  }
}

if (!isset($loader)) {
  die("Failed to find autoloader");
}

#### Extra - Register classes in "tests" directory
$loader->add('GitScan', __DIR__);

$userEmail = trim(shell_exec('git config --get user.email') ?? '');
if (strpos($userEmail, '(none)') !== false || $userEmail === '') {
    shell_exec('git config --global user.email "testbot@example.com"');
    shell_exec('git config --global user.name "Test Bot"');
}