<?php
/**
 * Provides functions for the plugin settings page in the WordPress admin.
 *
 * Settings can be accessed at WooCommerce -> Settings -> Briox Integration.
 *
 * @package   WooCommerce_Briox_Integration
 * @author    BjornTech <hello@bjorntech.com>
 * @license   GPL-3.0
 * @link      http://bjorntech.com
 * @copyright 2017-2019 BjornTech
 */
// Prevent direct file access
defined('ABSPATH') || exit;

if (!class_exists('Briox_integration_Woo_Settings', false)) {

/**
 * Briox_integration_Woo_Settings.
 */
    class Briox_integration_Woo_Settings extends WC_Settings_Page
    {

        private $license;
        private $fn;
        private $account_selection = array();

        /**
         * Constructor.
         */
        public function __construct()
        {
            $this->id = 'briox_integration';
            $this->label = __('Briox Integration', 'briox-integration-woo');
            add_action('woocommerce_settings_briox_connection_options', array($this, 'show_connection_button'), 20);
            add_action('woocommerce_settings_briox_advanced_options', array($this, 'show_clear_cache_button'), 10);
            add_filter('briox_get_sections', array($this, 'add_payment_options_settings_section'), 70);
            add_filter('briox_get_sections', array($this, 'add_accounts_settings_section'), 90);
            $this->account_selection = apply_filters('briox_get_account_selection', array());

            parent::__construct();
        }

        /**
         * Get sections.
         *
         * @return array
         */
        public function get_sections()
        {
            $sections = array(
                '' => __('Connection', 'briox-integration-woo'),
                'wc_order' => __('Orders', 'briox-integration-woo'),
            );

            $sections = apply_filters('briox_get_sections', $sections);

            $sections = array_merge($sections, array(
                'advanced' => __('Advanced', 'briox-integration-woo'),
            ));

            return apply_filters('woocommerce_get_sections_' . $this->id, $sections);
        }

        public function add_payment_options_settings_section($sections)
        {
            if (!array_key_exists('payment_options', $sections)) {
                $sections = array_merge($sections, array('payment_options' => __('Payment', 'briox-integration-woo')));
            }
            return $sections;
        }
        
        public function add_accounts_settings_section($sections)
        {
            if (!array_key_exists('accounts', $sections)) {
                $sections = array_merge($sections, array('accounts' => __('Accounts', 'briox-integration-woo')));
            }
            return $sections;
        }

        public function show_connection_button()
        {
            $connected = apply_filters('briox_is_connected', false);

            echo '<div id=briox_titledesc_connect>';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            if (!$connected) {
                echo '<label for="briox_connect">' . __('Connect to Briox', 'briox-integration-woo') . '<span class="woocommerce-help-tip" data-tip="' . __('Connect the plugn to Briox', 'briox-integration-woo') . '"></span></label>';
            } else {
                echo '<label for="briox_disconnect">' . __('Disconnect from Briox', 'briox-integration-woo') . '<span class="woocommerce-help-tip" data-tip="' . __('Disconnect the plugin from Briox', 'briox-integration-woo') . '"></span></label>';
            }
            echo '</th>';
            echo '<td class="forminp forminp-button">';
            if (!$connected) {
                echo '<button name="briox_connect" id="briox_connect" class="button briox_connection">' . __('Connect', 'briox-integration-woo') . '</button>';
            } else {
                echo '<button name="briox_disconnect" id="briox_disconnect" class="button briox_connection">' . __('Disconnect', 'briox-integration-woo') . '</button>';
            }
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        public function show_clear_cache_button()
        {
            echo '<div id=briox_titledesc_clear_cache>';
            echo '<tr valign="top">';
            echo '<th scope="row" class="titledesc">';
            echo '<label for="briox_clear_cache">' . __('Clear static data cache', 'briox-integration-woo') . '<span class="woocommerce-help-tip" data-tip="' . __('Clear the cache for static data imported from Briox. The next pageload after clearing will take a little bit longer time than normal.', 'briox-integration-woo') . '"></span></label>';
            echo '</th>';
            echo '<td class="forminp forminp-button">';
            echo '<button name="briox_clear_cache" id="briox_clear_cache" class="button">' . __('Clear', 'briox-integration-woo') . '</button>';
            echo '</td>';
            echo '</tr>';
            echo '</div>';
        }

        /**
         * Output the settings.
         */
        public function output()
        {
            global $current_section;
            $settings = $this->get_settings($current_section);
            WC_Admin_Settings::output_fields($settings);
        }

        /**
         * Save settings.
         */
        public function save()
        {
            global $current_section;

            $settings = $this->get_settings($current_section);
            WC_Admin_Settings::save_fields($settings);
        }

        public function get_payment_methods()
        {
            $options = array();
            $objects = apply_filters('briox_get_payment_methods', array());
            if (!empty($objects)) {
                $options[''] = __('Select payment method', 'briox-integration-woo');
                foreach ($objects as $object) {
                    $options[$object['account']] = Briox_integration_Woo_Util::get_language_text($object);
                }
            }
            return $options;
        }

        public function get_payment_terms()
        {
            $options = array();
            $objects = apply_filters('briox_get_terms_of_payments', array());
            if (!empty($objects)) {
                $options[''] = __('Select payment term', 'briox-integration-woo');
                foreach ($objects as $object) {
                    $options[$object['id']] = Briox_integration_Woo_Util::get_language_text($object);
                }
            }
            return $options;
        }

        /**
         * Get settings array.
         *
         * @param string $current_section Current section name.
         * @return array
         */
        public function get_settings($current_section = '')
        {
            $settings = array();
            if ('payment_options' == $current_section) {
                if (WC()->payment_gateways()) {

                    $payment_gateways = apply_filters('briox_payment_gateways', WC()->payment_gateways->payment_gateways());
                    foreach ($payment_gateways as $key => $payment_gateway) {
                        $payment_methods = apply_filters('briox_get_payment_methods', array());
                        if (!empty($payment_methods)) {
                            $payment_method = get_option('briox_payment_method_' . $key);
                            foreach ($payment_methods as $terms_of_payment) {
                                if (get_option('briox_payment_method_' . $key) && $terms_of_payment['account'] == $payment_method) {
                                    update_option('briox_payment_account_' . $key, Briox_integration_Woo_Util::get_language_text($terms_of_payment,'description'));
                                }
                            }
                        }

                        $description = (($title = $payment_gateway->get_title()) ? $title : $payment_gateway->get_method_title());
                        $section_settings = array(
                            array(
                                'title' => sprintf(__('Settings for %s', 'briox-integration-woo'), $description),
                                'type' => 'title',
                                'desc' => '',
                                'id' => 'briox_payment_section_' . $key,
                            ),
                            array(
                                'title' => __('Term of payment', 'briox-integration-woo'),
                                'type' => 'select',
                                'default' => '',
                                'options' => $this->get_payment_terms(),
                                'id' => 'briox_term_of_payment_' . $key,
                            ),
                            array(
                                'title' => __('Payment methods', 'briox-integration-woo'),
                                'type' => 'select',
                                'default' => '',
                                'options' => $this->get_payment_methods(),
                                'id' => 'briox_payment_method_' . $key,
                            ),
                            array(
                                'title' => __('Automatic payment', 'briox-integration-woo'),
                                'type' => 'checkbox',
                                'default' => '',
                                'id' => 'briox_automatic_payment_' . $key,
                            ),
                            array(
                                'type' => 'sectionend',
                                'id' => 'briox_payment_section_' . $key,
                            ),
                        );
                        $settings = array_merge($settings, $section_settings);

                    }
                }
            } elseif ('accounts' === $current_section) {
                $settings = array(
                    array(
                        'title' => __('Sales Accounts', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_sales_accounts',
                    ),
                    array(
                        'title' => __('Domestic sales 25%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3001,
                        'options' => $this->account_selection,
                        'id' => 'briox_se_25_vat',
                    ),
                    array(
                        'title' => __('Domestic sales 12%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3002,
                        'options' => $this->account_selection,
                        'id' => 'briox_se_12_vat',
                    ),
                    array(
                        'title' => __('Domestic sales 6%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3003,
                        'options' => $this->account_selection,
                        'id' => 'briox_se_6_vat',
                    ),
                    array(
                        'title' => __('Export sales', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3105,
                        'options' => $this->account_selection,
                        'id' => 'briox_export_vat',
                    ),
                    array(
                        'title' => __('Sales in EU', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3106,
                        'options' => $this->account_selection,
                        'id' => 'briox_eu_incl_vat',
                    ),
                    array(
                        'title' => __('Sales in EU (reversed VAT)', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3108,
                        'options' => $this->account_selection,
                        'id' => 'briox_eu_excl_vat',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_sales_accounts',
                    ),
                    array(
                        'title' => __('Sales Accounts (virtual products)', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_sales_accounts_virtual',
                    ),
                    array(
                        'title' => __('Domestic sales 25%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3001,
                        'options' => $this->account_selection,
                        'id' => 'briox_se_25_vat_virtual',
                    ),
                    array(
                        'title' => __('Domestic sales 12%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3002,
                        'options' => $this->account_selection,
                        'id' => 'briox_se_12_vat_virtual',
                    ),
                    array(
                        'title' => __('Domestic sales 6%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3003,
                        'options' => $this->account_selection,
                        'id' => 'briox_se_6_vat_virtual',
                    ),
                    array(
                        'title' => __('Sales in rest of the world', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3305,
                        'options' => $this->account_selection,
                        'id' => 'briox_world_vat_virtual',
                    ),
                    array(
                        'title' => __('Sales in EU (incl. VAT)', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3306,
                        'options' => $this->account_selection,
                        'id' => 'briox_eu_incl_vat_virtual',
                    ),
                    array(
                        'title' => __('Sales in EU (excl. VAT)', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3308,
                        'options' => $this->account_selection,
                        'id' => 'briox_eu_excl_vat_virtual',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_sales_accounts_virtual',
                    ),
                    array(
                        'title' => __('Shipping Accounts', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_shipping_accounts',
                    ),
                    array(
                        'title' => __('Shipping Sweden 25%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3520,
                        'options' => $this->account_selection,
                        'id' => 'briox_se_25_shipping',
                    ),
                    array(
                        'title' => __('Shipping EU', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3521,
                        'options' => $this->account_selection,
                        'id' => 'briox_eu_incl_shipping',
                    ),
                    array(
                        'title' => __('Shipping Export', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => 3522,
                        'options' => $this->account_selection,
                        'id' => 'briox_export_shipping',
                    ),
                    array(
                        'title' => __('Shipping Sweden 6%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => '',
                        'options' => $this->account_selection,
                        'id' => 'briox_se_6_shipping',
                    ),
                    array(
                        'title' => __('Shipping Sweden 12%', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => '', 
                        'options' => $this->account_selection,
                        'id' => 'briox_se_12_shipping',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_shipping_accounts',
                    ),
                );
            } elseif ('advanced' === $current_section) {
                $costcenter_choice[''] = __('No cost center', 'briox-integration-woo');
                $costcenters = apply_filters('briox_get_cost_centers', array());
                if (!empty($costcenters)) {
                    foreach ($costcenters as $costcenter) {
                        $costcenter_choice[$costcenter['code']] = Briox_integration_Woo_Util::get_language_text($costcenter, 'text');
                    }
                }

                $project_choice[''] = __('No project', 'briox-integration-woo');
                $projects = apply_filters('briox_get_projects', array());
                if (!empty($projects)) {
                    foreach ($projects as $project) {
                        $project_choice[$project['ProjectNumber']] = $project['Description'];
                    }
                }

                $settings = array(
                    array(
                        'title' => __('Advanced options', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_advanced_options',
                    ),
                    array(
                        'title' => __('Enable logging', 'briox-integration-woo'),
                        'default' => '',
                        'type' => 'checkbox',
                        'id' => 'briox_logging',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_advanced_options',
                    ),
                    array(
                        'title' => __('Advanced order options', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_advanced_order_options',
                    ),
                    array(
                        'title' => __('Invoice amounts excl tax', 'briox-integration-woo'),
                        'default' => '',
                        'type' => 'checkbox',
                        'id' => 'briox_amounts_excl_tax',
                    ),
                    array(
                        'title' => __('Use cost center', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => '',
                        'options' => $costcenter_choice,
                        'id' => 'briox_cost_center',
                    ),
                    array(
                        'title' => __('Use project', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => '',
                        'options' => $project_choice,
                        'id' => 'briox_project',
                    ),
                    array(
                        'title' => __('Delivery days', 'briox-integration-woo'),
                        'type' => 'number',
                        'desc' => __('Number of working days between the order date and the delivery date to be set on the Briox order or invoice.', 'briox-integration-woo'),
                        'desc_tip' => true,
                        'default' => 0,
                        'id' => 'briox_default_delivery_days',
                    ),
                    array(
                        'title' => __('Handle missing articles by', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => '',
                        'desc' => __('Select what should happen if a WooCommerce order contains a product that does not exist as an Article in Briox', 'briox-integration-woo'),
                        'desc_tip' => true,
                        'options' => array(
                            '' => __('Adding the product to the Order/Invoice wihtout an Article number', 'briox-integration-woo'),
                            'error' => __('Stop the processing of the Order/Invoice and log an error in the error-log', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_no_articlenumber_in_orderrow',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_advanced_order_options',
                    ),
                    array(
                        'title' => __('Advanced product options', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_advanced_product_options',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_advanced_product_options',
                    ),
                    array(
                        'title' => __('Advanced price & stocklevel options', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_advanced_price_stocklevel_options',
                    ),
                    array(
                        'title' => __('Update product type', 'briox-integration-woo'),
                        'type' => 'checkbox',
                        'desc_tip' => true,
                        'desc' => __('Update product type (stock or if virtual is set service) in Briox from WooCommerce', 'briox-integration-woo'),
                        'default' => '',
                        'id' => 'briox_wc_product_update_type',
                    ),
                    array(
                        'title' => __('Set backorder option instock', 'briox-integration-woo'),
                        'type' => 'select',
                        'desc_tip' => __('Select how a product imported from Briox should handle backorders when the product has stock level > 0.', 'briox-integration-woo'),
                        'default' => '',
                        'options' => array(
                            '' => __('Do not set from Briox', 'briox-integration-woo'),
                            'no' => __('Do not allow', 'briox-integration-woo'),
                            'notify' => __('Allow, but notify customer', 'briox-integration-woo'),
                            'yes' => __('Allow', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_backorder_option_instock',
                    ),
                    array(
                        'title' => __('Set backorder option outofstock', 'briox-integration-woo'),
                        'type' => 'select',
                        'desc_tip' => __('Select how a product imported from Briox should handle backorders when the product has stock level <= 0', 'briox-integration-woo'),
                        'default' => '',
                        'options' => array(
                            '' => __('Do not set from Briox', 'briox-integration-woo'),
                            'no' => __('Do not allow', 'briox-integration-woo'),
                            'notify' => __('Allow, but notify customer', 'briox-integration-woo'),
                            'yes' => __('Allow', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_backorder_option_outofstock',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_advanced_price_stocklevel_options',
                    ),
                    array(
                        'title' => __('Advanced customer options', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_advanced_customer_options',
                    ),
                    array(
                        'title' => __('Do not update billing details', 'briox-integration-woo'),
                        'type' => 'checkbox',
                        'default' => '',
                        'desc' => __('Check if you do not want want Briox customer billing details to be updated from latest order on existing customers.', 'briox-integration-woo'),
                        'desc_tip' => true,
                        'id' => 'briox_do_not_update_customer_billing',
                    ),
                    array(
                        'title' => __('Do not update delivery details', 'briox-integration-woo'),
                        'type' => 'checkbox',
                        'default' => '',
                        'desc' => __('Check if you do not want want Briox customer delivery details to be updated from latest order on existing customers.', 'briox-integration-woo'),
                        'desc_tip' => true,
                        'id' => 'briox_do_not_update_customer_delivery',
                    ),
                    array(
                        'title' => __('Send invoice from Briox', 'briox-integration-woo'),
                        'default' => '',
                        'type' => 'checkbox',
                        'id' => 'briox_send_customer_email_invoice',
                    ),
                    array(
                        'title' => __('Reply-adress', 'briox-integration-woo'),
                        'type' => 'email',
                        'default' => '',
                        'id' => 'fornox_invoice_email_from',
                    ),
                    array(
                        'title' => __('E-mail subject', 'briox-integration-woo'),
                        'type' => 'text',
                        'desc' => __('Subject text on the Briox mail containing the invoice. The variable {no} = document number. The variable {name} =  customer name', 'briox-integration-woo'),
                        'desc_tip' => true,
                        'id' => 'fornox_invoice_email_subject',
                    ),
                    array(
                        'title' => __('E-mail body', 'briox-integration-woo'),
                        'desc' => __('Body text on the Briox mail containing the invoice. The variable {no}  = document number. The variable {name} =  customer name', 'briox-integration-woo'),
                        'id' => 'fornox_invoice_email_body',
                        'desc_tip' => true,
                        'css' => 'width:100%; height: 65px;',
                        'type' => 'textarea',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_advanced_customer_options',
                    ),
                    array(
                        'title' => __('Advanced connection options', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => __('Do NOT change these settings without prior contact with BjornTech support.', 'briox-integration-woo'),
                        'id' => 'briox_advanced_connection_options',
                    ),
                    array(
                        'title' => __('Service url', 'briox-integration-woo'),
                        'type' => 'text',
                        'description' => __('The url to the BjornTech Briox service. Do NOT change unless instructed by BjornTech.', 'briox-integration-woo'),
                        'default' => '',
                        'desc_tip' => true,
                        'id' => 'briox_service_url'
                    ),
                    array(
                        'title' => __('Check invoices from', 'briox-integration-woo'),
                        'default' => '',
                        'type' => 'datetime',
                        'desc_tip' => __('Date and time (in the format YYYY-MM-DD HH:MM) when the plugin last checked Briox for changed invoices.', 'briox-integration-woo'),
                        'id' => 'briox_integration_sync_last_sync_invoices',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_advanced_connection_options',
                    ),
                );
            } elseif ('wc_order' === $current_section) {
                $settings = array(
                    array(
                        'title' => __('Setup for what should happen when a WooCommerce order is created', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => '',
                        'id' => 'briox_wc_order_options',
                    ),
                    array(
                        'title' => __('WooCommerce order creates', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => '',
                        'options' => array(
                            '' => __('Nothing', 'briox-integration-woo'),
                            'invoice' => __('Briox Invoice', 'briox-integration-woo'),
                            'order' => __('Briox Order', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_woo_order_creates',
                    ),
                    array(
                        'title' => __('Automatic Order/Invoice creation', 'briox-integration-woo'),
                        'type' => 'select',
                        'default' => '',
                        'desc' => __('Create or update a Briox Order/Invoice when a WooCommerce order gets status Processing or Completed', 'briox-integration-woo'),
                        'desc_tip' => true,
                        'options' => array(
                            '' => __('Do not create automatically', 'briox-integration-woo'),
                            'processing' => __('When the WooCommerce order is set to processing', 'briox-integration-woo'),
                            'completed' => __('When the WooCommerce order is set to completed', 'briox-integration-woo'),
                        ),
                        'id' => 'briox_woo_order_create_automatic_from',
                    ),
                    array(
                        'title' => __('Our reference', 'briox-integration-woo'),
                        'type' => 'text',
                        'desc' => __('Enter a text for the field "Our reference"', 'briox-integration-woo'),
                        'desc_tip' => true,
                        'id' => 'fornox_our_reference',
                    ),
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_wc_order_options',
                    ),
                );
            } elseif ($current_section == '') {

                $connected = apply_filters('briox_is_connected', false);

                if (!$connected) {
                $instruction_text =  __('In order to connect this plugin with Briox, the integration module must be active on your Briox-account.<br>', 'briox-integration-woo');
                $instruction_text .=  __('Install the integration module and create an API Code using <a href="https://support.briox.se/hc/sv/articles/208332265-Integrera-Briox-med-ett-annat-system">this guide</a>.<br><br>', 'briox-integration-woo');
                $instruction_text .=  __('Enter the Client identifier, Authentication token and a valid email adress to where the confirmation email is to be sent in the fields below. Then press connect and check your mailbox for the confirmation email.', 'briox-integration-woo');
                } else {
                    $valid_to = strtotime(get_option('briox_valid_to'));
                    $instruction_text = sprintf(__('Connected using Briox authorization code %s<br><br>', 'briox-integration-woo'), get_option('briox_authentication_token'));
                    $instruction_text .= sprintf(__('Your plugin is connected to the BjornTech Briox service and is valid to %s', 'briox-integration-woo'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $valid_to));
                }
    
                $settings = array(
                    array(
                        'title' => __('Connection with Briox', 'briox-integration-woo'),
                        'type' => 'title',
                        'desc' => $instruction_text,
                        'id' => 'briox_connection_options',
                    ),
                );
                $connect_settings = $connected ? array() : array(
                    array(
                        'title' => __('Briox Client identifier', 'briox-integration-woo'),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                        'desc' => __('In order to be able to connect this plugin with Briox a license you do need the Briox Client identifier.', 'briox-integration-woo'),
                        'id' => 'briox_client_identifier',
                    ),
                    array(
                        'title' => __('Briox Authentication token', 'briox-integration-woo'),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                        'desc' => __('In order to be able to connect this plugin with Briox a license you do need the Briox Authentication token.', 'briox-integration-woo'),
                        'id' => 'briox_authentication_token',
                    ),
                    array(
                        'title' => __('Briox User email', 'briox-integration-woo'),
                        'type' => 'email',
                        'default' => '',
                        'desc_tip' => true,
                        'desc' => __('Enter an email adress to where you want the configmation e-mail to be sent.', 'briox-integration-woo'),
                        'id' => 'briox_user_email',
                    ),
                );
                $general_settings = array(
                    array(
                        'type' => 'sectionend',
                        'id' => 'briox_connection_options',
                    ),
                );

                $settings = array_merge($settings, $connect_settings, $general_settings);
            }

            return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
        }

    }

    return new Briox_integration_Woo_Settings();
}
