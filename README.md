# Eventum SCM hook scripts

## CVS

Setup in your CVS server:

###  CVS 1.11:

 * `CVSROOT/loginfo`:

```
# process any message with eventum
ALL /path/to/eventum-cvs-hook.php -n cvs http://eventum.example.org/ $USER %{sVv}
```

###  CVS 1.12:

 * `CVSROOT/loginfo`:
```
# process any message with eventum
ALL /path/to/eventum-cvs-hook.php -n cvs http://eventum.example.org/ $USER "%p" %{sVv}
```
 * `CVSROOT/config`:
```
UseNewInfoFmtStrings=yes
```

## SVN

 * Setup in your svn server `hooks/post-commit`:

```sh
#!/bin/sh
REPO="$1"
REV="$2"
/path/to/eventum-svn-hook.php -n svn http://eventum.example.org/ "$REPO" "$REV"
```

## Git

 * Setup in your git repo `hooks/post-receive`:

```sh
#!/bin/sh
/path/to/eventum-git-hook.php -n git http://eventum.example.org/
```
## GitLab

GitLab is supported by Eventum itself, without scripts from this project.

Configure project in GitLab webhook to post to:
 - `https://eventum.example.org/scm_ping.php`

Recommended events are:
 - `push_events`: true
 - `tag_push_events`: true
 - `note_events`: true
 - `issues_events`: true
 - `merge_requests_events`: true

## Re-submit failed payload

In case Eventum server down, hooks create dump of post context which can be re-submitted to Eventum server.

```sh
/path/to/eventum-cvs-hook.php -n cvs http://eventum.example.org/ -l /tmp/eventum-cvs-hookuKrXLh
```

This is supported by CVS hook.
