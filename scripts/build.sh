#!/usr/bin/env bash

## This script generates git-scan.phar.
## NOTE: as written, it will *not* work within nix-shell, but it will work on non-nix environments.

## Determine the absolute path of the directory with the file
## usage: absdirname <file-path>
function absdirname() {
  pushd $(dirname $0) >> /dev/null
    pwd
  popd >> /dev/null
}

SCRDIR=$(absdirname "$0")
PRJDIR=$(dirname "$SCRDIR")
export PATH="$PRJDIR/extern:$PATH"

set -ex
composer install --prefer-dist --no-progress --no-suggest --no-dev
which box
php -d phar.read_only=0 `which box` build -v
