git-scan
========

Git-scan is designed for users who have many overlapping git repositories -- for example, developers who work with "composer" or "drush-make" may build out working directories which include half a dozen or more repos. Key features:

 * Zero configuration
 * Works with deeply nested repositories
 * Filters and displays repositories based on their status ("*git scan status*")
 * Performs safe (fast-forward) updates on repositories when valid ("*git scan update*")
 * Executes custom commands ("*git scan foreach -c '...'*")
 * Agnostic to the build system (eg works equally well with "composer", "drush-make", and manually-written build scripts)
 * Agnostic to the branch/submission/review process (eg works with personal read/write repos as well as GitHub repos that require pull-requests)

Limitations:

 * IO-intensive -- Performs filesystem scan and does not cache results

The concepts and use-case are described in more depth in the blog post, [Developer Tip: Managing Multiple Git Repos](https://civicrm.org/blogs/totten/developer-tip-managing-multiple-git-repositories)

Download
========

git-scan is distributed in PHAR format, which is a portable executable file (for PHP). It should run on most
Unix-like systems where PHP 5.3+ is installed.

Simply download [git-scan](https://download.civicrm.org/git-scan/git-scan.phar) to somewhere in your [PATH](https://en.wikipedia.org/wiki/PATH_%28variable%29), and make it executable, eg.

```bash
cd /usr/local/bin  # or wherever else you want it to go, such as ~/bin
sudo curl -LsS https://download.civicrm.org/git-scan/git-scan.phar -o git-scan
sudo chmod +x git-scan
```

Examples
========

```bash
me@localhost:~/drupal-demo$ git scan st
[[ Finding repositories ]]
[[ Checking statuses ]]
 5/5 [============================] 100%
[[ Results ]]

+--------+---------------------------------------------------------+--------------+---------------------+
| Status | Path                                                    | Local Branch | Remote Branch       |
+--------+---------------------------------------------------------+--------------+---------------------+
|     S  | drupal-demo/sites/all/modules/civicrm                   | master       | upstream/master     |
|        | drupal-demo/sites/all/modules/civicrm/drupal            | 7.x-master   | upstream/7.x-master |
|  FMN   | drupal-demo/sites/all/modules/civicrm/packages          | master       | upstream/master     |
|        | drupal-demo/sites/all/modules/contrib/civicrm_developer | master       | origin/master       |
+--------+---------------------------------------------------------+--------------+---------------------+
[F] Fast-forwards are not possible
[M] Modifications have not been committed
[N] New files have not been committed
[S] Stash contains data


me@localhost:~/drupal-demo$ git scan up 
[[ Finding repositories ]]
[[ Fast-forwarding ]]
Fast-forward drupal-demo/sites/all/modules/civicrm (master <= upstream/master)...
Fast-forward drupal-demo/sites/all/modules/civicrm/drupal (7.x-master <= upstream/7.x-master)...
Skip drupal-demo/sites/all/modules/civicrm/packages: Cannot be fast-forwarded
Fast-forward drupal-demo/sites/all/modules/contrib/civicrm_developer (master <= origin/master)...


me@localhost:~/drupal-demo$ git scan foreach -c 'echo "This is $(pwd)."; echo "The relative path is $path."; echo'
This is /home/me/drupal-demo/sites/all/modules/civicrm.
The relative path is sites/all/modules/civicrm/.

This is /home/me/drupal-demo/sites/all/modules/civicrm/drupal.
The relative path is sites/all/modules/civicrm/drupal/.

This is /home/me/drupal-demo/sites/all/modules/civicrm/packages.
The relative path is sites/all/modules/civicrm/packages/.

This is /home/me/drupal-demo/sites/all/modules/contrib/civicrm_developer.
The relative path is sites/all/modules/contrib/civicrm_developer/.


me@localhost:~/drupal-demo$ git scan foreach -c 'git pull --rebase' -v
[[ Finding repositories ]]
[[ /home/me/drupal-demo/sites/all/modules/civicrm ]]
STDOUT Current branch master is up to date.
[[ /home/me/drupal-demo/sites/all/modules/civicrm/drupal ]]
STDOUT Current branch 7.x-master is up to date.
[[ /home/me/drupal-demo/sites/all/modules/civicrm/packages ]]
STDERR Cannot pull with rebase: You have unstaged changes.
STDERR Please commit or stash them.                       
[[ /home/me/drupal-demo/sites/all/modules/civicrm/packages: exit code = 1 ]]
[[ /home/me/drupal-demo/sites/all/modules/contrib/civicrm_developer ]]
STDOUT Current branch master is up to date.

me@localhost:~/drupal-demo$ git scan am https://github.com/example/mymodule/pull/1234
In "sites/all/modules/mymodule/", the current branch is "master" based on "origin/master". What would you like to do it?
  [keep   ] Keep the current branch "master" along with any local changes. Apply patches on top.
  [rebuild] Rebuild the branch "master" based on "origin/master". Destroy any local changes. Apply changes on top.
  [new    ] Create a new branch "merge-master-20160411152732" based on "origin/master". Apply changes on top.
  [abort  ] Abort the auto-merge process. (default)
> new
```

Configuration
=============

You may optionally create a file, `~/.git-scan.json`, to customize the
behavior. Supported options:

 * `excludes`: An array of path names to skip when scanning (e.g. `.svn` or `.hg`).

Development: Unit-Tests
=======================

If you have previously installed [phpunit](http://phpunit.de/), then you can run the test suite. Something like:

```
$ composer create-project totten/git-scan
$ cd git-scan
$ phpunit
PHPUnit 8.5.15 by Sebastian Bergmann.

Configuration read from /home/me/src/git-scan/phpunit.xml.dist

.................................................

Time: 2 seconds, Memory: 6.50Mb

OK (49 tests, 121 assertions)
```

Development: Build (PHAR)
=========================

If you are developing new changes to `git-scan` and want to create a new
build of `git-scan.phar` from source, you must have
[`git`](https://git-scm.com), [`composer`](https://getcomposer.org/), and
[`box`](http://box-project.github.io/box2/) installed.  Then run commands
like:

```
$ git clone https://github.com/totten/git-scan
$ cd git-scan
$ composer install
$ which box
/usr/local/bin/box
$ php -dphar.readonly=0 /usr/local/bin/box build
```
