<?php

/**
 * This class handles syncing products to Briox.
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

if (!class_exists('Briox_Integration_Woo_WC_Product_Handler', false)) {

    class Briox_Integration_Woo_WC_Product_Handler
    {
        public $briox_wc_product_sync;
        public $briox_queue_wc_products;

        public function __construct()
        {
            $this->briox_queue_wc_products = new Briox_Queue_WC_Products();
            $this->briox_wc_product_sync = new Briox_WC_Product_Sync();
            add_action('briox_start_wc_product_sync', array($this, 'start_wc_product_sync'));
            add_action('briox_push_product_to_queue', array($this, 'push_product_to_queue'), 10, 1);
            add_action('briox_dispatch_queue', array($this, 'dispatch_queue'));
            add_filter('briox_get_sections', array($this, 'add_settings_section'), 50);
            add_filter('woocommerce_get_settings_briox_integration', array($this, 'get_settings'), 50, 2);
            add_filter('woocommerce_save_settings_briox_integration_wc_products', array($this, 'save_settings_section'));
            add_action('woocommerce_settings_briox_wc_products_selection', array($this, 'show_start_sync_button'), 10);
            add_action('wp_ajax_briox_sync_wc_products', array($this, 'ajax_sync_wc_products'));
            add_action('briox_sync_product_to_item', array($this, 'sync_product_to_item'));
            add_filter('briox_get_or_create_item', array($this, 'get_or_create_item'), 10, 2);
            add_filter('woocommerce_duplicate_product_exclude_meta', array($this, 'duplicate_product_exclude_meta'));
            add_action('woocommerce_product_duplicate_before_save', array($this, 'product_duplicate_before_save'), 10, 2);

            if ('yes' == get_option('briox_create_products_automatically')) {
                add_action('woocommerce_update_product', array($this, 'wc_product_was_updated'), 100, 2);
                add_action('woocommerce_update_product_variation', array($this, 'wc_product_was_updated'), 100, 2);
                add_action('woocommerce_process_product_meta', array($this, 'wc_product_was_updated'), 100, 2);
                add_action('woocommerce_product_quick_edit_save', array($this, 'wc_product_was_updated'), 100, 1);
                add_action('woocommerce_product_bulk_edit_save', array($this, 'wc_product_was_updated'), 100, 1);
                add_action('woocommerce_process_product_meta_simple', array($this, 'wc_product_was_updated'), 10, 1);
                add_action('woocommerce_process_product_meta_variable', array($this, 'wc_product_was_updated'), 10, 1);
                add_action('woocommerce_process_product_meta_booking', array($this, 'wc_product_was_updated'), 10, 1);
                add_action('woocommerce_process_product_meta_external', array($this, 'wc_product_was_updated'), 10, 1);
                add_action('woocommerce_process_product_meta_subscription', array($this, 'wc_product_was_updated'), 10, 1);
                add_action('woocommerce_process_product_meta_variable-subscription', array($this, 'wc_product_was_updated'), 10, 1);
                add_action('woocommerce_process_product_meta_bundle', array($this, 'wc_product_was_updated'), 10, 1);
            }

            /**
             * Adding columns inclusive Sync button product list page
             */
            add_filter('manage_edit-product_columns', array($this, 'briox_product_header'), 20);
            add_action('manage_product_posts_custom_column', array($this, 'briox_product_content'));
            add_action('wp_ajax_briox_update_article', array($this, 'button_sync'));
        }

        public function product_duplicate_before_save($duplicate, $product)
        {
            $duplicate->set_sku('');
            $duplicate->set_stock_quantity(0);
        }

        public function duplicate_product_exclude_meta($meta_to_exclude)
        {
            return $meta_to_exclude;
        }

        public function briox_product_header($columns)
        {

            $columns = Briox_integration_Woo_Util::array_insert_before('date', $columns, 'briox_update_product', __('Update Briox', 'wc-briox-shipping'));

            return $columns;
        }

        public function briox_product_content($column)
        {
            global $post;

            if ('briox_update_product' === $column) {
                echo '<a class="button wc-action-button briox update_product" data-product-id="' . esc_html($post->ID) . '">Update</a>';
            }
        }

        public function button_sync()
        {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'ajax-briox-integration')) {
                wp_die();
            }

            $product_id = sanitize_text_field($_POST['product_id']);

            $product = wc_get_product($product_id);
            do_action('briox_sync_product_to_item', $product);

            echo true;
            die;
        }

        /**
         * Add section for WC products settings
         */
        public function add_settings_section($sections)
        {
            if (!array_key_exists('wc_products', $sections)) {
                $sections = array_merge($sections, array('wc_products' => __('Products to Briox', 'briox-integration-woo')));
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
         * Settings for WC products to Briox
         */
        public function get_settings($settings, $current_section)
        {
            if ('wc_products' == $current_section) {

                $briox_pricelists = apply_filters('briox_get_pricelist', array());
                $pricelists = array('' => __('Do not update price on the Briox item', 'briox-integration-woo'));
                if (!empty($briox_pricelists)) {
                    foreach ($briox_pricelists['pricelists'] as $briox_pricelist) {
                        $pricelists[$briox_pricelist['code']] = Briox_integration_Woo_Util::get_language_text($briox_pricelist, 'description');
                    }
                }

                $category_options = array();
                $product_categories = Briox_integration_Woo_Util::get_product_categories();
                if (!empty($product_categories)) {
                    foreach ($product_categories as $category) {
                        $category_options[$category->slug] = $category->name;
                    }
                }

                $settings = array(
                    array(
                        'title' => __('Select how and what products to include when syncing products to Briox', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_wc_products_selection',
                    ),
                    array(
                        'title' => __('Sync automatically', 'briox-integration-woo'),
                        'default' => '',
                        'type' => 'checkbox',
                        'desc_tip' => true,
                        'desc' => __('Sync WooCommerce product data to Briox automatically', 'briox-integration-woo'),
                        'id' => 'briox_create_products_automatically',
                    ),
                    array(
                        'title' => __('Create items', 'briox-integration-woo'),
                        'type' => 'select',
                        'desc_tip' => __('Select if you want a Briox Item to be created if no match of a WooCommerce product is found in Briox', 'briox-integration-woo'),
                        'default' => '',
                        'options' => array(
                            '' => __('Create only if a SKU or Article number is set in WooCommerce', 'briox-integration-woo'),
                            'always_create' => __('Always create. If no SKU is set on the product. Briox will create a number.', 'briox-integration-woo'),
                            'never_create' => __('Never create. No products will be created in Briox by the plugin', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_create_products_from_wc',
                    ),
                    array(
                        'title' => __('Include product type', 'briox-integration-woo'),
                        'type' => 'select',
                        'desc_tip' => __('Select the type of product to be included in the product sync', 'briox-integration-woo'),
                        'default' => 'simple_variable',
                        'options' => array(
                            'simple_variable' => __('Include both simple and variable products', 'briox-integration-woo'),
                            'simple' => __('Include only simple products', 'briox-integration-woo'),
                            'variable' => __('Include only variable products', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_wc_products_include',
                    ),
                    array(
                        'title' => __('Include products with status', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 'publish',
                        'desc' => __('Select if you want to sync all products or only the ones with status "publish"', 'briox-integration-woo'),
                        'desc_tip' => true,
                        'options' => array(
                            '' => __('Sync all products, regardless of status', 'briox-integration-woo'),
                            'publish' => __('Sync all products with status "publish"', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_wc_get_product_status',
                    ),
                    array(
                        'title' => __('Product categories to sync', 'briox-integration-woo'),
                        'type' => 'multiselect',
                        'class' => 'wc-enhanced-select',
                        'css' => 'width: 400px;',
                        'id' => 'briox_wc_products_product_categories',
                        'default' => '',
                        'description' => __('If you only want to sync products included in certain product categories, select them here. Leave blank to enable for all categories.', 'briox-integration-woo'),
                        'options' => $category_options,
                        'desc_tip' => true,
                        'custom_attributes' => array(
                            'data-placeholder' => __('Select product categories or leave empty for all', 'briox-integration-woo'),
                        ),
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_wc_products_selection',
                    ),
                    array(
                        'title' => __('Select optional data', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_wc_products_data',
                    ),

                    array(
                        'title' => __('Update description field', 'briox-integration-woo'),
                        'type' => 'select',
                        'desc_tip' => __('Update the description field in Briox with the short or long description field in WooCommerce', 'briox-integration-woo'),
                        'default' => '',
                        'options' => array(
                            '' => __('Do not update description field', 'briox-integration-woo'),
                            'short_description' => __('WooCommerce short description', 'briox-integration-woo'),
                            'description' => __('WooCommerce description', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_wc_product_description',
                    ),
                    array(
                        'title' => __('Update price', 'briox-integration-woo'),
                        'type' => 'select',
                        'desc_tip' => __('Select if and what pricelist to uptate with the price from WooCommerce', 'briox-integration-woo'),
                        'default' => '',
                        'options' => $pricelists,
                        'desc_tip' => __('Select the Briox pricelist to be updated', 'briox-integration-woo'),
                        'id' => 'briox_wc_product_pricelist',
                    ),
                    array(
                        'title' => __('Handle stock', 'briox-integration-woo'),
                        'type' => 'checkbox',
                        'desc_tip' => __('Update stock-goods field in Briox from WooCommerce', 'briox-integration-woo'),
                        'default' => '',
                        'id' => 'briox_wc_product_update_stock_data',
                    ),
                    array(
                        'title' => __('Update stock level', 'briox-integration-woo'),
                        'type' => 'checkbox',
                        'desc_tip' => __('Update stock level in Briox from WooCommerce', 'briox-integration-woo'),
                        'default' => '',
                        'id' => 'briox_wc_product_update_stock_level',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_wc_products_data',
                    ),
                );
            }
            return $settings;
        }

        public function show_start_sync_button()
        {
            echo '<div id=briox_titledesc_sync_wc_products>';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            echo '<label for="briox_sync_wc_products">' . __('Manual sync', 'briox-integration-woo') . '<span class="woocommerce-help-tip" data-tip="' . __('Sync WooCommerce products to Briox', 'briox-integration-woo') . '"></span></label>';
            echo '</th>';
            echo '<td class="forminp forminp-button">';
            echo '<button name="briox_sync_wc_products" id="briox_sync_wc_products" class="button">' . __('Syncronize', 'briox-integration-woo') . '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        public function ajax_sync_wc_products()
        {
            if (!wp_verify_nonce(sanitize_text_field($_POST['nonce']), 'ajax-briox-integration')) {
                wp_die();
            }

            do_action('briox_start_wc_product_sync', true);

            $response = array(
                'result' => 'success',
                'message' => __('Your selection of WooCommerce products have been added to the updating queue.', 'briox-integration-woo'),
            );

            wp_send_json($response);
        }

        /**
         * Add a wc product id to the syncing queue
         */
        public function push_product_to_queue($product_id)
        {
            $this->briox_wc_product_sync->push_to_queue(wc_get_product($product_id));
        }

        /**
         * Start the processing of the queued wc products to Briox
         */
        public function dispatch_queue()
        {
            $this->briox_wc_product_sync->save()->dispatch();
        }

        public function wc_product_was_updated($product_id, $product = false)
        {
            $this->briox_wc_product_sync->push_to_queue(is_object($product) ? $product : wc_get_product($product_id));
            $this->briox_wc_product_sync->save()->dispatch();
            Briox_Integration_Woo_Logger::log('debug', sprintf('WooCommerce product %s was updated', $product_id));
        }

        /**
         * Initiate the sync of wc products to Briox
         */
        public function start_wc_product_sync($sync_all = false, $product = false)
        {
            $type = $sync_all ? 'all' : 'new';
            if ($sync_all || !($transient = get_site_transient('briox_sync_wc_products_last_sync_time')) || ($transient <= time())) {
                set_site_transient('briox_sync_wc_products_last_sync_time', time() + MINUTE_IN_SECONDS);
                $this->briox_queue_wc_products->data(array('type' => $type))->dispatch();
            }
        }

        public function get_item($item_id)
        {

            if ($item_id) {

                try {
                    $item = Briox_API::get_item($item_id);
                    Briox_Integration_Woo_Logger::log('debug', sprintf('WooCommerce product id %s is linked with Briox item id %s"', $product_id, $item_id));
                } catch (Briox_API_Exception $e) {
                    $code = $e->getCode();
                    if (404 != $code) {
                        throw new $e($e->getMessage(), $code, $e);
                    }
                    $item = null;
                }

            }

            return $item;

        }

        public function get_or_create_item($item_id, $product_id)
        {
            try {

                if (!($item = $this->get_item($item_id))) {

                    $products_from_wc = get_option('briox_create_products_from_wc');

                    if ((!$products_from_wc && $item_id) || 'always_create' == $products_from_wc) {

                        $item = Briox_API::create_item(array(
                            'item_id' => $item_id,
                            'description' => sprintf(__('WooCommerce product %s', 'briox-integration-woo'), $product_id),
                        ));
                        $item_id = $item['item_id'];
                        $product = wc_get_product($product_id);
                        if ($item_id != $product->get_sku()) {
                            $product->set_sku($item_id);
                            $product->save();
                        }
                        Briox_Integration_Woo_Logger::log('debug', sprintf('WooCommerce product id %s created Briox item id %s', $product_id, $item_id));

                    } else {

                        Briox_Integration_Woo_Logger::log('debug', sprintf('WooCommerce %s has no item id and will not be created', $product_id));

                    }

                }

            } catch (Briox_API_Exception $e) {

                Briox_Integration_Woo_Logger::log('debug', sprintf('Error %s when get/create Briox item id %s from WooCommerce product id %s', $e->getMessage(), $item_id, $product_id));

            }

            return $item;
        }

        public function sync_product_to_item($product)
        {
            if ($item = $this->get_or_create_item($product->get_sku(), $product->get_id())) {
                $this->update_article($item_id['item_id'], $product);
            }
        }

        public function create_item($item_id, $object)
        {
            $item_data = $this->create_item_data($object, $item_id);
            $item = Briox_API::create_item($item_data);
            if (!$item_id) {
                Briox_integration_Woo_Util::set_briox_article_number($object, $item['item_id']);
            }
            Briox_Integration_Woo_Logger::log('debug', sprintf('Created Briox article %s from WooCommerce product %s', $item['item_id'], $object->get_id()));
        }

        public function update_article($item_id, $object)
        {
            $item_data = $this->create_item_data($object, $item_id);
            $item = Briox_API::update_item($item_id, $item_data);
            Briox_Integration_Woo_Logger::log('debug', sprintf('Updated Briox item id %s from product %s', $item_id, $object->get_id()));
        }

        public function create_item_data($object, $item_id = false)
        {
            $item_data = array(
                'description' => Briox_integration_Woo_Util::clean_briox_text($object->get_name()),
                'active' => true,
            );

            if ($item_id) {
                $item_data['item_id'] = $item_id;
            }

            if ('short_description' == ($description_variant = get_option('briox_wc_product_description'))) {
                $item_data['notes'] = Briox_integration_Woo_Util::clean_briox_text($object->get_short_description());
            } elseif ('description' == $description_variant) {
                $item_data['notes'] = Briox_integration_Woo_Util::clean_briox_text($object->get_description());
            } elseif ('purchase_note' == $description_variant) {
                $item_data['notes'] = Briox_integration_Woo_Util::clean_briox_text($object->get_purchase_note());
            }

            if ('yes' == get_option('briox_wc_product_update_stock_data')) {
                $item_data = array_merge($item_data, array(
                    'stock_item' => true == $object->get_manage_stock(),
                ));
            }

            if ('yes' == get_option('briox_wc_product_update_stock_level')) {
                $item_data['stock'] = $object->get_stock_quantity();
            }

            if ('yes' == get_option('briox_wc_product_update_type')) {
                $item_data['type'] = $object->get_virtual() ? 'Service' : 'Goods';
            }

            if ($pricelist = get_option('briox_wc_product_pricelist')) {
                $item_data["prices"] = array(
                    array(
                        "price_list" => $pricelist,
                        "price" => $object->get_regular_price(),
                    ),
                );
            }

            return $item_data;
        }

    }

    class Briox_Queue_WC_Products extends WP_Async_Request
    {

        protected $action = 'briox_queue_wc_products';
        protected $products_added = 0;

        protected function handle()
        {
            $sync_all = sanitize_text_field($_POST['type']) == 'all';

            try {

                $include_products = explode('_', get_option('briox_wc_products_include', 'simple_variable'));

                $args = array(
                    'limit' => -1,
                    'return' => 'ids',
                    'type' => $include_products,
                );

                $this_sync_time = gmdate('U');

                if (($product_status = get_option('briox_wc_get_product_status', 'publish')) != '') {
                    $args['status'] = $product_status;
                }

                if (!$sync_all) {
                    $last_sync_done = get_option('briox_last_wc_product_sync_done');
                    if (false !== $last_sync_done) {
                        $args['date_modified'] = $last_sync_done . '...' . $this_sync_time;
                    }
                    Briox_Integration_Woo_Logger::log('debug', sprintf('Automatic sync of WooCommerce products changed after %s', date("Y-m-d H:i:s", $last_sync_done)));
                    update_option('briox_last_wc_product_sync_done', $this_sync_time);
                } else {
                    Briox_Integration_Woo_Logger::log('debug', 'Manual sync of all products in WooCommerce to Briox requested');
                }

                if (!empty($product_categories = get_option('briox_wc_products_product_categories', ''))) {
                    $args['category'] = $product_categories;
                }

                $default_lang = apply_filters('wpml_default_language', null);

                if ($default_lang) {
                    $args['suppress_filters'] = true;
                    Briox_Integration_Woo_Logger::log('debug', sprintf('WMPL or Polylang detected, using products with language code %s when syncing products', $default_lang));
                }

                $products_ids = wc_get_products($args);
                $number_to_sync = count($products_ids);

                Briox_Integration_Woo_Logger::log('debug', sprintf('Got %d products from WooCommerce and starting sync to Briox', $number_to_sync));

                if ($number_to_sync > 0) {
                    $products_added = array();
                    foreach ($products_ids as $original_product_id) {
                        if ($default_lang) {
                            $product_id = apply_filters('wpml_object_id', $original_product_id, 'product', true, $default_lang);
                            if (!in_array($product_id, $products_added)) {
                                do_action('briox_push_product_to_queue', $product_id);
                                $products_added[] = $product_id;
                                if ($product_id != $original_product_id) {
                                    Briox_Integration_Woo_Logger::log('debug', sprintf('Added product id %s to the sync queue instead of product id %s as the default language is %s', $product_id, $original_product_id, $default_lang));
                                }
                            } else {
                                Briox_Integration_Woo_Logger::log('debug', sprintf('Skipping product id %s as it was a language duplicate for product id %s', $original_product_id, $product_id));
                            }
                        } else {
                            do_action('briox_push_product_to_queue', $original_product_id);
                        }
                    }
                    if ($default_lang) {
                        Briox_Integration_Woo_Logger::log('debug', sprintf('Added %d products to queue for updating Briox', count($products_added)));
                    }
                }

                do_action('briox_dispatch_queue');

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug', print_r($t, true));
                }
            }

            return false;
        }
    }

    class Briox_WC_Product_Sync extends WP_Background_Process
    {
        private $customer;

        /**
         * @var string
         */
        protected $action = 'briox_wc_product_sync';

        public function task($product)
        {
            try {

                if ($product_id = $product->get_id()) {

                    Briox_Integration_Woo_Logger::log('debug', sprintf('Starting to process WooCommerce product %s', $product_id));

                    $product_type = $product->get_type();

                    if ('variable' == $product_type) {
                        $variations = $this->get_all_variations($product);
                        foreach ($variations as $variation) {
                            if (!is_object($variation)) {
                                $variation = wc_get_product($variation['variation_id']);
                            }
                            do_action('briox_sync_product_to_item', $variation);
                        }
                    }

                    if ('simple' == $product_type) {
                        do_action('briox_sync_product_to_item', $product);
                    }

                }

            } catch (Throwable $t) {
                if (method_exists($t, 'write_to_logs')) {
                    $t->write_to_logs();
                } else {
                    Briox_Integration_Woo_Logger::log('debug', print_r($t, true));
                }
            }

            return false;
        }

        /**
         * Get an array of available variations for the current product.
         * Use our own to get all variations regardless of filtering
         *
         * @return array
         */
        public function get_all_variations($product)
        {
            $available_variations = array();

            foreach ($product->get_children() as $child_id) {
                $variation = wc_get_product($child_id);

                $available_variations[] = $product->get_available_variation($variation);
            }
            $available_variations = array_values(array_filter($available_variations));

            return $available_variations;
        }

        protected function complete()
        {
            parent::complete();
            Briox_Integration_Woo_Logger::log('debug', 'Product sync to Briox is complete');
        }

    }

    new Briox_Integration_Woo_WC_Product_Handler();

}
