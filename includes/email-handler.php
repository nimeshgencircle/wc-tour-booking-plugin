<?php
/**
 * Email Handler
 * Injects traveler and room data into WooCommerce order emails.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'woocommerce_email_order_details', 'wctb_email_traveler_details', 15, 4 );
function wctb_email_traveler_details( $order, $sent_to_admin, $plain_text, $email ) {

    $tour_items = [];
    foreach ( $order->get_items() as $item ) {
        $travelers = $item->get_meta( '_wctb_travelers' );
        if ( ! empty( $travelers ) ) {
            $tour_items[] = [
                'name'      => $item->get_name(),
                'date'      => $item->get_meta( '_wctb_selected_date' ),
                'travelers' => $travelers,
            ];
        }
    }

    if ( empty( $tour_items ) ) return;

    if ( $plain_text ) {
        echo "\n" . strtoupper( __( 'Tour Booking Details', 'wc-tour-booking' ) ) . "\n";
        echo str_repeat( '-', 40 ) . "\n";

        foreach ( $tour_items as $ti ) {
            echo $ti['name'] . "\n";
            if ( $ti['date'] ) echo __( 'Tour Date: ', 'wc-tour-booking' ) . $ti['date'] . "\n";

            foreach ( $ti['travelers'] as $i => $t ) {
                echo sprintf(
                    __( "Traveler %d: %s | %s | %s | Age: %s | %s\n", 'wc-tour-booking' ),
                    $i + 1, $t['name'], $t['email'], $t['phone'], $t['age'], wctb_room_pair_label( $t )
                );
            }
            echo "\n";
        }
    } else {
        ?>
        <h2 style="font-family:sans-serif;font-size:18px;margin:30px 0 10px;">
            <?php esc_html_e( 'Tour Booking Details', 'wc-tour-booking' ); ?>
        </h2>
        <?php foreach ( $tour_items as $ti ) : ?>
        <h3 style="font-family:sans-serif;font-size:14px;margin-bottom:5px;"><?php echo esc_html( $ti['name'] ); ?></h3>
        <?php if ( $ti['date'] ) : ?>
            <p style="font-family:sans-serif;font-size:13px;">
                <strong><?php esc_html_e( 'Tour Date:', 'wc-tour-booking' ); ?></strong>
                <?php echo esc_html( $ti['date'] ); ?>
            </p>
        <?php endif; ?>
        <table cellpadding="8" cellspacing="0" style="width:100%;border-collapse:collapse;font-family:sans-serif;font-size:13px;margin-bottom:20px;">
            <thead>
                <tr style="background:#f5f5f5;">
                    <th style="border:1px solid #ddd;text-align:left;">#</th>
                    <th style="border:1px solid #ddd;text-align:left;"><?php esc_html_e( 'Name', 'wc-tour-booking' ); ?></th>
                    <th style="border:1px solid #ddd;text-align:left;"><?php esc_html_e( 'Email', 'wc-tour-booking' ); ?></th>
                    <th style="border:1px solid #ddd;text-align:left;"><?php esc_html_e( 'Phone', 'wc-tour-booking' ); ?></th>
                    <th style="border:1px solid #ddd;text-align:left;"><?php esc_html_e( 'Age', 'wc-tour-booking' ); ?></th>
                    <th style="border:1px solid #ddd;text-align:left;"><?php esc_html_e( 'Room', 'wc-tour-booking' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $ti['travelers'] as $i => $t ) : ?>
                <tr>
                    <td style="border:1px solid #ddd;"><?php echo esc_html( $i + 1 ); ?></td>
                    <td style="border:1px solid #ddd;"><?php echo esc_html( $t['name'] ); ?></td>
                    <td style="border:1px solid #ddd;"><?php echo esc_html( $t['email'] ); ?></td>
                    <td style="border:1px solid #ddd;"><?php echo esc_html( $t['phone'] ); ?></td>
                    <td style="border:1px solid #ddd;"><?php echo esc_html( $t['age'] ); ?></td>
                    <td style="border:1px solid #ddd;"><?php echo esc_html( wctb_room_pair_label( $t ) ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endforeach;
    }
}
