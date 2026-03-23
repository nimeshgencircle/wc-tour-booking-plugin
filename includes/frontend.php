<?php
/**
 * Frontend
 * Product page: per-date tour rows with individual action buttons.
 * Traveler form is handled on the checkout page (checkout-travelers.js).
 */

defined( 'ABSPATH' ) || exit;

// ─── Enqueue assets ───────────────────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'wctb_enqueue_assets' );
function wctb_enqueue_assets() {
    if ( ! is_product() && ! is_checkout() ) return;

    wp_enqueue_style( 'wctb-frontend', WCTB_URL . 'assets/css/frontend.css', [], WCTB_VERSION );

    // ── Product page JS ──
    if ( is_product() ) {
        wp_enqueue_script( 'wctb-frontend', WCTB_URL . 'assets/js/frontend.js', [ 'jquery' ], WCTB_VERSION, true );

        global $post;
        $product_id = $post->ID;
        $dates_raw  = get_post_meta( $product_id, '_wctb_dates', true );
        $dates      = $dates_raw ? json_decode( $dates_raw, true ) : [];

        // Resolve 'auto' button type per date using date-scoped availability.
        $resolved_dates = [];
        foreach ( $dates as $date ) {
            $btn        = $date['button_type'] ?? 'auto';
            $date_value = ( $date['start'] ?? '' ) . ' to ' . ( $date['end'] ?? '' );
            if ( $btn === 'auto' ) {
                $seats_for_date = wctb_get_available_seats( $product_id, $date_value );
                $btn = $seats_for_date > 0 ? 'book_now' : 'waitlist';
            }
            $resolved_dates[] = array_merge( $date, [ 'button_type' => $btn ] );
        }

        wp_localize_script( 'wctb-frontend', 'wctb_data', [
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'product_id'     => $product_id,
            'dates'          => $resolved_dates,
            'checkout_url'   => wc_get_checkout_url(),
            'waitlist_nonce' => wp_create_nonce( 'wctb_waitlist_nonce' ),
            'inquiry_nonce'  => wp_create_nonce( 'wctb_inquiry_nonce' ),
            'journey_nonce'  => wp_create_nonce( 'wctb_journey_nonce' ),
            'i18n'           => [
                'adding'           => __( 'Adding…',                               'wc-tour-booking' ),
                'error'            => __( 'Something went wrong. Please try again.', 'wc-tour-booking' ),
                'fill_required'    => __( 'Please fill in all required fields.',    'wc-tour-booking' ),
                'waitlist_success' => __( "You've been added to the waitlist!",     'wc-tour-booking' ),
                'inquiry_success'  => __( 'Your inquiry has been sent!',            'wc-tour-booking' ),
                'journey_success'  => __( 'Your request has been sent! We\'ll be in touch soon.', 'wc-tour-booking' ),
            ],
        ] );
    }

    // ── Checkout page JS (regular checkout + order-pay) ──
    if ( is_checkout() ) {
        wp_enqueue_script( 'wctb-checkout', WCTB_URL . 'assets/js/checkout-travelers.js', [ 'jquery' ], WCTB_VERSION, true );

        $tour_items   = [];
        $is_order_pay = wctb_is_order_pay_page();

        if ( $is_order_pay ) {
            // Order-pay page: build tour items from the existing WC order.
            $order_id = absint( get_query_var( 'order-pay' ) );
            $order    = $order_id ? wc_get_order( $order_id ) : null;
            if ( $order ) {
                foreach ( $order->get_items() as $item ) {
                    $pid  = $item->get_product_id();
                    if ( ! get_post_meta( $pid, '_wctb_dates', true ) ) continue;
                    $date = $item->get_meta( '_wctb_travel_date' ) ?: (string) $item->get_meta( '_wctb_selected_date' );
                    $tour_items[] = [
                        'cart_key'      => '',
                        'product_id'    => $pid,
                        'name'          => get_the_title( $pid ),
                        'base_price'    => (float) get_post_meta( $pid, '_wctb_base_price', true ),
                        'supplement'    => (float) get_post_meta( $pid, '_wctb_single_supplement', true ),
                        'room_enabled'  => get_post_meta( $pid, '_wctb_enable_room_selection', true ) === 'yes',
                        'available'     => wctb_get_available_seats( $pid, $date ),
                        'date'          => $date,
                        'max_travelers' => (int) get_option( 'wctb_max_travelers_per_booking', 10 ),
                        'initial_count' => (int) $item->get_quantity(),
                    ];
                }
            }
        } else {
            // Regular checkout: build from cart.
            foreach ( WC()->cart->get_cart() as $key => $item ) {
                $pid  = $item['product_id'];
                if ( ! get_post_meta( $pid, '_wctb_dates', true ) ) continue;
                $date = $item['wctb_selected_date'] ?? '';
                $tour_items[] = [
                    'cart_key'      => $key,
                    'product_id'    => $pid,
                    'name'          => get_the_title( $pid ),
                    'base_price'    => (float) get_post_meta( $pid, '_wctb_base_price', true ),
                    'supplement'    => (float) get_post_meta( $pid, '_wctb_single_supplement', true ),
                    'room_enabled'  => get_post_meta( $pid, '_wctb_enable_room_selection', true ) === 'yes',
                    'available'     => wctb_get_available_seats( $pid, $date ),
                    'date'          => $date,
                    'max_travelers' => (int) get_option( 'wctb_max_travelers_per_booking', 10 ),
                    'initial_count' => 1,
                ];
            }
        }

        wp_localize_script( 'wctb-checkout', 'wctb_checkout', [
            'tour_items'   => $tour_items,
            'is_order_pay' => $is_order_pay,
            'currency'     => get_option( 'wctb_currency_symbol', get_woocommerce_currency_symbol() ),
            'ajax_url'     => admin_url( 'admin-ajax.php' ),
            'i18n'       => [
                'traveler'        => __( 'Traveler',                                                   'wc-tour-booking' ),
                'first_name'      => __( 'First Name',                                                 'wc-tour-booking' ),
                'last_name'       => __( 'Last Name',                                                  'wc-tour-booking' ),
                'email'           => __( 'Email Address',                                              'wc-tour-booking' ),
                'phone'           => __( 'Phone Number',                                               'wc-tour-booking' ),
                'age'             => __( 'Age',                                                        'wc-tour-booking' ),
                'room_pref'       => __( 'Room Preference',                                            'wc-tour-booking' ),
                'queen_bed'       => __( 'Double Room - Queen Bed',                                    'wc-tour-booking' ),
                'twin_beds'       => __( 'Double Room - Twin Beds',                                    'wc-tour-booking' ),
                'sharing_pref'    => __( 'Sharing Preference',                                         'wc-tour-booking' ),
                'shared_room'     => __( 'Shared Room',                                                'wc-tour-booking' ),
                'single_room'     => __( 'Private Room',                                               'wc-tour-booking' ),
                'num_travelers'   => __( 'Number of Travelers',                                        'wc-tour-booking' ),
                'pricing_summary' => __( 'Pricing Summary',                                            'wc-tour-booking' ),
                'per_person'      => __( 'per person',                                                 'wc-tour-booking' ),
                'supplement_note' => __( 'Private room supplement: ',                                  'wc-tour-booking' ),
                'fill_required'   => __( 'Please complete all traveler details before placing your order.', 'wc-tour-booking' ),
                'departure_date'  => __( 'Departure Date',                                             'wc-tour-booking' ),
                'return_date'     => __( 'Return Date',                                                'wc-tour-booking' ),
                'fully_booked'    => __( 'This date is fully booked.',                                 'wc-tour-booking' ),
            ],
        ] );
    }
}

