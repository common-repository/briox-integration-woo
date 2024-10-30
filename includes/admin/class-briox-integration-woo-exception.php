<?php

defined('ABSPATH') || exit;

if (!class_exists('Briox_Exception', false)) {
    class Briox_Exception extends Exception
    {
        /**
         * Contains a log object instance
         * @access protected
         */
        protected $log;

        /**
         * Contains the object instance
         * @access protected
         */
        protected $request_data;

        /**
         * Contains the url
         * @access protected
         */
        protected $request_url;

        /**
         * Contains the response data
         * @access protected
         */
        protected $response_data;

        /**
         * __Construct function.
         *
         * Redefine the exception so message isn't optional
         *
         * @access public
         * @return void
         */
        public function __construct($message, $code = 0, Exception $previous = null, $request_url = '', $request_data = '', $response_data = '')
        {
            // make sure everything is assigned properly
            parent::__construct($message, $code, $previous);

            $this->request_data = $request_data;
            $this->request_url = $request_url;
            $this->response_data = $response_data;
        }

        /**
         * write_to_logs function.
         *
         * Stores the exception dump in the WooCommerce system logs
         *
         * @access public
         * @return void
         */
        public function write_to_logs()
        {
            Briox_Integration_Woo_Logger::separator('error');
            Briox_Integration_Woo_Logger::log('error', 'Briox Exception file: ' . $this->getFile(), true);
            Briox_Integration_Woo_Logger::log('error', 'Briox Exception line: ' . $this->getLine(), true);
            Briox_Integration_Woo_Logger::log('error', 'Briox Exception code: ' . $this->getCode(), true);
            Briox_Integration_Woo_Logger::log('error', 'Briox Exception message: ' . $this->getMessage(), true);
            Briox_Integration_Woo_Logger::separator('error');
        }

        /**
         * write_standard_warning function.
         *
         * Prints out a standard warning
         *
         * @access public
         * @return void
         */
        public function write_standard_warning()
        {

            wp_kses(
                __("An error occured. For more information check out the <strong>briox-integration-woo</strong> logs inside <strong>WooCommerce -> System Status -> Logs</strong>.", 'briox-integration-woo'), array('strong' => array())
            );

        }
    }

}

if (!class_exists('Briox_API_Exception', false)) {
    class Briox_API_Exception extends Briox_Exception
    {
        /**
         * write_to_logs function.
         *
         * Stores the exception dump in the WooCommerce system logs
         *
         * @access public
         * @return void
         */
        public function write_to_logs()
        {
            Briox_Integration_Woo_Logger::separator('error');
            Briox_Integration_Woo_Logger::log('error', 'Briox API Exception file: ' . $this->getFile(), true);
            Briox_Integration_Woo_Logger::log('error', 'Briox API Exception line: ' . $this->getLine(), true);
            Briox_Integration_Woo_Logger::log('error', 'Briox API Exception code: ' . $this->getCode(), true);
            Briox_Integration_Woo_Logger::log('error', 'Briox API Exception message: ' . $this->getMessage(), true);

            if (!empty($this->request_url)) {
                Briox_Integration_Woo_Logger::log('error', 'Briox API Exception Request URL: ' . $this->request_url, true);
            }

            if (!empty($this->request_data)) {
                Briox_Integration_Woo_Logger::log('error', 'Briox API Exception Request DATA: ' . $this->request_data, true);
            }

            if (!empty($this->response_data)) {
                Briox_Integration_Woo_Logger::log('error', 'Briox API Exception Response DATA: ' . $this->response_data, true);
            }

            Briox_Integration_Woo_Logger::separator('error');

        }
    }
}
