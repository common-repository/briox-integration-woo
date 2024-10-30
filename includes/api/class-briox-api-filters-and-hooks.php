<?php

/**
 * class Briox_API_Hooks_And_Filters
 *
 * Version 1.0
 *
 */

defined('ABSPATH') || exit;

if (!class_exists('Briox_API_Hooks_And_Filters', false)) {

    class Briox_API_Hooks_And_Filters
    {
        private $briox;

        public function __construct()
        {
            add_filter('briox_is_connected', array($this, 'is_connected'));
            add_filter('briox_get_item', array($this, 'get_item'), 10, 2);
            add_filter('briox_update_item', array($this, 'update_item'), 10, 3);
            add_filter('briox_get_pricelist', array($this, 'get_pricelist'));
            add_filter('briox_get_price', array($this, 'get_price'), 10, 3);
            add_filter('briox_get_cost_centers', array($this, 'get_cost_centers'));
            add_filter('briox_get_projects', array($this, 'get_projects'));
            add_filter('briox_get_account_selection', array($this, 'get_account_selection'), 10, 1);
            add_filter('briox_get_payment_methods', array($this, 'get_payment_methods'), 10, 1);
            add_filter('briox_get_terms_of_payments', array($this, 'get_terms_of_payments'), 10, 1);
            add_action('briox_clear_cache', array($this, 'clear_cache'));
        }

        public function is_connected($is_connected)
        {
            return get_option('refresh_token') ? true : false;
        }

        public function get_price($price, $product, $pricelist)
        {
            $item_id = $product->get_sku();
            try {
                $prices = Briox_API::get_prices($item_id, $pricelist);
                $price = $prices['Price'];
            } catch (Briox_API_Exception $e) {
                if (404 != $e->getCode()) {
                    throw new $e($e->getMessage(), $e->getCode(), $e);
                }
            }
            return $price;
        }

        public function get_item($response, $item_id)
        {
            try {
                return Briox_API::get_item($item_id);
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return $response;
        }

        public function update_item($response, $item_id, $item)
        {
            try {
                return Briox_API::update_item($item_id, $item);
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return false;
        }

        public function get_pricelist()
        {
            try {
                $pricelists = get_site_transient('briox_pricelists');
                if (!is_array($pricelists)) {
                    $pricelists = Briox_API::get_pricelist();
                    set_site_transient('briox_pricelists', $pricelists, HOUR_IN_SECONDS);
                }
                return $pricelists;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return false;
        }

        public function get_cost_centers($cost_centers)
        {
            try {
                $cost_centers = get_site_transient('briox_cost_centers');
                if (!is_array($cost_centers)) {
                    $cost_centers = Briox_API::get_all_cost_centers();
                    set_site_transient('briox_cost_centers', $cost_centers, HOUR_IN_SECONDS);
                }
                return $cost_centers;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return $cost_centers;
        }

        public function get_projects($projects)
        {
            try {
                $projects = get_site_transient('briox_projects');
                if (!is_array($projects)) {
                    $projects = Briox_API::get_all_projects();
                    set_site_transient('briox_projects', $projects, HOUR_IN_SECONDS);
                }
                return $projects;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return $projects;
        }

        public function get_account_selection($account_selection)
        {
            try {
                $account_selection[''] = __('Select account', 'briox-integration-woo');
                $accounts = get_site_transient('briox_all_accounts');
                if (!is_array($accounts)) {
                    $accounts = Briox_API::get_all_accounts();
                    set_site_transient('briox_all_accounts', $accounts, HOUR_IN_SECONDS);
                }
                foreach ($accounts as $account) {
                    if ($account['active'] == true) {
                        $account_selection[$account['id']] = ($account['id'] . ' - ' . Briox_integration_Woo_Util::get_language_text($account, 'description'));
                    }
                }
                ksort($account_selection);
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return $account_selection;
        }

        public function get_payment_methods($payment_methods)
        {
            try {
                $payment_methods = get_site_transient('briox_get_payment_methods');
                if (!is_array($payment_methods)) {
                    $payment_methods = Briox_API::get_payment_methods();
                    set_site_transient('briox_get_payment_methods', $payment_methods, HOUR_IN_SECONDS);
                }
                return $payment_methods;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return $payment_methods;
        }

        public function get_terms_of_payments($terms_of_payments)
        {
            try {
                $terms_of_payments = get_site_transient('briox_terms_of_payments');
                if (!is_array($terms_of_payments)) {
                    $terms_of_payments = Briox_API::get_terms_of_payments();
                    set_site_transient('briox_terms_of_payments', $terms_of_payments, HOUR_IN_SECONDS);
                }
                return $terms_of_payments;
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return $terms_of_payments;
        }

        public function clear_cache()
        {
            delete_site_transient('briox_all_accounts');
            delete_site_transient('briox_pricelists');
            delete_site_transient('briox_projects');
            delete_site_transient('briox_cost_centers');
            delete_site_transient('briox_get_payment_methods');
            delete_site_transient('briox_terms_of_payments');
        }
    }

    new Briox_API_Hooks_And_Filters();
}
