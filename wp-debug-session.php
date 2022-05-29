<?php

/* ================ */
/* WP Debug Session */
/* ---------------- */
/* . Version 1.05 . */
/* ================ */

// Home: https://gist.github.com/majick777/6c4e4074ce4a59fe09f7baa855732aee
// Author: DreamJester of http://wordquest.org/

// Description: Enables setting WordPress Debug, Debug Logging and Debug Display via Querystring.

// This focusses the debug information for a single user test session, displaying it to the user
// and/or logging it as relevant (also means log is kept to minimum great on live sites.) Session
// is kept active via cookie value detection (eg. so it is persistent between POST requests etc.)
// Can output all errors to browser console or stores for output at all at once on PHP shutdown.
// Works with all errors including fatal errors and user errors if triggered.

// Installation and Recommended Usage
// ----------------------------------
// 1. Simply place wp-debug-session.php in the same directory as your wp-config.php
//    (reminder: check your user/group permissions are matching the rest of your files.)
// 2. Instead of using `define('WP_DEBUG', true/false);` in wp-config.php, replace it with:
//    if (file_exists('wp-debug-session.php')) {include('wp-debug-session.php');}
// [Alternative] Copy file to 000-debug-session.php in your /wp-content/mu-plugins/ folder
//    (the drawback being you will miss any debug errors before must-use plugins are loaded.)
// 3. WP Debug Session will autoload and you can use the querystring values to access debugging.
//    eg. http://example.com/?wpdebug=5&debuglog=1&debugdisplay=3
//    for a 5 minute debug session with error logging and error display to console (see options)
// 4. [Optional] Change your querystring keys below to something more obscure to restrict access.
// 5. [Reminder] Set ?wpdebug=0 to end a debug session before session timeout (destroys cookie.)

// Querystring Switch Values
// -------------------------
// Note: debugging states can also be "forced" as static by defining constants noted.
// wpdebug - length of WP_DEBUG session (in minutes), 0 for off.
// debuglog - logging mode (WP_DEBUG_LOG):
// 				0 or 'off' = turn debug logging off
// 				1 or 'on' = turn debug logging on (/wp-content/debug.log)
//				2 or 'separate' = use /wp-content/debug-session.log file
//				(works if WP_DEBUG is false! ...but fails to write on many servers :-( )
// 				3 or 'logger' = use log4php logging class handler (not implemented yet)
// debugdisplay - display mode (WP_DEBUG_DISPLAY):
//				0 or 'off' = turn debug display off
//				1 or 'on' = turn direct debug display output on
//				2 or 'shutdown' = output on shutdown (default) [WP_DEBUG_ON_SHUTDOWN]
//				3 or 'console' = output to browser console [WP_DEBUG_TO_CONSOLE]
// debugbacktrace - full debug backtrace: 0 or 'off', 1 or 'on' [WP_DEBUG_BACKTRACE]
//				(backtrace is only output in display on shutdown mode)
// debughtml - HTML for display output: 0 or 'off', 1 or 'on' [WP_DEBUG_TEXT_ONLY]
//				(only valid if debugdisplay is 'on' or 'shutdown')
// debugip - whether to log IP address: 0 or 'off', 1 or 'on' [WP_DEBUG_IP]
// debugid - set a specific ID for this debug session session [WP_DEBUG_SESSION]
//				(alphanumeric only, displayed and/or recorded in log)
// debugclear - remove all lines from debug log for a specific debug ID session
//				(set value to debug session ID to clear from log)

// --- define querystring keys ---
$wp_debug_keys = array(
	'switch' => 'wpdebug', 'log' => 'debuglog', 'display' => 'debugdisplay', 'html' => 'debughtml',
	'backtrace' => 'debugbacktrace', 'ip' => 'debugip', 'id' => 'debugid'
);

// --- set default settings ---
$wp_debug = array(
	'switch' => '0', 'log' => '1', 'display' => '2', 'backtrace' => '0',
	'html' => '1', 'ip' => '0', 'id' => NULL, 'info' => ''
);

