<?php

/**
 * Utility functions for WooCommerce Briox Integration.
 *
 * @package   WooCommerce_Briox_Integration
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2019 BjornTech
 */
// Prevent direct file access

defined('ABSPATH') || exit;

if (!class_exists('Briox_integration_Woo_Util', false)) {
    final class Briox_integration_Woo_Util
    {

        public static function remove_blanks($items)
        {
            if (is_array($items)) {
                foreach ($items as $key => $item) {
                    if (is_array($item)) {
                        self::remove_blanks($item);
                    } elseif (!$item) {
                        unset($items[$key]);
                    }
                }
            }
            return $items;
        }

        public static function get_bank_account($order)
        {
            return strval(get_option('briox_' . $order->get_payment_method() . '_bank_account'));
        }

        public static function get_fee_account($order)
        {
            return strval(get_option('briox_' . $order->get_payment_method() . '_fee_account'));
        }

        public static function get_fee_vat_account($order)
        {
            return strval(get_option('briox_' . $order->get_payment_method() . '_fee_vat_account'));
        }

        public static function get_fee_reverse_vat_account($order)
        {
            return strval(get_option('briox_' . $order->get_payment_method() . '_fee_reverse_vat_account'));
        }

        /**
         * sset_briox_order_vouchernumber function
         *
         * Set the Briox Order DocumentNumber on an order
         *
         * @access public
         * @return void
         */
        public static function set_briox_order_vouchernumber($order_id, $briox_payment_vouchernumber)
        {
            if ($briox_payment_vouchernumber != "") {
                update_post_meta($order_id, '_briox_order_vouchernumber', $briox_payment_vouchernumber);
            } else {
                delete_post_meta($order_id, '_briox_order_vouchernumber');
            }
        }

        /**
         * get_briox_order_vouchernumber
         *
         * If the order has a Briox Order DocumentNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         * @return bool
         */
        public static function get_briox_order_vouchernumber($order_id)
        {
            return (($result = get_post_meta($order_id, '_briox_order_vouchernumber', true)) == "" ? false : $result);
        }

        /**
         * sset_briox_payment_vouchernumber function
         *
         * Set the Briox Order DocumentNumber on an order
         *
         * @access public
         * @return void
         */
        public static function set_briox_payment_vouchernumber($order_id, $briox_payment_vouchernumber)
        {
            if ($briox_payment_vouchernumber != "") {
                update_post_meta($order_id, '_briox_payment_vouchernumber', $briox_payment_vouchernumber);
            } else {
                delete_post_meta($order_id, '_briox_payment_vouchernumber');
            }
        }

        /**
         * get_briox_payment_vouchernumber
         *
         * If the order has a Briox payment documentNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         * @return bool
         */
        public static function get_briox_payment_vouchernumber($order_id)
        {
            return (($result = get_post_meta($order_id, '_briox_payment_vouchernumber', true)) == "" ? false : $result);
        }

        /**
         * set_briox_invoice_documentnumber
         *
         * Set the Briox Invoice DocumentNumber on an order
         *
         * @access public
         * @return void
         */
        public static function set_briox_invoice_documentnumber($order_id, $briox_order_documentnumber)
        {
            if ($briox_order_documentnumber != "") {
                update_post_meta($order_id, '_briox_invoice_number', $briox_order_documentnumber);
            } else {
                delete_post_meta($order_id, '_briox_invoice_number');
            }
        }

        /**
         * get_briox_invoce_documentnumber
         *
         * If the order has a Briox Order DocumentNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         * @return bool
         */
        public static function get_briox_invoice_documentnumber($order_id)
        {
            $result = get_post_meta($order_id, '_briox_invoice_number', true);
            if ((!$result)) {
                $result = get_post_meta($order_id, 'Briox Invoice number', true);
            }

            return $result;
        }

        /**
         * sset_briox_order_documentnumber function
         *
         * Set the Briox Order DocumentNumber on an order
         *
         * @access public
         * @return void
         */

        public static function set_briox_order_documentnumber($order_id, $briox_order_documentnumber)
        {
            if ($briox_order_documentnumber != "") {
                update_post_meta($order_id, '_briox_order_documentnumber', $briox_order_documentnumber);
            } else {
                delete_post_meta($order_id, '_briox_order_documentnumber');
            }
        }

        /**
         * get_briox_order_documentnumber
         *
         * If the order has a Briox Order DocumentNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         * @return bool
         */
        public static function get_briox_order_documentnumber($order_id)
        {
            $result = get_post_meta($order_id, '_briox_order_documentnumber', true);
            if (!$result) {
                $result = get_post_meta($order_id, 'FORTNOX_ORDER_DOCUMENTNUMBER', true);
            }

            return ($result == "" ? false : $result);
        }

        private static function get_translation($translations, $selected)
        {
            foreach ($translations as $key => $translation) {
                if (isset($translation[$selected])) {
                    return $translation[$selected];
                }
            }
            return false;
        }

        public static function get_language_text($text_object, $default = false)
        {

            $label = isset($text_object['translations']) ? 'translations' : 'language';

            if (isset($text_object[$label]) && ($translation = self::get_translation($text_object[$label], 'en_GB'))) {
                return $translation;
            } elseif (isset($text_object[$label]) && ($translation = self::get_translation($text_object[$label], 'sv_SE'))) {
                return $translation;
            } elseif ($default && isset($text_object[$default])) {
                return $text_object[$default];
            }

            return __('No description', 'briox-integration-woo');

        }

        public static function clean_briox_text($text)
        {
            preg_match_all("~(*UTF8)[\p{L}\’\\\x{0308}\x{030a}a-zåäöéáœæøüA-ZÅÄÖÉÁÜŒÆØ0-9 –:\.`´’,;\^¤#%§£$€¢¥©™°&\/\(\)=\+\-\*_\!?²³®½\@\x{00a0}\n\r]*~", $text, $result);
            if (isset($result[0])) {
                $text = implode('', $result[0]);
            }
            return $text;
        }

        public static function clean_briox_article_number($article_number)
        {

            preg_match_all("~[a-zåäöA-ZÅÄÖ0-9\_\-\+\/\.]*~", $article_number, $result);
            if (isset($result[0])) {
                $article_number = implode('', $result[0]);
            }
            return substr($article_number, 0, 50);
        }

        public static function get_product_categories()
        {
            $cat_args = array(
                'orderby' => 'name',
                'order' => 'asc',
                'hide_empty' => true,
            );
            return get_terms('product_cat', $cat_args);
        }

        public static function is_izettle($order)
        {
            return 'izettle' == $order->get_created_via();
        }

        public static function decode_external_reference($external_reference)
        {
            return strstr(Briox_integration_Woo_Util::encode_external_reference($external_reference), ':', true);
        }

        public static function encode_external_reference($external_reference)
        {
            $cost_center = get_option('briox_cost_center');
            $project = get_option('briox_project');
            return implode(':', array($external_reference, $cost_center, $project));
        }

        /**
         * Set_briox_customer_number function
         *
         * Set the Briox CustomerNumber on an order
         *
         * @access private
         * @return void
         */
        public static function set_briox_customer_number($order_id, $briox_customer_number)
        {
            if ($briox_customer_number != "") {
                update_post_meta($order_id, '_briox_customer_number', $briox_customer_number);
            } else {
                delete_post_meta($order_id, '_briox_customer_number');
            }

        }

        /**
         * get_briox_customer_number
         *
         * If the order has a Briox CustomerNumber, we will return it. If not present we return FALSE.
         *
         * @access public
         * @return string
         */
        public static function get_briox_customer_number($order)
        {
            return (($result = get_post_meta($order->get_id(), '_briox_customer_number', true)) == "" ? null : $result);
        }

        /*
         * Inserts a new key/value before the key in the array.
         *
         * @param $key
         *   The key to insert before.
         * @param $array
         *   An array to insert in to.
         * @param $new_key
         *   The key to insert.
         * @param $new_value
         *   An value to insert.
         *
         * @return
         *   The new array if the key exists, FALSE otherwise.
         *
         * @see array_insert_after()
         */
        public static function array_insert_before($key, array &$array, $new_key, $new_value)
        {
            if (array_key_exists($key, $array)) {
                $new = array();
                foreach ($array as $k => $value) {
                    if ($k === $key) {
                        $new[$new_key] = $new_value;
                    }
                    $new[$k] = $value;
                }
                return $new;
            }
            return false;
        }

        /*
         * Inserts a new key/value after the key in the array.
         *
         * @param $key
         *   The key to insert after.
         * @param $array
         *   An array to insert in to.
         * @param $new_key
         *   The key to insert.
         * @param $new_value
         *   An value to insert.
         *
         * @return
         *   The new array if the key exists, FALSE otherwise.
         *
         * @see array_insert_before()
         */
        public static function array_insert_after($key, array &$array, $new_key, $new_value)
        {
            if (array_key_exists($key, $array)) {
                $new = array();
                foreach ($array as $k => $value) {
                    $new[$k] = $value;
                    if ($k === $key) {
                        $new[$new_key] = $new_value;
                    }
                }
                return $new;
            }
            return false;
        }

        public static function is_eu($order)
        {
            $countries = new WC_Countries();
            return (in_array($order->get_billing_country(), $countries->get_european_union_countries()));
        }

    }
}
