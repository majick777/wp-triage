<?php

/* ================= */
/* === WP Triage === */
/* ----------------- */
/* . Version 1.0.8 . */
/* ================= */

// By WP Medic: https://wpmedic.tech

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
// 3. WP Triage will autoload and you can use the querystring values to access debugging.
//    eg. http://example.com/?wpdebug=5&log=1&display=3
//    for a 5 minute debug session with error logging and error display to console (see options)
// 4. [Optional] Change your querystring keys below to something more obscure to restrict access.
// 5. [Reminder] Set ?wpdebug=0 to end a debug session before session timeout (destroys cookie.)

// Querystring Switch Values
// -------------------------
// Note: debugging states can also be "forced" by defining constants noted.
// wpdebug - length of WP_DEBUG session (in minutes), 0 for off.
// log - logging mode (WP_DEBUG_LOG):
// 				0 or 'off' = turn debug logging off
// 				1 or 'on' = turn debug logging on (/wp-content/debug.log)
//				2 or 'separate' = use /wp-content/debug-session.log file
//				(works if WP_DEBUG is false! ...but fails to write on many servers :-( )
// 				3 or 'logger' = use log4php logging class handler (not implemented yet)
// display - display mode (WP_DEBUG_DISPLAY):
//				0 or 'off' = turn debug display off
//				1 or 'on' = turn direct debug display output on
//				2 or 'shutdown' = output on shutdown (default) [WP_TRIAGE_ON_SHUTDOWN]
//				3 or 'console' = output to browser console [WP_TRIAGE_TO_CONSOLE]
// backtrace - full debug backtrace: 0 or 'off', 1 or 'on' [WP_TRIAGE_BACKTRACE]
//				(backtrace is only output in display on shutdown mode)
//				can also trace particular error types: 'error', 'strict', 'notice' etc.
// html - HTML for display output: 0 or 'off', 1 or 'on' [WP_TRIAGE_TEXT_ONLY]
//				(only valid if debugdisplay is 'on' or 'shutdown')
// ip - whether to log IP address: 0 or 'off', 1 or 'on' [WP_TRIAGE_IP]
// instance - set a specific ID for this debug session session [WP_TRIAGE_ID]
//				(alphanumeric only, displayed and/or recorded in log)
// triageclear - remove all lines from debug log for a specific debug ID session
//				(set value to debug session ID to clear from log)

// --- define querystring keys ---
$wp_triage_keys = array(
	'switch' => 'wpdebug',
	'log' => 'log',
	'display' => 'display',
	'html' => 'html',
	'backtrace' => 'backtrace',
	'ip' => 'ip',
	'id' => 'instance',
);

// --- set default settings ---
global $wp_triage;
$wp_triage = array(
	'switch' => '0', 
	'log' => '1',
	'display' => '2',
	'backtrace' => '0',
	'html' => '1',
	'ip' => '0',
	'id' => NULL,
	'info' => array(),
);

// --- maybe clear setting cookies ---
// TODO: allow switch to be wpdebug or wptriage
// 1.0.4: do check to maybe clear all cookies first
if ( isset( $_GET[$wp_triage_keys['switch']] ) && in_array( $_GET[ $wp_triage_keys['switch']], array( '0', 'off' ) ) ) {

	// --- delete cookies via negative expiry ---
	foreach ( $wp_triage_keys as $setting => $key ) {
		$wp_triage[$setting] = 0;
		setcookie( $key, '', time() - 3600 );
	}
	$wp_triage['info'][] = 'WP Triage Cookies Cleared.';

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
			if ( 'on' == $value ) {
				$value = '1';
			}
			
			// --- logging mode ---
			if ( $key == $wp_triage_keys['log'] ) {
				if ( 'log' == $value ) {
					$value = '1';
				}
				if ( 'separate' == $value ) {
					$value = '2';
				}
				if ( 'log4php' == $value ) {
					$value = '3';
				}
				if ( 'custom' == $value ) {
					$value = '4';
				}
			}
			
			// --- display mode ---
			if ( $key == $wp_triage_keys['display'] ) {
				if ( 'display' == $value ) {
					$value = '1';
				}
				if ( 'console' == $value ) {
					$value = '2';
				}
				if ( 'shutdown' == $value ) {
					$value = '3';
				}
			}

			// --- HTML / plain text ---
			if ( $key == $wp_triage_keys['html'] ) {
				if ( ( 'nohtml' == $value ) || ( 'textonly' == $value ) ) {
					$value = '1';
				}
			}

			// 1.0.8: allow for different backtrace types
			if ( $key == $wp_triage_keys['backtrace'] ) {
				if ( '1' == $value ) {
					$value = 'all';
				}
				// TODO: strict error type value checking ?
				// $error_types = array();
				// if ( in_array( $value, $error_types ) ) {
					$wp_triage[$setting] = $value;
				// }
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
				$expires = isset( $expiry ) ? $expiry : ( time() + 3600 );
				setcookie( $key, (int)$value, $expires );

			} elseif ( 'id' == $setting ) {

				// --- sanitize and set session ID cookie ---
				$wp_triage['id'] = preg_replace( "/[^0-9a-zA-Z]/i", '', $value );
				$expires = isset( $expiry ) ? $expiry : ( time() + 3600 );
				setcookie( $key, $value, $expires );

			}
		}
	}
}

