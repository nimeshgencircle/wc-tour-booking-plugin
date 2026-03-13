<?php
/**
 * Checkout Handler
 * Manages traveler data in session/cart, custom price calculation,
 * and storing data in order items.
 */

defined( 'ABSPATH' ) || exit;

// ─── 1. Add selected date to cart item (travelers collected at checkout) ───────
add_filter( 'woocommerce_add_cart_item_data', 'wctb_add_cart_item_data', 10, 3 );
function wctb_add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
    // Only store the selected tour date; traveler data is collected on checkout page.
    if ( isset( $_POST['wctb_selected_date'] ) ) {
        $cart_item_data['wctb_selected_date'] = sanitize_text_field( $_POST['wctb_selected_date'] );
    }
    return $cart_item_data;
}

/**
 * Sanitize traveler array from user input.
 */
function wctb_sanitize_travelers( array $raw ): array {
    $clean = [];
    foreach ( $raw as $t ) {
        $clean[] = [
            'name'       => sanitize_text_field( $t['name']      ?? '' ),
            'email'      => sanitize_email(      $t['email']     ?? '' ),
            'phone'      => sanitize_text_field( $t['phone']     ?? '' ),
            'age'        => absint(              $t['age']       ?? 0  ),
            'room_type'  => in_array( $t['room_type'] ?? 'shared', [ 'shared', 'single' ] )
                                ? $t['room_type'] : 'shared',
        ];
    }
    return $clean;
}

// ─── 2. Restore traveler data from session ───────────────────────────────────
add_filter( 'woocommerce_get_cart_item_from_session', 'wctb_get_cart_item_from_session', 10, 2 );
function wctb_get_cart_item_from_session( $cart_item, $values ) {
    if ( isset( $values['wctb_travelers'] ) ) {
        $cart_item['wctb_travelers']      = $values['wctb_travelers'];
        $cart_item['wctb_traveler_count'] = $values['wctb_traveler_count'];
    }
    if ( isset( $values['wctb_selected_date'] ) ) {
        $cart_item['wctb_selected_date'] = $values['wctb_selected_date'];
    }
    return $cart_item;
}

// ─── 3. (Price is now set by wctb_set_price_from_traveler_data below) ────────

// ─── 4. Display traveler summary in cart ─────────────────────────────────────
add_filter( 'woocommerce_get_item_data', 'wctb_cart_item_display', 10, 2 );
function wctb_cart_item_display( $item_data, $cart_item ) {
    if ( empty( $cart_item['wctb_travelers'] ) ) return $item_data;

    // Tour date
    if ( ! empty( $cart_item['wctb_selected_date'] ) ) {
        $item_data[] = [
            'key'   => __( 'Tour Date', 'wc-tour-booking' ),
            'value' => esc_html( $cart_item['wctb_selected_date'] ),
        ];
    }

    // Traveler summary
    foreach ( $cart_item['wctb_travelers'] as $i => $t ) {
        $item_data[] = [
            'key'   => sprintf( __( 'Traveler %d', 'wc-tour-booking' ), $i + 1 ),
            'value' => esc_html( $t['name'] ) . ' – ' . esc_html( wctb_room_pair_label( $t ) ),
        ];
    }

    return $item_data;
}

// ─── 5. Save traveler data to order item meta ─────────────────────────────────
add_action( 'woocommerce_checkout_create_order_line_item', 'wctb_save_order_item_meta', 10, 4 );
function wctb_save_order_item_meta( $item, $cart_item_key, $values, $order ) {
    if ( empty( $values['wctb_travelers'] ) ) return;

    $item->add_meta_data( '_wctb_travelers',      $values['wctb_travelers'],      true );
    $item->add_meta_data( '_wctb_traveler_count', $values['wctb_traveler_count'], true );

    if ( ! empty( $values['wctb_selected_date'] ) ) {
        $item->add_meta_data( '_wctb_selected_date', $values['wctb_selected_date'], true );
    }
}

// ─── 6. Display traveler info in admin order view ────────────────────────────
add_filter( 'woocommerce_hidden_order_itemmeta', 'wctb_hide_raw_meta' );
function wctb_hide_raw_meta( $hidden ) {
    $hidden[] = '_wctb_travelers';
    $hidden[] = '_wctb_traveler_count';
    $hidden[] = '_wctb_selected_date';
    return $hidden;
}

