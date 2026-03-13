<?php
/**
 * Admin Settings
 * General plugin settings under WooCommerce > Tour Settings.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', 'wctb_admin_settings_menu' );
function wctb_admin_settings_menu() {
    add_submenu_page(
        'woocommerce',
        __( 'Tour Booking Settings', 'wc-tour-booking' ),
        __( 'Tour Settings', 'wc-tour-booking' ),
        'manage_woocommerce',
        'wctb-settings',
        'wctb_settings_page'
    );
}

function wctb_settings_page() {
    if ( isset( $_POST['wctb_settings_nonce'] ) && wp_verify_nonce( $_POST['wctb_settings_nonce'], 'wctb_save_settings' ) ) {
        update_option( 'wctb_currency_symbol',          sanitize_text_field( $_POST['wctb_currency_symbol'] ?? get_woocommerce_currency_symbol() ) );
        update_option( 'wctb_max_travelers_per_booking', absint( $_POST['wctb_max_travelers_per_booking'] ?? 10 ) );
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'wc-tour-booking' ) . '</p></div>';
    }

    $currency_symbol         = get_option( 'wctb_currency_symbol', get_woocommerce_currency_symbol() );
    $max_travelers_per_booking = get_option( 'wctb_max_travelers_per_booking', 10 );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Tour Booking Settings', 'wc-tour-booking' ); ?></h1>
        <form method="post">
            <?php wp_nonce_field( 'wctb_save_settings', 'wctb_settings_nonce' ); ?>
            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Currency Symbol', 'wc-tour-booking' ); ?></th>
                    <td>
                        <input type="text" name="wctb_currency_symbol"
                               value="<?php echo esc_attr( $currency_symbol ); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e( 'Symbol shown in traveler price summaries.', 'wc-tour-booking' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Max Travelers Per Booking', 'wc-tour-booking' ); ?></th>
                    <td>
                        <input type="number" name="wctb_max_travelers_per_booking" min="1" max="100"
                               value="<?php echo esc_attr( $max_travelers_per_booking ); ?>" class="small-text" />
                        <p class="description"><?php esc_html_e( 'Maximum number of travelers a single customer can book at once.', 'wc-tour-booking' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