// ─── Helper: detect order-pay page ───────────────────────────────────────────
function wctb_is_order_pay_page(): bool {
    return is_checkout() && (bool) get_query_var( 'order-pay' );
}

// ─── Helper: format date range label ──────────────────────────────────────────
function wctb_format_date_range( string $start, string $end ): string {
    $fmt   = get_option( 'date_format' );
    $ts_s  = strtotime( $start );
    $ts_e  = strtotime( $end );
    $start_label = date_i18n( $fmt, $ts_s );
    $end_label   = date_i18n( $fmt, $ts_e );

    // If same month & year, condense: "March 21–27, 2026"
    if ( date( 'F Y', $ts_s ) === date( 'F Y', $ts_e ) ) {
        return date_i18n( 'F j', $ts_s ) . '–' . date_i18n( 'j, Y', $ts_e );
    }
    // If same year: "March 28 – April 3, 2026"
    if ( date( 'Y', $ts_s ) === date( 'Y', $ts_e ) ) {
        return date_i18n( 'F j', $ts_s ) . ' – ' . date_i18n( 'F j, Y', $ts_e );
    }
    return $start_label . ' – ' . $end_label;
}

// ─── Product page: capacity info ──────────────────────────────────────────────
//add_action( 'woocommerce_single_product_summary', 'wctb_display_tour_info', 25 );
function wctb_display_tour_info() {
    global $post;
    $product_id = $post->ID;
    if ( ! get_post_meta( $product_id, '_wctb_dates', true ) ) return;

    $available = wctb_get_available_seats( $product_id );
    $max       = (int) get_post_meta( $product_id, '_wctb_max_travelers', true );

    if ( ! $max ) return;

    $pct   = $max > 0 ? round( ( ( $max - $available ) / $max ) * 100 ) : 0;
    $class = $available === 0 ? 'wctb-seats--full' : ( $available <= 3 ? 'wctb-seats--low' : 'wctb-seats--ok' );

    echo '<div class="wctb-tour-info">';
    echo '<p class="wctb-seats ' . esc_attr( $class ) . '">';
    echo $available > 0
        ? esc_html( sprintf( _n( '%d seat available', '%d seats available', $available, 'wc-tour-booking' ), $available ) )
        : '<strong>' . esc_html__( 'Fully Booked', 'wc-tour-booking' ) . '</strong>';
    echo '</p>';
    echo '<div class="wctb-capacity-bar"><div class="wctb-capacity-fill" style="width:' . esc_attr( $pct ) . '%"></div></div>';
    echo '</div>';
}

