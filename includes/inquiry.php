<?php
/**
 * Inquiry System
 * Handles inquiry form submissions and admin listing.
 */

defined( 'ABSPATH' ) || exit;

// ─── AJAX ─────────────────────────────────────────────────────────────────────
add_action( 'wp_ajax_wctb_submit_inquiry',        'wctb_handle_inquiry_submit' );
add_action( 'wp_ajax_nopriv_wctb_submit_inquiry', 'wctb_handle_inquiry_submit' );

function wctb_handle_inquiry_submit() {
    check_ajax_referer( 'wctb_inquiry_nonce', 'nonce' );

    $product_id = absint( $_POST['product_id'] ?? 0 );
    $name       = sanitize_text_field( $_POST['name']    ?? '' );
    $email      = sanitize_email(      $_POST['email']   ?? '' );
    $phone      = sanitize_text_field( $_POST['phone']   ?? '' );
    $message    = sanitize_textarea_field( $_POST['message'] ?? '' );

    if ( ! $product_id || ! $name || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'Please fill in all required fields.', 'wc-tour-booking' ) ] );
    }

    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'wctb_inquiry',
        [
            'product_id' => $product_id,
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'message'    => $message,
        ],
        [ '%d', '%s', '%s', '%s', '%s' ]
    );

    $product = wc_get_product( $product_id );
    $subject = sprintf( __( '[%s] New Tour Inquiry – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), $product ? $product->get_name() : "Product #$product_id" );
    $body    = "Name: $name\nEmail: $email\nPhone: $phone\nMessage:\n$message";
    wp_mail( get_option( 'admin_email' ), $subject, $body );

    wp_send_json_success( [ 'message' => __( 'Thank you! Your inquiry has been sent. We will be in touch shortly.', 'wc-tour-booking' ) ] );
}

// ─── Admin menu page ──────────────────────────────────────────────────────────
add_action( 'admin_menu', 'wctb_inquiry_admin_menu' );
function wctb_inquiry_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __( 'Tour Inquiries', 'wc-tour-booking' ),
        __( 'Tour Inquiries', 'wc-tour-booking' ),
        'manage_woocommerce',
        'wctb-inquiries',
        'wctb_inquiry_admin_page'
    );
}

function wctb_inquiry_admin_page() {
    global $wpdb;
    $entries = $wpdb->get_results(
        "SELECT i.*, p.post_title AS tour_name
         FROM {$wpdb->prefix}wctb_inquiry i
         LEFT JOIN {$wpdb->posts} p ON p.ID = i.product_id
         ORDER BY i.created_at DESC"
    );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Tour Inquiries', 'wc-tour-booking' ); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Tour', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Phone', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Message', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'wc-tour-booking' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $entries as $e ) : ?>
                <tr>
                    <td><?php echo esc_html( $e->tour_name ?: "#{$e->product_id}" ); ?></td>
                    <td><?php echo esc_html( $e->name ); ?></td>
                    <td><a href="mailto:<?php echo esc_attr( $e->email ); ?>"><?php echo esc_html( $e->email ); ?></a></td>
                    <td><?php echo esc_html( $e->phone ); ?></td>
                    <td><?php echo nl2br( esc_html( $e->message ) ); ?></td>
                    <td><?php echo esc_html( $e->created_at ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
