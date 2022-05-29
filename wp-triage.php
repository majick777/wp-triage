<?php

/* ================= */
/* === WP Triage === */
/* ----------------- */
/* . Version 1.0.6 . */
/* ================= */

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
// 1. Simply place wp-triage.php in the same directory as your wp-config.php
//    (reminder: check your user/group permissions are matching the rest of your files.)
// 2. Instead of using `define('WP_DEBUG', true/false);` in wp-config.php, replace it with:
//    if (file_exists(dirname(__FILE__).'/wp-triage.php')) {include dirname(__FILE__).'/wp-triage.php';}
// [Alternative] Copy file to 000-wp-triage.php in your /wp-content/mu-plugins/ folder
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
//				2 or 'shutdown' = output on shutdown (default) [WP_TRIAGE_ON_SHUTDOWN]
//				3 or 'console' = output to browser console [WP_TRIAGE_TO_CONSOLE]
// debugbacktrace - full debug backtrace: 0 or 'off', 1 or 'on' [WP_TRIAGE_BACKTRACE]
//				(backtrace is only output in display on shutdown mode)
// debughtml - HTML for display output: 0 or 'off', 1 or 'on' [WP_TRIAGE_TEXT_ONLY]
//				(only valid if debugdisplay is 'on' or 'shutdown')
// debugip - whether to log IP address: 0 or 'off', 1 or 'on' [WP_TRIAGE_IP]
// debugid - set a specific ID for this debug session session [WP_TRIAGE_ID]
//				(alphanumeric only, displayed and/or recorded in log)
// triageclear - remove all lines from debug log for a specific debug ID session
//				(set value to debug session ID to clear from log)

// --- define querystring keys ---
$wp_triage_keys = array(
	'switch' => 'wpdebug', 'log' => 'debuglog', 'display' => 'debugdisplay', 'html' => 'debughtml',
	'backtrace' => 'debugbacktrace', 'ip' => 'debugip', 'id' => 'debugid'
);

// --- set default settings ---
$wp_triage = array(
	'switch' => '0', 'log' => '1', 'display' => '2', 'backtrace' => '0',
	'html' => '1', 'ip' => '0', 'id' => NULL, 'info' => ''
);

// --- maybe clear all cookies ---
// 1.0.4: do check to maybe clear all cookies first
if ( isset( $_GET[ $wp_triage_keys['switch']] )
  && in_array( $_GET[ $wp_triage_keys['switch']], array( '0', 'off' ) ) ) {
	foreach ( $wp_triage_keys as $setting => $key ) {
		$wp_triage[$setting] = 0;
		setcookie( $key, '', time() - 3600 );
	}
} else {

	// --- loop to get/change settings ---
	foreach ( $wp_triage_keys as $setting => $key ) {

		// --- check for existing cookies ---
		if ( isset($_COOKIE[$key] ) ) {
			if ( ( is_numeric( $_COOKIE[$key] ) ) && ( $_COOKIE[$key] > 0 ) ) {
				$wp_triage[$setting] = (int)$_COOKIE[$key];
			} elseif ( 'id' == $setting ) {
				$wp_triage[$setting] = $_COOKIE[$key];
			}
		}

		// --- check for querystring changes ---
		if ( isset( $_GET[$key] ) ) {

			// --- set shorthand value ---
			$value = $_GET[$key];

			// --- map any querystring word values to numerical ---
			if ( 'on' == $value ) {$value = '1';}
			if ( $key == $wp_triage_keys['log'] ) {
				if ( 'log' == $value ) {$value = '1';}
				if ( 'separate' == $value ) {$value = '2';}
				if ( 'log4php' == $value ) {$value = '3';}
				if ( 'custom' == $value ) {$value = '4';}
			}
			if ( $key == $wp_triage_keys['display'] ) {
				if ( 'display' == $value ) {$value = '1';}
				if ( 'console' == $value ) {$value = '2';}
				if ( 'shutdown' == $value ) {$value = '3';}
			}

			if ($key == $wp_triage_keys['html']) {
				if ( ( 'nohtml' == $value ) || ( 'textonly' == $value ) ) {$value = '1';}
			}

			// --- set key value and matching cookie ---
			if ( ( '0' == $value ) || ( 'off' == $value ) ) {

				// --- remove this cookie ---
				$wp_triage[$setting] = 0; 
				setcookie( $key, '', time() - 3600 );

			} elseif ( ( is_numeric( $value ) ) && ( $value > 0 ) ) {

				// --- use wpdebug setting as minutes for expiry ---
				$wp_triage[$setting] = (int)$value;
				if ( $key == $wp_triage_keys['switch'] ) {
					$expiry = time() + ( (int)$value * 60 );
					// --- set extra cookie for calculating expiry display ---
					// 1.0.6: change cookie name to triageexpiry
					setcookie( 'triageexpiry', $expiry, $expiry );
				}
				if ( isset( $expiry ) ) {$expires = $expiry;} else {$expires = time() + 3600;}
				setcookie( $key, (int)$value, $expires );

			} elseif ( 'id' == $setting ) {

				// --- sanitize and set session ID cookie ---
				$wp_triage['id'] = preg_replace( "/[^0-9a-zA-Z]/i", '', $value );
				if ( isset( $expiry ) ) {$expires = $expiry;} else {$expires = time() + 3600;}
				setcookie( $key, $value, $expires );

			}
		}
	}
}

