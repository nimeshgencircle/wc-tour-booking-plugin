<?php
/**
 * Seat Availability
 * Calculates available seats by subtracting confirmed travelers from maximum capacity.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Count confirmed travelers for a product across active orders.
 *
 * @param int $product_id
 * @return int
 */
function wctb_get_confirmed_travelers( int $product_id ): int {
    $count = 0;

    $orders = wc_get_orders( [
        'status'     => [ 'processing', 'completed', 'on-hold' ],
        'limit'      => -1,
        'return'     => 'ids',
    ] );

    foreach ( $orders as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) continue;

        foreach ( $order->get_items() as $item ) {
            if ( (int) $item->get_product_id() !== $product_id ) continue;

            // Number of travelers stored in item meta
            $travelers = (int) $item->get_meta( '_wctb_traveler_count' );
            if ( $travelers > 0 ) {
                $count += $travelers;
            } else {
                // Fall back to quantity
                $count += (int) $item->get_quantity();
            }
        }
    }

    return $count;
}

/**
 * Get available seats for a product.
 *
 * @param int $product_id
 * @return int  Negative means overbooked; 0 means full.
 */
function wctb_get_available_seats( int $product_id ): int {
    $max       = (int) get_post_meta( $product_id, '_wctb_max_travelers', true );
    $confirmed = wctb_get_confirmed_travelers( $product_id );
    return max( 0, $max - $confirmed );
}

/**
 * Determine the button type for a product.
 *
 * @param int $product_id
 * @return string  'book_now' | 'waitlist' | 'inquiry'
 */
function wctb_get_button_type( int $product_id ): string {
    $override = get_post_meta( $product_id, '_wctb_button_type', true );

    if ( $override && $override !== 'auto' ) {
        return $override;
    }

    $available = wctb_get_available_seats( $product_id );
    return $available > 0 ? 'book_now' : 'waitlist';
}
