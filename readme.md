# WP Triage

## quick error debugging

### Brought to you by WP Medic
Love this tool? [Become a WP Medic Patron](https://patreon.com/wpmedic)

[WP Paramedic Home](https://wpmedic.tech/wp-triage/) - [GitHub](https://github.com/majick777/wp-triage/)

***

### Introduction

WP Triage enables a WordPress Debug session via Querystring, with optional display and logging.

This focusses the debug information for a single user test session, displaying it to the user and/or logging it as relevant (also means log is kept to minimum great on live sites.) Session
is kept active via cookie value detection (eg. so it is persistent between POST requests etc.) Can output all errors to browser console or stores for output at all at once on PHP shutdown.
Works with all errors including fatal errors and user errors if triggered.


### Installation

1. Simply place wp-triage.php in the same directory as your wp-config.php
(reminder: check your user/group permissions are matching the rest of your files.)

2. Instead of using `define('WP_DEBUG', true/false);` in wp-config.php, replace it with:

```
if (file_exists(dirname(__FILE__).'/wp-triage.php')) {include dirname(__FILE__).'/wp-triage.php';}
```
[Alternative] Copy `wp-triage.php` to `000-wp-triage.php` in your `/wp-content/mu-plugins/` folder
(the drawback being you will miss any debug errors before must-use plugins are loaded.)

3. WP Triage will autoload and you can use the querystring values to access debugging.

eg, `http:example.com/?wpdebug=5`
to enable debug logging for the next 5 minutes
eg. `http:example.com/?wpdebug=5&log=1&display=console`
for a 5 minute debug session with error logging and error display to console instead of footer.
(see Usage Notes for all querystring options.)

4. [Optional] Change your querystring keys below to something more obscure to restrict access.

5. [Reminder] Set ?wpdebug=0 to end a debug session before session timeout (destroys cookie.)


### Usage Notes

#### Querystring Switch Values

Note: debugging states can also be forced by defining the constants noted.

`wpdebug` (or `wptriage`) - length of WP_DEBUG session (in minutes), 0 to turn off.

`log` - logging mode (`WP_DEBUG_LOG`):

0 or 'off' = turn debug logging off
1 or 'on' = turn debug logging on (/wp-content/debug.log)
2 or 'separate' = use /wp-content/debug-session.log file
(works if WP_DEBUG is false! ...but fails to write on many servers :-( )
3 or 'logger' = use log4php logging class handler (not implemented yet)

`display` - display mode (`WP_DEBUG_DISPLAY`):

0 or 'off' = turn debug display off
1 or 'on' = turn direct debug display output on
2 or 'shutdown' = output on shutdown (default) [WP_TRIAGE_ON_SHUTDOWN]
3 or 'console' = output to browser console [WP_TRIAGE_TO_CONSOLE]

`backtrace` - full debug backtrace: 0 or 'off', 1 or 'on' [`WP_TRIAGE_BACKTRACE`]

(backtrace is only output in display on shutdown mode)
can also trace particular error types: 'error', 'strict', 'notice' etc.

`html` - HTML for display output: 0 or 'off', 1 or 'on' [`WP_TRIAGE_TEXT_ONLY`]
(only valid if debugdisplay is 'on' or 'shutdown')

`ip` - whether to log IP address: 0 or 'off', 1 or 'on' [`WP_TRIAGE_IP`]

`instance` - set a specific ID for this debug session session [`WP_TRIAGE_ID`]

(alphanumeric only, displayed and/or recorded in log)

`clear` - remove all lines from debug log for a specific debug ID session