// ─── Product page: per-date tour rows ─────────────────────────────────────────
add_action( 'woocommerce_single_product_summary', 'wctb_render_tour_button', 35 );
function wctb_render_tour_button() {
    global $post;
    $product_id = $post->ID;
    $dates_raw  = get_post_meta( $product_id, '_wctb_dates', true );
    if ( ! $dates_raw ) return;

    $dates = json_decode( $dates_raw, true );

    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

    echo '<div class="wctb-dates-list">';

    foreach ( $dates as $date ) {
        $btn_type   = $date['button_type'] ?? 'auto';
        $date_value = $date['start'] . ' to ' . $date['end'];

        if ( $btn_type === 'auto' || $btn_type === 'book_now') {
            $seats    = wctb_get_available_seats( $product_id, $date_value );
            $btn_type = $seats > 0 ? 'book_now' : 'waitlist';
        }

        $label      = wctb_format_date_range( $date['start'], $date['end'] );
        $date_value = esc_attr( $date_value );

        echo '<div class="wctb-date-list-row">';
        echo '<span class="wctb-date-list-label">' . esc_html( $label ) . '</span>';

        switch ( $btn_type ) {
            case 'book_now':
                printf(
                    '<button type="button" class="wctb-btn wctb-btn--dark wctb-btn-book-now" data-date="%s" data-product="%d">%s &rarr;</button>',
                    $date_value,
                    (int) $product_id,
                    esc_html__( 'Book Now', 'wc-tour-booking' )
                );
                break;

            case 'waitlist':
                printf(
                    '<button type="button" class="wctb-btn wctb-btn--dark wctb-btn-waitlist" data-date="%s">%s &rarr;</button>',
                    $date_value,
                    esc_html__( 'Join Waitlist', 'wc-tour-booking' )
                );
                break;

            case 'inquiry':
                printf(
                    '<button type="button" class="wctb-btn wctb-btn--dark wctb-btn-inquiry" data-date="%s">%s &rarr;</button>',
                    $date_value,
                    esc_html__( 'Check Availability', 'wc-tour-booking' )
                );
                break;

            case 'custom_journey':
                printf(
                    '<button type="button" class="wctb-btn wctb-btn--dark wctb-btn-custom-journey" data-date="%s">%s &rarr;</button>',
                    $date_value,
                    esc_html__( 'Begin Custom Journey', 'wc-tour-booking' )
                );
                break;
        }

        echo '</div>';
    }

    echo '</div>';

    wctb_render_waitlist_popup( $product_id );
    wctb_render_inquiry_popup( $product_id );
    wctb_render_custom_journey_popup( $product_id );
}