add_action( 'woocommerce_after_order_itemmeta', 'wctb_display_traveler_admin_meta', 10, 3 );
function wctb_display_traveler_admin_meta( $item_id, $item, $product ) {
    $travelers = $item->get_meta( '_wctb_travelers' );
    $date      = $item->get_meta( '_wctb_selected_date' );

    if ( empty( $travelers ) ) return;

    echo '<div class="wctb-admin-travelers" style="margin-top:10px;">';
    if ( $date ) {
        echo '<p><strong>' . esc_html__( 'Tour Date:', 'wc-tour-booking' ) . '</strong> ' . esc_html( $date ) . '</p>';
    }
    echo '<table class="widefat" style="margin-top:5px;">';
    echo '<thead><tr>
        <th>#</th>
        <th>' . esc_html__( 'Name', 'wc-tour-booking' ) . '</th>
        <th>' . esc_html__( 'Email', 'wc-tour-booking' ) . '</th>
        <th>' . esc_html__( 'Phone', 'wc-tour-booking' ) . '</th>
        <th>' . esc_html__( 'Age', 'wc-tour-booking' ) . '</th>
        <th>' . esc_html__( 'Room', 'wc-tour-booking' ) . '</th>
    </tr></thead><tbody>';

    foreach ( $travelers as $i => $t ) {
        echo '<tr>';
        echo '<td>' . esc_html( $i + 1 ) . '</td>';
        echo '<td>' . esc_html( $t['name'] ) . '</td>';
        echo '<td>' . esc_html( $t['email'] ) . '</td>';
        echo '<td>' . esc_html( $t['phone'] ) . '</td>';
        echo '<td>' . esc_html( $t['age'] ) . '</td>';
        echo '<td>' . esc_html( wctb_room_pair_label( $t ) ) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

// ─── 7. Validate seat availability before checkout ────────────────────────────
add_action( 'woocommerce_check_cart_items', 'wctb_validate_cart_capacity' );
function wctb_validate_cart_capacity() {
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( empty( $cart_item['wctb_traveler_count'] ) ) continue;

        $product_id = $cart_item['product_id'];
        $available  = wctb_get_available_seats( $product_id );
        $requested  = (int) $cart_item['wctb_traveler_count'];

        if ( $requested > $available ) {
            $product = wc_get_product( $product_id );
            wc_add_notice(
                sprintf(
                    __( 'Sorry, "%s" only has %d seats available. Please reduce your traveler count.', 'wc-tour-booking' ),
                    $product->get_name(),
                    $available
                ),
                'error'
            );
        }
    }
}

// ─── 8. Parse traveler data from the update_order_review AJAX post ────────────
//
// WooCommerce fires woocommerce_checkout_update_order_review with $post_data
// as a URL-encoded string (e.g. "billing_first_name=...&wctb_checkout_travelers=...")
// $_POST is NOT populated at this point, so we must parse $post_data ourselves.
//
add_action( 'woocommerce_checkout_update_order_review', 'wctb_recalc_price_from_checkout_data' );
function wctb_recalc_price_from_checkout_data( $post_data ) {
    // Parse the URL-encoded string into an array
    $parsed = [];
    parse_str( $post_data, $parsed );

    if ( empty( $parsed['wctb_checkout_travelers'] ) ) return;

    $raw = wp_unslash( $parsed['wctb_checkout_travelers'] );
    $all = json_decode( $raw, true );
    if ( ! is_array( $all ) ) return;

    // Persist in session so woocommerce_before_calculate_totals can use it
    WC()->session->set( 'wctb_checkout_travelers', $all );
}

// ─── 9. Set cart item price from session traveler data ────────────────────────
//
// This is the ONLY place prices are set for tour products.
// It runs on every cart recalculation — including the AJAX order review.
//
add_action( 'woocommerce_before_calculate_totals', 'wctb_set_price_from_traveler_data', 20 );
function wctb_set_price_from_traveler_data( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $session_data = WC()->session ? WC()->session->get( 'wctb_checkout_travelers' ) : null;

    foreach ( $cart->get_cart() as $cart_item ) {
        $pid = $cart_item['product_id'];
        if ( ! get_post_meta( $pid, '_wctb_dates', true ) ) continue;

        $base_price = (float) get_post_meta( $pid, '_wctb_base_price', true );
        $supplement = (float) get_post_meta( $pid, '_wctb_single_supplement', true );

        // Try to find traveler data for this product in session
        $group = null;
        if ( is_array( $session_data ) ) {
            foreach ( $session_data as $g ) {
                if ( (int) ( $g['product_id'] ?? 0 ) === (int) $pid ) {
                    $group = $g;
                    break;
                }
            }
        }

        if ( $group && ! empty( $group['travelers'] ) ) {
            $travelers = $group['travelers'];
            $count     = count( $travelers );
            $singles   = 0;
            foreach ( $travelers as $t ) {
                if ( ( $t['room_type'] ?? 'shared' ) === 'single' ) $singles++;
            }
            $price = ( $base_price * $count ) + ( $supplement * $singles );
        } else {
            // No traveler data yet — use base price × 1 as placeholder
            $price = $base_price;
        }

        $cart_item['data']->set_price( $price );
    }
}
