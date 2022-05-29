<?php

/* ---------------------- */
/* WP Debug Mode Switcher */
/* ---------------------- */
/* .... Version 1.00 .... */

// default settings and querystring keys
$wp_debug['switch'] = '0'; $wp_debug['log'] = '1'; $wp_debug['display'] = '4';
$debugkey = 'wpdebug'; $logkey = 'debuglog'; $displaykey = 'debugdisplay';

// loop to get/change settings
$debugkeys = array('switch' => $debugkey, 'log' => $logkey, 'display' => $displaykey);
foreach ($debugkeys as $setting => $key) {

	// check for existing cookies
	if ( (isset($_COOKIE[$key])) && (is_numeric($_COOKIE[$key])) && ($_COOKIE[$key] > 0) ) {
		$wp_debug[$setting] = (int)$_COOKIE[$key];
	}

	// check for querystring changes
	if (isset($_GET[$key])) {

		// map any querystring word values to numerical
		if ($_GET[$key] == 'on') {$_GET[$key] = '1';}
		if ($key == $logkey) {
			if ($_GET[$key] == 'log') {$_GET[$key] = '1';}
			if ($_GET[$key] == 'separate') {$_GET[$key] = '2';}
			if ($_GET[$key] == 'log4php') {$_GET[$key] = '3';}
			if ($_GET[$key] == 'custom') {$_GET[$key] = '4';}
		}
		if ($key == $displaykey) {
			if ($_GET[$key] == 'display') {$_GET[$key] = '1';}
			if ($_GET[$key] == 'nohtml') {$_GET[$key] = '2';}
			if ($_GET[$key] == 'textonly') {$_GET[$key] = '2';}
			if ($_GET[$key] == 'console') {$_GET[$key] = '3';}
			if ($_GET[$key] == 'shutdown') {$_GET[$key] = '4';}
		}

		// set key value and matching cookie
		if ( ($_GET[$key] == '0') || ($_GET[$key] == 'off') ) {
			$wp_debug[$setting] = 0; setcookie($key, '', time() - 3600);
		} elseif ( (is_numeric($_GET[$key])) && ($_GET[$key] > 0) ) {
			$wp_debug[$setting] = (int)$_GET[$key];
			// use wpdebug setting as minutes for expiry
			if ($key == $debugkey) {$expires = time() + ($_GET[$key] * 60);}
			if (!isset($expires)) {$expires = time() + 3600;}
			setcookie($key, $_GET[$key], $expires);
		}
	}
}

// maybe set debug mode
if ( ($wp_debug['switch'] > 0) && !defined('WP_DEBUG') ) {define('WP_DEBUG', true);}

// catch for already defined constants
if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {$wp_debug['log'] = '1';}
if (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
	if (defined('WP_DEBUG_ON_SHUTDOWN') && WP_DEBUG_ON_SHUTDOWN) {$wp_debug['display'] = '4';}
	elseif (defined('WP_DEBUG_CONSOLE') && WP_DEBUG_CONSOLE) {$wp_debug['display'] = '3';}
	elseif (defined('WP_DEBUG_TEXT_ONLY') && WP_DEBUG_TEXT_ONLY) {$wp_debug['display'] = '2';}
	else {$wp_debug['display'] == '1';}
}

// maybe set debug log mode
if ($wp_debug['log'] > 0) {
	if ($wp_debug['log'] == '1') {
		// 1 [on/log]: standard debug log mode
		if (!defined('WP_DEBUG_LOG')) {define('WP_DEBUG_LOG', true);}
	} elseif ($wp_debug['log'] == '2') {
		// 2 [separate]: use separate wp-debug.log file
		// note: this will log errors even if WP_DEBUG is false
		if (!defined('WP_DEBUG_LOG')) {define('WP_DEBUG_LOG', false);}
		@ini_set('log_errors', 1);
		@ini_set('error_log', dirname(__FILE__).'/wp-debug.log');
	} elseif ($wp_debug['log'] == '3') {
		// 3 [log4php]: TODO: custom logging using log4php ?
		/* ref: http://logging.apache.org/log4php/ */
		// $logger = dirname(__FILE__).'/log4php/Logger.php';
		// if (file_exists($logger)) {include($logger);}
		// new Logger();
		// define('WP_DEBUG_LOGGER', 'log4php');
	} elseif ($wp_debug['log'] == '4') {
		// 4 [custom]: TODO: use custom error handler class ?
		/* ref: http://deadlytechnology.com/scripts/php-error-class/ */
		// $errorclass = dirname(__FILE__).'/class.error_handler.php';
		// if (file_exists($errorclass)) {include($errorclass);}
		// new error_handler();
		// define('WP_DEBUG_LOGGER', 'errorhandler');
	}
} elseif (!defined('WP_DEBUG_LOG')) {define('WP_DEBUG_LOG', false);}

