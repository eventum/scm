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

$arguments = $argv;
$default_options = array(
    'n' => 'cvs',
);
$options = _getopt('l:n:') + $default_options;
$PROGRAM = basename(realpath(array_shift($arguments)), '.php');

if (isset($options['l'])) {
    $context = load_cvs_context($options['l']);
} else {
    $context = create_context($argv, $arguments);
}

$eventum_url = array_shift($context['arguments']);
$context['eventum_url'] = $eventum_url;
$context['scm_name'] = $options['n'];

try {
    main($context);
} catch (Exception $e) {
    error_log("ERROR[$PROGRAM]: " . $e->getMessage());
    error_log('Debug saved to: ' . store_environment($context));
    exit(1);
}
exit(0);

function main($context)
{
    $commit_msg = cvs_commit_msg($context['stdin']);

    // parse the commit message and get all issue numbers we can find
    $issues = match_issues($commit_msg);
    if (!$issues) {
        return;
    }

    list($username, $modified_files) = cvs_commit_info($context['arguments']);
    list($commitid, $files) = cvs_get_files($modified_files);

    $params = array(
        'scm' => 'cvs',
        'commit_date' => $context['time'],
        'scm_name' => $context['scm_name'],
        'username' => $username,
        'commit_msg' => $commit_msg,
        'issue' => $issues,
        'files' => $files,
        'commitid' => $commitid,
    );

    scm_ping($params);
}

/**
 * create files payload
 *
 * @param array $modified_files
 * @return array
 */
function cvs_get_files($modified_files)
{
    // create array with predefined keys
    $files = array(
        'added',
        'removed',
        'modified',
    );
    $files = array_fill_keys($files, array());

    $commitid = array();
    foreach ($modified_files as $file) {
        $filename = $file['filename'];

        if (!$file['old_revision']) {
            $change_type = 'added';
        } elseif (!$file['new_revision']) {
            $change_type = 'removed';
        } else {
            $change_type = 'modified';
        }

        $files['versions'][$filename] = array($file['old_revision'], $file['new_revision']);
        $files[$change_type][] = $filename;
        $commitid[] = $file['commitid'];
    }

    // flatten commitid
    $commitid = array_unique($commitid);
    if (count($commitid) > 1) {
        throw new InvalidArgumentException('Commit Id should be unique');
    }
    $commitid = current($commitid);

    return array($commitid, $files);
}

/**
 * @param array $argv
 * @return array of username, modified files
 */
function cvs_commit_info($argv)
{
    // user who is committing these changes
    $username = array_shift($argv);

    if (count($argv) === 1) {
        $modified_files = cvs_parse_info_1_11($argv);
    } else {
        $modified_files = cvs_parse_info_1_12($argv);
    }

    // parse told to skip (for example adding new dir)
    if (!$modified_files) {
        exit(0);
    }

    return array($username, $modified_files);
}

/**
 * assume the old way ("PATH {FILE,rev1,rev2 }+")
 * CVSROOT/loginfo: ALL eventum-cvs-hook $USER %{sVv}
 *
 * @return array
 */
function cvs_parse_info_1_11($argv)
{
    $args = explode(' ', array_shift($argv));

    // save what the name of the module is
    $cvs_module = array_shift($args);

    // skip if we're importing or adding new dirrectory
    $msg = implode(' ', array_slice($args, -3));
    if (in_array($msg, array('- Imported sources', '- New directory'))) {
        return null;
    }

    // now parse the list of modified files
    $modified_files = array();
    foreach ($args as $file_info) {
        list($filename, $old_revision, $new_revision) = explode(',', $file_info);
        $modified_files[] = array(
            'filename' => "$cvs_module/$filename",
            'old_revision' => cvs_filter_none($old_revision),
            'new_revision' => cvs_filter_none($new_revision),
        );
    }

    return $modified_files;
}

/**
 * assume the new way ("PATH" {"FILE" "rev1" "rev2"}+)
 * CVSROOT/loginfo: ALL eventum-cvs-hook $USER "%p" %{sVv}
 *
 * @return array
 */
function cvs_parse_info_1_12($args)
{
    // save what the name of the module is
    $cvs_module = array_shift($args);

    // skip if we're importing or adding new directory
    // TODO: checked old way with CVS 1.11, but not checked the new way
    $msg = implode(' ', array_slice($args, -3));
    if (in_array($msg, array('- Imported sources', '- New directory'))) {
        return null;
    }

    // now parse the list of modified files
    $modified_files = array();
    $use_rcs = !file_exists('CVS/Entries');
    while ($file_info = array_splice($args, 0, 3)) {
        list($filename, $old_revision, $new_revision) = $file_info;
        $old_revision = cvs_filter_none($old_revision);
        $new_revision = cvs_filter_none($new_revision);
        $modified_files[] = array(
            'filename' => "$cvs_module/$filename",
            'old_revision' => $old_revision,
            'new_revision' => $new_revision,
            'commitid' => $use_rcs ? rcs_commitid($filename, $new_revision) : cvs_commitid($filename),
        );
    }

    return $modified_files;
}

/**
 * @param string $pattern
 * @param array $input
 * @return string|null
 * @internal
 */
function cvs_match_commitid($pattern, array $input)
{
    // find line matching 'Commit Identifier'
    $lines = preg_grep($pattern, $input);
    if (!$lines) {
        return null;
    }

    // match commit id
    if (!preg_match($pattern, current($lines), $m)) {
        return null;
    }

    return $m['commitid'];
}

/**
 * Extract 'commitid' from file using rcs
 *
 * @param string $filename
 * @param string $revision
 * @return string
 */
function rcs_commitid($filename, $revision)
{
    $result = execx('rcs log -r' . escapeshellarg($revision) . ' ' . escapeshellarg($filename));

    // date: 2019/08/13 09:04:57;  author: glen;  state: Exp;  lines: +2 -3; commitid: uoHAXoFNyk8CxQyB
    $pattern = '/^date:.+commitid:\s+(?P<commitid>\S+)/sm';

    return cvs_match_commitid($pattern, $result);
}

/**
 * Extract 'commitid' from file, Requires CVS 1.12+
 *
 * @param string $filename
 * @return string
 */
function cvs_commitid($filename)
{
    $result = execx('cvs -Qn status ' . escapeshellarg($filename));

    $pattern = '/Commit Identifier:\s+(?P<commitid>\S+)/';

    return cvs_match_commitid($pattern, $result);
}

/**
 * filter out NONE revision
 *
 * @param string $rev
 */
function cvs_filter_none($rev)
{
    if ($rev !== 'NONE') {
        return $rev;
    }

    return null;
}

/**
 * Obtain CVS commit message
 *
 * @return string
 */
function cvs_commit_msg($input)
{
    // get the full commit message
    $commit_msg = rtrim(substr($input, strpos($input, 'Log Message:') + strlen('Log Message:') + 1));

    return $commit_msg;
}

/**
 * @param string $dump_file
 * @return array
 */
function load_cvs_context($dump_file)
{
    $context = load_context($dump_file);

    // extract real cwd to use
    $root = preg_quote($context['env']['CVSROOT'], '/');
    if (preg_match("/^Update of (?P<cwd>$root.+)$/m", $context['stdin'], $m)) {
        $context['original_cwd'] = $context['cwd'];
        $context['cwd'] = $m['cwd'];
    }

    if (chdir($context['cwd']) === false) {
        throw new RuntimeException("Unable to change directory to: {$context['cwd']}");
    }

    return $context;
}
