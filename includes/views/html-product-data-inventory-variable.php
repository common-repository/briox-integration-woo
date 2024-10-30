<?php
/**
 * Adds a barcode-field.
 *
 * @package WooCommerce\Admin
 */

defined('ABSPATH') || exit;

woocommerce_wp_text_input(
    array(
        'id' => "_briox_article_number_{$loop}",
        'value' => get_post_meta($variation->ID, '_briox_article_number', true),
        'label' => '<abbr title="' . esc_attr__('Briox Article number', 'briox-integration-woo') . '">' . esc_html__('Article number', 'briox-integration-woo') . '</abbr>',
        'desc_tip' => true,
        'description' => __('Briox Article number', 'briox-integration-woo'),
    )
);
