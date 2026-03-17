<?php
/**
 * Seat Availability
 * Calculates available seats by subtracting confirmed travelers from maximum capacity.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Count confirmed travelers for a product, optionally filtered by tour date.
 *
 * @param int    $product_id
 * @param string $date  Optional. Value of _wctb_selected_date (e.g. "2026-03-18 to 2026-03-20").
 *                      Pass an empty string to count across all dates.
 * @return int
 */
function wctb_get_confirmed_travelers( int $product_id, string $date = '' ): int {
    $count = 0;

    $orders = wc_get_orders( [
        'status' => [ 'pending', 'processing', 'completed', 'on-hold' ],
        'limit'  => -1,
        'return' => 'ids',
    ] );

    foreach ( $orders as $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) continue;

        foreach ( $order->get_items() as $item ) {
            if ( (int) $item->get_product_id() !== $product_id ) continue;

            // When a date filter is supplied, skip items that belong to a different date.
            if ( $date !== '' ) {
                $item_date = (string) $item->get_meta( '_wctb_selected_date' );
                if ( $item_date !== $date ) continue;
            }

            // Number of travelers stored in item meta; fall back to quantity.
            $travelers = (int) $item->get_meta( '_wctb_traveler_count' );
            $count    += $travelers > 0 ? $travelers : (int) $item->get_quantity();
        }
    }

    return $count;
}

/**
 * Get available seats for a product, optionally scoped to a specific tour date.
 *
 * @param int    $product_id
 * @param string $date  Optional. See wctb_get_confirmed_travelers().
 * @return int  0 means full; negative means overbooked.
 */
function wctb_get_available_seats( int $product_id, string $date = '' ): int {
    $max       = (int) get_post_meta( $product_id, '_wctb_max_travelers', true );
    $confirmed = wctb_get_confirmed_travelers( $product_id, $date );
    return max( 0, $max - $confirmed );
}

/**
 * Determine the button type for a product, optionally scoped to a specific tour date.
 *
 * @param int    $product_id
 * @param string $date  Optional. See wctb_get_available_seats().
 * @return string  'book_now' | 'waitlist' | 'inquiry'
 */
function wctb_get_button_type( int $product_id, string $date = '' ): string {
    $override = get_post_meta( $product_id, '_wctb_button_type', true );

    if ( $override && $override !== 'auto' ) {
        return $override;
    }

    $available = wctb_get_available_seats( $product_id, $date );
    return $available > 0 ? 'book_now' : 'waitlist';
}
