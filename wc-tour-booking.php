<?php
/**
 * Plugin Name: WooCommerce Tour Booking
 * Plugin URI:  https://encircletechnologies.com/
 * Description: Extends WooCommerce with full tour booking functionality: traveler management, room selection, waitlist, inquiry, and seat availability.
 * Version:     1.0.0
 * Author:      Encircle Technologies
 * Author URI:  https://encircletechnologies.com/
 * Text Domain: wc-tour-booking
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

defined( 'ABSPATH' ) || exit;

// ─── Constants ───────────────────────────────────────────────────────────────
define( 'WCTB_VERSION',  '1.0.0' );
define( 'WCTB_FILE',     __FILE__ );
define( 'WCTB_DIR',      plugin_dir_path( __FILE__ ) );
define( 'WCTB_URL',      plugin_dir_url( __FILE__ ) );
define( 'WCTB_BASENAME', plugin_basename( __FILE__ ) );


// wc-tour-booking.php

/**
 * Declare compatibility with WooCommerce features.
 * Must run before WooCommerce initializes — use 'before_woocommerce_init'.
 */
add_action( 'before_woocommerce_init', 'wctb_declare_woocommerce_compatibility' );
function wctb_declare_woocommerce_compatibility() {
    if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        return;
    }

    // ── HPOS (High-Performance Order Storage) ──────────────────────────────
    // The plugin reads orders via wc_get_orders() and $order->get_items(),
    // which are HPOS-compatible APIs. Safe to declare compatible.
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'custom_order_tables',
        WCTB_FILE,   // path to wc-tour-booking.php — defined as __FILE__ at plugin root
        true         // true = compatible
    );

    // ── Cart & Checkout Blocks ─────────────────────────────────────────────
    // The checkout traveler form is injected via woocommerce_checkout_before_customer_details,
    // which is a shortcode-checkout hook and does NOT fire in the block-based checkout.
    // Declare incompatible so WooCommerce warns users who switch to block checkout.
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
        'cart_checkout_blocks',
        WCTB_FILE,
        false        // false = NOT compatible with block checkout
    );
}

// ─── WooCommerce dependency check ────────────────────────────────────────────
add_action( 'admin_notices', 'wctb_woocommerce_missing_notice' );
function wctb_woocommerce_missing_notice() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        echo '<div class="notice notice-error"><p>'
           . esc_html__( 'WooCommerce Tour Booking requires WooCommerce to be installed and active.', 'wc-tour-booking' )
           . '</p></div>';
    }
}

// ─── Boot ─────────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', 'wctb_init', 20 );
function wctb_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    // Load includes
    require_once WCTB_DIR . 'includes/product-fields.php';
    require_once WCTB_DIR . 'includes/admin-settings.php';
    require_once WCTB_DIR . 'includes/room-logic.php';
    require_once WCTB_DIR . 'includes/checkout-handler.php';
    require_once WCTB_DIR . 'includes/seat-availability.php';
    require_once WCTB_DIR . 'includes/waitlist.php';
    require_once WCTB_DIR . 'includes/inquiry.php';
    require_once WCTB_DIR . 'includes/order-meta.php';
    require_once WCTB_DIR . 'includes/email-handler.php';
    require_once WCTB_DIR . 'includes/frontend.php';
}

// ─── Activation / Deactivation ───────────────────────────────────────────────
register_activation_hook( __FILE__, 'wctb_activate' );
function wctb_activate() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Waitlist table
    $sql_waitlist = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wctb_waitlist (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id  BIGINT(20) UNSIGNED NOT NULL,
        name        VARCHAR(150)        NOT NULL,
        email       VARCHAR(150)        NOT NULL,
        phone       VARCHAR(50)         NOT NULL DEFAULT '',
        travelers   INT                 NOT NULL DEFAULT 1,
        created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        status      VARCHAR(20)         NOT NULL DEFAULT 'pending',
        PRIMARY KEY (id),
        KEY product_id (product_id)
    ) $charset_collate;";

    // Inquiry table
    $sql_inquiry = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}wctb_inquiry (
        id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id  BIGINT(20) UNSIGNED NOT NULL,
        name        VARCHAR(150)        NOT NULL,
        email       VARCHAR(150)        NOT NULL,
        phone       VARCHAR(50)         NOT NULL DEFAULT '',
        message     TEXT,
        created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY product_id (product_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_waitlist );
    dbDelta( $sql_inquiry );

    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, 'wctb_deactivate' );
function wctb_deactivate() {
    flush_rewrite_rules();
}