// --- maybe clear all cookies ---
// 1.0.4: do check to maybe clear all cookies first
if ( isset($_GET[$wp_debug_keys['switch']]) && in_array($_GET[$wp_debug_keys['switch']], array('0', 'off')) ) {
	foreach ($wp_debug_keys as $setting => $key) {
		$wp_debug[$setting] = 0; setcookie($key, '', time() - 3600);
	}
} else {

	// --- loop to get/change settings ---
	foreach ($wp_debug_keys as $setting => $key) {

		// --- check for existing cookies ---
		if (isset($_COOKIE[$key])) {
			if ( (is_numeric($_COOKIE[$key])) && ($_COOKIE[$key] > 0) ) {
				$wp_debug[$setting] = (int)$_COOKIE[$key];
			} elseif ($setting == 'id') {$wp_debug[$setting] = $_COOKIE[$key];}
		}

		// --- check for querystring changes ---
		if (isset($_GET[$key])) {

			// --- set shorthand value ---
			$value = $_GET[$key];

			// --- map any querystring word values to numerical ---
			if ($value == 'on') {$value = '1';}
			if ($key == $wp_debug_keys['log']) {
				if ($value == 'log') {$value = '1';}
				if ($value == 'separate') {$value = '2';}
				if ($value == 'log4php') {$value = '3';}
				if ($value == 'custom') {$value = '4';}
			}
			if ($key == $wp_debug_keys['display']) {
				if ($value == 'display') {$value = '1';}
				if ($value == 'console') {$value = '2';}
				if ($value == 'shutdown') {$value = '3';}
			}

			if ($key == $wp_debug_keys['html']) {
				if ($value == 'nohtml') {$value = '1';}
				if ($value == 'textonly') {$value = '1';}
			}

			// --- set key value and matching cookie ---
			if ( ($value == '0') || ($value == 'off') ) {

				// --- remove this cookie ---
				$wp_debug[$setting] = 0; setcookie($key, '', time() - 3600);

			} elseif ( (is_numeric($value)) && ($value > 0) ) {

				// --- use wpdebug setting as minutes for expiry ---
				$wp_debug[$setting] = (int)$value;
				if ($key == $wp_debug_keys['switch']) {
					$expiry = time() + ((int)$value * 60);
					// --- set extra cookie for calculating expiry display ---
					setcookie('debugexpiry', $expiry, $expiry);
				}
				if (isset($expiry)) {$expires = $expiry;} else {$expires = time() + 3600;}
				setcookie($key, (int)$value, $expires);

			} elseif ($setting == 'id') {

				// --- sanitize and set session ID cookie ---
				$wp_debug['id'] = preg_replace("/[^0-9a-zA-Z]/i", '', $value);
				if (isset($expiry)) {$expires = $expiry;} else {$expires = time() + 3600;}
				setcookie($key, $value, $expires);

			}
		}
	}
}

// --- maybe set WP_DEBUG debug mode ---
if ($wp_debug['switch'] > 0) {
	if (!defined('WP_DEBUG')) {define('WP_DEBUG', true);}
	$wp_debug['info'] = 'WP Debug Session Active. ';
	if (isset($expiry)) {$timeleft = $_GET[$wp_debug_keys['switch']];}
	elseif (isset($_COOKIE['debugexpiry'])) {
		$expires = $_COOKIE['debugexpiry'];
		// 1.0.3: fix to incorrect variable name (expiry)
		$timeleft = round( (($expires - time())/60), 1, PHP_ROUND_HALF_DOWN );
	}
	if ($timeleft == 1) {$plural = '';} else {$plural = 's';}
	$wp_debug['info'] .= $timeleft.' minute'.$plural.' remaining. ';
}

