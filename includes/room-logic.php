<?php
/**
 * Room Logic
 *
 * Pairing rule (mirrors JS pairInfo()):
 *   Pair A : index 0 ↔ index 1
 *   Pair B : index 2 ↔ index 3
 *   Pair C : index 4 ↔ index 5  … etc.
 *
 * Cascade rule:
 *   If either traveler in a pair is Single, the other must also be Single.
 *   (Cascade is enforced by the JS on the frontend; PHP re-validates here.)
 */

defined( 'ABSPATH' ) || exit;

/* ─────────────────────────────────────────────────────────────────────────────
   Pairing helpers
───────────────────────────────────────────────────────────────────────────── */

/**
 * Returns the 0-based pair index for a given traveler index.
 * 0,1 → 0 | 2,3 → 1 | 4,5 → 2 …
 */
function wctb_pair_index( int $traveler_index ): int {
    return (int) floor( $traveler_index / 2 );
}

/**
 * Returns the partner index for a given traveler index.
 * 0 → 1 | 1 → 0 | 2 → 3 | 3 → 2 …
 */
function wctb_pair_partner( int $idx ): int {
    return ( $idx % 2 === 0 ) ? $idx + 1 : $idx - 1;
}

/**
 * Pair label: 0 → 'A', 1 → 'B', 2 → 'C' …
 */
function wctb_pair_label( int $pair_index ): string {
    return chr( 65 + $pair_index ); // 65 = 'A'
}

/* ─────────────────────────────────────────────────────────────────────────────
   Cascade + annotate
───────────────────────────────────────────────────────────────────────────── */

/**
 * Apply cascade rule then annotate every traveler with room_pair meta.
 *
 * Processing steps:
 *   1. Walk pairs (0↔1, 2↔3 …).
 *   2. If either in a pair is 'single', force both to 'single'.
 *   3. Assign room_pair label ('A', 'B' …) and room_pair_type ('shared'|'single').
 *
 * @param  array $travelers  Raw traveler array (keys: room_type, …).
 * @return array             Annotated array.
 */
function wctb_pair_travelers( array $travelers ): array {
    $count = count( $travelers );

    for ( $i = 0; $i < $count; $i += 2 ) {
        $j          = $i + 1;               // partner index
        $has_partner = $j < $count;

        $room_i = $travelers[ $i ]['room_type'] ?? 'shared';
        $room_j = $has_partner ? ( $travelers[ $j ]['room_type'] ?? 'shared' ) : 'single';

        // Cascade: if either wants single, both must be single
        if ( $room_i === 'single' || $room_j === 'single' ) {
            $travelers[ $i ]['room_type'] = 'single';
            if ( $has_partner ) {
                $travelers[ $j ]['room_type'] = 'single';
            }
        }

        $pair_label = wctb_pair_label( wctb_pair_index( $i ) );

        $travelers[ $i ]['room_pair']      = $pair_label;
        $travelers[ $i ]['room_pair_type'] = $travelers[ $i ]['room_type'];

        if ( $has_partner ) {
            $travelers[ $j ]['room_pair']      = $pair_label;
            $travelers[ $j ]['room_pair_type'] = $travelers[ $j ]['room_type'];
        }
    }

    // Solo traveler at the end (odd count) has no partner → always single supplement
    if ( $count % 2 !== 0 ) {
        $last = $count - 1;
        $travelers[ $last ]['room_pair']      = wctb_pair_label( wctb_pair_index( $last ) );
        $travelers[ $last ]['room_pair_type'] = $travelers[ $last ]['room_type'];
    }

    return $travelers;
}

/* ─────────────────────────────────────────────────────────────────────────────
   Price calculation
───────────────────────────────────────────────────────────────────────────── */

/**
 * Calculate total booking price.
 *
 * Formula:
 *   Total = (base_price × count) + (supplement × single_count)
 *
 * Note: After pairing, paired singles are both counted individually.
 *
 * @param  int   $product_id
 * @param  array $travelers   Already paired/annotated.
 * @return float
 */
function wctb_calculate_total( int $product_id, array $travelers ): float {
    $base_price = (float) get_post_meta( $product_id, '_wctb_base_price', true );
    $supplement = (float) get_post_meta( $product_id, '_wctb_single_supplement', true );
    $count      = count( $travelers );

    $single_count = 0;
    foreach ( $travelers as $t ) {
        if ( ( $t['room_type'] ?? 'shared' ) === 'single' ) {
            $single_count++;
        }
    }

    return ( $base_price * $count ) + ( $supplement * $single_count );
}

/* ─────────────────────────────────────────────────────────────────────────────
   Display helpers
───────────────────────────────────────────────────────────────────────────── */

/**
 * Human-readable room label for a traveler.
 * e.g. "Shared Room – Pair A"  or  "Single Room – Pair B"
 *
 * @param  array $traveler  Annotated traveler (has room_pair key).
 * @return string
 */
function wctb_room_pair_label( array $traveler ): string {
    $pair = $traveler['room_pair'] ?? '?';

    if ( ( $traveler['room_type'] ?? 'shared' ) === 'single' ) {
        return sprintf( __( 'Single Room – Pair %s', 'wc-tour-booking' ), $pair );
    }

    return sprintf( __( 'Shared Room – Pair %s', 'wc-tour-booking' ), $pair );
}