// ─── Waitlist popup ───────────────────────────────────────────────────────────
function wctb_render_waitlist_popup( $product_id ) { ?>
<div id="wctb-waitlist-modal" class="wctb-modal" role="dialog" aria-modal="true" style="display:none;">
    <div class="wctb-modal__overlay"></div>
    <div class="wctb-modal__content">
        <button class="wctb-modal__close"
            aria-label="<?php esc_attr_e( 'Close', 'wc-tour-booking' ); ?>">&times;</button>
        <h2><?php esc_html_e( 'Join the Waitlist', 'wc-tour-booking' ); ?></h2>
        <p><?php esc_html_e( "Leave your details and we'll contact you if a spot opens up.", 'wc-tour-booking' ); ?></p>
        <input type="hidden" id="wctb-waitlist-product-id" value="<?php echo esc_attr( $product_id ); ?>">
        <input type="hidden" id="wctb-waitlist-date" value="">
        <div class="wctb-form-row">
            <div class="wctb-form-group"><label
                    for="wctb-wl-first-name"><?php esc_html_e( 'First Name *', 'wc-tour-booking' ); ?></label><input
                    type="text" id="wctb-wl-first-name" class="wctb-input" required></div>
            <div class="wctb-form-group"><label
                    for="wctb-wl-last-name"><?php esc_html_e( 'Last Name *', 'wc-tour-booking' ); ?></label><input
                    type="text" id="wctb-wl-last-name" class="wctb-input" required></div>
        </div>
        <div class="wctb-form-group"><label
                for="wctb-wl-email"><?php esc_html_e( 'Email Address *', 'wc-tour-booking' ); ?></label><input
                type="email" id="wctb-wl-email" class="wctb-input" required></div>
        <div class="wctb-form-group"><label
                for="wctb-wl-phone"><?php esc_html_e( 'Phone Number', 'wc-tour-booking' ); ?></label><input type="tel"
                id="wctb-wl-phone" class="wctb-input"></div>
        <div class="wctb-form-group">
            <label
                for="wctb-wl-travelers"><?php esc_html_e( 'Number of Travelers *', 'wc-tour-booking' ); ?></label><input
                type="number" id="wctb-wl-travelers" class="wctb-input" min="1" value="1" required></div>
        <div class="wctb-form-group"><label
                for="wctb-wl-message-text"><?php esc_html_e( 'Message', 'wc-tour-booking' ); ?></label><textarea
                id="wctb-wl-message-text" class="wctb-textarea" rows="3"
                placeholder="<?php esc_attr_e( 'Any special requests or questions?', 'wc-tour-booking' ); ?>"></textarea>
        </div>
        <button type="button" id="wctb-wl-submit"
            class="button wctb-btn wctb-btn--primary wctb-btn--full"><?php esc_html_e( 'Submit Waitlist Request', 'wc-tour-booking' ); ?></button>
        <div id="wctb-wl-message" class="wctb-message" style="display:none;"></div>
    </div>
</div>
<?php }

