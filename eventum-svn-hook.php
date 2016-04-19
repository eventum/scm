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

/*
 * @see http://forge.mysql.com/wiki/Eventum:Subversion_integration about SVN integration.
 *
 * Setup in your svn server hooks/post-commit:
 *
 * #!/bin/sh
 * REPO="$1"
 * REV="$2"
 * /path/toeventum-svn-hook.php "$REPO" "$REV"
 */

// URL to your Eventum installation.
// https is supported transparently by PHP 5 if you have openssl module enabled.
$eventum_url = 'http://eventum.example.com/';
// SCM repository name. Needed if multiple repositories configured
$scm_name = 'svn';

//
// DO NOT CHANGE ANYTHING AFTER THIS LINE
//

// save name of this script
$PROGRAM = basename(realpath(array_shift($argv)), '.php');

$dir = __DIR__;
require_once "$dir/helpers.php";

// load eventum-svn-hook.conf.php from dir of this script if it exists
$configfile = "$dir/$PROGRAM.conf.php";
if (file_exists($configfile)) {
    require_once $configfile;
}

try {
    main($scm_name, $argv);
} catch (Exception $e) {
    error_log("ERROR[$PROGRAM]: " . $e->getMessage());
    exit(1);
}
exit(0);

function main($scm_name, $argv)
{
    if (count($argv) != 2) {
        $count = count($argv);
        throw new InvalidArgumentException("Invalid arguments, got $count, expected 2");
    }

    $repos = $argv[0];
    $new_revision = $argv[1];
    $old_revision = $new_revision - 1;

    global $svnlook;
    if (!isset($svnlook)) {
        $svnlook = '/usr/bin/svnlook';
    }

    if (!is_executable($svnlook)) {
        throw new BadFunctionCallException('svnlook is not executable, edit $svnlook');
    }

    $results = svnlook('info', $repos, $new_revision);
    list($username, $date, $commit_msg) = svn_commit_info($results);

    // parse the commit message and get all issue numbers we can find
    $issues = match_issues($commit_msg);
    if (!$issues) {
        return;
    }

    $files = array();
    $old_versions = array();
    $new_versions = array();

    $modified_files = svn_commit_files($repos, $old_revision, $new_revision);
    foreach ($modified_files as $i => &$file) {
        $files[$i] = $file['filename'];

        // add old revision if content was changed
        if (array_search('A', $file['flags']) === false) {
            $old_versions[$i] = $file['old_revision'];
        }
        // add new revision if it was not removed
        if (array_search('D', $file['flags']) === false) {
            $new_versions[$i] = $file['new_revision'];
        }
    }

    $params = array(
        'scm' => 'svn',
        'scm_name' => $scm_name,
        'username' => $username,
        'commit_msg' => $commit_msg,
        'issue' => $issues,
        'files' => $files,
        'commitid' => $new_revision,
        'old_versions' => $old_versions,
        'new_versions' => $new_versions,
    );

    scm_ping($params);
}

/**
 * Process username, date and commit message from svnlook output
 *
 * @param array $results
 * @return array
 */
function svn_commit_info($results)
{
    // get commit date and username and commit message
    $username = array_shift($results);
    $date = array_shift($results);

    // ignore commit message length value
    array_shift($results);

    // get the full commit message
    $commit_msg = implode("\n", $results);

    return array($username, $date, $commit_msg);
}

/**
 * Get files affected from $rev
 *
 * @param string $rev
 * @return array
 */
function svn_commit_files($repo, $old_revision, $new_revision)
{
    $modified_files = array();

    $files = svnlook('changed', $repo, $new_revision);
    foreach ($files as $file_info) {
        // http://svnbook.red-bean.com/en/1.7/svn.ref.svnlook.c.changed.html
        // flags:
        // - 'A ' Item added to repository
        // - 'D ' Item deleted from repository
        // - 'U ' File contents changed
        // - '_U' Properties of item changed; note the leading underscore
        // - 'UU' File contents and properties changed
        list($flags, $filename) = preg_split('/\s+/', $file_info, 2);
        $modified_files[] = array(
            'flags' => preg_split('//', $flags, -1, PREG_SPLIT_NO_EMPTY),
            'filename' => $filename,
            'old_revision' => $old_revision,
            'new_revision' => $new_revision
        );
    }

    return $modified_files;
}

/**
 * Execute svnlook command on $repo for $revision
 *
 * @param string $command
 * @param string $repo
 * @param int $revision
 * @return array
 */
function svnlook($command, $repo, $revision)
{
    global $svnlook;

    return execx("$svnlook $command $repo -r $revision");
}