// --- maybe set WP_DEBUG debug mode ---
if ( $wp_triage['switch'] > 0 ) {
	if ( !defined( 'WP_DEBUG' ) ) {define( 'WP_DEBUG', true );}
	$wp_triage['info'] = 'WP Debug Session Active. ';
	if ( isset( $expiry ) ) {
		$timeleft = $_GET[$wp_triage_keys['switch']];
	} elseif ( isset( $_COOKIE['triageexpiry'] ) ) {
		// 1.0.6: change cookie name to triagexpiry
		$expires = $_COOKIE['triageexpiry'];
		// 1.0.3: fix to incorrect variable name (expiry)
		$timeleft = round( ( ( $expires - time() ) / 60 ), 1, PHP_ROUND_HALF_DOWN );
	}
	if ( $timeleft == 1 ) {$plural = '';} else {$plural = 's';}
	$wp_triage['info'] .= $timeleft . ' minute' . $plural . ' remaining. ';

	// --- disable default WordPress error handler ---
	// 1.0.6: added to bypass Site Health error catcher while triaging
	// ref: https://core.trac.wordpress.org/ticket/44458
	if ( !defined( 'WP_DISABLE_FATAL_ERROR_HANDLER' ) ) {
		define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
	}
	if ( function_exists( 'add_filter' ) ) {
		add_filter( 'wp_fatal_error_handler_enabled', '__return-false', 999 );
	}
}

// --- check for any already defined constants (forced states) ---
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	$wp_triage['log'] = '1';
}
if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
	if ( defined( 'WP_TRIAGE_ON_SHUTDOWN' ) && WP_TRIAGE_ON_SHUTDOWN) {
		$wp_triage['display'] = '3';
	} elseif ( defined( 'WP_TRIAGE_TO_CONSOLE' ) && WP_TRIAGE_TO_CONSOLE ) {
		$wp_triage['display'] = '2';
	} else {
		$wp_triage['display'] == '1';
	}
}
if ( defined(  'WP_TRIAGE_BACKTRACE' ) && WP_TRIAGE_BACKTRACE ) {
	$wp_triage['backtrace'] = '1';
}
if ( defined( 'WP_TRIAGE_TEXT_ONLY' ) && WP_TRIAGE_TEXT_ONLY ) {
	$wp_triage['html'] = '1';
}
if ( defined( 'WP_TRIAGE_IP' ) && WP_TRIAGE_IP ) {
	$wp_triage['ip'] = '1';
}
if ( defined( 'WP_TRIAGE_ID' ) && WP_TRIAGE_ID ) {
	$wp_triage['id'] = WP_TRIAGE_ID;
}
if ( defined( 'WP_TRIAGE_LOGGER' ) && WP_TRIAGE_LOGGER ) {
	$wp_triage['logger'] = WP_TRIAGE_LOGGER;
}

