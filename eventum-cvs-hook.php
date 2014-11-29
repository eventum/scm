#!/usr/bin/php
<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 encoding=utf-8: */
// +----------------------------------------------------------------------+
// | Eventum - Issue Tracking System                                      |
// +----------------------------------------------------------------------+
// | Copyright (c) 2003 - 2008 MySQL AB                                   |
// | Copyright (c) 2008 - 2010 Sun Microsystem Inc.                       |
// | Copyright (c) 2011 - 2014 Eventum Team.                              |
// |                                                                      |
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License as published by |
// | the Free Software Foundation; either version 2 of the License, or    |
// | (at your option) any later version.                                  |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to:                           |
// |                                                                      |
// | Free Software Foundation, Inc.                                       |
// | 51 Franklin Street, Suite 330                                          |
// | Boston, MA 02110-1301, USA.                                          |
// +----------------------------------------------------------------------+
// | Authors: João Prado Maia <jpm@mysql.com>                             |
// |          Elan Ruusamäe <glen@delfi.ee>                               |
// +----------------------------------------------------------------------+

/**
 * Setup in your CVS server:
 *
 * for CVS 1.11:
 * CVSROOT/loginfo:
 * # process any message with eventum
 * ALL /path/to/eventum-cvs-hook.php $USER %{sVv}
 *
 * for CVS 1.12:
 * CVSROOT/loginfo:
 * # process any message with eventum
 * ALL /path/to/eventum-cvs-hook.php $USER "%p" %{sVv}
 *
 * CVSROOT/config:
 * UseNewInfoFmtStrings=yes
 */

// URL to your Eventum installation.
// https is supported transparently by PHP 5 if you have openssl module enabled.
$eventum_url = 'http://eventum.example.com/';
// SCM repository name. Needed if multiple repositories configured
$scm_name = 'cvs';

//
// DO NOT CHANGE ANYTHING AFTER THIS LINE
//

// save name of this script
$PROGRAM = basename(array_shift($argv));

$dir = dirname(__FILE__);
require_once "$dir/helpers.php";

// load eventum-cvs-hook.conf.php from dir of this script if it exists
$configfile = $dir . DIRECTORY_SEPARATOR . basename(__FILE__, '.php') . '.conf.php';
if (file_exists($configfile)) {
    require_once $configfile;
}

$commit_msg = cvs_commit_msg();

// parse the commit message and get all issue numbers we can find
$issues = match_issues($commit_msg);

if ($issues) {
    // grab fileinfo
    list($username, $cvs_module, $modified_files) = cvs_commit_info($argv);

    // need to encode all of the url arguments
    $commit_msg = rawurlencode($commit_msg);
    $cvs_module = rawurlencode($cvs_module);
    $username = rawurlencode($username);
    $scm_name = rawurlencode($scm_name);

    // build the GET url to use
    $ping_url = $eventum_url. "scm_ping.php?scm_name=$scm_name&module=$cvs_module&username=$username&commit_msg=$commit_msg";
    foreach ($issues as $issue_id) {
        $ping_url .= "&issue[]=$issue_id";
    }

    for ($i = 0; $i < count($modified_files); $i++) {
        $ping_url .= "&files[$i]=" . rawurlencode($modified_files[$i]['filename']);
        $ping_url .= "&old_versions[$i]=" . rawurlencode($modified_files[$i]['old_revision']);
        $ping_url .= "&new_versions[$i]=" . rawurlencode($modified_files[$i]['new_revision']);
    }

    $res = wget($ping_url, true);
    if (!$res) {
        error_log("Error: Couldn't read response from $ping_url");
        exit(1);
    }

    list($headers, $data) = $res;
    // status line is first header in response
    $status = array_shift($headers);
    list($proto, $status, $msg) = explode(' ', trim($status), 3);
    if ($status != '200') {
        error_log("Error: Could not ping the Eventum SCM handler script: HTTP status code: $status $msg");
        exit(1);
    }

    // prefix response with our name
    foreach (explode("\n", trim($data)) as $line) {
        echo "$PROGRAM: $line\n";
    }
}

/**
 * @param array $argv
 * @return array of username, cvs module, modified files
 */
function cvs_commit_info($argv) {
    // user who is committing these changes
    $username = array_shift($argv);

    if (count($argv) == 3) {
        $info = cvs_parse_info_1_11($argv);
    } else {
        $info = cvs_parse_info_1_12($argv);
    }

    // parse told to skip (for example adding new dir)
    if (!$info) {
        exit(0);
    }

    list($cvs_module, $modified_files) = $info;

    return array($username, $cvs_module, $modified_files);
}

/**
 * assume the old way ("PATH {FILE,rev1,rev2 }+")
 * CVSROOT/loginfo: ALL eventum-cvs-hook $USER %{sVv}
 *
 * @return array
 */
function cvs_parse_info_1_11($argv) {
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
            'filename'     => $filename,
            'old_revision' => cvs_filter_none($old_revision),
            'new_revision' => cvs_filter_none($new_revision),
        );
    }

    return array($cvs_module, $modified_files);
}

/**
 * assume the new way ("PATH" {"FILE" "rev1" "rev2"}+)
 * CVSROOT/loginfo: ALL eventum-cvs-hook $USER "%p" %{sVv}
 *
 * @return array
 */
function cvs_parse_info_1_12($args) {
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
    while ($file_info = array_splice($args, 0, 3)) {
        list($filename, $old_revision, $new_revision) = $file_info;
        $modified_files[] = array(
            'filename'     => $filename,
            'old_revision' => cvs_filter_none($old_revision),
            'new_revision' => cvs_filter_none($new_revision),
        );
    }

    return array($cvs_module, $modified_files);
}

/**
 * filter out NONE revision
 *
 * @param string $rev
 * @return null
 */
function cvs_filter_none($rev)
{
    if ($rev != 'NONE') {
        return $rev;
    }
    return null;
}

/**
 * Obtain CVS commit message
 * @return string
 */
function cvs_commit_msg()
{
    // get the full commit message
    $input = stream_get_contents(STDIN);
    $commit_msg = rtrim(substr($input, strpos($input, 'Log Message:') + strlen('Log Message:') + 1));
    return $commit_msg;
}