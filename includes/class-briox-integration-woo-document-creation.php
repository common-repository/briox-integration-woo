<?php

/**
 * This class handles tranferring invoices to Briox.
 *
 * @package   Briox_Integration_Woo
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2019 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('Briox_Integration_Woo_Document_Creation', false)) {

    class Briox_Integration_Woo_Document_Creation
    {

        public function briox_finish_order($order_id)
        {
            try {
                $order = wc_get_order($order_id);
                $fn_order_number = Briox_integration_Woo_Util::get_briox_order_documentnumber($order_id);
                if ($fn_order_number) {
                    $fn_order = Briox_API::getOrder($fn_order_number);
                    $briox_invoice = Briox_API::finishOrder($fn_order['DocumentNumber']);
                    Briox_integration_Woo_Util::set_briox_invoice_documentnumber($order_id, $briox_invoice['DocumentNumber']);
                    $order->set_status('completed', sprintf('Invoice %s created in Briox', $briox_invoice['DocumentNumber']));
                    Briox_Integration_Woo_Logger::log('debug',sprintf('WooCommerce order %s created Briox invoice %s', $order_id, $briox_invoice['DocumentNumber']));
                }
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
        }

        public function briox_processing_invoice($order_id)
        {
            try {

                $invoice_number = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($order_id);

                $order = new WC_Order($order_id);

                $briox_invoice = Briox_integration_Woo_Util::remove_blanks(array_merge_recursive(
                    $this->get_items($order),
                    $this->get_fee_items($order),
                    $this->get_shipping_items($order),
                    $this->get_details($order)
                ));

                if (!$invoice_number) {
                    $briox_invoice = Briox_API::create_invoice($briox_invoice);
                    Briox_integration_Woo_Util::set_briox_invoice_documentnumber($order_id, $briox_invoice['id']);
                    Briox_Integration_Woo_Logger::log('debug',sprintf('WooCommerce order %s created Briox invoice %s', $order_id, $briox_invoice['id']));
                } else {
                    $briox_invoice = Briox_API::update_invoice($invoice_number, $briox_invoice);
                    Briox_Integration_Woo_Logger::log('debug',sprintf('WooCommerce order %s updated Briox invoice %s', $order_id, $briox_invoice['id']));
                }

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
        }

        public function briox_processing_order($order_id)
        {
            try {
                $order = new WC_Order($order_id);

                $order_items = $this->get_items($order);

                $fee_items = $this->get_fee_items($order);

                $shipping_items = $this->get_shipping_items($order);

                $order_details = $this->get_details($order);

                $full_order = array_merge_recursive($order_items, $fee_items, $order_details, $shipping_items);

                $fn_order_number = Briox_integration_Woo_Util::get_briox_order_documentnumber($order_id);

                if (!$fn_order_number) {
                    $fn_order = Briox_API::create_order($full_order);
                    Briox_integration_Woo_Util::set_briox_order_documentnumber($order_id, $fn_order['DocumentNumber']);
                    Briox_Integration_Woo_Logger::log('debug',sprintf(__('WooCommerce order %s created Briox order %s', 'briox-integration-woo'), $order_id, $fn_order['DocumentNumber']));
                } else {
                    $fn_order = Briox_API::getOrder($fn_order_number);
                    if (!$invoice_id = $fn_order['InvoiceReference']) {
                        $empty_order_rows = $this->get_empty_rows($order);
                        $fn_order = Briox_API::updateOrder($fn_order['DocumentNumber'], $empty_order_rows);
                        $fn_order = Briox_API::updateOrder($fn_order['DocumentNumber'], $full_order);
                        Briox_Integration_Woo_Logger::log('debug',sprintf(__('WooCommerce order %s updated Briox order %s', 'briox-integration-woo'), $order_id, $fn_order['DocumentNumber']));
                    } else {
                        Briox_integration_Woo_Util::set_briox_invoice_documentnumber($order_id, $invoice_id);
                    }
                }

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
        }

        public function briox_cancelled_invoice($order_id)
        {
            try {
                $order = new WC_Order($order_id);
                Briox_Integration_Woo_Logger::log('debug',sprintf('Cancel WooCommerce order id %s', $order_id));

                if ($invoice_number = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($order_id)) {

                    $briox_invoice = Briox_API::get_invoice($invoice_number);

                    Briox_API::update_invoice($briox_invoice['DocumentNumber'], array(
                        "Comments" => __('Cancelled from WooCommerce', 'briox-integration-woo'),
                    ));

                    Briox_API::cancelInvoice($briox_invoice['DocumentNumber']);

                }
            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
        }

        public function briox_cancelled_order($order_id)
        {
            try {
                $order = new WC_Order($order_id);
                Briox_Integration_Woo_Logger::log('debug','Cancel WooCommerce order %s', $order_id);

                if ($fn_order_number = Briox_integration_Woo_Util::get_briox_order_documentnumber($order_id)) {

                    $fn_order = Briox_API::getOrder($fn_order_number);
                    Briox_API::updateOrder($fn_order['DocumentNumber'], array(
                        "Comments" => __('Cancelled from WooCommerce', 'briox-integration-woo'),
                    ));

                    Briox_API::cancelOrder($fn_order['DocumentNumber']);
                }

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
        }
        public function briox_refunded_invoice($order_id, $refund_id)
        {
            try {

                $refund_number = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($refund_id);
                $invoice_number = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($order_id);
                $briox_invoice = Briox_API::get_invoice($invoice_number);
                Briox_Integration_Woo_Logger::log('debug',print_r($refund_number, true));
                Briox_Integration_Woo_Logger::log('debug',print_r($invoice_number, true));
                Briox_Integration_Woo_Logger::log('debug',print_r($briox_invoice, true));
                if (!$refund_number && $invoice_number) {
                    $credit_invoice = Briox_API::credit_invoice($invoice_number);
                    Briox_integration_Woo_Util::set_briox_invoice_documentnumber($refund_id, $credit_invoice['DocumentNumber']);
                    Briox_Integration_Woo_Logger::log('debug',sprintf(__('WooCommerce order %s credited Briox invoice %s with credit invoice %s', 'briox-integration-woo'), $order_id, $invoice_number, $credit_invoice['DocumentNumber']));
                }

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }
        }

        public function briox_order_number_content($column)
        {
            global $post;
            $order = wc_get_order($post->ID);
            $order_status = $order->get_status();

            if ('briox_order_number' === $column) {
                if (($order_number_meta = Briox_integration_Woo_Util::get_briox_order_documentnumber($post->ID)) !== false) {
                    echo sprintf('%8s', $order_number_meta);
                }
            }
            $briox_invoicenumber = Briox_integration_Woo_Util::get_briox_invoice_documentnumber($post->ID);
            if ('briox_sync_order' === $column && (('cancelled' != $order_status && 'refunded' != $order_status && 'completed' != $order_status) || ('completed' == $order_status && !$briox_invoicenumber))) {
                $briox_ordernumber = Briox_integration_Woo_Util::get_briox_order_documentnumber($post->ID);
                if ($briox_ordernumber) {
                    echo '<a class="button button wc-action-button briox sync" data-order-id="' . esc_html($order->get_id()) . '">Resync</a>';
                } else {
                    echo '<a class="button button wc-action-button briox sync" data-order-id="' . esc_html($order->get_id()) . '">Sync</a>';
                }

            }
        }

    }

}