// --- check for any already defined constants (forced states) ---
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {$wp_debug['log'] = '1';}
if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
	if (defined('WP_DEBUG_ON_SHUTDOWN') && WP_DEBUG_ON_SHUTDOWN) {$wp_debug['display'] = '3';}
	elseif (defined('WP_DEBUG_TO_CONSOLE') && WP_DEBUG_TO_CONSOLE) {$wp_debug['display'] = '2';}
	else {$wp_debug['display'] == '1';}
}
if (defined('WP_DEBUG_BACKTRACE') && WP_DEBUG_BACKTRACE) {$wp_debug['backtrace'] = '1';}
if (defined('WP_DEBUG_TEXT_ONLY') && WP_DEBUG_TEXT_ONLY) {$wp_debug['html'] = '1';}
if (defined('WP_DEBUG_IP') && WP_DEBUG_IP) {$wp_debug['ip'] = '1';}
if (defined('WP_DEBUG_SESSION') && WP_DEBUG_SESSION) {$wp_debug['id'] = WP_DEBUG_SESSION;}

// --- set default log file path ---
if (defined('WP_CONTENT_DIR')) {$wp_debug['logfile'] = WP_CONTENT_DIR.'/debug.log';}
elseif (defined('ABSPATH')) {$wp_debug['logfile'] = ABSPATH.'wp-content/debug.log';}
else {$wp_debug['logfile'] = dirname(__FILE__).'/wp-content/debug.log';}

// --- maybe add IP address info ---
if ($wp_debug['ip'] == '1') {
	if (!empty($_SERVER['HTTP_CLIENT_IP'])) {$ip = $_SERVER['HTTP_CLIENT_IP'];}
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];}
	else {$ip = $_SERVER['REMOTE_ADDR'];}
	define('WP_DEBUG_IP_ADDRESS', $ip.' ');
	$wp_debug['info'] .= 'IP: '.$ip.'. ';
}

// --- maybe define debug session ID ---
if ( ($wp_debug['id'] != NULL) && (!defined('WP_DEBUG_SESSION')) ) {
	$id = preg_replace("/[^0-9a-zA-Z]/i", '', $wp_debug['id']);
	define('WP_DEBUG_SESSION', $id);
	$wp_debug['info'] .= 'Session "'.$id.'". ';
}

// --- maybe set debug log mode ---
if ($wp_debug['log'] > 0) {

	// --- maybe prepend error info string (if not using error handler class) ---
	if ( ($wp_debug['display'] == '0') || ($wp_debug['display'] == '1') ) {
		$prepend = '';
		if (defined('WP_DEBUG_IP') && WP_DEBUG_IP) {$prepend = WP_DEBUG_IP_ADDRESS.' ';}
		if (defined('WP_DEBUG_SESSION')) {$prepend .= '['.WP_DEBUG_SESSION.']';}
		if ($prepend != '') {ini_set('error_prepend_string', $prepend);}
	}

	if ($wp_debug['log'] == '1') {

		// --- 1 [on/log]: standard debug log mode ---
		// (note: logging is only triggered if WP_DEBUG is true)
		if (!defined('WP_DEBUG_LOG')) {define('WP_DEBUG_LOG', true);}
		$wp_debug['info'] .= 'Logging to /wp-content/debug.log... ';

	} elseif ($wp_debug['log'] == '2') {

		// --- 2 [separate]: use separate /wp-content/debug-session.log file ---
		// note: this will log errors even if WP_DEBUG is set to false!
		// ...but fails to write at all to this file on many servers :-(
		if (defined('WP_CONTENT_DIR')) {$logfile = WP_CONTENT_DIR.'/debug-session.log';}
		elseif (defined('ABSPATH')) {$logfile = ABSPATH.'wp-content/debug-session.log';}
		else {$logfile = dirname(__FILE__).'/wp-content/debug-session.log';}

		// --- fallback if the alternative debug log file path is not writeable ---
		if (is_writeable($logfile)) {
			if (!defined('WP_DEBUG_LOG')) {define('WP_DEBUG_LOG', false);}
			$wp_debug['logfile'] = $logfile;
			ini_set('log_errors', 1);
			ini_set('error_log', $wp_debug['logfile']);
			$wp_debug['info'] .= 'Logging to /wp-content/debug-session.log... ';
		} else {
			if (!defined('WP_DEBUG_LOG')) {define('WP_DEBUG_LOG', true);}
			$wp_debug['info'] .= 'Logging to /wp-content/debug.log... ';
		}

	} elseif ($wp_debug['log'] == '3') {

		// 3 [log4php]: TODO: custom logging using log4php ?
		/* ref: http://logging.apache.org/log4php/ */
		// $logger = dirname(__FILE__).'/log4php/Logger.php';
		// if (file_exists($logger)) {include($logger);}
		// global $wp_debug_logger; $wp_debug_logger = new Logger();
		// if (!defined('WP_DEBUG_LOGGER'])) {define('WP_DEBUG_LOGGER', 'log4php');}
		// $wp_debug['info'] .= 'Logging via log4php. ';

	}
} elseif (!defined('WP_DEBUG_LOG')) {define('WP_DEBUG_LOG', false);}

