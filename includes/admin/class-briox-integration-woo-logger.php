<?php
/**
 * Briox_Integration_Woo_Logger class
 *
 * @class         Briox_Integration_Woo_Logger
 * @package        Woocommerce_Briox/Classes
 * @category    Logs
 */

defined('ABSPATH') || exit;

if (!class_exists('Briox_Integration_Woo_Log', false)) {

    class Briox_Integration_Woo_Logger
    {

        private static $logger;
        private static $log_all;
        private static $handle = 'briox-integration-woo';

        /**
         * Log
         *
         * @param string $level
         * One of the following:
         * 'emergency': System is unusable.
         * 'alert': Action must be taken immediately.
         * 'critical': Critical conditions.
         * 'error': Error conditions.
         * 'warning': Warning conditions.
         * 'notice': Normal but significant condition.
         * 'info': Informational messages.
         * 'debug': Debug-level messages.
         *
         * @param string|arrray $message
         */
        public static function log($level, $message, $force = false, $wp_debug = false)
        {
            if (empty(self::$logger)) {
                self::$logger = wc_get_logger();
                self::$log_all = ('yes' != get_option('briox_logging')) ? false : true;
            }

            if (true === self::$log_all || true === $force) {

                if (is_array($message)) {
                    $message = print_r($message, true);
                }

                self::$logger->add(
                    self::$handle,
                    $message,
                    $level
                );

                if (true === $wp_debug && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log($message);
                }
            }
        }

        /**
         * separator function.
         *
         * Inserts a separation line for better overview in the logs.
         *
         * @access public
         * @return void
         */
        public static function separator($level)
        {
            self::log($level, '--------------------');
        }

    }

}
