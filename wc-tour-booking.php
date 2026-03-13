<?php
/**
 * Plugin Name: WooCommerce Tour Booking
 * Plugin URI:  https://yoursite.com/wc-tour-booking
 * Description: Extends WooCommerce with full tour booking functionality: traveler management, room selection, waitlist, inquiry, and seat availability.
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://yoursite.com
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
