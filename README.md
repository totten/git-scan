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
```
