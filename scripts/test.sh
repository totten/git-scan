#!/usr/bin/env bash

## Determine the absolute path of the directory with the file
## usage: absdirname <file-path>
function absdirname() {
  pushd $(dirname $0) >> /dev/null
    pwd
  popd >> /dev/null
}

SCRIPTDIR=$(absdirname "$0")
PRJDIR=$(dirname "$SCRIPTDIR")
set -ex

if php -r 'exit(version_compare(PHP_VERSION, "8.1", ">=") ? 0 : 1);' ; then
  PHPUNIT_VERSION=9.5.21
else
  PHPUNIT_VERSION=8.5.15
fi
PHPUNIT_URL="https://phar.phpunit.de/phpunit-{$PHPUNIT_VERSION}.phar"
PHPUNIT_DIR="$PRJDIR/extern/phpunit-$PHPUNIT_VERSION"
PHPUNIT_BIN="$PHPUNIT_DIR/phpunit"
[ ! -f "$PHPUNIT_BIN" ] && ( mkdir -p "$PHPUNIT_DIR" ; curl -L "$PHPUNIT_URL" -o "$PHPUNIT_BIN" )

pushd "$PRJDIR" >> /dev/null
  composer install --prefer-dist --no-progress --no-suggest --no-dev
  php "$PHPUNIT_BIN"
popd >> /dev/null