// --- maybe set debug display mode ---
if ( ($wp_debug['switch'] > 0) && ($wp_debug['display'] > 0) ) {

	// --- 1 [on]: standard error output display ---
	if (!defined('WP_DEBUG_DISPLAY')) {define('WP_DEBUG_DISPLAY', true);}
	@ini_set('display_errors', 1);

	// --- 2 [shutdown]: output all errors on shutdown (default) ---
	if ($wp_debug['display'] == '2') {
		define('WP_DEBUG_ON_SHUTDOWN', true);
		global $errorhandler;
		$errorhandler = new DebugErrorHandler();
		// maybe set for backtrace displays
		if ($wp_debug['backtrace'] == '1') {
			if (!defined('WP_DEBUG_BACKTRACE')) {define('WP_DEBUG_BACKTRACE', true);}
			$wp_debug['info'] .= 'Error backtrace display available. ';
		}
	}

	// --- 3 [console]: browser console log errors ---
	if ($wp_debug['display'] == '3') {
		@ini_set('html_errors', false);
		define('WP_DEBUG_TO_CONSOLE', true);
		global $errorhandler;
		$errorhandler = new DebugErrorHandler();
	}

	// --- maybe no HTML display output ---
	if ($wp_debug['html'] == '0') {
		@ini_set('html_errors', false);
		if (!defined('WP_DEBUG_TEXT_ONLY')) {define('WP_DEBUG_TEXT_ONLY', true);}
		$wp_debug['info'] .= 'Text only output. ';
	}
} elseif (!defined('WP_DEBUG_DISPLAY')) {define('WP_DEBUG_DISPLAY', false);}

// --- maybe clear log file of a specified debug session ID ---
// (or error type eg. E_ERROR, E_NOTICE etc.)
if (isset($_GET['debugclear'])) {
	$id = preg_replace("/[^0-9a-zA-Z]/i", '', $_GET['debugclear']);
	if ($id != '') {
		$loglines = array(); $changedlog = false;
		if (file_exists($wp_debug['logfile'])) {
			$fh = fopen($wp_debug['logfile'], 'r');
			if ($fh) {
				$logline = fgets($fh);
				while ($logline !== false) {
					if ( (substr($id, 0, 2) == 'E_') && (strstr($logline, $id.':')) ) {
						$changedlog = true; $type = 'errors'; // enables deletion of error types
					} elseif (strstr($logline, '['.$id.']')) {$changedlog = true; $type = 'session';}
					else {$loglines[] = $logline;}
					$logline = fgets($fh);
				}
				if ($changedlog) {
					$logdata = implode('', $loglines);
					$fh = @fopen($wp_debug['logfile'], 'w');
					if ($fh) {
						fwrite($fh, $logdata); fclose($fh);
						$wp_debug['info'] .= 'Debug '.$type.' "'.$id.'" cleared from log. ';
					} else {$wp_debug['info'] .= 'Failed rewriting debug log to clear of "'.$id.'". ';}
				} else {$wp_debug['info'] .= 'No lines for "'.$id.'" found to clear. ';}
			} else {$wp_debug['info'] .= 'Failed reading debug log to clear of "'.$id.'". ';}
		} else {$wp_debug['info'] .= 'No debug log found to clear of "'.$id.'". ';}
	}
}

