<?php
// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Ensure logs directory exists
$log_dir = ALIGN_DIR_PATH . 'logs';
if (!file_exists($log_dir)) {
    wp_mkdir_p($log_dir);
    // Protect logs directory from direct web access
    file_put_contents($log_dir . '/.htaccess', "Order Deny,Allow\nDeny from all");
}

define('ALIGN_LOG_FILE', $log_dir . '/theme-errors.log');

/**
 * Check whether a file path belongs to the Align theme (OS-agnostic: normalizes Windows backslashes).
 */
function align_logger_is_theme_file($file) {
    return strpos(str_replace('\\', '/', (string) $file), 'themes/align') !== false;
}

/**
 * Align Theme Custom Logger
 * Write logs in a beautiful and clear format.
 */
function align_theme_logger($message, $level = 'ERROR', $file = '', $line = '') {
    // Avoid timezone errors if WP has not finished loading
    $date = function_exists('current_time') ? current_time('Y-m-d H:i:s') : date('Y-m-d H:i:s');
    
    $level = strtoupper($level);

    $log_entry  = str_repeat("-", 80) . PHP_EOL;
    $log_entry .= "[{$date}] [{$level}]" . PHP_EOL;
    $log_entry .= "Message : {$message}" . PHP_EOL;
    if ($file) {
        $log_entry .= "File    : {$file}" . PHP_EOL;
        $log_entry .= "Line    : {$line}" . PHP_EOL;
    }
    $log_entry .= str_repeat("-", 80) . PHP_EOL . PHP_EOL;

    error_log($log_entry, 3, ALIGN_LOG_FILE);
}

/**
 * Custom Error Handler (Catches Warnings, Notices, Deprecated...)
 */
function align_error_handler($errno, $errstr, $errfile, $errline) {
    // Ignore errors suppressed by @
    if (!(error_reporting() & $errno)) {
        return false;
    }

    $level = 'ERROR';
    switch ($errno) {
        case E_WARNING:
        case E_CORE_WARNING:
        case E_COMPILE_WARNING:
        case E_USER_WARNING:
            $level = 'WARNING';
            break;
        case E_NOTICE:
        case E_USER_NOTICE:
            $level = 'NOTICE';
            break;
        case E_DEPRECATED:
        case E_USER_DEPRECATED:
            $level = 'DEPRECATED';
            break;
    }

    // Only log errors belonging to the Align theme (to avoid logging errors from WP core or other plugins)
    if (align_logger_is_theme_file($errfile)) {
        align_theme_logger($errstr, $level, $errfile, $errline);
    }

    // Return false so WordPress can still write logs to the default debug.log (if WP_DEBUG is enabled)
    return false;
}
set_error_handler('align_error_handler');

/**
 * Custom Exception Handler (Catches uncaught exceptions)
 */
function align_exception_handler($exception) {
    $file = $exception->getFile();
    if (align_logger_is_theme_file($file)) {
        align_theme_logger($exception->getMessage(), 'UNCAUGHT EXCEPTION', $file, $exception->getLine());
    }
    // Always forward to the default PHP error log so uncaught errors are never swallowed silently.
    error_log(sprintf('[Align Core] Uncaught %s: %s in %s:%d', get_class($exception), $exception->getMessage(), $file, $exception->getLine()));
}
set_exception_handler('align_exception_handler');

/**
 * Fatal Error Catcher (Catches fatal errors, parse errors, etc.)
 */
function align_fatal_error_catcher() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        if (align_logger_is_theme_file($error['file'])) {
            align_theme_logger($error['message'], 'FATAL ERROR', $error['file'], $error['line']);
        }
    }
}
register_shutdown_function('align_fatal_error_catcher');
