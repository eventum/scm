# Eventum SCM hook scripts

## [3.2.1] - 2020-10-20

- Fix Undefined index: program notice, #9

[3.2.1]: https://github.com/eventum/scm/compare/v3.2.0...v3.2.1

## [3.2.0] - 2019-08-20

- CVS: Add support for loading saved context, #7

[3.2.0]: https://github.com/eventum/scm/compare/v3.1.4...v3.2.0

## [3.1.4] - 2019-08-13

- include time in `save_environment`, #4

[3.1.4]: https://github.com/eventum/scm/compare/v3.1.3...v3.1.4

## [3.1.3] - 2017-10-24

- use --no-renames for git status extract, #3

[3.1.3]: https://github.com/eventum/scm/compare/v3.1.2...v3.1.3

## [3.1.2] - 2016-09-06

- git hook does not match multiline messages, #2

[3.1.2]: https://github.com/eventum/scm/compare/v3.1.1...v3.1.2

## [3.1.1] - 2016-09-06

- save execution environment in case of errors
- use 5s timeout for making requests
- automate .phar building using travis

[3.1.1]: https://github.com/eventum/scm/compare/v3.1.0...v3.1.1

## [3.1.0] - 2016-04-28

This version of hooks require Eventum 3.1.0.
From this version onwards eventum-scm is not released as part of main Eventum release.

- cvs/svn/git hooks rewritten to handle new payload for Eventum 3.1.0
- use JSON payload to post data to Eventum, [134fd35]
- lose config, use command line arguments to configure hooks
- use GL_REPO as git project name

[3.1.0]: https://github.com/eventum/scm/compare/v3.0.12...v3.1.0
[134fd35]: https://github.com/eventum/scm/commit/134fd35

## [3.0.12] - 2016-04-19

- cvs/svn/git: include commitid information on commits

[3.0.12]: https://github.com/eventum/scm/compare/v3.0.4...v3.0.12

## [3.0.4] - 2015-10-31

- no functional changes

[3.0.4]: https://github.com/eventum/scm/compare/v3.0.3...v3.0.4

## [3.0.3] - 2015-10-13

- git: default to `GL_REPO`, [6a5d58c]

[3.0.3]: https://github.com/eventum/scm/compare/v3.0.2...v3.0.3
[6a5d58c]: https://github.com/eventum/scm/commit/6a5d58c

## [3.0.2] - 2015-08-04

- fix cvs hook handling in old (1.11) cvs server, [2a83268]
- add makefile and box configuration to build standalone .phar [7927586]

[3.0.2]: https://github.com/eventum/scm/compare/v3.0.0-pre1...v3.0.2
[2a83268]: https://github.com/eventum/scm/commit/2a83268
[7927586]: https://github.com/eventum/scm/commit/7927586

## [3.0.0-pre1] - 2014-11-30

- moved out of Eventum sourcetree as separate git repo
- added git hook script [0e6c1ea], [LP#1078454]
- use json format to read scm ping responses
- restructure code to be more modular, move common code to helpers
- eventum now supports multiple scm repositories [d9f120b]

[3.0.0-pre1]: https://github.com/eventum/scm/compare/v2.4.0-pre1...v3.0.0-pre1
[LP#1078454]: https://bugs.launchpad.net/eventum/+bug/1078454
[0e6c1ea]: https://github.com/eventum/scm/commit/0e6c1ea
[d9f120b]: https://github.com/eventum/scm/commit/d9f120b

## [2.4.0-pre1] - 2014-10-04

- no functional changes

[2.4.0-pre1]: https://github.com/eventum/scm/compare/v2.3.6...v2.4.0-pre1

## [2.3.6] - 2014-01-28

- no changes

[2.3.6]: https://github.com/eventum/scm/compare/v2.3.5...v2.3.6

## [2.3.5] - 2014-01-24

- no changes

[2.3.5]: https://github.com/eventum/scm/compare/v2.3.4...v2.3.5

## [2.3.4] - 2013-11-28

- cvs: handle warnings better when `cvs add directory` is performed [44c9d4a]
- load local config for CVS/SVN integration from script dir [371973b]

[2.3.4]: https://github.com/eventum/scm/compare/v2.3.3...v2.3.4
[371973b]: https://github.com/eventum/scm/commit/371973b
[44c9d4a]: https://github.com/eventum/scm/commit/44c9d4a

## [2.3.3] - 2012-05-30

- cvs: support CVS 1.11 and 1.12 formats (autodetected if configured correctly) [62b7f9c]

[2.3.3]: https://github.com/eventum/scm/compare/v2.3.2...v2.3.3
[62b7f9c]: https://github.com/eventum/scm/commit/62b7f9c

## [2.3.2] - 2011-12-03

- use PHP's native `stream_get_contents()` instead of getInput function to read data from STDIN [2589912]

[2.3.2]: https://github.com/eventum/scm/compare/v2.3.1...v2.3.2
[2589912]: https://github.com/eventum/scm/commit/2589912

## [2.3.1] - 2011-02-10

- no changes

[2.3.1]: https://github.com/eventum/scm/compare/v2.3...v2.3.1

## [2.3] - 2010-07-28

- cvs: handle importing sources and adding new directories nicely [930e438]
- cvs: commit hook prints remote response [a83f486]

[2.3]: https://github.com/eventum/scm/compare/v2.2...v2.3
[930e438]: https://github.com/eventum/scm/commit/930e438
[a83f486]: https://github.com/eventum/scm/commit/a83f486

## [2.2] - 2009-01-14

- no functional changes

[2.2]: https://github.com/eventum/scm/compare/v2.1.1...v2.2

## [2.1.1] - 2008-01-09

- cvs: handle errors better from HTTP response [8e10cfe]

[2.1.1]: https://github.com/eventum/scm/compare/v2.1...v2.1.1
[8e10cfe]: https://github.com/eventum/scm/commit/8e10cfe

## [2.1] - 2007-11-20

- no functional changes

[2.1]: https://github.com/eventum/scm/compare/v2.0.1...v2.1

## [2.0.1] - 2007-04-17

- no functional changes

[2.0.1]: https://github.com/eventum/scm/compare/v2.0.0...v2.0.1

## [2.0.0] - 2007-04-12

- fix issue id detection in scm commit message [a567490]
- new config format, use `$eventum_url` variable, allows `https://` urls [b6bf3b1]
- drop use legacy `$HTTP_SERVER_VARS`
- initial svn hook script [698176a]
- allow SCM commit messages contain multiple issue IDs [e89814b]
- properly encode post data containing `+` (use `rawurlencode` instead of `base64_encode`) [ac8b3ee]
- report errors from from Eventum server [60304fb]

[2.0.0]: https://github.com/eventum/scm/compare/v1.7.1...v2.0.0
[60304fb]: https://github.com/eventum/scm/commit/60304fb
[698176a]: https://github.com/eventum/scm/commit/698176a
[a567490]: https://github.com/eventum/scm/commit/a567490
[ac8b3ee]: https://github.com/eventum/scm/commit/ac8b3ee
[b6bf3b1]: https://github.com/eventum/scm/commit/b6bf3b1
[e89814b]: https://github.com/eventum/scm/commit/e89814b
