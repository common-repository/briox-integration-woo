<?php

/**
 * This class contains common functions for creating invoices and orders
 *
 * @package   Briox_Integration_Woo
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2019 BjornTech
 */

// Prevent direct file access
defined('ABSPATH') || exit;

if (!class_exists('WP_Async_Request', false)) {
    include_once plugin_dir_path(WC_PLUGIN_FILE) . 'includes/libraries/wp-async-request.php';
}

if (!class_exists('WP_Background_Process', false)) {
    include_once plugin_dir_path(WC_PLUGIN_FILE) . 'includes/libraries/wp-background-process.php';
}

if (!class_exists('Briox_Integration_Woo_Document_Sync', false)) {

    class Briox_Integration_Woo_Document_Sync extends WP_Background_Process
    {

        protected $action = 'document_sync';

        protected function task($order_object)
        {
            try {
                $action = 'woo_briox_integration_' . $order_object->type . '_' . $order_object->document;
                Briox_Integration_Woo_Logger::log('debug',sprintf('WooCommerce order id %s is executing "%s"', $order_object->order_id, $action));
                if ('refunded' == $order_object->type) {
                    do_action($action, $order_object->order_id, $order_object->refund_id);
                } else {
                    do_action($action, $order_object->order_id);
                }
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
            return false;
        }

        protected function complete()
        {
            parent::complete();
            Briox_Integration_Woo_Logger::log('debug','Invoice processing completed');
        }

    }

}

require_once plugin_dir_path(self::PLUGIN_FILE) . 'includes/class-briox-integration-woo-document-creation.php';

if (!class_exists('Briox_Integration_Woo_Document_Handler', false)) {

    class Briox_Integration_Woo_Document_Handler extends Briox_Integration_Woo_Document_Creation
    {

        public $briox_sync_documents;

        public function __construct()
        {

            /**
             * Actions initiated by order-statuses from WooCommerce
             */
            if ($order_status = get_option('briox_woo_order_create_automatic_from')) {

                add_action('woocommerce_order_status_' . $order_status, array($this, 'create_customer'), 20);
                add_action('woocommerce_order_status_' . $order_status, array($this, 'processing_document'), 30);
                add_action('woocommerce_order_status_cancelled', array($this, 'cancelled_document'), 20);
                add_action('woocommerce_order_status_completed', array($this, 'completed_document'), 40);
                add_action('woocommerce_order_fully_refunded', array($this, 'refunded_document'), 20, 2);
            }

            /**
             * Invoce actions
             */
            add_action('woo_briox_integration_processing_invoice', array($this, 'briox_processing_invoice'));
            add_action('woo_briox_integration_cancelled_invoice', array($this, 'briox_cancelled_invoice'));
            add_action('woo_briox_integration_refunded_invoice', array($this, 'briox_refunded_invoice'), 10, 2);

            /**
             * Order actions
             */
            add_action('woo_briox_integration_processing_order', array($this, 'briox_processing_order'));
            add_action('woo_briox_integration_cancelled_order', array($this, 'briox_cancelled_order'));
            add_action('woo_briox_integration_finish_order', array($this, 'briox_finish_order'));

            /**
             * Adding columns inclusive Sync button order list page
             */
            add_filter('manage_edit-shop_order_columns', array($this, 'briox_document_number_header'), 20);
            add_action('manage_shop_order_posts_custom_column', array($this, 'briox_invoice_number_content'));
            add_action('wp_ajax_briox_sync', array($this, 'button_sync'));

            /**
             * Initiate sync function
             */
            $this->briox_sync_documents = new Briox_Integration_Woo_Document_Sync();

        }

        public function briox_document_number_header($columns)
        {

            $new_columns = array();

            foreach ($columns as $column_name => $column_info) {

                $new_columns[$column_name] = $column_info;

                if ('order_number' === $column_name && ($creates = get_option('briox_woo_order_creates'))) {
                    if ('order' == $creates) {
                        $new_columns['briox_order_number'] = __('Briox Order/Invoice', 'briox-integration-woo');
                    } elseif ('invoice' == $creates) {
                        $new_columns['briox_invoice_number'] = __('Briox Invoice', 'briox-integration-woo');
                    }
                    $new_columns['briox_sync_document'] = __('Briox', 'briox-integration-woo');
                }
            }
            return $new_columns;
        }

        public function briox_invoice_number_content($column)
        {
            global $post;

            if ('briox_invoice_number' == $column || 'briox_order_number' == $column) {
                $briox_invoice = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($post->ID);

                if ('briox_invoice_number' == $column) {
                    echo sprintf('%s', $briox_invoice ? $briox_invoice : '-');
                }

                if ('briox_order_number' == $column) {
                    $fn_order = Briox_integration_Woo_Util::get_briox_order_documentnumber($post->ID);
                    echo sprintf('%s/%s', $fn_order ? $fn_order : '-', $briox_invoice ? $briox_invoice : '-');
                }
            }

            if ('briox_sync_document' === $column) {
                $order = wc_get_order($post->ID);
                $order_status = $order->get_status();
                $briox_document = '';
                if ($this->wc_order_creates_order($order)) {
                    $briox_document = Briox_integration_Woo_Util::get_briox_order_documentnumber($post->ID);
                } else {
                    $briox_document = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($post->ID);
                }

                if (('cancelled' != $order_status && 'refunded' != $order_status && 'completed' != $order_status) || ('completed' == $order_status && !$briox_document)) {
                    if ($briox_document) {
                        echo '<a class="button button wc-action-button briox sync" data-order-id="' . esc_html($order->get_id()) . '">Resync</a>';
                    } else {
                        echo '<a class="button button wc-action-button briox sync" data-order-id="' . esc_html($order->get_id()) . '">Sync</a>';
                    }
                }
            }
        }

        public function get_empty_rows($order)
        {
            $rows[$this->document_type($order, '_rows')] = '';
            return $rows;
        }

        public function document_type($order, $type)
        {
            if ($this->wc_order_creates_order($order)) {
                return 'order' . $type;
            }
            return 'invoice' . $type;
        }

        public function wc_order_creates_order($order)
        {
            $creates = get_option('briox_woo_order_creates');
            if ('order' == $creates && !Briox_integration_Woo_Util::is_izettle($order)) {
                return true;
            }
            return false;
        }

        public function create_customer($order_id)
        {
            Briox_Integration_Woo_Logger::log('debug',sprintf('Create customer called for order id %s', $order_id));
            $this->add_to_queue(__FUNCTION__, 'create_customer', $order_id);
        }

        public function processing_document($order_id)
        {
            $this->add_to_queue(__FUNCTION__, 'processing', $order_id);
        }

        public function cancelled_document($order_id)
        {
            $this->add_to_queue(__FUNCTION__, 'cancelled', $order_id);
        }

        public function refunded_document($order_id, $refund_id)
        {
            $this->add_to_queue(__FUNCTION__, 'refunded', $order_id, $refund_id);
        }

        public function completed_document($order_id)
        {
            $order = wc_get_order($order_id);
            if ($this->wc_order_creates_order($order)) {
                if (($briox_order = Briox_integration_Woo_Util::get_briox_order_documentnumber($order_id)) === false) {
                    $this->add_to_queue(__FUNCTION__, 'create_customer', $order_id);
                    $this->add_to_queue(__FUNCTION__, 'processing', $order_id);
                }
                if (!($briox_invoice = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($order_id))) {
                    $this->add_to_queue(__FUNCTION__, 'finish', $order_id);
                } else {
                    Briox_Integration_Woo_Logger::log('debug',sprintf('WooCommerce order %s already created Briox invoice %s', $order_id, $briox_invoice));
                }
            } else {
                if (!Briox_integration_Woo_Util::get_briox_invoice_documentnumber($order_id)) {
                    $this->add_to_queue(__FUNCTION__, 'create_customer', $order_id);
                    $this->add_to_queue(__FUNCTION__, 'processing', $order_id);
                }
            }
        }

        public function add_to_queue($function, $type, $order_id, $refund_id = false)
        {
            Briox_Integration_Woo_Logger::log('debug',sprintf('Add order id %s from function %s with type %s to queue', $order_id, $function, $type));
            $order = wc_get_order($order_id);
            if ($this->wc_order_creates_order($order)) {
                $queue_object = (object) array(
                    'function' => $function,
                    'type' => $type,
                    'document' => 'order',
                    'order_id' => $order_id,
                    'refund_id' => $refund_id,
                );
                $this->briox_sync_documents->push_to_queue($queue_object);
                $this->briox_sync_documents->save()->dispatch();
            } else {
                $queue_object = (object) array(
                    'function' => $function,
                    'type' => $type,
                    'document' => 'invoice',
                    'order_id' => $order_id,
                    'refund_id' => $refund_id,
                );
                $this->briox_sync_documents->push_to_queue($queue_object);
                $this->briox_sync_documents->save()->dispatch();
            }
        }

        public function get_merchandise_account($order, $item)
        {
            $country = $order->get_billing_country();
            $virtual = '';
            if (($wc_product = $item->get_product()) && $wc_product->get_virtual()) {
                $virtual = '_virtual';
            }
            $account = strval(get_option('briox_export_vat')); 
            if ($country == 'SE') {
                $tax_rate = WC_Tax::get_base_tax_rates($item->get_tax_class());
                $tax_string = floatval(reset($tax_rate)['rate']);
                $account = strval(get_option('briox_se_' . $tax_string . '_vat' . $virtual));
            } elseif (Briox_integration_Woo_Util::is_eu($order)) {
                if ('true' == get_post_meta($order->get_id(), '_vat_number_is_validated', true)) {
                    $account = strval(get_option('briox_eu_excl_vat' . $virtual));
                } else {
                    $account = strval(get_option('briox_eu_incl_vat' . $virtual));
                }
            }
            return $account;
        }

        public function button_sync()
        {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'ajax-briox-integration')) {
                wp_die();
            }

            $order_id = sanitize_text_field($_POST['order_id']);

            $order = wc_get_order($order_id);
            do_action('woo_briox_integration_create_customer', $order_id, true);
            if ($this->wc_order_creates_order($order)) {
                if (!($briox_invoice_number = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($order_id))) {
                    do_action('woo_briox_integration_processing_order', $order_id);
                } else {
                    do_action('briox_process_changed_invoices', $briox_invoice_number);
                }
            } else {
                do_action('woo_briox_integration_processing_invoice', $order_id);
            }

            echo true;
            die;
        }

        public function get_shipping_account($order, $tax_rate)
        {

            $country = $order->get_billing_country();

            if ($country == 'SE') {
                $account = strval(get_option('briox_se_' . $tax_rate . '_shipping'));
            } elseif (Briox_integration_Woo_Util::is_eu($order)) {
                $account = strval(get_option('briox_eu_incl_shipping'));
            } else {
                $account = strval(get_option('briox_export_shipping'));
            }

            return $account;

        }

        public function get_item_price($wc_order_item)
        {
            if ('yes' == get_option('briox_amounts_excl_tax')) {
                return $wc_order_item->get_total();
            } else {
                return $wc_order_item->get_total() + $wc_order_item->get_total_tax();
            }
        }

        public function get_items($order)
        {
            $rows = array();

            $no_articlenumber_in_orderrow = get_option('briox_no_articlenumber_in_orderrow');

            $wc_order_items = $order->get_items();
            if ($wc_order_items !== false) {
                foreach ($wc_order_items as $wc_order_item) {

                    $item = false;

                    if (($product = $wc_order_item->get_product()) != false) {
                        try {
                            $item = Briox_API::get_item($product->get_sku());
                        } catch (Briox_API_Exception $e) {
                            if ('error' == $no_articlenumber_in_orderrow || 404 != $e->getCode()) {
                                throw new $e($e->getMessage(), $e->getCode(), $e);
                            }
                        }
                    }

                    $row = array(
                        "account" => $this->get_merchandise_account($order, $wc_order_item),
                        "amount" => $wc_order_item->get_quantity(),
                        "price" => $this->get_item_price($wc_order_item) / $wc_order_item->get_quantity(),
                    );

                    if ($item) {
                        $row["itemno"] = $item['item_id'];
                    } elseif (!$no_articlenumber_in_orderrow) {
                        $row["itemno"] = '';
                        $row["description"] = $wc_order_item->get_name();
                    }

                    $rows[$this->document_type($order, '_rows')][] = $row;
                }
            }
            return $rows;
        }

        public function get_fee_items($order)
        {
            $rows = array();
            $fees = $order->get_fees();
            if ($fees !== false) {
                foreach ($fees as $fee) {
                    $row = array(
                        "itemno" => '',
                        "price" => $this->get_item_price($fee),
                        "description" => $fee->get_name(),
                        "amount" => floatval("1"),
                    );

                    $rows[$this->document_type($order, '_rows')][] = $row;
                }
            }
            return $rows;
        }

        public function get_country($country_code)
        {
            $country = WC()->countries->countries[$country_code];
            $country = $country == 'USA (US)' ? 'USA' : $country;
            $country = $country == 'Storbritannien (UK)' ? 'Storbritannien' : $country;
            $country = $country == 'United Kingdom (UK)' ? 'United Kingdom' : $country;
            $country = $country == 'Sweden' ? 'Sverige' : $country;
            return $country;
        }

        public function create_shipping_row($price, $shipping_item, $order, $tax_percent)
        {
            $row = array(
                "itemno" => '',
                "price" => $price,
                "description" => sprintf(__('Shipping - %s', 'briox-integration-woo'), $shipping_item->get_method_title()),
                "amount" => 1,
                "account" => $this->get_shipping_account($order, strstr($tax_percent, '%', true)),
            );

            return $row;
        }

        public function get_shipping_items($order)
        {
            $rows = array();
            $order_excl_tax = 'yes' == get_option('briox_amounts_excl_tax');
            foreach ($order->get_shipping_methods() as $shipping_item) {

                if ($shipping_item->get_total_tax()) {
                    foreach ($shipping_item->get_taxes()['total'] as $tax_rate => $tax_amount) {
                        if (!empty($tax_amount)) {
                            $tax_percent = WC_Tax::get_rate_percent($tax_rate);
                            $amount = $tax_amount / ($tax_percent / 100);
                            $price = $amount + ($order_excl_tax ? 0 : $tax_amount);
                            if (0 != $price) {
                                $rows[$this->document_type($order, 'rows')][] = $this->create_shipping_row($price, $shipping_item, $order, $tax_percent);
                            }
                        }
                    }
                } else {
                    $price = $this->get_item_price($shipping_item);
                    if (0 != $price) {
                        $rows[$this->document_type($order, '_rows')][] = $this->create_shipping_row($price, $shipping_item, $order, $tax_percent);
                    }
                }

            }
            return $rows;
        }

        public function get_details($order, $refund = true)
        {
            $meta_list = array();

            $order_id = $order->get_id();
            $date_created = $order->get_date_created();
            $delivery_correct = 0;
            if (0 < ($default_delivery_days = get_option('briox_default_delivery_days', 0))) {
                $delivery_correct = (false === ($pos = array_search($date_created->date('N'), array((string) 6, (string) 5))) ? $default_delivery_days : $default_delivery_days + 1 + $pos);
            };
            $customer_name = $order->get_billing_company();
            if (preg_match('/\((.*?)\)/', $customer_name, $match)) {
                $customer_name = trim(strstr($customer_name, '(', true));
            }

            $customer_number = Briox_integration_Woo_Util::get_briox_customer_number($order);

            $cost_center = get_option('briox_cost_center');
            $project = get_option('briox_project');

            $common_data = array(
                "customerid" => $customer_number,
                "currency" => $order->get_currency(),
                "orderno" => $order->get_order_number(),
                "ourreference" => ($reference = get_option('fornox_our_reference')) ? $reference : '',
                'costcenter' => $cost_center ? $cost_center : '',
                'project' => $project ? $project : '',
                $this->document_type($order, 'date') => $date_created->date('Y-m-d'),
            );

            $payment_data = array();

            $payment_gateways = array();
            if (WC()->payment_gateways()) {
                $payment_gateways = WC()->payment_gateways->payment_gateways();
            }

            $payment_method = $order->get_payment_method();

            if ($order->get_date_paid()) {
                if ($payment_method) {
                    $meta_list[] = sprintf(__('Payment via %s (%s)', 'briox-integration-woo'), isset($payment_gateways[$payment_method]) ? $payment_gateways[$payment_method]->get_title() : ucfirst($payment_method), $order->get_transaction_id());
                }
                $meta_list[] = sprintf(__('Paid on %1$s @ %2$s', 'briox-integration-woo'),
                    wc_format_datetime($order->get_date_paid()),
                    wc_format_datetime($order->get_date_paid(), get_option('time_format'))
                );
            }

            $payment_data = array(
                "deliverydate" => date('Y-m-d', $date_created->date('U') + ($delivery_correct * (24 * 60 * 60))),
                "paymentterm" => get_option('briox_term_of_payment_' . $payment_method, ''),
                'invoicetext' => ($info_text = implode('. ', $meta_list)) ? $info_text : '',
            );

            $shipping_items = $order->get_shipping_methods();

            $shipping_item = reset($shipping_items);

            $delivery_data = array();
            if ($shipping_item) {
                $delivery_data = array(
                    "shippingcondition" => get_option('briox_term_of_delivery_' . $shipping_item->get_method_id(), ''),
                    "shippingmethod" => get_option('briox_way_of_delivery_' . $shipping_item->get_method_id(), ''),
                );
            }

            return array_merge($common_data, $payment_data, $delivery_data);
        }

    }

    new Briox_Integration_Woo_Document_Handler();

}
