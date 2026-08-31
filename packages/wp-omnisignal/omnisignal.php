<?php

/**
 * Plugin Name: OmniSignal &bull; Server-Side Conversion Tracking & CAPI for WooCommerce & WordPress
 * Plugin URI: https://omnisignal.dev
 * Description: Pure Signal. Zero Noise. Attribution Nirvana for WooCommerce & WordPress. Recover lost ad conversions across Google Ads, Meta CAPI, TikTok, LinkedIn, and Microsoft Ads.
 * Version: 2.0.0
 * Author: OmniSignal
 * Author URI: https://omnisignal.dev
 * Text Domain: omnisignal
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 7.0
 * WC tested up to: 9.3
 * License: GPLv2 or later
 */
if (! defined('ABSPATH')) {
    exit;
}

define('OMNISIGNAL_VERSION', '2.0.0');
define('OMNISIGNAL_PATH', plugin_dir_path(__FILE__));
define('OMNISIGNAL_URL', plugin_dir_url(__FILE__));
define('OMNISIGNAL_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once OMNISIGNAL_PATH.'includes/class-omnisignal-tracker.php';
require_once OMNISIGNAL_PATH.'includes/class-omnisignal-api.php';
require_once OMNISIGNAL_PATH.'includes/class-omnisignal-woocommerce.php';
require_once OMNISIGNAL_PATH.'includes/class-omnisignal-forms.php';
require_once OMNISIGNAL_PATH.'includes/class-omnisignal-admin.php';

// Declare High-Performance Order Storage (HPOS) Compatibility for WooCommerce
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Add Settings Link to Plugins Table
add_filter('plugin_action_links_'.OMNISIGNAL_PLUGIN_BASENAME, function ($links) {
    $settings_link = '<a href="'.admin_url('admin.php?page=omnisignal-settings').'">' . __('Settings', 'omnisignal') . '</a>';
    $docs_link = '<a href="https://omnisignal.dev/docs#woocommerce" target="_blank">' . __('Docs', 'omnisignal') . '</a>';
    array_unshift($links, $settings_link, $docs_link);
    return $links;
});

add_action('plugins_loaded', function () {
    OmniSignal_Tracker::init();
    OmniSignal_WooCommerce::init();
    OmniSignal_Forms::init();
    if (is_admin()) {
        OmniSignal_Admin::init();
    }
});