// --- set default log file path ---
if ( defined( 'WP_CONTENT_DIR' ) ) {
	$wp_triage['logfile'] = WP_CONTENT_DIR . '/debug.log';
} elseif ( defined( 'ABSPATH' ) ) {
	$wp_triage['logfile'] = ABSPATH . 'wp-content/debug.log';
} else {
	$wp_triage['logfile'] = dirname( __FILE__ ) . '/wp-content/debug.log';
}

// --- maybe add IP address info ---
if ( '1' == $wp_triage['ip'] ) {
	// TODO: improve IP detection order ?
	if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	define( 'WP_TRIAGE_IP_ADDRESS', $ip . ' ' );
	$wp_triage['info'] .= 'IP: ' . $ip . '. ';
}

// --- maybe define debug session ID ---
if ( ( null != $wp_triage['id'] ) && !defined( 'WP_TRIAGE_ID' ) ) {
	$id = preg_replace( "/[^0-9a-zA-Z]/i", '', $wp_triage['id'] );
	define( 'WP_TRIAGE_ID', $id );
	$wp_triage['info'] .= 'Session ID: "' . $id . '". ';
}

// --- maybe set debug log mode ---
if ( $wp_triage['log'] > 0 ) {

	// --- maybe prepend error info string ---
	// (for when not using error handler class)
	if ( ( '0' == $wp_triage['display'] ) || ( '1' == $wp_triage['display'] ) ) {
		$prepend = '';
		if ( defined( 'WP_TRIAGE_IP' ) && WP_TRIAGE_IP) {
			$prepend = WP_TRIAGE_IP_ADDRESS . ' ';
		}
		if ( defined( 'WP_TRIAGE_ID' ) ) {
			$prepend .= '[' . WP_TRIAGE_ID . ']';
		}
		if ( '' != $prepend ) {
			ini_set( 'error_prepend_string', $prepend );
		}
	}

	if ( '1' == $wp_triage['log'] ) {

		// --- 1 [on/log]: standard debug log mode ---
		// (note: logging is only triggered if WP_DEBUG is true)
		if ( !defined( 'WP_DEBUG_LOG' ) ) {
			define( 'WP_DEBUG_LOG', true );
		}
		$wp_triage['info'] .= 'Logging to /wp-content/debug.log... ';

	} elseif ( '2' == $wp_triage['log'] ) {

		// --- 2 [separate]: use separate /wp-content/triage-session.log file ---
		// note: this will log errors even if WP_DEBUG is set to false!
		// ...but fails to write at all to this file on many servers :-(
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$logfile = WP_CONTENT_DIR . '/triage-session.log';
		} elseif (defined('ABSPATH')) {
			$logfile = ABSPATH . 'wp-content/triage-session.log';
		} else {
			$logfile = dirname( __FILE__ ).'/wp-content/triage-session.log';
		}

		// --- fallback if the alternative debug log file path is not writeable ---
		if ( is_writeable( $logfile ) ) {
			if ( !defined('WP_DEBUG_LOG' ) ) {
				define( 'WP_DEBUG_LOG', false );
			}
			$wp_triage['logfile'] = $logfile;
			ini_set( 'log_errors', 1 );
			ini_set( 'error_log', $wp_triage['logfile'] );
			$wp_triage['info'] .= 'Logging to /wp-content/triage-session.log... ';
		} else {
			if ( !defined( 'WP_DEBUG_LOG' ) ) {
				define( 'WP_DEBUG_LOG', true );
			}
			$wp_triage['info'] .= 'Logging to /wp-content/debug.log... ';
		}

	} elseif ( '3' == $wp_triage['log'] ) {

		// 3 [log4php]: TODO: custom logging using log4php ?
		/* ref: http://logging.apache.org/log4php/ */
		// $logger = dirname(__FILE__).'/log4php/Logger.php';
		// if (file_exists($logger)) {include($logger);}
		// global $wp_triage_logger; $wp_triage_logger = new Logger();
		// if (!defined('WP_DEBUG_LOGGER'])) {define('WP_DEBUG_LOGGER', 'log4php');}
		// $wp_triage['info'] .= 'Logging via log4php. ';

	} 
	// elseif ( '4' == $wp_triage['log'] ) {
		// TODO: custom logging function ?
	// }
} elseif ( !defined( 'WP_DEBUG_LOG' ) ) {
	define( 'WP_DEBUG_LOG', false );
}