// --- set debug info constant ---
if (!defined('WP_DEBUG_INFO')) {define('WP_DEBUG_INFO', $wp_debug['info']);}
if (!defined('WP_DEBUG_IP_ADDRESS')) {define('WP_DEBUG_IP_ADDRESS', '');}

// --- manual debug session debug values ---
// echo "<!-- WP Debug Session Options: "; print_r($wp_debug); echo " -->";


/* ------------------------- */
/* Debug Error Handler Class */
/* ------------------------- */

/* modified from ref: https://stackoverflow.com/a/30637618/5240159 */

class DebugErrorHandler {

	public static $throwables = array();

	public static $oldhandler = null;

	public static $ieconsolefixed = FALSE;

	public static $error_types = array(
		E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE',
		E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING',
		E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_COMPILE_WARNING => 'E_COMPILE_WARNING',
		E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
		E_STRICT => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
		E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED'
    );

	public static $console_errors = array(
		'E_ERROR' => 'error', 'E_WARNING' => 'warn', 'E_PARSE' => 'error', 'E_NOTICE' => 'info',
		'E_CORE_ERROR' => 'error', 'E_CORE_WARNING' => 'warn',
		'E_COMPILE_ERROR' => 'error', 'E_COMPILE_WARNING' => 'warn',
		'E_USER_ERROR' => 'error', 'E_USER_WARNING' => 'warn', 'E_USER_NOTICE' => 'info',
		'E_STRICT' => 'warn', 'E_RECOVERABLE_ERROR' => 'warn',
		'E_DEPRECATED' => 'log', 'E_USER_DEPRECATED' => 'log'
    );

    public static $display_errors = array(
		'E_ERROR' => 'ERROR', 'E_WARNING' => 'WARNING', 'E_PARSE' => 'Parse Error',
		'E_NOTICE' => 'Notice', 'E_CORE_ERROR' => 'CORE ERROR', 'E_CORE_WARNING' => 'Core Warning',
		'E_COMPILE_ERROR' => 'Compile Error', 'E_COMPILE_WARNING' => 'Compile Warning',
		'E_USER_ERROR' => 'User Error', 'E_USER_WARNING' => 'User Warning', 'E_USER_NOTICE' => 'User Notice',
		'E_STRICT' => 'Strict Error', 'E_RECOVERABLE_ERROR' => 'Recoverable Error',
		'E_DEPRECATED' => 'Deprecated', 'E_USER_DEPRECATED' => 'User Deprecated'
    );

	public function __construct() {

		// --- set error handling method ---
		$oldhandler = set_error_handler(array($this, 'error_handler'));
		// 1.0.5: store existing error handler
		if (!is_null($oldhandler)) {self::$oldhandler = $oldhandler;}

		// --- set shutdown handling method ---
		register_shutdown_function(array($this, 'shutdown_handler'));

		// --- set exception handling method ---
		// [disabled, see exception_handler method for more info]
		// set_exception_handler(array($this, 'exception_handler'));

    }

	public static function set_throwable($number, $text, $file, $line, $context) {

		// --- maybe log to error log ---
		if (WP_DEBUG_LOG) {

			// --- set default error message ---
			$message = self::$error_types[$number].': line '.$line.' in '.$file.': '.$text;

			// --- maybe add debug session ID to log message ---
			if (defined('WP_DEBUG_SESSION') && WP_DEBUG_SESSION) {
				// --- force debug session ID to alphanumeric (to be safest) ---
				$id = preg_replace("/[^0-9a-zA-Z]/i", '', WP_DEBUG_SESSION);
				if ($id != '') {$message = WP_DEBUG_IP_ADDRESS.'['.$id.'] '.$message;}
			} else {$message = WP_DEBUG_IP_ADDRESS.$message;}

			// TODO: maybe use custom debug logger class (log4php) ?
			// if (defined('WP_DEBUG_LOGGER') && WP_DEBUG_LOGGER) {
			//	global $wp_debug_logger;
			// 	if (WP_DEBUG_LOGGER == 'log4php') {$wp_debug_logger->???();}
			// } else {
				// use default error logging function
				error_log($message);
			// }
		}

		// --- no display output if error message was suppressed using @ ---
		// if (0 === error_reporting()) {return;}

		// --- maybe output error to display ---
		if (WP_DEBUG_DISPLAY) {self::output_error(self::$error_types[$number], $text, $file, $line);}

		// --- store errors for shutdown display ---
		$error = array('type' => self::$error_types[$number], 'text' => $text, 'file' => $file, 'line' => $line);
		if (defined('WP_DEBUG_BACKTRACE') && WP_DEBUG_BACKTRACE) {$error['backtrace'] = debug_backtrace();}
		self::$throwables[] = $error;

		// --- maybe run existing error handler ---
		// 1.0.5: pass values to old error handler
		if (!is_null(self::$oldhandler)) {call_user_func(self::$oldhandler, $number, $text, $file, $line, $context);}

		// --- disable default error reporting ---
		return true;
	}