// maybe set debug output mode
if ($wp_debug['display'] > 0) {
	// 1 [on]: standard error output display
	if (!defined('WP_DEBUG_DISPLAY')) {define('WP_DEBUG_DISPLAY', true);}
	@ini_set('display_errors', 1);
	// 2 [nohtml/textonly]: text only error output display
	if ($wp_debug['display'] == '2') {
		@ini_set('html_errors', false);
		if (!defined('WP_DEBUG_TEXT_ONLY')) {define('WP_DEBUG_TEXT_ONLY', true);}
	}
	// 3 [console]: browser console log errors
	if ($wp_debug['display'] == '3') {
		@ini_set('html_errors', false);
		define('WP_DEBUG_CONSOLE', true);
		$errorhandler = new DebugErrorHandler();
	}
	// 4 [shutdown]: output all errors on shutdown
	if ($wp_debug['display'] == '4') {
		define('WP_DEBUG_ON_SHUTDOWN', true);
		$errorhandler = new DebugErrorHandler();
	}
} elseif (!defined('WP_DEBUG_DISPLAY')) {define('WP_DEBUG_DISPLAY', false);}


/* Debug Error Handler Class */
/* ------------------------- */

/* modified from ref: https://stackoverflow.com/a/30637618/5240159 */

class DebugErrorHandler {

	public static $throwables = array();

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
		set_error_handler(array($this, 'error_handler'));
		set_exception_handler(array($this, 'exception_handler'));
		register_shutdown_function(array($this, 'shutdown_handler'));
    }

	public static function set_throwable($e_number, $e_text, $e_file, $e_line, $e_log = TRUE) {
		// maybe log to error log
		if ($e_log) {error_log(self::$error_types[$e_number].': line '.$e_line.' in '.$e_file.': '.$e_text);}

		// no output if error was suppressed using @
		if (0 === error_reporting()) {return;}

		// output error to display and store for shutdown
		if (WP_DEBUG_DISPLAY) {self::output_error(self::$error_types[$e_number], $e_text, $e_file, $e_line);}
		self::$throwables[$e_number][] = array('type' => self::$error_types[$e_number], 'text' => $e_text, 'file' => $e_file, 'line' => $e_line);
		return true;
	}

	public static function output_error($e_type, $e_text, $e_file, $e_line, $shutdown = FALSE) {

		// for shutdown output, only output if shutting down
		$htmlwrap = false;
		if ( (defined('WP_DEBUG_ON_SHUTDOWN')) && WP_DEBUG_ON_SHUTDOWN) {
			if ($shutdown) {$htmlwrap = true;} else {return;}
		}

		// display output fixes
		$e_text = strip_tags($e_text);
		$e_text = str_replace("\r\n", "\n", $e_text);
		$e_file = str_replace(ABSPATH, '', $e_file);
		$e_display = self::$display_errors[$e_type];

		// maybe wrap in HTML for display
		if ( $htmlwrap && (ini_get('html_errors')) ) {
			$e_display = "<font color='red'>".$e_display."</font>";
			$e_line = "<font color='green'><b>".$e_line."</b></font>";
			$e_file = "<font color='blue'>".$e_file."</font>";
			$e_text = str_replace("\n", "<br>", $e_text);
			$e_text = "<b>".$e_text."</b>";
		}

		// set error message line
		$message = $e_display." on line ".$e_line." of file ".$e_file." : ".$e_text;

		// debug to browser console
		if ( (defined('WP_DEBUG_CONSOLE')) && WP_DEBUG_CONSOLE) {
			// escape new lines and quotes, and strip tags for javascript output
			$message = str_replace("\n", "\\n", $message);
			$message = str_replace("'", "\'", $message);

			// no-crash javascript console fix for Internet Explorer
			// ref: https://www.codeforest.net/debugging-php-in-browsers-javascript-console
			if (!self::$ieconsolefixed) {
				echo '<script>if (!window.console) console = {};';
				echo 'console.log = console.log || function(){};';
				echo 'console.warn = console.warn || function(){};';
				echo 'console.error = console.error || function(){};';
				echo 'console.info = console.info || function(){};';
				echo 'console.debug = console.debug || function(){};</script>';
				self::$ieconsolefixed = TRUE;
			}
			// debug log to browser console
			$logtype = self::$console_errors[$e_type];
			$message = "<script>console.".$logtype."('".$message."');</script>";
		}

		echo $message;
	}

	public static function error_handler($e_number = '', $e_text = '', $e_file = '', $e_line = '') {
		self::set_throwable($e_number, $e_text, $e_file, $e_line, WP_DEBUG_LOG);
		return true;
	}

	public static function exception_handler(Exception $e) {
		self::set_throwable($e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine());
		return true;
	}

	public static function shutdown_handler() {
		// get last (possibly fatal) error
		$e = error_get_last();
		if ($e !== NULL) {
			self::set_throwable($e['type'], $e['message'], $e['file'], $e['line'], WP_DEBUG_LOG);
			if (WP_DEBUG_DISPLAY) {
				self::output_error(self::$error_types[$e['type']], $e['message'], $e['file'], $e['line']);
			}
		}

		// maybe output all errors on shutdown
		if ( (defined('WP_DEBUG_ON_SHUTDOWN')) && (WP_DEBUG_ON_SHUTDOWN) ) {
			echo "<div id='error-display' style='background-color:#DDD; padding:20px; line-height:1.2em;'>";
			foreach (self::$throwables as $errors) {
				foreach ($errors as $e) {
					echo "<p style='margin-bottom:5px'>";
					self::output_error($e['type'], $e['text'], $e['file'], $e['line'], TRUE);
					echo "</p>".PHP_EOL;
				}
			}
			echo "</div>";
		}

	}

	// public static function throw_error($error_text, $error_type = E_USER_NOTICE) {
	// 	trigger_error($error_text, $error_type); exit;
	// }

}
