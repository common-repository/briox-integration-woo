<?php

/**
 * The main plugin file for Woo Briox Integration.
 *
 * This file is included during the WordPress bootstrap process if the plugin is active.
 *
 * @package   Briox_Integration_Woo
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      https://bjorntech.com
 * @copyright 2019 Finnvid Innovation AB
 *
 * @wordpress-plugin
 * Plugin Name:       Briox Integration Woo
 * Plugin URI:        https://www.bjorntech.com/briox-integration
 * Description:       Sync your WooCommerce shop with Briox
 * Version:           1.0.0
 * Author:            BjornTech
 * Author URI:        https://bjorntech.com
 * Text Domain:       briox-integration-woo
 * Domain Path:       /languages
 *
 * WC requires at least: 4.0
 * WC tested up to: 4.4
 *
 * Copyright:         2020 BjornTech
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('ABSPATH') || exit;

/**
 *    Briox_Integration_Woo
 */

if (!class_exists('Briox_Integration_Woo', false)) {
    class Briox_Integration_Woo
    {
        /**
         * Plugin data
         */
        const NAME = 'Woo Briox Integration';
        const VERSION = '1.0.0';
        const SCRIPT_HANDLE = 'briox-integration-woo';
        const PLUGIN_FILE = __FILE__;

        public $plugin_basename;
        public $includes_dir;
        public $admin_url;

        /**
         * Plugin helper classes
         */
        public $briox;
        public $plugin_version;

        /**
         * Static class instance.
         *
         * @var null|Briox_Integration_Woo
         */
        public static $instance = null;

        /**
         * Init and hook in the integration.
         **/
        public function __construct()
        {
            $this->plugin_basename = plugin_basename(self::PLUGIN_FILE);
            $this->includes_dir = plugin_dir_path(self::PLUGIN_FILE) . 'includes/';
            $this->vendor_dir = plugin_dir_path(self::PLUGIN_FILE) . 'vendor/';
            $this->admin_url = trailingslashit(plugins_url('admin', self::PLUGIN_FILE));
            $this->plugin_version = self::VERSION;

            $this->includes();

            add_action('plugins_loaded', array($this, 'maybe_load_plugin'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
            add_filter('woocommerce_get_settings_pages', array($this, 'include_settings'));
            add_action('woocommerce_admin_field_infotext', array($this, 'show_infotext'), 10);
            add_action('woocommerce_api_briox_admin', array($this, 'admin_callback_handler'));

            //admin
            if (is_admin()) {
                add_action('wp_ajax_briox_clear_notice', array($this, 'ajax_clear_notice'));
                add_action('admin_enqueue_scripts', array($this, 'add_admin_styles_and_scripts'));
                add_action('wp_ajax_briox_sync_all', array($this, 'ajax_sync_products'));
                add_action('wp_ajax_briox_clear_cache', array($this, 'ajax_clear_cache'));
                add_action('wp_ajax_briox_connection', array($this, 'ajax_briox_connection'));
                add_action('wp_ajax_briox_check_activation', array($this, 'ajax_briox_check_activation'));
                add_action('in_admin_header', array($this, 'briox_modal_admin'));
            }
        }

        public function include_settings($settings)
        {
            $settings[] = include $this->includes_dir . 'admin/class-briox-integration-woo-settings.php';
            return $settings;
        }

        private function includes()
        {
            require_once $this->includes_dir . 'admin/class-briox-integration-woo-util.php';
        }

        public function maybe_load_plugin()
        {
            if (!class_exists('WooCommerce')) {
                return;
            }

            require_once $this->includes_dir . 'api/class-briox-api.php';
            require_once $this->includes_dir . 'admin/class-briox-integration-woo-exception.php';
            require_once $this->includes_dir . 'admin/class-briox-integration-woo-logger.php';
            require_once $this->includes_dir . 'admin/class-briox-integration-woo-notices.php';

            add_action('init', array($this, 'init'));
        }

        /**
         * Add Admin JS
         */
        public function add_admin_styles_and_scripts()
        {
            wp_register_style('briox-integration', plugin_dir_url(__FILE__) . 'assets/css/briox.css', array(), $this->plugin_version);
            wp_enqueue_style('briox-integration');

            wp_enqueue_script('briox-integration-woo-admin-script', plugins_url('/admin/js/admin.js', __FILE__), ['jquery'], $this->plugin_version, true);

            wp_localize_script('briox-integration-woo-admin-script', 'briox', array(
                'nonce' => wp_create_nonce('ajax-briox-integration'),
                'redirect_warning' => __('I agree to the BjornTech Privacy Policy', 'briox-integration-woo'),
                'email_warning' => __('Enter mail and save before connecting to the service', 'briox-integration-woo'),
                'sync_message' => __('Number of days back to sync.', 'briox-integration-woo'),
                'sync_warning' => __('Please enter the number of days back you want to sync.', 'briox-integration-woo'),
            ));
        }

        public function init()
        {

            require_once $this->includes_dir . 'class-briox-integration-woo-customer-handler.php';
            require_once $this->includes_dir . 'class-briox-integration-woo-document-creation-handler.php';
            require_once $this->includes_dir . 'api/class-briox-api-filters-and-hooks.php';
            require_once $this->includes_dir . 'class-briox-integration-woo-wc-product-handler.php';
            require_once $this->includes_dir . 'class-briox-integration-woo-price-stocklevel-handler.php';

            if (!get_site_transient('briox_integration_activated_or_upgraded')) {
                update_option('token_expiry', 0);
                Briox_integration_Woo_Notice::clear();
                set_site_transient('briox_integration_activated_or_upgraded', date('c'));
            }

        }

        public function ajax_clear_notice()
        {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'ajax-briox-integration')) {
                wp_die();
            }

            $parents = sanitize_text_field($_POST['parents']);
            if (isset($parents)) {
                $id = substr($parents, strpos($parents, 'id-'));
                Briox_Integration_Woo_Logger::log('debug',sprintf('Clear notice %s', $id));
                Briox_integration_Woo_Notice::clear($id);
            }

            $response = array(
                'status' => 'success',
            );

            wp_send_json($response);
            exit;
        }

        public static function add_action_links($links)
        {
            $links = array_merge(array(
                '<a href="' . admin_url('admin.php?page=wc-settings&tab=briox_integration') . '">' . __('Settings', 'briox-integration-woo') . '</a>',
            ), $links);

            return $links;
        }

        public function briox_modal_admin()
        {?>
            <div id="briox-modal-id" class="briox-modal" style="display: none">
                <div class="briox-modal-content briox-centered">
                    <span class="briox-close">&times;</span>
                    <div class="briox-messages briox-centered">
                        <h1><p id="briox-status"></p></h1>
                    </div>
                    <div class="bjorntech-logo briox-centered">
                        <img id="briox-logo-id" class="briox-centered" src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/BjornTech_logo_small.png'; ?>" />
                    </div>
                </div>
            </div>
        <?php }

        public function ajax_briox_check_activation()
        {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'ajax-briox-integration')) {
                wp_die();
            }

            $message = '';
            if (get_site_transient('briox_handle_account')) {
                if ($connected = get_site_transient('briox_connect_result')) {
                    delete_site_transient('briox_handle_account');
                    delete_site_transient('briox_connect_result');
                    if ($connected == 'failure') {
                        $message = __('The activation of the account failed', 'briox-integration-woo');
                    }
                } else {
                    $message = __('We have sent a mail with the activation link. Click on the link to activate the service.', 'briox-integration-woo');
                }
            } else {
                $connected = 'failure';
                $message = __('The link has expired, please connect again to get a new link.', 'briox-integration-woo');
            }

            $response = array(
                'status' => $connected ? $connected : 'waiting',
                'message' => $message,
            );

            wp_send_json($response);
            wp_die();
        }

        public function show_infotext($value)
        {
            echo '<div id="' . esc_attr($value['id']) . '">';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            echo '<label for="' . esc_attr($value['id']) . '">' . esc_html($value['title']) . wc_help_tip($value['desc']) . '</label>';
            echo '</th>';
            echo '<td class="' . esc_attr(sanitize_title($value['id'])) . '-description">';
            echo wp_kses_post(wpautop(wptexturize($value['text'])));
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        public function admin_callback_handler()
        {
            if (array_key_exists('nonce', $_REQUEST) && sanitize_text_field($_REQUEST['nonce']) == get_site_transient('briox_handle_account')) {
                if (array_key_exists('client_identifier', $_REQUEST) && sanitize_text_field($_REQUEST['client_identifier']) == get_option('briox_client_identifier')) {
                    $request_body = file_get_contents("php://input");
                    $json = json_decode($request_body);
                    if ($json !== null && json_last_error() === JSON_ERROR_NONE) {
                        $refresh_token = sanitize_text_field($json->refresh_token);
                        update_option('refresh_token', $refresh_token);
                        update_option('briox_valid_to', sanitize_text_field($json->valid_to));
                        delete_option('briox_access_token');
                        delete_option('token_expiry');
                        Briox_Integration_Woo_Logger::log('debug',sprintf('Got refresh token %s from service', $refresh_token));
                        set_site_transient('briox_connect_result', 'success', MINUTE_IN_SECONDS);
                        wp_die('', '', 200);
                    } else {
                        Briox_Integration_Woo_Logger::log('debug','Failed decoding authorize json');
                    }
                } else {
                    Briox_Integration_Woo_Logger::log('debug','Faulty call to admin callback');
                }
            } else {
                Briox_Integration_Woo_Logger::log('debug','Nonce not verified at admin_callback_handler');
            }
            set_site_transient('briox_connect_result', 'failure', MINUTE_IN_SECONDS);
            wp_die();
        }

        public function ajax_sync_products()
        {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'ajax-briox-integration')) {
                wp_die();
            }

            do_action('briox_sync_price_stocklevel_start', 'all');

            $response = array(
                'result' => 'success',
                'message' => __('Your selection of Briox articles have been added to the updating queue and updating of WooCommerce has been started', 'briox-integration-woo'),
            );

            wp_send_json($response);
        }

        public function ajax_clear_cache()
        {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'ajax-briox-integration')) {
                wp_die();
            }

            do_action('briox_clear_cache');

            delete_option('briox_access_token');
            delete_option('token_expiry');
            delete_option('briox_valid_to');

            Briox_integration_Woo_Notice::clear();

            $response = array(
                'result' => 'success',
                'message' => __('The cache holding Briox data has been cleared.', 'briox-integration-woo'),
            );

            wp_send_json($response);
        }

        private function sanatize_fields($key)
        {
            if (false === strpos($key, 'email')) {
                $new_option = sanitize_text_field($_POST[$key]);
            } else {
                $new_option = sanitize_email($_POST[$key]);
            }
            return $new_option;
        }

        public function ajax_briox_connection()
        {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'ajax-briox-integration')) {
                wp_die();
            }

            $id = sanitize_text_field($_POST['id']);

            $options = array(
                'client_identifier' => __('client identifier', 'briox-integration-woo'),
                'authentication_token' => __('authentication token', 'briox-integration-woo'),
                'user_email' => _('email adress', 'briox-integration-woo'),
            );

            if ('briox_connect' == $id) {

                foreach ($options as $key => $option) {

                    $current_option = get_option('briox_' . $key);

                    $new_option = $this->sanatize_fields($key);

                    if (!$new_option) {
                        $response = array(
                            'result' => 'error',
                            'message' => sprintf(__('A valid %s must be present.', 'briox-integration-woo'), $option),
                        );
                    } elseif ($current_option != $new_option) {
                        $response = array(
                            'result' => 'error',
                            'message' => sprintf(__('You must save the new %s before connecting.', 'briox-integration-woo'), $option),
                        );
                    }
                }

                if (!$response) {

                    foreach ($options as $key => $option) {
                        $new_option = $this->sanatize_fields($key);
                        update_option('briox_' . $key, $new_option);
                    }

                    $nonce = wp_create_nonce('briox_handle_account');
                    set_site_transient('briox_handle_account', $nonce, DAY_IN_SECONDS);

                    $url = 'https://' . Briox_API::service_url() . 'connect?' . http_build_query(array(
                        'user_email' => $user_email,
                        'plugin_version' => $this->plugin_version,
                        'client_identifier' => $client_identifier,
                        'authentication_token' => $authentication_token,
                        'site_url' => $site_url,
                        'nonce' => $nonce,
                    ));

                    $sw_response = wp_remote_get($url, array('timeout' => 20));

                    if (is_wp_error($sw_response)) {
                        $code = $sw_response->get_error_code();
                        $error = $sw_response->get_error_message($code);
                        $response_body = json_decode(wp_remote_retrieve_body($sw_response));
                        $response = array(
                            'result' => 'error',
                            'message' => __('Something went wrong when connecting to the BjornTech service. Contact support at hello@bjorntech.com', 'briox-integration-woo'),
                        );
                        Briox_Integration_Woo_Logger::log('debug',sprintf('Failed connecting the plugin to the service %s - %s', print_r($code, true), print_r($error, true)));
                    } else {
                        $response_body = json_decode(wp_remote_retrieve_body($sw_response));
                        $response_code = wp_remote_retrieve_response_code($response);
                        if ($response_body) {
                            $response = (array) $response_body;
                        }

                    }
                }

            } elseif ('briox_disconnect' == $id) {

                delete_option('refresh_token');
                delete_option('briox_access_token');
                delete_option('token_expiry');
                delete_option('briox_valid_to');

                $response = array(
                    'result' => 'success',
                    'message' => __('Successfully disconnected from Briox', 'briox-integration-woo'),
                );

            }

            wp_send_json($response);

        }

        /**
         * Get a singelton instance of the class.
         *
         * @return Briox_Integration_Woo
         */
        public static function get_instance()
        {
            if (null === self::$instance) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        private function is_front_end()
        {
            return !is_admin() || defined('DOING_AJAX');
        }
    }
}

Briox_Integration_Woo::get_instance();

/**
 * Activation activities to be performed then the plugin is activated
 */
function briox_integration_woo_activate()
{

    delete_site_transient('briox_integration_activated_or_upgraded');

}

register_activation_hook(__FILE__, 'briox_integration_woo_activate');

/**
 * Upgrade activities to be performed when the plugin is upgraded
 */
function briox_integration_woo_upgrade_completed($upgrader_object, $options)
{
    $our_plugin = plugin_basename(__FILE__);

    if ($options['action'] == 'update' && $options['type'] == 'plugin' && isset($options['plugins'])) {
        foreach ($options['plugins'] as $plugin) {
            if ($plugin == $our_plugin) {

                /**
                 * Delete transient containing the date for activation or upgrade
                 */
                delete_site_transient('briox_integration_activated_or_upgraded');
            }
        }
    }
}
add_action('upgrader_process_complete', 'briox_integration_woo_upgrade_completed', 10, 2);
