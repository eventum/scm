#!/usr/bin/env php
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

require_once __DIR__ . '/helpers.php';

$original_argv = $argv;
$default_options = array(
    'n' => 'svn',
);
$options = _getopt('n:') + $default_options;

$PROGRAM = basename(realpath(array_shift($argv)), '.php');
$eventum_url = array_shift($argv);
$scm_name = $options['n'];

try {
    main($scm_name, $argv);
} catch (Exception $e) {
    error_log("ERROR[$PROGRAM]: " . $e->getMessage());
    error_log('Debug saved to: ' . save_environment($original_argv));
    exit(1);
}
exit(0);

function main($scm_name, $argv)
{
    if (count($argv) !== 2) {
        throw new InvalidArgumentException('Invalid arguments');
    }

    list($repos, $rev) = $argv;

    global $svnlook;
    if (!isset($svnlook)) {
        $svnlook = '/usr/bin/svnlook';
    }

    if (!is_executable($svnlook)) {
        throw new BadFunctionCallException('svnlook is not executable, edit $svnlook');
    }

    $results = svnlook('info', $repos, $rev);
    list($username, $date, $commit_msg) = svn_commit_info($results);

    // parse the commit message and get all issue numbers we can find
    $issues = match_issues($commit_msg);
    if (!$issues) {
        return;
    }

    $files = svn_commit_files($repos, $rev);

    $params = array(
        'scm' => 'svn',
        'scm_name' => $scm_name,
        'username' => $username,
        'commit_msg' => $commit_msg,
        'issue' => $issues,
        'files' => $files,
        'commitid' => $rev,
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
function svn_commit_files($repo, $rev)
{
    // create array with predefined keys
    $files = array(
        'added',
        'removed',
        'modified',
    );
    $files = array_fill_keys($files, array());

    $changes = svnlook('changed', $repo, $rev);
    foreach ($changes as $change) {
        // http://svnbook.red-bean.com/en/1.7/svn.ref.svnlook.c.changed.html
        // flags:
        // - 'A ' Item added to repository
        // - 'D ' Item deleted from repository
        // - 'U ' File contents changed
        // - '_U' Properties of item changed; note the leading underscore
        // - 'UU' File contents and properties changed
        list($change_info, $filename) = preg_split('/\s+/', $change, 2);
        $flags = preg_split('//', $change_info, -1, PREG_SPLIT_NO_EMPTY);

        if (in_array('A', $flags, true)) {
            $change_type = 'added';
        } elseif (in_array('D', $flags, true)) {
            $change_type = 'removed';
        } else {
            $change_type = 'modified';
        }

        $files[$change_type][] = $filename;
    }

    return $files;
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