	public static function output_error($type, $text, $file, $line, $shutdown = FALSE) {

		// --- for shutdown output - only output if shutting down ---
		$html = false;
		if ( (defined('WP_DEBUG_ON_SHUTDOWN')) && WP_DEBUG_ON_SHUTDOWN) {
			if ($shutdown) {$html = true;} else {return;}
		}

		// --- display output fixes ---
		$text = strip_tags($text);
		$text = str_replace("\r\n", "\n", $text);
		$file = str_replace(ABSPATH, '', $file);
		$display = self::$display_errors[$type];

		// --- maybe wrap in HTML for display ---
		if ( $html && (ini_get('html_errors')) ) {
			$display = "<font color='red'>".$display."</font>";
			$line = "<font color='green'><b>".$line."</b></font>";
			$file = "<font color='blue'>".$file."</font>";
			$text = str_replace("\n", "<br>", $text);
			$text = "<b>".$text."</b>";
		}

		// --- set error message line ---
		$message = $display." on line ".$line." of file ".$file." : ".$text;

		// --- maybe debug to browser console ---
		if ( (defined('WP_DEBUG_TO_CONSOLE')) && WP_DEBUG_TO_CONSOLE) {

			// --- escape new lines and quotes, and strip tags for javascript output ---
			$message = str_replace("\n", "\\n", $message);
			$message = str_replace("'", "\'", $message);

			// --- no-crash javascript console fix for Internet Explorer ---
			// ref: https://www.codeforest.net/debugging-php-in-browsers-javascript-console
			if (!self::$ieconsolefixed) {
				echo '<script>if (!window.console) console = {};';
				echo 'console.log = console.log || function(){};';
				echo 'console.warn = console.warn || function(){};';
				echo 'console.error = console.error || function(){};';
				echo 'console.info = console.info || function(){};';
				echo 'console.debug = console.debug || function(){};</script>';
				self::$ieconsolefixed = TRUE;

				// --- output to console basic debug option info ---
				echo "<script>console.log('".WP_DEBUG_INFO."');</script>";
			}

			// --- output script wrapper for logging to console ---
			$logtype = self::$console_errors[$type];
			$message = "<script>console.".$logtype."('".$message."');</script>";
		}

		echo $message;
	}

	public static function error_handler($number = '', $text = '', $file = '', $line = '', $context = '') {
		self::set_throwable($number, $text, $file, $line, $context);
		return true;
	}