// --- bug out if turned off explicitly ---
if ( defined( 'WP_TRIAGE' ) && !WP_TRIAGE ) {
	$wp_triage['switch'] = 0;
}

// --- maybe set WP_DEBUG debug mode ---
if ( $wp_triage['switch'] > 0 ) {
	// 1.0.8: define base switch as well
	if ( !defined( 'WP_TRIAGE' ) ) {
		define( 'WP_TRIAGE', $wp_triage['switch'] );
	}
	if ( !defined( 'WP_DEBUG' ) ) {
		define( 'WP_DEBUG', true );
	}
	$wp_triage['info'][] = 'WP Triage Debug Session Active.';
	if ( isset( $expiry ) ) {
		$timeleft = $_GET[$wp_triage_keys['switch']];
	} elseif ( isset( $_COOKIE['triageexpiry'] ) ) {
		// 1.0.6: change cookie name to triagexpiry
		$expires = $_COOKIE['triageexpiry'];
		// 1.0.3: fix to incorrect variable name (expiry)
		$timeleft = round( ( ( $expires - time() ) / 60 ), 1, PHP_ROUND_HALF_DOWN );
	}
	// 1.0.8: simplify plural check
	$plural = ( $timeleft == 1 ) ? '' : 's';
	$wp_triage['info'][] = $timeleft . ' minute' . $plural . ' remaining for debug session.';

	// --- disable default WordPress error handler ---
	// 1.0.6: added to bypass Site Health error catcher while triaging
	// ref: https://core.trac.wordpress.org/ticket/44458
	// TODO: retest behaviour with WPs internal handler not disabled ?
	if ( !defined( 'WP_DISABLE_FATAL_ERROR_HANDLER' ) ) {
		define( 'WP_DISABLE_FATAL_ERROR_HANDLER', true );
		$wp_triage['info'][] = 'WordPress Error Handler disabled.';
	}
	if ( function_exists( 'add_filter' ) ) {
		add_filter( 'wp_fatal_error_handler_enabled', '__return_false', 999 );
	}
}

// --- check for any already defined constants (forced states) ---
if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
	$wp_triage['log'] = '1';
}
if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
	if ( defined( 'WP_TRIAGE_ON_SHUTDOWN' ) && WP_TRIAGE_ON_SHUTDOWN ) {
		$wp_triage['display'] = '3';
	} elseif ( defined( 'WP_TRIAGE_TO_CONSOLE' ) && WP_TRIAGE_TO_CONSOLE ) {
		$wp_triage['display'] = '2';
	} else {
		$wp_triage['display'] == '1';
	}
}
if ( defined(  'WP_TRIAGE_BACKTRACE' ) ) {
	$wp_triage['backtrace'] = WP_TRIAGE_BACKTRACE;
	$wp_triage['info'][] = 'Error Backtracing: ' . WP_TRIAGE_BACKTRACE;
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
	// 1.0.7: check for wp-content path (possible subdir install)
	$path = realpath( dirname( __FILE__ ) . '/wp-content/' );
	if ( $path && is_dir( $path ) ) {
		$wp_triage['logfile'] = $path . 'debug.log';
	} else {
		$wp_triage['logfile'] = dirname( __FILE__ ) . '/debug.log';
	}
}

