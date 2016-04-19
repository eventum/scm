#!/usr/bin/php
<?php

/*
 * This file is part of the Eventum (Issue Tracking System) package.
 *
 * @copyright (c) Eventum Team
 * @license GNU General Public License, version 2 or later (GPL-2+)
 *
 * For the full copyright and license information,
 * please see the COPYING and AUTHORS files
 * that were distributed with this source code.
 */

// URL to your Eventum installation.
// https is supported transparently by PHP 5 if you have openssl module enabled.
$eventum_url = 'http://eventum.example.com/';
// SCM repository name. Needed if multiple repositories configured

// default to $GL_REPO
$scm_name = getenv('GL_REPO') ?: 'git';

//
// DO NOT CHANGE ANYTHING AFTER THIS LINE
//

// save name of this script
$PROGRAM = basename(realpath(array_shift($argv)), '.php');

$dir = __DIR__;
require_once "$dir/helpers.php";

// load eventum-git-hook.conf.php from dir of this script if it exists
$configfile = "$dir/$PROGRAM.conf.php";
if (file_exists($configfile)) {
    require_once $configfile;
}

$reflist = git_receive_refs();
process_push($reflist);
exit(0);

function process_push($reflist)
{
    $nullsha1 = '0000000000000000000000000000000000000000';
    $emptysha1 = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';

    // process each branch push
    foreach ($reflist as $refs) {
        list($old, $new, $refname) = $refs;
        if ($new == $nullsha1) {
            // remote branch is deleted. nothing to do
            continue;
        }

        if ($old == $nullsha1) {
            // remote branch is created. use emptysha1 instead
            $old = $emptysha1;
        }

        $revlist = git_rev_list($old, $new, '--no-merges --author-date-order --reverse');
        foreach ($revlist as $rev) {
            git_scm_ping($rev, $refname);
        }
    }
}

/**
 * Submit Git data to Eventum
 *
 * @param string $rev
 */
function git_scm_ping($rev, $refname)
{
    $commit_msg = git_commit_msg($rev);
    $issues = match_issues($commit_msg);
    if (!$issues) {
        return;
    }

    global $PROGRAM, $scm_name;
    $author_email = git_commit_author_email($rev);
    $author_name = git_commit_author_name($rev);
    $commit_date = git_commit_author_date($rev);
    $files = git_commit_files($rev);
    $branch = git_branch_name($refname);

    $params = array(
        'scm' => 'git',
        'scm_name' => $scm_name,
        'author_email' => $author_email,
        'author_name' => $author_name,
        'commit_date' => $commit_date,
        'branch' => $branch,
        'commit_msg' => $commit_msg,
        'issue' => $issues,
        'files' => $files,
        'commitid' => $rev,
    );

    try {
        scm_ping($params);
    } catch (Exception $e) {
        error_log("ERROR[$PROGRAM]: " . $e->getMessage());
        exit(1);
    }
}

/*
 * A post-receive hook gets its arguments from STDIN,
 * in the form <oldrev> <newrev> <refname>.
 * Since these arguments are coming from stdin, not from a command line argument,
 * you need to use read instead of $1 $2 $3.
 *
 * The post-receive hook can receive multiple branches at once (for example if someone does a git push --all),
 * so we also need to wrap the read in a while loop.
 */
function git_receive_refs()
{
    $result = array();
    $input = stream_get_contents(STDIN);
    foreach (explode(PHP_EOL, rtrim($input, PHP_EOL)) as $line) {
        $result[] = explode(' ', $line);
    }

    return $result;
}

/**
 * Get files affected from $rev
 *
 * @param string $rev
 * @return array
 */
function git_commit_files($rev)
{
    $file_info = execx("git show --pretty=format: --name-status $rev");

    // man git-show --diff-filter
    $map = array(
        'A' => 'added',
        'D' => 'removed',
        'M' => 'modified',
    );
    $files = array_fill_keys(array_values($map), array());
    foreach ($file_info as $line) {
        list($status, $filename) = explode("\t", $line, 2);

        if (isset($map[$status])) {
            $change_type = $map[$status];
        } else {
            error_log("Unknown type: $line");
            $change_type = 'unknown';
        }

        $files[$change_type][] = $filename;
    }

    return $files;
}

/**
 * @param string $old revision start
 * @param string $new revision end
 * @param string $options
 * @return array
 */
function git_rev_list($old, $new, $options = '')
{
    return execx("git rev-list $old..$new $options");
}

/**
 * @param string $rev
 * @return string
 */
function git_commit_author_email($rev)
{
    return git_format($rev, '%ae');
}

/**
 * @param string $rev
 * @return string
 */
function git_commit_author_name($rev)
{
    return git_format($rev, '%an');
}

/**
 * @param string $rev
 * @return string
 */
function git_commit_author_date($rev)
{
    return git_format($rev, '%at');
}

/**
 * @param string $rev
 * @return string
 */
function git_commit_msg($rev)
{
    return git_format($rev, '%B');
}

/**
 * Pretty format $rev with $format. It should be one line out output
 *
 * @param string $rev
 * @return string
 */
function git_format($rev, $format)
{
    return execl("git log --format=$format -n1 $rev");
}

/**
 * Get short Git shorter unique SHA1 reference
 *
 * @param string $rev
 * @return string
 */
function git_short_rev($rev)
{
    return execl("git rev-parse --short $rev");
}

/**
 * get git branch name for the refname
 *
 * @param string $refname
 * @return string
 */
function git_branch_name($refname)
{
    return execl("git rev-parse --symbolic --abbrev-ref $refname");
}