	public static function exception_handler(Exception $e) {
		// [Currently disabled as causing this:]
		// "Fatal error: Uncaught TypeError: Argument 1 passed to
		// DebugErrorHandler::exception_handler() must be an instance of Exception, instance of ParseError given
		self::set_throwable($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
		return true;
	}

	public static function shutdown_handler() {

		// --- get last (possibly fatal) error ---
		$e = error_get_last();
		if ($e !== NULL) {
			self::set_throwable($e['type'], $e['message'], $e['file'], $e['line'], WP_DEBUG_LOG);
			if (WP_DEBUG_DISPLAY) {
				self::output_error(self::$error_types[$e['type']], $e['message'], $e['file'], $e['line']);
			}
		}

		// --- maybe output all errors on shutdown ---
		if ( WP_DEBUG_DISPLAY && (defined('WP_DEBUG_ON_SHUTDOWN')) && WP_DEBUG_ON_SHUTDOWN) {

			$html = true;
			if ( (defined('WP_DEBUG_TEXT_ONLY')) && WP_DEBUG_TEXT_ONLY) {$html = false;}

			if ($html) {

				// --- error display box style ---
				echo "<style>.error-display-box {background-color:#DDD; padding:20px; line-height:1.2em; font-size:1em;}</style>";

				//--- load/severity display switcher script ---
				echo "<script>function showhidebacktrace(id) {
					if (document.getElementById('backtrace-'+id).style.display == '') {
						document.getElementById('backtrace-'+id).style.display = 'none';
					} else {document.getElementById('backtrace-'+id).style.display = '';}
				}
				function showseverityerrors() {
					document.getElementById('php-error-display').style.display = 'none';
					document.getElementById('php-error-severity').style.display = '';
				}
				function showloaderrors() {
					document.getElementById('php-error-severity').style.display = 'none';
					document.getElementById('php-error-display').style.display = '';
				}</script>";
			}

			// --- output basic debug option info ---
			if ($html) {echo "<div id='php-debug-info' class='error-display-box'>";}
			echo WP_DEBUG_INFO;
			if ($html) {
				echo "<br>".PHP_EOL;
				echo "<font style='font-size:0.8em;'>";
				echo "<a href='javascript:void(0);' onclick='showseverityerrors();'>Errors by Severity</a> | ";
				echo "<a href='javascript:void(0);' onclick='showloaderrors();'>Errors by Load Order</a>";
				if (defined('WP_DEBUG_BACKTRACE') && WP_DEBUG_BACKTRACE) {
					echo " | Click on Error to Show/Hide Debug Backtrace";
				}
				echo "</font></div><br>";
			}

			// --- loop errors and display output ---
			if ($html) {
				echo "<div id='php-error-display' class='error-display-box' style='display:none;'>";
				echo "<b>Errors by Load Order</b><br><br>".PHP_EOL;
			} else {echo PHP_EOL."Errors by Load Order".PHP_EOL;}
			$severity = array(); $i = 0;
			foreach (self::$throwables as $e) {
				if ($html) {echo "<p style='margin-bottom:5px' onclick='showhidebacktrace(\"".$i."\");'>";}
				self::output_error($e['type'], $e['text'], $e['file'], $e['line'], TRUE);
				$error = array('text' => $e['text'], 'file' => $e['file'], 'line' => $e['line']);
				if (isset($e['backtrace'])) {
					$error['backtrace'] = $e['backtrace'];
					if ($html) {
						echo "<div id='backtrace-".$i."' style='display:none;'>";
						var_dump($e['backtrace']); echo "</div>"; $i++;
					}
				}
				$severity[$e['type']][] = $error;
				if ($html) {echo "</p>";}
				echo PHP_EOL;
			}
			if ($html) {echo "</div>";}

			// --- separate error display by severity ---
			if ($html) {
				echo "<div id='php-error-severity' class='error-display-box'>";
				echo "<b>Errors by Severity</b><br><br>".PHP_EOL;
			} else {echo PHP_EOL."Errors by Severity".PHP_EOL;}
			if (count($severity) > 0) {
				$j = 0;
				foreach ($severity as $type => $error) {
					foreach ($error as $e) {
						if ($html) {echo "<p style='margin-bottom:5px' onclick='showhidebacktrace(\"x".$j."\");'>";}
						self::output_error($type, $e['text'], $e['file'], $e['line'], TRUE);
						if ($html) {
							if (isset($e['backtrace'])) {
								echo "<div id='backtrace-x".$j."' style='display:none;'>";
								var_dump($e['backtrace']); echo "</div>"; $j++;
							}
							echo "</p>";
						}
						echo PHP_EOL;
					}
				}
			}
			if ($html) {echo "</div>";}
		}

	}

	// public static function throw_error($error_text, $error_type = E_USER_NOTICE) {
	// 	trigger_error($error_text, $error_type); exit;
	// }

}