// --- maybe add IP address info ---
if ( '1' == $wp_triage['ip'] ) {
	// TODO: improve IP detection order here ?
	if ( !empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
		$ip = $_SERVER['HTTP_CLIENT_IP'];
	} elseif ( !empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
	} else {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	define( 'WP_TRIAGE_IP_ADDRESS', $ip . ' ' );
	$wp_triage['info'][] = 'Client IP: ' . $ip . '.';
}

// --- maybe define debug session ID ---
if ( ( null != $wp_triage['id'] ) && !defined( 'WP_TRIAGE_ID' ) ) {
	$id = preg_replace( "/[^0-9a-zA-Z]/i", '', $wp_triage['id'] );
	define( 'WP_TRIAGE_ID', $id );
	$wp_triage['info'][] = 'Session ID: ' . $id . '.';
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
		$wp_triage['info'][] .= 'Logging to /wp-content/debug.log...';

	} elseif ( '2' == $wp_triage['log'] ) {

		// --- 2 [separate]: use separate /wp-content/triage-session.log file ---
		// note: this will log errors even if WP_DEBUG is set to false!
		// ...but fails to write at all to this file on many servers :-(
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$logfile = WP_CONTENT_DIR . '/triage-session.log';
		} elseif ( defined( 'ABSPATH' ) ) {
			$logfile = ABSPATH . 'wp-content/triage-session.log';
		} else {
			// 1.0.7: check for wp-content path (possible subdir install)
			$path = realpath( dirname( __FILE__ ) . '/wp-content/' );
			if ( $path && is_dir( $path ) ) {
				$logfile = $path . 'triage-session.log';
			} else {
				$logfile = dirname( __FILE__ ) . '/triage-session.log';
			}
		}

		// --- fallback if the alternative debug log file path is not writeable ---
		if ( is_writeable( $logfile ) ) {
			if ( !defined('WP_DEBUG_LOG' ) ) {
				define( 'WP_DEBUG_LOG', false );
			}
			$wp_triage['logfile'] = $logfile;
			ini_set( 'log_errors', 1 );
			ini_set( 'error_log', $wp_triage['logfile'] );
			$wp_triage['info'][] = 'Logging to /wp-content/triage-session.log... ';
		} else {
			if ( !defined( 'WP_DEBUG_LOG' ) ) {
				define( 'WP_DEBUG_LOG', true );
			}
			$wp_triage['info'][] = 'Logging to /wp-content/debug.log...';
		}

	} elseif ( '3' == $wp_triage['log'] ) {

		// 3 [log4php]: TODO: add custom logging using log4php ?
		/* ref: http://logging.apache.org/log4php/ */
		// $logger = dirname(__FILE__).'/log4php/Logger.php';
		// if (file_exists($logger)) {include($logger);}
		// global $wp_triage_logger; $wp_triage_logger = new Logger();
		// if (!defined('WP_DEBUG_LOGGER'])) {define('WP_DEBUG_LOGGER', 'log4php');}
		// $wp_triage['info'] .= 'Logging via log4php. ';

	}
	// elseif ( '4' == $wp_triage['log'] ) {
		// TODO: maybe add a custom logging function ?
		//
	// }

} elseif ( !defined( 'WP_DEBUG_LOG' ) ) {
	// --- fallback logging definition ---
	define( 'WP_DEBUG_LOG', false );
}

// --- maybe set debug display mode ---
if ( ( $wp_triage['switch'] > 0 ) && ( $wp_triage['display'] > 0 ) ) {

	// --- 1 [on]: standard error output display ---
	if ( !defined( 'WP_DEBUG_DISPLAY' ) ) {
		define( 'WP_DEBUG_DISPLAY', true );
	}
	@ini_set( 'display_errors', 1 );

	// --- 1 [direct] errors display as they happen ---
	if ( '1' == $wp_triage['display'] ) {
		$wp_triage['info'][] = 'Display error output direct to screen.';
	}

	// --- 2 [shutdown]: output all errors on shutdown (default) ---
	if ( '2' == $wp_triage['display'] ) {
		define( 'WP_TRIAGE_ON_SHUTDOWN', true );
		global $errorhandler;
		$errorhandler = new TriageErrorHandler();
		$wp_triage['info'][] = 'Display error output on Shutdown.';
	}

	// --- maybe set for backtrace displays ---
	if ( ( '1' == $wp_triage['display'] ) || ( '2' == $wp_triage['display'] ) ) {
		if ( '0' != $wp_triage['backtrace'] ) {
			if ( !defined( 'WP_TRIAGE_BACKTRACE' ) ) {
				define( 'WP_TRIAGE_BACKTRACE', $wp_triage['backtrace'] );
			}
			if ( 'all' == $wp_triage['backtrace'] ) {
				$wp_triage['info'][] = 'Error backtrace display available for all errors.';
			} else {
				$wp_triage['info'][] = 'Error backtrace display available for: ' . $wp_triage['backtrace'];
			}
		}
	}

	// --- 3 [console]: browser console log errors ---
	if ( '3' == $wp_triage['display'] ) {
		@ini_set( 'html_errors', false );
		define( 'WP_TRIAGE_TO_CONSOLE', true );
		global $errorhandler;
		$errorhandler = new TriageErrorHandler();
		$wp_triage['info'][] = 'Display error output in Developer Console.';
	}

	// --- maybe no HTML display output ---
	if ( '0' == $wp_triage['html'] ) {
		@ini_set( 'html_errors', false );
		if ( !defined( 'WP_TRIAGE_TEXT_ONLY' ) ) {
			define( 'WP_TRIAGE_TEXT_ONLY', true );
		}
		$wp_triage['info'][] = 'Text only output.';
	}

} elseif ( !defined( 'WP_DEBUG_DISPLAY' ) ) {
	// --- no debug display output ---
	define( 'WP_DEBUG_DISPLAY', false );
}