// ─── Inquiry / Check Availability popup ───────────────────────────────────────
function wctb_render_inquiry_popup( $product_id ) { ?>
<div id="wctb-inquiry-modal" class="wctb-modal" role="dialog" aria-modal="true" style="display:none;">
    <div class="wctb-modal__overlay"></div>
    <div class="wctb-modal__content">
        <button class="wctb-modal__close"
            aria-label="<?php esc_attr_e( 'Close', 'wc-tour-booking' ); ?>">&times;</button>
        <h2><?php esc_html_e( 'Check Availability', 'wc-tour-booking' ); ?></h2>
        <p><?php esc_html_e( "Leave your details and we'll check availability and get back to you.", 'wc-tour-booking' ); ?>
        </p>
        <input type="hidden" id="wctb-inquiry-product-id" value="<?php echo esc_attr( $product_id ); ?>">
        <input type="hidden" id="wctb-inquiry-date" value="">
        <div class="wctb-form-row">
            <div class="wctb-form-group"><label
                    for="wctb-inq-first-name"><?php esc_html_e( 'First Name *', 'wc-tour-booking' ); ?></label><input
                    type="text" id="wctb-inq-first-name" class="wctb-input" required></div>
            <div class="wctb-form-group"><label
                    for="wctb-inq-last-name"><?php esc_html_e( 'Last Name *', 'wc-tour-booking' ); ?></label><input
                    type="text" id="wctb-inq-last-name" class="wctb-input" required></div>
        </div>
        <div class="wctb-form-group"><label
                for="wctb-inq-email"><?php esc_html_e( 'Email Address *', 'wc-tour-booking' ); ?></label><input
                type="email" id="wctb-inq-email" class="wctb-input" required></div>
        <div class="wctb-form-group"><label
                for="wctb-inq-phone"><?php esc_html_e( 'Phone Number', 'wc-tour-booking' ); ?></label><input type="tel"
                id="wctb-inq-phone" class="wctb-input"></div>
        <div class="wctb-form-group">
            <label
                for="wctb-inq-travelers"><?php esc_html_e( 'Number of Travelers *', 'wc-tour-booking' ); ?></label><input
                type="number" id="wctb-inq-travelers" class="wctb-input" min="1" value="1" required></div>
        <div class="wctb-form-group"><label
                for="wctb-inq-message"><?php esc_html_e( 'Message', 'wc-tour-booking' ); ?></label><textarea
                id="wctb-inq-message" class="wctb-textarea" rows="3"
                placeholder="<?php esc_attr_e( 'Any special requests or questions?', 'wc-tour-booking' ); ?>"></textarea>
        </div>
        <button type="button" id="wctb-inq-submit"
            class="button wctb-btn wctb-btn--primary wctb-btn--full"><?php esc_html_e( 'Check Availability', 'wc-tour-booking' ); ?></button>
        <div id="wctb-inq-message-feedback" class="wctb-message" style="display:none;"></div>
    </div>
</div>
<?php }

