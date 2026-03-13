<?php
/**
 * Waitlist System
 * Handles waitlist form submissions and admin management.
 */

defined( 'ABSPATH' ) || exit;

// ─── AJAX handlers ───────────────────────────────────────────────────────────
add_action( 'wp_ajax_wctb_submit_waitlist',        'wctb_handle_waitlist_submit' );
add_action( 'wp_ajax_nopriv_wctb_submit_waitlist', 'wctb_handle_waitlist_submit' );

function wctb_handle_waitlist_submit() {
    check_ajax_referer( 'wctb_waitlist_nonce', 'nonce' );

    $product_id = absint( $_POST['product_id'] ?? 0 );
    $name       = sanitize_text_field( $_POST['name']      ?? '' );
    $email      = sanitize_email(      $_POST['email']     ?? '' );
    $phone      = sanitize_text_field( $_POST['phone']     ?? '' );
    $travelers  = absint(              $_POST['travelers']  ?? 1  );

    if ( ! $product_id || ! $name || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'Please fill in all required fields.', 'wc-tour-booking' ) ] );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'wctb_waitlist';

    $inserted = $wpdb->insert( $table, [
        'product_id' => $product_id,
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'travelers'  => $travelers,
        'status'     => 'pending',
    ], [ '%d', '%s', '%s', '%s', '%d', '%s' ] );

    if ( ! $inserted ) {
        wp_send_json_error( [ 'message' => __( 'Could not save your request. Please try again.', 'wc-tour-booking' ) ] );
    }

    // Email admin
    $product = wc_get_product( $product_id );
    $subject = sprintf( __( '[%s] New Waitlist Request – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), $product ? $product->get_name() : "Product #$product_id" );
    $body    = sprintf(
        "Name: %s\nEmail: %s\nPhone: %s\nTravelers: %d\nTour: %s",
        $name, $email, $phone, $travelers, $product ? $product->get_name() : "Product #$product_id"
    );
    wp_mail( get_option( 'admin_email' ), $subject, $body );

    // Confirmation to user
    wp_mail( $email, __( 'You have been added to the waitlist', 'wc-tour-booking' ),
        sprintf( __( "Hi %s,\n\nYou are on the waitlist for %s.\nWe'll contact you if space becomes available.\n\nThanks!", 'wc-tour-booking' ),
            $name, $product ? $product->get_name() : '' )
    );

    wp_send_json_success( [ 'message' => __( 'You have been added to the waitlist. We will contact you if a spot opens up.', 'wc-tour-booking' ) ] );
}

// ─── Admin menu page ──────────────────────────────────────────────────────────
add_action( 'admin_menu', 'wctb_waitlist_admin_menu' );
function wctb_waitlist_admin_menu() {
    add_submenu_page(
        'woocommerce',
        __( 'Tour Waitlist', 'wc-tour-booking' ),
        __( 'Tour Waitlist', 'wc-tour-booking' ),
        'manage_woocommerce',
        'wctb-waitlist',
        'wctb_waitlist_admin_page'
    );
}

function wctb_waitlist_admin_page() {
    global $wpdb;
    $table = $wpdb->prefix . 'wctb_waitlist';

    // Handle status update
    if ( isset( $_POST['wctb_waitlist_action'], $_POST['wctb_waitlist_nonce'] )
        && wp_verify_nonce( $_POST['wctb_waitlist_nonce'], 'wctb_waitlist_action' ) ) {

        $entry_id = absint( $_POST['entry_id'] );
        $action   = sanitize_text_field( $_POST['wctb_waitlist_action'] );

        if ( in_array( $action, [ 'approved', 'rejected' ] ) ) {
            $wpdb->update( $table, [ 'status' => $action ], [ 'id' => $entry_id ], [ '%s' ], [ '%d' ] );
        }
    }

    $entries = $wpdb->get_results( "SELECT w.*, p.post_title AS tour_name FROM {$table} w LEFT JOIN {$wpdb->posts} p ON p.ID = w.product_id ORDER BY w.created_at DESC" );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Tour Waitlist', 'wc-tour-booking' ); ?></h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Tour', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Phone', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Travelers', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Date', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'wc-tour-booking' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'wc-tour-booking' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $entries as $e ) : ?>
                <tr>
                    <td><?php echo esc_html( $e->tour_name ?: "#{$e->product_id}" ); ?></td>
                    <td><?php echo esc_html( $e->name ); ?></td>
                    <td><?php echo esc_html( $e->email ); ?></td>
                    <td><?php echo esc_html( $e->phone ); ?></td>
                    <td><?php echo esc_html( $e->travelers ); ?></td>
                    <td><?php echo esc_html( $e->created_at ); ?></td>
                    <td><span class="wctb-status wctb-status--<?php echo esc_attr( $e->status ); ?>"><?php echo esc_html( ucfirst( $e->status ) ); ?></span></td>
                    <td>
                        <?php if ( $e->status === 'pending' ) : ?>
                        <form method="post" style="display:inline;">
                            <?php wp_nonce_field( 'wctb_waitlist_action', 'wctb_waitlist_nonce' ); ?>
                            <input type="hidden" name="entry_id" value="<?php echo esc_attr( $e->id ); ?>">
                            <button name="wctb_waitlist_action" value="approved" class="button button-primary button-small"><?php esc_html_e( 'Approve', 'wc-tour-booking' ); ?></button>
                            <button name="wctb_waitlist_action" value="rejected" class="button button-small"><?php esc_html_e( 'Reject', 'wc-tour-booking' ); ?></button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
