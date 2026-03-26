<?php
/**
 * Order Meta
 * Displays traveler and room data in admin order pages and emails.
 */

defined( 'ABSPATH' ) || exit;

// ─── Order detail page – traveler panel ──────────────────────────────────────
add_action( 'woocommerce_admin_order_data_after_billing_address', 'wctb_order_admin_traveler_panel' );
function wctb_order_admin_traveler_panel( $order ) {
    $has_tour = false;

    foreach ( $order->get_items() as $item ) {
        $travelers = $item->get_meta( '_wctb_travelers' );
        if ( empty( $travelers ) ) continue;
        $has_tour = true;
        break;
    }

    if ( ! $has_tour ) return;

    echo '<div class="wctb-order-travelers" style="margin-top:20px;">';
    echo '<h3>' . esc_html__( 'Tour Booking Details', 'wc-tour-booking' ) . '</h3>';

    foreach ( $order->get_items() as $item ) {
        $travelers = $item->get_meta( '_wctb_travelers' );
        if ( empty( $travelers ) ) continue;

        $date = $item->get_meta( '_wctb_selected_date' );
        echo '<h4>' . esc_html( $item->get_name() ) . '</h4>';
        if ( $date ) {
            echo '<p><strong>' . esc_html__( 'Tour Date:', 'wc-tour-booking' ) . '</strong> ' . esc_html( $date ) . '</p>';
        }

        echo '<table class="widefat" style="margin-bottom:15px;">';
        echo '<thead><tr>
            <th>#</th>
            <th>' . esc_html__( 'Name', 'wc-tour-booking' ) . '</th>
            <th>' . esc_html__( 'Gender', 'wc-tour-booking' ) . '</th>
            <th>' . esc_html__( 'DOB', 'wc-tour-booking' ) . '</th>
            <th>' . esc_html__( 'Email', 'wc-tour-booking' ) . '</th>
            <th>' . esc_html__( 'Phone', 'wc-tour-booking' ) . '</th>
            <th>' . esc_html__( 'Room', 'wc-tour-booking' ) . '</th>
        </tr></thead><tbody>';

        foreach ( $travelers as $i => $t ) {
            echo '<tr>';
            printf( '<td>%d</td>', $i + 1 );
            printf( '<td>%s</td>', esc_html( $t['name'] ) );
            printf( '<td>%s</td>', esc_html( $t['gender'] ?? '' ) );
            printf( '<td>%s</td>', esc_html( $t['dob'] ?? '' ) );
            printf( '<td>%s</td>', esc_html( $t['email'] ) );
            printf( '<td>%s</td>', esc_html( $t['phone'] ) );
            printf( '<td>%s</td>', esc_html( wctb_room_pair_label( $t ) ) );
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '</div>';
}