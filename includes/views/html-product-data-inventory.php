<?php
/**
 * Adds iZettle specific fields.
 *
 * @package WooCommerce\Admin
 */

defined('ABSPATH') || exit;

echo '<div id="briox_product_data" class="panel woocommerce_options_panel">'; 

woocommerce_wp_text_input(
    array(
        'id' => '_briox_article_number',
        'value' =>  get_post_meta($product_object->get_id(), '_briox_article_number', true),
        'label' => '<abbr title="' . esc_attr__('Briox Article number','briox-integration-woo')  . '">' . esc_html__('Article number','briox-integration-woo') . '</abbr>',
        'desc_tip' => true,
        'description' => __('Briox Article number','briox-integration-woo'),
    )
);

echo '</div>';