// ─── Begin Custom Journey popup ───────────────────────────────────────────────
function wctb_render_custom_journey_popup( $product_id ) { ?>
<div id="wctb-custom-journey-modal" class="wctb-modal" role="dialog" aria-modal="true" style="display:none;">
    <div class="wctb-modal__overlay"></div>
    <div class="wctb-modal__content">
        <button class="wctb-modal__close"
            aria-label="<?php esc_attr_e( 'Close', 'wc-tour-booking' ); ?>">&times;</button>
        <h2><?php esc_html_e( 'Begin Your Custom Journey', 'wc-tour-booking' ); ?></h2>
        <p><?php esc_html_e( "Tell us about your ideal trip and we'll craft a personalised itinerary for you.", 'wc-tour-booking' ); ?>
        </p>
        <input type="hidden" id="wctb-cj-product-id" value="<?php echo esc_attr( $product_id ); ?>">
        <input type="hidden" id="wctb-cj-travel-date" value="">
        <div class="wctb-form-row">
            <div class="wctb-form-group"><label
                    for="wctb-cj-first-name"><?php esc_html_e( 'First Name *', 'wc-tour-booking' ); ?></label><input
                    type="text" id="wctb-cj-first-name" class="wctb-input" required></div>
            <div class="wctb-form-group"><label
                    for="wctb-cj-last-name"><?php esc_html_e( 'Last Name *', 'wc-tour-booking' ); ?></label><input
                    type="text" id="wctb-cj-last-name" class="wctb-input" required></div>
        </div>
        <div class="wctb-form-group"><label
                for="wctb-cj-phone"><?php esc_html_e( 'Phone Number *', 'wc-tour-booking' ); ?></label><input type="tel"
                id="wctb-cj-phone" class="wctb-input" required></div>
        <div class="wctb-form-group">
            <label for="wctb-cj-contact-method"><?php esc_html_e( 'Method of Contact *', 'wc-tour-booking' ); ?></label>
            <select id="wctb-cj-contact-method" class="wctb-input" required>
                <option value=""><?php esc_html_e( '— Select —', 'wc-tour-booking' ); ?></option>
                <option value="Email"><?php esc_html_e( 'Email', 'wc-tour-booking' ); ?></option>
                <option value="Phone"><?php esc_html_e( 'Phone', 'wc-tour-booking' ); ?></option>
                <option value="WhatsApp"><?php esc_html_e( 'WhatsApp', 'wc-tour-booking' ); ?></option>
            </select>
        </div>
        <div class="wctb-form-group">
            <label
                for="wctb-cj-destination"><?php esc_html_e( 'Where would you like to travel? *', 'wc-tour-booking' ); ?></label><input
                type="text" id="wctb-cj-destination" class="wctb-input" required
                placeholder="<?php esc_attr_e( 'e.g. Patagonia, South America', 'wc-tour-booking' ); ?>"></div>
        <div class="wctb-form-group">
            <label
                for="wctb-cj-priorities"><?php esc_html_e( 'Top 3 Priorities *', 'wc-tour-booking' ); ?></label><textarea
                id="wctb-cj-priorities" class="wctb-textarea" rows="3" required
                placeholder="<?php esc_attr_e( 'e.g. 1. Adventure  2. Local cuisine  3. Comfortable accommodation', 'wc-tour-booking' ); ?>"></textarea>
        </div>
        <div class="wctb-form-group">
            <label
                for="wctb-cj-travel-style"><?php esc_html_e( 'When I travel I…', 'wc-tour-booking' ); ?></label><textarea
                id="wctb-cj-travel-style" class="wctb-textarea" rows="2"
                placeholder="<?php esc_attr_e( 'e.g. prefer off-the-beaten-path experiences, always seek out local markets…', 'wc-tour-booking' ); ?>"></textarea>
        </div>
        <div class="wctb-form-row">
            <div class="wctb-form-group">
                <label for="wctb-cj-budget"><?php esc_html_e( 'Daily Budget *', 'wc-tour-booking' ); ?></label><input
                    type="text" id="wctb-cj-budget" class="wctb-input" required
                    placeholder="<?php esc_attr_e( 'e.g. $300/day', 'wc-tour-booking' ); ?>"></div>
            <div class="wctb-form-group">
                <label
                    for="wctb-cj-travelers"><?php esc_html_e( 'Number of Travelers *', 'wc-tour-booking' ); ?></label><input
                    type="number" id="wctb-cj-travelers" class="wctb-input" min="1" value="1" required></div>
        </div>
        <div class="wctb-form-group">
            <label
                for="wctb-cj-travel-when"><?php esc_html_e( 'When are you looking to travel? *', 'wc-tour-booking' ); ?></label><input
                type="text" id="wctb-cj-travel-when" class="wctb-input" required
                placeholder="<?php esc_attr_e( 'e.g. March 2026, flexible in summer', 'wc-tour-booking' ); ?>"></div>
        <div class="wctb-form-group">
            <label
                for="wctb-cj-notes"><?php esc_html_e( 'Anything else we should know?', 'wc-tour-booking' ); ?></label><textarea
                id="wctb-cj-notes" class="wctb-textarea" rows="3"
                placeholder="<?php esc_attr_e( 'Special requirements, dietary needs, accessibility needs…', 'wc-tour-booking' ); ?>"></textarea>
        </div>
        <button type="button" id="wctb-cj-submit"
            class="button wctb-btn wctb-btn--primary wctb-btn--full"><?php esc_html_e( 'Send My Request', 'wc-tour-booking' ); ?></button>
        <div id="wctb-cj-message-feedback" class="wctb-message" style="display:none;"></div>
    </div>
</div>
<?php }

// ─── Order-pay: inject traveler section before the Pay button ────────────────
add_action( 'woocommerce_pay_order_before_submit', 'wctb_order_pay_traveler_section' );
function wctb_order_pay_traveler_section() {
    $order_id = absint( get_query_var( 'order-pay' ) );
    if ( ! $order_id ) return;

    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $has_tour = false;
    foreach ( $order->get_items() as $item ) {
        if ( get_post_meta( $item->get_product_id(), '_wctb_dates', true ) ) {
            $has_tour = true;
            break;
        }
    }
    if ( ! $has_tour ) return;

    echo '<div id="wctb-checkout-travelers-wrap"></div>';
    echo '<input type="hidden" name="wctb_checkout_travelers" id="wctb_checkout_travelers_data" value="">';
}

