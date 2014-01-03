git-scan
========

Git-scan is designed for users who have many overlapping git repositories -- for example, developers who work with "composer" or "drush-make" may build out working directories which include half a dozen or more repos. Key features:

 * Zero configuration (unlike "mr", "repo", or "composer", it doesn't require any special config files)
 * Categorizes/filters repositories based on their status
 * Agnostic to the build system (eg works equally well with "composer", "drush-make", and manually-written install scripts)
 * Agnostic to the submission/review process (eg works with personal read/write repos as well as GitHub repos that require pull-requests)

Examples
========

```
me@localhost:~/drupal-demo$ git scan st -O
[[ Finding repositories ]]
[[ Checking statuses ]]
 5/5 [============================] 100%
[[ Results ]]

+--------+---------------------------------------------------------+--------------+---------------------+
| Status | Path                                                    | Local Branch | Remote Branch       |
+--------+---------------------------------------------------------+--------------+---------------------+
|     S  | drupal-demo/sites/all/modules/civicrm                   | master       | upstream/master     |
|        | drupal-demo/sites/all/modules/civicrm/drupal            | 7.x-master   | upstream/7.x-master |
|  MNP   | drupal-demo/sites/all/modules/civicrm/packages          | master       | upstream/master     |
|        | drupal-demo/sites/all/modules/contrib/civicrm_developer | master       | origin/master       |
+--------+---------------------------------------------------------+--------------+---------------------+
[M] Local repo has (m)odifications that have not been committed
[N] Local repo has (n)ew files that have not been committed
[P] Local branch cannot be fast-forwarded (strictly)
    Local commits have not been (p)ushed upstream (usually)
[S] Changes have been (s)tashed


me@localhost:~/drupal-demo$  git scan up 
[[ Finding repositories ]]
[[ Fast-forwarding ]]
Fast-forward drupal-demo/sites/all/modules/civicrm (master <= upstream/master)...
Fast-forward drupal-demo/sites/all/modules/civicrm/drupal (7.x-master <= upstream/7.x-master)...
Skip drupal-demo/sites/all/modules/civicrm/packages: Cannot be fast-forwarded
Fast-forward drupal-demo/sites/all/modules/contrib/civicrm_developer (master <= origin/master)...


me@localhost:~/drupal-demo$  git scan foreach -c 'echo "This is $(pwd)."; echo "The relative path is $path."; echo'
This is /home/me/drupal-demo/sites/all/modules/civicrm.
The relative path is sites/all/modules/civicrm/.

This is /home/me/drupal-demo/sites/all/modules/civicrm/drupal.
The relative path is sites/all/modules/civicrm/drupal/.

This is /home/me/drupal-demo/sites/all/modules/civicrm/packages.
The relative path is sites/all/modules/civicrm/packages/.

This is /home/me/drupal-demo/sites/all/modules/contrib/civicrm_developer.
The relative path is sites/all/modules/contrib/civicrm_developer/.


me@localhost:~/drupal-demo$  git scan foreach -c 'echo "This is $(pwd)."; echo "The relative path is $path."; echo'
This is /home/me/drupal-demo/sites/all/modules/civicrm.
The relative path is sites/all/modules/civicrm/.

This is /home/me/drupal-demo/sites/all/modules/civicrm/drupal.
The relative path is sites/all/modules/civicrm/drupal/.

This is /home/me/drupal-demo/sites/all/modules/civicrm/packages.
The relative path is sites/all/modules/civicrm/packages/.

This is /home/me/drupal-demo/sites/all/modules/contrib/civicrm_developer.
The relative path is sites/all/modules/contrib/civicrm_developer/.

```
