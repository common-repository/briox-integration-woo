<?php

/**
 * This class contains function to create Briox Customers
 *
 * @package   Briox_Integration_Woo
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2019 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Briox_Integration_Woo_Customer_Handler', false)) {

    class Briox_Integration_Woo_Customer_Handler
    {

        public function __construct()
        {
            add_action('woo_briox_integration_create_customer_invoice', array($this, 'briox_create_customer'));
            add_action('woo_briox_integration_create_customer_order', array($this, 'briox_create_customer'));
            add_action('woo_briox_integration_create_customer', array($this, 'briox_create_customer'));
        }

        public function add_settings_section($sections)
        {
            if (!array_key_exists('customers', $sections)) {
                $sections = array_merge($sections, array('customers' => __('Customers', 'briox-integration-woo')));
            }
            return $sections;
        }

        public function save_settings_section($true)
        {
            return $true;
        }

        public function get_settings($settings, $current_section)
        {
            if ('customers' === $current_section) {

                $settings = array(
                    array(
                        'title' => __('Customer options', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_customers_options',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_customers_options',
                    ),
                );

            }
            return $settings;
        }

        public function billing_details($order, $order_id, $email = false, $new = true)
        {
            $data = array();
            if ($new || (!$new && 'yes' != get_option('briox_do_not_update_customer_billing'))) {
                $base_country = WC_Countries::get_base_country();
                $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

                if ($company = $order->get_billing_company()) {
                    $billing_name = $company;
                    $customerbusinesstype = 1;
                } else {
                    $billing_name = $customer_name;
                    $customerbusinesstype = 0;
                }

                $data = array_merge($data, array(
                    "name" => $billing_name,
                    "address" => array(
                        "type" => "invoice",
                        "addressline1" => $order->get_billing_address_1(),
                        "addressline2" => $order->get_billing_address_2(),
                        "zip" => $order->get_billing_postcode(),
                        "city" => $order->get_billing_city(),
                        "countrycode" => $order->get_billing_country(),
                    ),
                    "edocument" => array(
                        "type" => "invoice",
                        "email" => $email,
                    ),
                    "phone" => $order->get_billing_phone(),
                    "email" => $email,
                    "currency" => $order->get_currency(),
                    "includevat" => (0 == $customerbusinesstype ? '1' : '0'),
                    "customerbusinesstype" => $customerbusinesstype,
                    "customerref" => $customer_name,
                ));

                // Not impolemented: Domestic construction VAT = 1, Tax border(FINLAND ONLY) = 5
                if ($base_country != $order->get_billing_country() && 'true' == get_post_meta($order_id, '_vat_number_is_validated', true)) {
                    $data['customertype'] = '2'; // EU reverse charge VAT = 2
                } elseif ($base_country == $order->get_billing_country()) {
                    $data['customertype'] = '0'; // Domestic VAT = 0,
                } elseif (Briox_integration_Woo_Util::is_eu($order)) {
                    $data['customertype'] = '3'; // EU VAT = 3
                } else {
                    $data['customertype'] = '3'; // Export VAT = 4
                }

                if (get_post_meta($order_id, '_vat_number', true)) {
                    $data["vatnumber"] = $vat_number;
                }
            }
            return $data;

        }

        public function delivery_details($order, $order_id, $new = true)
        {
            $data = array();
            if ($order->get_formatted_shipping_address()) {
                if ($new || (!$new && 'yes' != get_option('briox_do_not_update_customer_delivery'))) {

                    $shipping_person = $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();

                    if ($shipping_company = $order->get_shipping_company()) {
                        $shipping_to = $shipping_company . ', ' . __('Att:', 'briox-integration-woo') . ' ' . $shipping_person;
                    } else {
                        $shipping_to = $shipping_person;
                    }

                    $data = array_merge($data, array(
                        "deliveryname" => $shipping_to,
                        "deladdress" => array(
                            "type" => "delivery",
                            "name" => $shipping_to,
                            "addressline1" => $order->get_shipping_address_1(),
                            "addressline2" => $order->get_shipping_address_2(),
                            "zip" => $order->get_shipping_postcode(),
                            "city" => $order->get_shipping_city(),
                            "countrycode" => $order->get_shipping_country(),
                        ),
                    ));
                }
            }
            return $data;
        }

        public function briox_create_customer($order_id)
        {
            try {

                $order = wc_get_order($order_id);

                $email = apply_filters('briox_customer_email', $order->get_billing_email(), $order_id);

                Briox_Integration_Woo_Logger::log('debug',sprintf('Searching for customer %s by email in Briox', $email));

                try {
                    $customers = Briox_API::get_customers_by_email($email);
                    $customer = $customers[0];
                    Briox_Integration_Woo_Logger::log('debug',sprintf('Briox customer %s found', $customer['custno']));
                } catch (Briox_API_Exception $e) {
                    if (400 != $e->getCode()) {
                        throw new $e($e->getMessage(), $e->getCode(), $e);
                    }
                    Briox_Integration_Woo_Logger::log('debug',sprintf('Briox customer not found.'));
                    $customer = false;
                }

                $customer_data = array_merge($this->delivery_details($order, $order_id, false === $customer), $this->billing_details($order, $order_id, $email, false === $customer));

                $customer_data = Briox_integration_Woo_Util::remove_blanks(apply_filters('briox_customer_data_before_processing', $customer_data, $order_id));

                if (false === $customer) {
                    $customer = Briox_API::create_customer($customer_data);
                    Briox_Integration_Woo_Logger::log('debug',sprintf('Created Briox customer %s', $customer['custno']));
                } elseif ($customer_data) {
                    $customer = Briox_API::update_customer($customer['custno'], $customer_data);
                    Briox_Integration_Woo_Logger::log('debug',sprintf('Updated customer %s', $customer['custno']));
                }

                Briox_integration_Woo_Util::set_briox_customer_number($order_id, $customer['custno']);

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
        }

    }

    new Briox_Integration_Woo_Customer_Handler();
}