// --- maybe set debug display mode ---
if ( ( $wp_triage['switch'] > 0 ) && ( $wp_triage['display'] > 0 ) ) {

	// --- 1 [on]: standard error output display ---
	if ( !defined( 'WP_DEBUG_DISPLAY' ) ) {
		define( 'WP_DEBUG_DISPLAY', true );
	}
	@ini_set( 'display_errors', 1 );

	// --- 2 [shutdown]: output all errors on shutdown (default) ---
	if ( '2' == $wp_triage['display'] ) {
		define( 'WP_TRIAGE_ON_SHUTDOWN', true );
		global $errorhandler;
		$errorhandler = new TriageErrorHandler();
		// maybe set for backtrace displays
		if ( '1' == $wp_triage['backtrace'] ) {
			if ( !defined( 'WP_TRIAGE_BACKTRACE' ) ) {
				define( 'WP_TRIAGE_BACKTRACE', true );
			}
			$wp_triage['info'] .= 'Error backtrace display available. ';
		}
	}

	// --- 3 [console]: browser console log errors ---
	if ( '3' == $wp_triage['display'] ) {
		@ini_set( 'html_errors', false );
		define( 'WP_TRIAGE_TO_CONSOLE', true );
		global $errorhandler;
		$errorhandler = new TriageErrorHandler();
	}

	// --- maybe no HTML display output ---
	if ( '0' == $wp_triage['html'] ) {
		@ini_set( 'html_errors', false );
		if ( !defined( 'WP_TRIAGE_TEXT_ONLY' ) ) {
			define( 'WP_TRIAGE_TEXT_ONLY', true );
		}
		$wp_triage['info'] .= 'Text only output. ';
	}
} elseif ( !defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}

// --- maybe clear log file of a specified debug session ID ---
// (or error type eg. E_ERROR, E_NOTICE etc.)
if ( isset( $_GET['triageclear'] ) ) {
	// TODO: clear for IP addreess or clear all
	$id = preg_replace( "/[^0-9a-zA-Z]/i", '', $_GET['triageclear'] );
	if ( '' != $id ) {
		$loglines = array();
		$changedlog = false;
		if ( file_exists( $wp_triage['logfile'] ) ) {
			$fh = fopen( $wp_triage['logfile'], 'r' );
			if ( $fh ) {
				$logline = fgets( $fh );
				while ( $logline !== false ) {
					if ( ( substr( $id, 0, 2) == 'E_' ) && ( strstr( $logline, $id.':' ) ) ) {
						// enables deletion of error types
						$changedlog = true; $type = 'errors'; 
					} elseif ( strstr( $logline, '[' . $id . ']' ) ) {
						$changedlog = true; $type = 'session';
					} else {
						$loglines[] = $logline;
					}
					$logline = fgets( $fh );
				}
				if ( $changedlog ) {
					$logdata = implode( '', $loglines );
					$fh = @fopen( $wp_triage['logfile'], 'w' );
					if ( $fh ) {
						fwrite( $fh, $logdata ); fclose($fh );
						$wp_triage['info'] .= 'Debug ' . $type . ' "' . $id . '" cleared from log. ';
					} else {
						$wp_triage['info'] .= 'Failed rewriting debug log to clear of "' . $id . '". ';
					}
				} else {
					$wp_triage['info'] .= 'No lines for "' . $id . '" found to clear. ';
				}
			} else {
				$wp_triage['info'] .= 'Failed reading debug log to clear of "' . $id . '". ';
			}
		} else {
			$wp_triage['info'] .= 'No debug log found to clear of "' . $id . '". ';
		}
	}
}

