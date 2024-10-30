<?php

/**
 * This class handles syncing customers with Briox.
 *
 * @package   BjornTech_Briox_Integration
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2019 BjornTech
 */

defined('ABSPATH') || exit;

if (!class_exists('WP_Async_Request', false)) {
    include_once plugin_dir_path(WC_PLUGIN_FILE) . 'includes/libraries/wp-async-request.php';
}

if (!class_exists('WP_Background_Process', false)) {
    include_once plugin_dir_path(WC_PLUGIN_FILE) . 'includes/libraries/wp-background-process.php';
}

if (!class_exists('Briox_Integration_Woo_Price_Stocklevel_Handler', false)) {

    class Briox_Integration_Woo_Price_Stocklevel_Handler
    {
        public $briox_sync_price_stocklevel_process;
        public $briox_sync_price_stocklevel_check;

        public function __construct()
        {
            $this->briox_sync_price_stocklevel_process = new Briox_Integration_Woo_Sync_Price_Stocklevel_Process();
            $this->briox_sync_price_stocklevel_check = new Briox_Integration_Woo_Sync_Price_Stocklevel_Check();

            if ('yes' == get_option('briox_sync_from_briox_automatically')) {
                add_action('init', array($this, 'sync_price_stocklevel'), 99);
            }

            add_action('briox_sync_price_stocklevel_start', array($this, 'price_stocklevel_start'));
            add_action('briox_process_price', array($this, 'process_price'), 10, 3);
            add_action('briox_process_stocklevel', array($this, 'process_stocklevel'), 10, 2);
            add_action('briox_sync_price_stocklevel_process_add', array($this, 'sync_price_stocklevel_process_add'));
            add_action('briox_sync_price_stocklevel_process_dispatch', array($this, 'sync_price_stocklevel_process_dispatch'));
            add_filter('briox_get_sections', array($this, 'add_settings_section'), 60);
            add_filter('woocommerce_get_settings_briox_integration', array($this, 'get_settings'), 60, 2);
            add_filter('woocommerce_save_settings_briox_integration_price_stocklevel', array($this, 'save_settings_section'));
            add_action('woocommerce_settings_briox_price_stocklevel_options', array($this, 'show_sync_all_button'), 10);
        }

        /**
         * Add section for price and stocklevel settings
         */
        public function add_settings_section($sections)
        {
            if (!array_key_exists('price_stocklevel', $sections)) {
                $sections = array_merge($sections, array('price_stocklevel' => __('Price & stocklevel', 'briox-integration-woo')));
            }
            return $sections;
        }

        /**
         * Save settings, possibly do some action when done
         */
        public function save_settings_section($true)
        {
            return $true;
        }

        /**
         * Settings for price and stocklevel settings
         */
        public function get_settings($settings, $current_section)
        {
            if ('price_stocklevel' == $current_section) {
                $briox_pricelists = apply_filters('briox_get_pricelist', array());
                $pricelists = array(
                    '' => __('Do not update the price', 'briox-integration-woo'),
                );
                if (!empty($briox_pricelists)) {
                    foreach ($briox_pricelists['pricelists'] as $briox_pricelist) {
                        $pricelists[$briox_pricelist['code']] = __('Use Briox pricelist', 'briox-integration-woo') . ' ' . $briox_pricelist['description'];
                    }
                }
                $settings = array(
                    array(
                        'title' => __('Price & stock level', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_price_stocklevel_options',
                    ),
                    array(
                        'title' => __('Sync automatically', 'briox-integration-woo'),
                        'default' => '',
                        'type' => 'checkbox',
                        'desc_tip' => true,
                        'desc' => __('Sync to WooCommerce automatically', 'briox-integration-woo'),
                        'id' => 'briox_sync_from_briox_automatically',
                    ),
                    array(
                        'title' => __('Price update', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => '',
                        'desc_tip' => __('Selet what price in Briox to use when updating WooCommerce.', 'briox-integration-woo'),
                        'options' => $pricelists,
                        'id' => 'briox_process_price',
                    ),
                    array(
                        'title' => __('Stock level update', 'briox-integration-woo'),
                        'default' => '',
                        'desc_tip' => __('Update stock level in WooCommerce automatically from Briox.', 'briox-integration-woo'),
                        'type' => 'checkbox',
                        'id' => 'briox_process_stocklevel',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_price_stocklevel_options',
                    ),
                );
            }
            return $settings;
        }

        public function show_sync_all_button()
        {
            echo '<div id=briox_titledesc_sync_all>';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            echo '<label for="briox_sync_all">' . __('Update all', 'briox-integration-woo') . '<span class="woocommerce-help-tip" data-tip="' . __('Update all prices and stocklevels from Briox', 'briox-integration-woo') . '"></span></label>';
            echo '</th>';
            echo '<td class="forminp forminp-button">';
            echo '<button name="briox_sync_all" id="briox_sync_all" class="button">' . __('Update', 'briox-integration-woo') . '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        public function process_price($product, $item, $pricelist)
        {
            foreach ($item['prices'] as $price) {
                if ($price['price_list'] == $pricelist) {
                    $price_amount = $price['price'];
                    $current_price = $product->get_price();
                    if ($current_price != $price['price'] && 0 != $price['price']) {
                        $product->set_price($price['price']);
                        $product->set_regular_price($price['price']);
                        $product->save();
                        Briox_Integration_Woo_Logger::log('debug',sprintf('Changed price on WooCommerce product id %s using pricelist %s from %s to %s', $product_id, $pricelist, $current_price, $price['price']));
                    }
                    break;
                }
            }

        }

        public function process_stocklevel($product, $item)
        {
            $product_id = $product->get_id();
            $item_number = $item['item_id'];
            if ($item['stock_item']) {
                $changed = false;

                if (!$product->get_manage_stock()) {
                    $product->set_manage_stock(true);
                    $changed = true;
                    Briox_Integration_Woo_Logger::log('debug',sprintf('Briox article %s is stock goods, did set WooCommerce product %s to manage stock', $item_number, $product_id));
                }

                if (($current_quantity = $product->get_stock_quantity()) != $item['available_units']) {
                    $product->set_stock_quantity($item['available_units']);
                    $changed = true;
                    Briox_Integration_Woo_Logger::log('debug',sprintf('Changed stock level on WooCommerce product %s from %s to %s', $product_id, $current_quantity, $item['available_units']));
                }

                if ($item['available_units'] > 0) {
                    if (($backorder_option = get_option('briox_backorder_option_instock')) && ($product->get_backorders() != $backorder_option)) {
                        $product->set_backorders($backorder_option);
                        $changed = true;
                        Briox_Integration_Woo_Logger::log('debug',sprintf('WooCommerce product %s set instock backorder option to %s', $product_id, $backorder_option));
                    }
                } else {
                    if (($backorder_option = get_option('briox_backorder_option_outofstock')) && ($product->get_backorders() != $backorder_option)) {
                        $product->set_backorders($backorder_option);
                        $changed = true;
                        Briox_Integration_Woo_Logger::log('debug',sprintf('WooCommerce product %s set outofstock backorder option to %s', $product_id, $backorder_option));
                    }
                }

                if ($changed) {
                    $product->save();
                }

            } else {

                if ($product->get_manage_stock()) {
                    $product->set_manage_stock(false);
                    $product->save();
                    Briox_Integration_Woo_Logger::log('debug',sprintf('Briox article %s is not stock goods, did set WooCommerce product %s to not manage stock', $item_number, $product_id));
                }

            }
        }

        /**
         * Add a product id to the syncing queue
         */
        public function sync_price_stocklevel_process_add($item)
        {
            if (!is_object($item)) {
                $item = Briox_API::get_item($sync_data->item);
            }

            $response = $this->briox_sync_price_stocklevel_process->push_to_queue($item);
        }

        /**
         * Start the processing of the queued products to Briox
         */
        public function sync_price_stocklevel_process_dispatch()
        {
            $response = $this->briox_sync_price_stocklevel_process->save()->dispatch();
        }

        /**
         * Initiate the sync of wc products to Briox
         */
        public function price_stocklevel_start()
        {
            $response = $this->briox_sync_price_stocklevel_check->dispatch();
        }

        public function sync_price_stocklevel()
        {
            if (get_site_transient('briox_sync_price_stocklevel_lock') === false) {
                set_site_transient('briox_sync_price_stocklevel_lock', microtime(), MINUTE_IN_SECONDS);
                do_action('briox_sync_price_stocklevel_start', 'changed');
            }
        }
    }

    class Briox_Integration_Woo_Sync_Price_Stocklevel_Check extends WP_Async_Request
    {

        protected $action = 'briox_sync_price_stocklevel_check';

        protected function handle()
        {
            try {
                $sync_type = sanitize_text_field($_POST['type']);

                $this_sync_time = date('Y-m-d H:i', current_time('timestamp'));
                $last_sync_done = get_option('briox_last_sync_products', $this_sync_time);

                $items = Briox_API::get_all_items();

                if (count($items) > 0) {
                    Briox_Integration_Woo_Logger::log('debug',sprintf('Added %s Briox item(s) to check if price or stocklevel changed', count($items)));
                    foreach ($items as $key => $item) {
                        do_action('briox_sync_price_stocklevel_process_add', (object) $item);
                    }
                    do_action('briox_sync_price_stocklevel_process_dispatch');
                } else {
                    Briox_Integration_Woo_Logger::log('debug',sprintf('No Briox products'));
                }

                update_option('briox_last_sync_products', $this_sync_time, true);

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug',print_r($t, true));
                }
            }

            return false;
        }
    }

    class Briox_Integration_Woo_Sync_Price_Stocklevel_Process extends WP_Background_Process
    {

        protected $action = 'briox_sync_price_stocklevel_process';

        protected $sync_data;

        protected function task($item)
        {
            try {

                $item = (array) $item;

                if ($product_id = wc_get_product_id_by_sku(trim($item['item_id']))) {

                    $product = wc_get_product($product_id);

                    if ($pricelist = get_option('briox_process_price')) {
                        do_action('briox_process_price', $product, $item, $pricelist);
                    }

                    if ('yes' == get_option('briox_process_stocklevel')) {
                        do_action('briox_process_stocklevel', $product, $item);
                    }
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

        private static function is_valid_manufacturer($manufacturer)
        {
            return !empty($manufacturer);
        }

        protected function complete()
        {
            parent::complete();
            Briox_Integration_Woo_Logger::log('debug','Price and stocklevel import from Briox complete');
        }

    }

    new Briox_Integration_Woo_Price_Stocklevel_Handler();
}