// --- maybe clear log file of a specified debug session ID ---
// (or error type eg. E_ERROR, E_NOTICE etc.)
if ( WP_TRIAGE && isset( $_GET['clear'] ) ) {
	// TODO: clear for IP addreess or clear all
	$id = preg_replace( "/[^0-9a-zA-Z]/i", '', $_GET['clear'] );
	if ( '' != $id ) {
		$loglines = array();
		$changedlog = false;
		if ( file_exists( $wp_triage['logfile'] ) ) {
			$fh = fopen( $wp_triage['logfile'], 'r' );
			if ( $fh ) {
				$logline = fgets( $fh );
				while ( $logline !== false ) {
					if ( ( substr( $id, 0, 2 ) == 'E_' ) && ( strstr( $logline, $id . ':' ) ) ) {
						// enables deletion of error types
						$changedlog = true;
						$type = 'errors';
					} elseif ( strstr( $logline, '[' . $id . ']' ) ) {
						$changedlog = true;
						$type = 'session';
					} else {
						$loglines[] = $logline;
					}
					$logline = fgets( $fh );
				}
				if ( $changedlog ) {
					$logdata = implode( '', $loglines );
					$fh = @fopen( $wp_triage['logfile'], 'w' );
					if ( $fh ) {
						fwrite( $fh, $logdata );
						fclose($fh );
						$wp_triage['info'][] = 'Cleared from debug log ' . $type . ' of ID ' . $id . '.';
					} else {
						$wp_triage['info'][] = 'Failed rewriting debug log to clear of ' . $id . '.';
					}
				} else {
					$wp_triage['info'][] = 'No debug lines found to clear for ID ' . $id . '.';
				}
			} else {
				$wp_triage['info'][] = 'Failed reading debug log to clear ID ' . $id . '.';
			}
		} else {
			$wp_triage['info'][] = 'No debug log found to clear ID ' . $id . '.';
		}
	}
}

// --- set debug info constant ---
if ( !defined( 'WP_TRIAGE_IP_ADDRESS' ) ) {
	define( 'WP_TRIAGE_IP_ADDRESS', '' );
}

// --- manual debug session debug values ---
// echo "<!-- WP Triage Session Options: " . print_r( $wp_triage, true ) . " -->";


/* ---------------------------------- */
/* === Triage Error Handler Class === */
/* ---------------------------------- */

/* originally modified from: https://stackoverflow.com/a/30637618/5240159 */

class TriageErrorHandler {

	// --- array of throwable errors ---
	public static $throwables = array();

	// --- old error handler function ---
	public static $oldhandler = null;

	// --- IE console fixed flag ---
	public static $ieconsolefixed = false;
	
	// ----------
	// Constuctor
	// ----------
	public function __construct() {

		// --- set error handling method ---
		// 1.0.8: removed context argument for PHP 7.2+
		if ( version_compare( phpversion(), '7.2', '<' ) ) {
			$oldhandler = set_error_handler( array( $this, 'error_handler_fallback' ) );
		} else {
			$oldhandler = set_error_handler( array( $this, 'error_handler' ) );
		}	
		// 1.0.5: store existing error handler
		if ( !is_null( $oldhandler ) ) {
			self::$oldhandler = $oldhandler;
		}

		// --- set exception handling method ---
		// 1.0.7: re-enabled to catch exception messages for PHP7
		// 1.0.8: check PHP version to handle exceptions
		if ( version_compare( phpversion(), '7', '<' ) ) {
			// 1.0.8: added fallback handler for PHP5
			set_exception_handler( array( $this, 'exception_handler_fallback' ) );
		} else {
			// 1.0.8: set Throwable type for error for PHP7+
			set_exception_handler( array( $this, 'exception_handler' ) );
		}

		// --- set shutdown handling method ---
		register_shutdown_function( array( $this, 'shutdown_handler' ) );

    }

	// --- array of error type codes ---
	public static function error_types( $key ) {
		$error_types = array(
			E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE', E_NOTICE => 'E_NOTICE',
			E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING',
			E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_COMPILE_WARNING => 'E_COMPILE_WARNING',
			E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
			E_STRICT => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
			E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED'
		);
		return $error_types[$key];
	}