// --- set debug info constant ---
if ( !defined( 'WP_TRIAGE_INFO')) {
	define( 'WP_TRIAGE_INFO', $wp_triage['info'] );
}
if ( !defined( 'WP_TRIAGE_IP_ADDRESS' ) ) {
	define( 'WP_TRIAGE_IP_ADDRESS', '' );
}

// --- manual debug session debug values ---
// echo "<!-- WP Triage Session Options: " . print_r( $wp_triage, true ) . " -->";


/* ---------------------------------- */
/* === Triage Error Handler Class === */
/* ---------------------------------- */

/* modified from ref: https://stackoverflow.com/a/30637618/5240159 */

class TriageErrorHandler {

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
		$oldhandler = set_error_handler( array( $this, 'error_handler' ) );
		// 1.0.5: store existing error handler
		if ( !is_null( $oldhandler ) ) {self::$oldhandler = $oldhandler;}

		// --- set shutdown handling method ---
		register_shutdown_function( array( $this, 'shutdown_handler' ) );

		// --- set exception handling method ---
		// [disabled, see exception_handler method for more info]
		// set_exception_handler(array($this, 'exception_handler'));

    }

	public static function set_throwable( $number, $text, $file, $line, $context ) {

		// --- maybe log to error log ---
		if ( WP_DEBUG_LOG ) {

			// --- set default error message ---
			// 1.0.6: remove ABSPATH from output
			$file = str_replace( ABSPATH, '', $file );
			$message = self::$error_types[$number] . ': line ' . $line.' in ' . $file . ': ' . $text;

			// --- maybe add debug session ID to log message ---
			if ( defined( 'WP_TRIAGE_ID' ) && WP_TRIAGE_ID ) {
				// --- force debug session ID to alphanumeric (to be safest) ---
				$id = preg_replace( "/[^0-9a-zA-Z]/i", '', WP_TRIAGE_ID );
				if ( '' != $id ) {
					$message = WP_TRIAGE_IP_ADDRESS . '[' . $id . '] '.$message;
				}
			} else {
				$message = WP_TRIAGE_IP_ADDRESS . $message;
			}

			// TODO: maybe use custom debug logger class (log4php) ?
			// global $wp_triage;
			// if ( isset( $wp_triage['logger'] ) && ( 'log4php' == $wp_triage['logger'] ) ) {
			// 	$wp_triage_logger->???();
			// } else {
				// --- use default error logging function ---
				error_log( $message );
			// }
		}

		// --- no display output if error message was suppressed using @ ---
		// if (0 === error_reporting()) {return;}

		// --- maybe output error to display ---
		if ( WP_DEBUG_DISPLAY ) {
			self::output_error( self::$error_types[$number], $text, $file, $line );
		}

		// --- store errors for shutdown display ---
		$error = array(
			'type' => self::$error_types[$number],
			'text' => $text,
			'file' => $file,
			'line' => $line
		);
		if ( defined( 'WP_TRIAGE_BACKTRACE' ) && WP_TRIAGE_BACKTRACE ) {
			$error['backtrace'] = debug_backtrace();
		}
		self::$throwables[] = $error;

		// --- maybe run existing error handler ---
		// 1.0.5: pass values to old error handler
		// 1.0.6: added extra is_callable check
		if ( !is_null( self::$oldhandler ) && is_callable( self::$oldhandler ) ) {
			call_user_func( self::$oldhandler, $number, $text, $file, $line, $context );
		}

		// --- disable default error reporting ---
		return true;
	}

	public static function output_error( $type, $text, $file, $line, $shutdown = false ) {

		// --- for shutdown output - only output if shutting down ---
		$html = false;
		if ( ( defined( 'WP_TRIAGE_ON_SHUTDOWN')) && WP_TRIAGE_ON_SHUTDOWN ) {
			if ( $shutdown ) {$html = true;} else {return;}
		}

		// --- display output fixes ---
		$text = strip_tags( $text );
		$text = str_replace( "\r\n", "\n", $text );
		$file = str_replace( ABSPATH, '', $file );
		$display = self::$display_errors[$type];

		// --- maybe wrap in HTML for display ---
		if ( $html && ini_get( 'html_errors' ) ) {
			$display = "<font color='red'>" . $display . "</font>";
			$line = "<font color='green'><b>" . $line . "</b></font>";
			$file = "<font color='blue'>" . $file . "</font>";
			$text = str_replace( "\n", "<br>", $text );
			$text = "<b>" . $text . "</b>";
		}

		// --- set error message line ---
		$message = $display . " on line " . $line . " of file " . $file . " : " . $text;

		// --- maybe debug to browser console ---
		if ( ( defined( 'WP_TRIAGE_TO_CONSOLE')) && WP_TRIAGE_TO_CONSOLE ) {

			// --- escape new lines and quotes, and strip tags for javascript output ---
			$message = str_replace( "\n", "\\n", $message );
			$message = str_replace( "'", "\'", $message );

			// --- no-crash javascript console fix for Internet Explorer ---
			// ref: https://www.codeforest.net/debugging-php-in-browsers-javascript-console
			if ( !self::$ieconsolefixed ) {
				echo '<script>if (!window.console) console = {};';
				echo 'console.log = console.log || function(){};';
				echo 'console.warn = console.warn || function(){};';
				echo 'console.error = console.error || function(){};';
				echo 'console.info = console.info || function(){};';
				echo 'console.debug = console.debug || function(){};</script>';
				self::$ieconsolefixed = true;

				// --- output to console basic debug option info ---
				echo "<script>console.log('" . WP_TRIAGE_INFO . "');</script>";
			}

			// --- output script wrapper for logging to console ---
			$logtype = self::$console_errors[$type];
			$message = "<script>console." . $logtype . "('" . $message . "');</script>";
		}

		echo $message;
	}

	public static function error_handler( $number = '', $text = '', $file = '', $line = '', $context = '' ) {
		self::set_throwable( $number, $text, $file, $line, $context );
		return true;
	}

	public static function exception_handler( Exception $e ) {
		// [Currently disabled as causing this:]
		// "Fatal error: Uncaught TypeError: Argument 1 passed to
		// TriageErrorHandler::exception_handler() must be an instance of Exception, instance of ParseError given
		self::set_throwable( $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine() );
		return true;
	}

	public static function shutdown_handler() {

		// --- get last (possibly fatal) error ---
		$e = error_get_last();
		if ( $e !== NULL ) {
			self::set_throwable( $e['type'], $e['message'], $e['file'], $e['line'], WP_DEBUG_LOG );
			if ( WP_DEBUG_DISPLAY ) {
				self::output_error( self::$error_types[$e['type']], $e['message'], $e['file'], $e['line'] );
			}
		}

		// --- maybe output all errors on shutdown ---
		if ( WP_DEBUG_DISPLAY && ( defined( 'WP_TRIAGE_ON_SHUTDOWN' ) ) && WP_TRIAGE_ON_SHUTDOWN ) {

			$html = true;
			if ( ( defined( 'WP_TRIAGE_TEXT_ONLY' ) ) && WP_TRIAGE_TEXT_ONLY ) {$html = false;}

			if ( $html ) {

				// --- error display box style ---
				echo '<style>.error-display-box {background-color:#DDD; padding:20px; line-height:1.2em; font-size:1em;}</style>';

				// --- load/severity display switcher script ---
				// 1.0.6: prefix javascript functions
				echo "<script>function triage_showhidebacktrace(id) {
					if (document.getElementById('backtrace-'+id).style.display == '') {
						document.getElementById('backtrace-'+id).style.display = 'none';
					} else {document.getElementById('backtrace-'+id).style.display = '';}
				}
				function triage_showseverityerrors() {
					document.getElementById('php-error-display').style.display = 'none';
					document.getElementById('php-error-severity').style.display = '';
				}
				function triage_showloaderrors() {
					document.getElementById('php-error-severity').style.display = 'none';
					document.getElementById('php-error-display').style.display = '';
				}</script>";
			}

			// --- output basic debug option info ---
			if ( $html ) {
				echo "<div id='php-debug-info' class='error-display-box'>";
			}

			echo WP_TRIAGE_INFO;

			if ( $html ) {
				echo '<br>' . PHP_EOL;
				echo '<font style="font-size:0.8em;">';
				echo '<a href="javascript:void(0);" onclick="triage_showseverityerrors();">Errors by Severity</a> | ';
				echo '<a href="javascript:void(0);" onclick="triage_showloaderrors();">Errors by Load Order</a>';
				if ( defined( 'WP_TRIAGE_BACKTRACE') && WP_TRIAGE_BACKTRACE ) {
					echo ' | Click on Error to Show/Hide Debug Backtrace';
				}
				echo '</font></div><br>';
			}

			// --- loop errors and display output ---
			if ( $html ) {
				echo '<div id="php-error-display" class="error-display-box" style="display:none;">';
				echo '<b>Errors by Load Order</b><br><br>' . PHP_EOL;
			} else {
				echo PHP_EOL . 'Errors by Load Order' . PHP_EOL;
			}
			$i = 0; $severity = array(); 
			foreach ( self::$throwables as $e ) {
				if ( $html ) {
					echo '<p style="margin-bottom:5px" onclick="triage_showhidebacktrace(\'' . $i . '\');">';
				}
				self::output_error( $e['type'], $e['text'], $e['file'], $e['line'], true );
				$error = array( 'text' => $e['text'], 'file' => $e['file'], 'line' => $e['line'] );
				if ( isset( $e['backtrace'] ) ) {
					$error['backtrace'] = $e['backtrace'];
					if ( $html ) {
						echo '<div id="backtrace-' . $i . '" style="display:none;">';
					} else {
						echo 'Error Backtrace: ' . PHP_EOL;
					}				
					var_dump( $e['backtrace'] );
					if ( $html ) {
						echo '</div>';
					}
					$i++;
				}
				$severity[$e['type']][] = $error;
				if ( $html ) {
					echo '</p>';
				}
				echo PHP_EOL;
			}
			if ( $html ) {
				echo '</div>';
			}

			// --- separate error display by severity ---
			if ( $html ) {
				echo '<div id="php-error-severity" class="error-display-box">';
				echo '<b>Errors by Severity</b><br><br>'  . PHP_EOL;
			} else {
				echo PHP_EOL . "Errors by Severity" . PHP_EOL;
			}
			if ( count( $severity ) > 0 ) {
				$j = 0;
				foreach ( $severity as $type => $error ) {
					foreach ( $error as $e ) {
						if ( $html ) {
							echo '<p style="margin-bottom:5px" onclick="triage_showhidebacktrace(\'x' . $j . '\');">';
						}
						self::output_error( $type, $e['text'], $e['file'], $e['line'], true );
						if ( isset( $e['backtrace'] ) ) {
							if ( $html ) {
								echo '<div id="backtrace-x' . $j . '" style="display:none;">';
							} else {
								echo 'Error Backtrace: ' . PHP_EOL;
							}
							var_dump( $e['backtrace'] ); 
							if ( $html ) {
								echo '</div>';
							}
							$j++;
						}
						if ( $html ) {
							echo '</p>';
						}
						echo PHP_EOL;
					}
				}
			}
			if ( $html ) {
				echo '</div>';
			}
		}

	}

	// public static function throw_error( $error_text, $error_type = E_USER_NOTICE ) {
	//	trigger_error( $error_text, $error_type );
	// }

}

