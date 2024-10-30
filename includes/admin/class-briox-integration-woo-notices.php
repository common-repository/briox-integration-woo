<?php
/**
 * This class handles notices to admin
 *
 * @package   Briox_Integration_Woo
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2018 BjornTech - Finnvid Innovation AB
 */

defined('ABSPATH') || exit;

if (!class_exists('Briox_integration_Woo_Notice', false)) {

    class Briox_integration_Woo_Notice
    {
        public function __construct()
        {
            add_action('admin_notices', array($this, 'check_displaylist'), 100);
        }

        /**
         * Adds a message to be displayed to the admin
         *
         * @param string  $message The message to be displayed
         * @param string  $type Type of message. Valid variants are 'error' (default), 'warning', 'success', 'info'
         * @param string|boolean  $id An unique id for the message
         * @param boolean  $dismiss 

         * @param boolean  $valid_to The message should be valid until. Set to false (default) if no time limit
         * 
         * @return string An unique id for the message, this can be used to delete it.
         */
        public static function add($message, $type = 'error', $id = false , $dismiss = true, $valid_to = false)
        {

            $notices = get_site_transient('briox_notices');
            if (!$notices) {
                $notices = array();
            }
            $notice = array(
                'type' => $type,
                'valid_to' => $valid_to === false ? false : $valid_to,
                'messsage' => $message,
                'dismissable' => $dismiss,
            );

            $id = $id === false ? uniqid('id-') : 'id-' . esc_html($id);
            $notices[$id] = $notice;

            set_site_transient('briox_notices', $notices);

            return $id;
        }

        public static function clear($id = false)
        {
            $notices = get_site_transient('briox_notices');
            if ($id && isset($notices[$id])) {
                unset($notices[$id]);
            } elseif (!$id) {
                $notices = array();
            }
            set_site_transient('briox_notices', $notices);
        }
     
        public static function display($message, $type = 'error',  $id = '', $dismiss = true)
        {
            $dismissable = $dismiss ? 'is-dismissible' : '';
            echo '<div class="bi_notice ' . $dismissable . ' notice notice-' . $type . ' ' . $id . '"><p>' . $message . '</p></div>';
        }

        public function check_displaylist()
        {
            $notices = get_site_transient('briox_notices');

            if (false !== $notices && !empty($notices)) {
                foreach ($notices as $key => $notice) {
                    self::display($notice['messsage'], $notice['type'], $key , $notice['dismissable']);
                    if ($notice['valid_to'] !== false && $notice['valid_to'] < time()) {
                        unset($notices[$key]);
                    }
                }
            }

            set_site_transient('briox_notices', $notices);
        }

    }

    new Briox_integration_Woo_Notice();
}