	// --- console error labels ---
	public static function console_errors( $key ) {
		$console_errors = array(
			'E_ERROR' => 'error', 'E_WARNING' => 'warn', 'E_PARSE' => 'error', 'E_NOTICE' => 'info',
			'E_CORE_ERROR' => 'error', 'E_CORE_WARNING' => 'warn',
			'E_COMPILE_ERROR' => 'error', 'E_COMPILE_WARNING' => 'warn',
			'E_USER_ERROR' => 'error', 'E_USER_WARNING' => 'warn', 'E_USER_NOTICE' => 'info',
			'E_STRICT' => 'warn', 'E_RECOVERABLE_ERROR' => 'warn',
			'E_DEPRECATED' => 'log', 'E_USER_DEPRECATED' => 'log'
		);
		return $console_errors[$key];
	}

	// --- display error labels ----
    public static function display_errors( $key ) {
		$display_errors = array(
			'E_ERROR' => 'ERROR', 'E_WARNING' => 'WARNING', 'E_PARSE' => 'Parse Error',
			'E_NOTICE' => 'Notice', 'E_CORE_ERROR' => 'CORE ERROR', 'E_CORE_WARNING' => 'Core Warning',
			'E_COMPILE_ERROR' => 'Compile Error', 'E_COMPILE_WARNING' => 'Compile Warning',
			'E_USER_ERROR' => 'User Error', 'E_USER_WARNING' => 'User Warning', 'E_USER_NOTICE' => 'User Notice',
			'E_STRICT' => 'Strict Error', 'E_RECOVERABLE_ERROR' => 'Recoverable Error',
			'E_DEPRECATED' => 'Deprecated', 'E_USER_DEPRECATED' => 'User Deprecated'
		);
		return $display_errors[$key];
	}