// ─── Checkout: inject traveler section above billing ─────────────────────────
add_action( 'woocommerce_checkout_before_customer_details', 'wctb_checkout_traveler_section' );
function wctb_checkout_traveler_section() {
    $has_tour = false;
    $cart_product_id = 0;
    foreach ( WC()->cart->get_cart() as $item ) {
        if ( get_post_meta( $item['product_id'], '_wctb_dates', true ) ) { 
            $cart_product_id = $item['product_id'];
            $has_tour = true; break; 
            }
    }
    if ( ! $has_tour ) return;     
   
                

    echo '<div id="wctb-checkout-travelers-wrap"></div>';
    echo '<input type="hidden" name="wctb_checkout_travelers" id="wctb_checkout_travelers_data" value="">';
}

// ─── Checkout: validate traveler data ─────────────────────────────────────────
add_action( 'woocommerce_checkout_process', 'wctb_checkout_validate_travelers' );
function wctb_checkout_validate_travelers() {
    if ( ! isset( $_POST['wctb_checkout_travelers'] ) ) return;
    $raw = wp_unslash( $_POST['wctb_checkout_travelers'] );
    if ( empty( $raw ) ) return;

    $all = json_decode( $raw, true );
    if ( ! is_array( $all ) ) {
        wc_add_notice( __( 'Traveler data is invalid. Please refresh and try again.', 'wc-tour-booking' ), 'error' );
        return;
    }

    foreach ( $all as $group ) {
        foreach ( $group['travelers'] ?? [] as $t ) {
            if ( empty( $t['first_name'] ) || empty( $t['last_name'] ) || empty( $t['email'] ) ) {
                wc_add_notice( __( 'Please complete all required traveler fields (First Name, Last Name, Email).', 'wc-tour-booking' ), 'error' );
                return;
            }
        }
    }
}

// ─── Checkout: save traveler data to order ────────────────────────────────────
add_action( 'woocommerce_checkout_update_order_meta', 'wctb_save_checkout_traveler_data' );
function wctb_save_checkout_traveler_data( $order_id ) {
    if ( empty( $_POST['wctb_checkout_travelers'] ) ) return;

    $raw = wp_unslash( $_POST['wctb_checkout_travelers'] );
    $all = json_decode( $raw, true );
    if ( ! is_array( $all ) ) return;

    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    foreach ( $order->get_items() as $item ) {
        $pid = $item->get_product_id();
        foreach ( $all as $group ) {
            if ( (int) $group['product_id'] !== $pid ) continue;

            $sanitized = array_map( function( $t ) {
                return [
                    'first_name'      => sanitize_text_field( $t['first_name'] ?? '' ),
                    'last_name'       => sanitize_text_field( $t['last_name']  ?? '' ),
                    'name'            => sanitize_text_field( ( $t['first_name'] ?? '' ) . ' ' . ( $t['last_name'] ?? '' ) ),
                    'email'           => sanitize_email(      $t['email']      ?? '' ),
                    'phone'           => sanitize_text_field( $t['phone']      ?? '' ),
                    'age'             => absint(              $t['age']        ?? 0  ),
                    'room_type'       => in_array( $t['room_type'] ?? 'shared', [ 'shared', 'single' ] ) ? $t['room_type'] : 'shared',
                    'room_preference' => in_array( $t['room_preference'] ?? 'queen', [ 'queen', 'twin' ] ) ? $t['room_preference'] : 'queen',
                ];
            }, $group['travelers'] ?? [] );

            $paired = wctb_pair_travelers( $sanitized );

            $item->add_meta_data( '_wctb_travelers',      $paired,          true );
            $item->add_meta_data( '_wctb_traveler_count', count( $paired ), true );
            $item->add_meta_data( '_wctb_num_travelers',  count( $paired ), true );
            $item->save();
        }
    }
}