	// -------------
	// Set Throwable
	// -------------
	public static function set_throwable( $number, $text, $file, $line, $context = false, $backtrace = false ) {

		// 1.0.8: fix for possible error code of 0
		if ( 0 == $number ) {
			$number = E_ERROR;
		}

		// --- maybe log to error log ---
		if ( WP_DEBUG_LOG ) {

			// --- set default error message ---
			// 1.0.6: remove ABSPATH from output
			$abspath = substr( ABSPATH, 0, -1 );
			$file = str_replace( $abspath, '', $file );
			$message = self::error_types( $number ) . ': line ' . $line.' in ' . $file . ': ' . $text;

			// --- maybe add debug session ID to log message ---
			if ( defined( 'WP_TRIAGE_ID' ) && WP_TRIAGE_ID ) {
				// --- force debug session ID to alphanumeric (to be safest) ---
				$id = preg_replace( "/[^0-9a-zA-Z]/i", '', WP_TRIAGE_ID );
				if ( '' != $id ) {
					$message = WP_TRIAGE_IP_ADDRESS . '[' . $id . '] ' . $message;
				}
			} else {
				$message = WP_TRIAGE_IP_ADDRESS . ' ' . $message;
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

		$error_type = self::error_types( $number );

		// --- maybe output error to display ---
		if ( WP_DEBUG_DISPLAY ) {
			self::output_error( $error_type, $text, $file, $line, $backtrace, false );
		}

		// --- store errors for shutdown display ---
		$error = array(
			'type' => $error_type,
			'text' => $text,
			'file' => $file,
			'line' => $line
		);

		if ( defined( 'WP_TRIAGE_BACKTRACE' ) && WP_TRIAGE_BACKTRACE ) {
			// 1.0.8: use already available backtrace for exceptions		
			if ( ( 'all' == WP_TRIAGE_BACKTRACE ) || strstr( strtolower( $error_type ), strtolower( WP_TRIAGE_BACKTRACE ) ) ) {
				if ( $backtrace ) {
					$error['backtrace'] = $backtrace;
				} else {
					$error['backtrace'] = self::debug_backtrace_string();
				}
			}
		}

		// --- store errors in throwable array ---
		self::$throwables[] = $error;

		// --- maybe run existing error handler ---
		// 1.0.5: pass values to old error handler
		// 1.0.6: added extra is_callable check
		if ( !is_null( self::$oldhandler ) && is_callable( self::$oldhandler ) ) {
			if ( version_compare( phpversion(), '7.2.0', '<' ) ) {
				// 1.0.8: only use context in PHP prior to 7.2
				call_user_func( self::$oldhandler, $number, $text, $file, $line, $context );
			} else {
				call_user_func( self::$oldhandler, $number, $text, $file, $line );
			}
		}

		// --- disable default error reporting ---
		return true;
	}

	// ------------
	// Output Error
	// ------------
	public static function output_error( $type, $text, $file, $line, $backtrace = false, $shutdown = false ) {

		// 1.0.8: corrected HTML check
		$html = ( defined( 'WP_TRIAGE_TEXT_ONLY' ) && WP_TRIAGE_TEXT_ONLY ) ? false : true;

		// --- check if outputting on shutdown ---
		// 1.0.8: simplified early return check
		if ( defined( 'WP_TRIAGE_ON_SHUTDOWN' ) && WP_TRIAGE_ON_SHUTDOWN && !$shutdown ) {
			return;
		}

		// --- display output fixes ---
		$text = strip_tags( $text );
		$text = str_replace( "\r\n", "\n", $text );
		$abspath = substr( ABSPATH, 0, -1 );
		$file = str_replace( $abspath, '', $file );
		$display = self::display_errors( $type );

		// --- maybe wrap in HTML for display ---
		if ( $html && ini_get( 'html_errors' ) ) {
			$display = '<font color="red">' . $display . '</font>';
			$line = '<font color="green"><b>' . $line . '</b></font>';
			$file = '<font color="blue">' . $file . '</font>';
			$text = str_replace( "\n", "<br>", $text );
			$text = '<b>' . $text . '</b>';
		}

		// --- set error message line ---
		$message = $display . ' on line ' . $line . ' of file ' . $file . ':<br>' . PHP_EOL;
		$message .= $text . '<br>' . PHP_EOL;

		// --- maybe debug to browser console ---
		if ( defined( 'WP_TRIAGE_TO_CONSOLE' ) && WP_TRIAGE_TO_CONSOLE ) {

			// --- escape new lines and quotes, and strip tags for javascript output ---
			$message = str_replace( "\n", "\\n", $message );
			$message = str_replace( "'", "\'", $message );

			// --- no-crash javascript console fix for Internet Explorer ---
			self::fix_ie_console();

			// --- output script wrapper for logging to console ---
			$logtype = self::console_errors( $type );
			// 1.0.8: sanitize message text for safety
			$message = "<script>console." . $logtype . "('" . esc_js( $message ) . "');</script>";

			echo $mesage;		
			return;
		}

		// --- output message ---
		echo $message . '<br>' . PHP_EOL;

		// --- debug backtrace ---
		// 1.0.8: added for direct error output display
		if ( defined( 'WP_TRIAGE_BACKTRACE' ) && WP_TRIAGE_BACKTRACE ) {
			if ( defined( 'WP_TRIAGE_ON_SHUTDOWN' ) && WP_TRIAGE_ON_SHUTDOWN ) {
				return;
			}
			if ( ( 'all' == WP_TRIAGE_BACKTRACE ) || strstr( strtolower( $error_type ), strtolower( WP_TRIAGE_BACKTRACE ) ) ) {
				if ( !$backtrace ) {
					$backtrace = self::debug_backtrace_string();
				}
				if ( $html ) {
					echo '<div id="backtrace">';
				} else {
					echo 'Error Backtrace: ' . PHP_EOL;
				}
				self::display_backtrace( $backtrace );
				if ( $html ) {
					echo '</div>' . PHP_EOL;
				}
			}
		}

	}
	
	// -----------------
	// Display Backtrace
	// -----------------
	public static function display_backtrace( $backtrace ) {
		
		if ( is_string( $backtrace ) ) {
			echo $backtrace;
		} else {
			echo var_dump( $backtrace, true );
		}
		
	}

	// ----------------------
	// Debug Backtrace String
	// ----------------------
	// ref: https://stackoverflow.com/a/15439989/5240159
	public static function debug_backtrace_string() {
		$html = ( defined( 'WP_TRIAGE_TEXT_ONLY' ) && WP_TRIAGE_TEXT_ONLY ) ? false : true;
		$stack = '';
		$i = 1;
		$trace = debug_backtrace();
		unset( $trace[0] );
		foreach ( $trace as $node ) {
			if ( !isset( $node['class'] ) || !strstr( $node['class'], 'TriageErrorHandler' ) ) {
				if ( $html ) {
					$stack .= '<font color="red">';
				}
				$stack .= "#" . $i . " ";
				if ( $html ) {
					$stack .= '</font>';
				}
				if ( isset( $node['file'] ) ) {
					if ( $html ) {
						$stack .= '<font color="blue">';
					}
					$abspath = substr( ABSPATH, 0, -1 );
					$stack .= str_replace( $abspath, '', $node['file'] );
					if ( $html ) {
						$stack .= '</font>';
					}
				}
				if ( isset( $node['line'] ) ) {
					$stack .= " on line ";
					if ( $html ) {
						$stack .= '<font color="green"><b>';
					}
					$stack .= $node['line'];
					if ( $html ) {
						$stack .= '</b></font>';
					}
				}
				$stack .= " of "; 
				if (isset( $node['class'] ) ) {
					$stack .= $node['class'] . "->"; 
				}
				$stack .= $node['function'] . "()";
				if ( $html ) {
					$stack .= '<br>';
				}
				$stack .= PHP_EOL;
				$i++;
			}
		}
		return $stack;
	} 

	// --------------
	// Fix IE Console
	// --------------
	// ref: https://www.codeforest.net/debugging-php-in-browsers-javascript-console
	public static function fix_ie_console() {

		// --- bug out if already fixed ---
		if ( self::$ieconsolefixed ) {
			return;
		}

		// --- output fixes ---
		echo '<script>if (!window.console) console = {};';
		echo 'console.log = console.log || function(){};';
		echo 'console.warn = console.warn || function(){};';
		echo 'console.error = console.error || function(){};';
		echo 'console.info = console.info || function(){};';
		echo 'console.debug = console.debug || function(){};</script>';
		
		// --- output to console basic debug option info ---
		global $wp_triage;
		echo "<script>console.log('" . $wp_triage['info'] . "');</script>";
		
		// --- flag to perform once only ---
		self::$ieconsolefixed = true;
	}	

	// -------------
	// Error Handler
	// -------------
	public static function error_handler( $number = '', $text = '', $file = '', $line = '' ) {
		$backtrace = ( defined( 'WP_TRIAGE_BACKTRACE' ) && WP_TRIAGE_BACKTRACE ) ? self::debug_backtrace_string() : false;
		self::set_throwable( $number, $text, $file, $line, '', $backtrace );
		return true;
	}

	// ----------------------
	// Error Handler Fallback
	// ----------------------
	public static function error_handler_fallback( $number = '', $text = '', $file = '', $line = '', $context = '' ) {
		$backtrace = ( defined( 'WP_TRIAGE_BACKTRACE' ) && WP_TRIAGE_BACKTRACE ) ? self::debug_backtrace_string() : false;
		self::set_throwable( $number, $text, $file, $line, $context, $backtrace );
		return true;
	}
	
	// -----------------
	// Exception Handler
	// -----------------
	// 1.0.6: was disabled as causing this type mismatch
	// "Fatal error: Uncaught TypeError: Argument 1 passed to
	// TriageErrorHandler::exception_handler() must be an instance of Exception, instance of ParseError given
	// 1.0.7: re-enabled with Exception type removed from error argument
	// 1.0.8: add Throwable to exception_handler function
	public static function exception_handler( \Throwable $e ) {

		// --- manual exception debug output ---
		// echo 'Exception Thrown!<br>'  . PHP_EOL;
		// echo 'Exception Code: ' . $e->getCode() . '<br>' . PHP_EOL;
		// echo 'Message: ' . $e->getMessage() . '<br>' . PHP_EOL;
		// echo 'File: ' . $e->getFile() . '<br>' . PHP_EOL;
		// echo 'Line: ' . $e->getLine() . '<br>' . PHP_EOL;
		// print_r( $e );
		
		self::set_throwable( $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), false, $e->getTrace() );
		return true;
	}

	// --------------------------
	// Exception Handler Fallback
	// --------------------------
	// 1.0.8: added fallback error handler for prior to PHP7
	public static function exception_handler_fallback( \Exception $e ) {
		self::set_throwable( $e->getCode(), $e->getMessage(), $e->getFile(), $e->getLine(), false, $e->getTrace() );
		return true;		
	}

	// ----------------
	// Shutdown Handler
	// ----------------
	public static function shutdown_handler() {

		// --- get last (possibly fatal) error ---
		$e = error_get_last();
		// print_r( $e );
		if ( $e !== NULL ) {
			$e['backtrace'] = ( defined( 'WP_TRIAGE_BACKTRACE' ) && WP_TRIAGE_BACKTRACE ) ? self::debug_backtrace_string() : false;
			self::set_throwable( $e['type'], $e['message'], $e['file'], $e['line'], WP_DEBUG_LOG, $e['backtrace'] );
			if ( WP_DEBUG_DISPLAY ) {
				$shutdown = WP_TRIAGE_ON_SHUTDOWN ? false : true; // ??? test this ???
				self::output_error( self::error_types( $e['type'] ), $e['message'], $e['file'], $e['line'], $e['backtrace'], $shutdown );
			}
		}

		// --- maybe output all errors on shutdown ---
		if ( WP_DEBUG_DISPLAY && defined( 'WP_TRIAGE_ON_SHUTDOWN' ) && WP_TRIAGE_ON_SHUTDOWN ) {

			// 1.0.8: simplified HTML check
			$html = ( defined( 'WP_TRIAGE_TEXT_ONLY' ) && WP_TRIAGE_TEXT_ONLY ) ? false : true;
			if ( $html ) {

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

			// 1.0.8: loop load info array of messages
			global $wp_triage;
			if ( isset( $wp_triage['info'] ) && is_array( $wp_triage['info'] ) ) {
				echo '<b>WP Triage Load Info</b><br>' . PHP_EOL;
				foreach ( $wp_triage['info'] as $message ) {
					echo $message . '<br>' . PHP_EOL;
				}
			}

			if ( $html ) {
				echo '<br>' . PHP_EOL;
				echo '<font style="font-size:0.8em;">';
				echo '<a href="javascript:void(0);" onclick="triage_showseverityerrors();">Errors by Severity</a> | ';
				echo '<a href="javascript:void(0);" onclick="triage_showloaderrors();">Errors by Load Order</a>';
				
				// 1.0.8: removed message in favour of backtrace buttons
				// if ( defined( 'WP_TRIAGE_BACKTRACE') && WP_TRIAGE_BACKTRACE ) {
				// 	echo ' | Click on Error to Show/Hide Debug Backtrace';
				// }
				echo '</font></div><br>';
			}

			// --- loop errors and display output ---
			if ( $html ) {
				echo '<div id="php-error-display" class="error-display-box" style="display:none;">';
				echo '<b>Errors by Load Order</b><br><br>' . PHP_EOL;
			} else {
				echo PHP_EOL . 'Errors by Load Order' . PHP_EOL;
			}
			$i = 0;
			$severity = array();
			foreach ( self::$throwables as $e ) {
				if ( $html ) {
					if ( isset( $e['backtrace'] ) ) {
						echo '<input type="button" class="backtrace-button" value="Backtrace" onclick="triage_showhidebacktrace(\'' . $i . '\');">';
					}
					echo '<div class="error-line">';
				}
				self::output_error( $e['type'], $e['text'], $e['file'], $e['line'], $e['backtrace'], true );
				$error = array( 'text' => $e['text'], 'file' => $e['file'], 'line' => $e['line'] );
				if ( isset( $e['backtrace'] ) ) {
					$error['backtrace'] = $e['backtrace'];
					if ( $html ) {
						echo '<div id="backtrace-' . $i . '" class="backtrace-display" style="display:none;">';
					} else {
						echo 'Error Backtrace: ' . PHP_EOL;
					}
					self::display_backtrace( $e['backtrace'] );
					if ( $html ) {
						echo '</div>';
					}
					$i++;
				}
				$severity[$e['type']][] = $error;
				if ( $html ) {
					echo '</div><br>';
				}
				echo PHP_EOL;
			}
			if ( $html ) {
				echo '</div>';
			}
			echo PHP_EOL;

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
							if ( isset( $e['backtrace'] ) ) {
								echo '<input type="button" class="backtrace-button" value="Backtrace" onclick="triage_showhidebacktrace(\'x' . $j . '\');">';
							}
							echo '<div class="error-line">';
						}
						self::output_error( $type, $e['text'], $e['file'], $e['line'], $e['backtrace'], true );
						if ( isset( $e['backtrace'] ) ) {
							if ( $html ) {
								echo '<div id="backtrace-x' . $j . '" class="backtrace-display" style="display:none;">';
							} else {
								echo 'Error Backtrace: ' . PHP_EOL;
							}
							self::display_backtrace( $e['backtrace'] );
							if ( $html ) {
								echo '</div>';
							}
							$j++;
						}
						if ( $html ) {
							echo '</div><br>';
						}
						echo PHP_EOL;
					}
				}
			}
			if ( $html ) {
				echo '</div>' . PHP_EOL;
				
				// 1.0.8: call output styles function
				self::output_styles();
			}
			echo PHP_EOL;
		}

	}

	// -------------
	// Output Styles
	// -------------
	// 1.0.8: added separate style function
	public static function output_styles() {

		// 1.0.8: added some more simple styling
		$css = '';
		$css .= '.error-display-box {background-color: #DDD; padding: 20px; line-height: 1.2em; font-size: 1em;} ';
		$css .= '.error-line {margin-bottom: 5px; display: inline-block; line-height: 1.4em; max-width: 80%;} ';
		$css .= '#php-error-display, #php-error-severity {padding-bottom: 80px;} ';
		$css .= 'body.wp-admin #php-debug-info, body.wp-admin #php-error-display, body.wp-admin #php-error-severity {margin-left: 150px;} ';
		$css .= 'input.backtrace-button {background: #BBB !important; color: #222 !important; margin-right: 25px; display: inline-block; vertical-align:top;} ';
		$css .= 'input.backtrace-button:hover {background: #CCC !important;} ';
		
		echo "<style>" . $css . "</style>";
	}

	// --------------
	// Throw an Error
	// --------------
	public static function throw_error( $error_text, $error_type = E_USER_NOTICE ) {
		trigger_error( $error_text, $error_type );
	}

}

