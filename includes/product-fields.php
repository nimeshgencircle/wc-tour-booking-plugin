<?php
/**
 * Product Fields
 * Adds tour-specific meta fields to the WooCommerce product edit page.
 */

defined( 'ABSPATH' ) || exit;

// ─── Add Tour Data tab to product tabs ───────────────────────────────────────
add_filter( 'woocommerce_product_data_tabs', 'wctb_add_product_tab' );
function wctb_add_product_tab( $tabs ) {
    $tabs['wctb_tour'] = [
        'label'    => __( 'Tour Settings', 'wc-tour-booking' ),
        'target'   => 'wctb_tour_data',
        'class'    => [],
        'priority' => 60,
    ];
    return $tabs;
}

// ─── Tab panel content ────────────────────────────────────────────────────────
add_action( 'woocommerce_product_data_panels', 'wctb_tour_data_panel' );
function wctb_tour_data_panel() {
    global $post;
    $product_id = $post->ID;

    // Retrieve saved dates (JSON array of {start, end} objects)
    $dates_raw = get_post_meta( $product_id, '_wctb_dates', true );
    $dates     = $dates_raw ? json_decode( $dates_raw, true ) : [];
    if ( empty( $dates ) ) {
        $dates = [ [ 'start' => '', 'end' => '' ] ];
    }

    ?>
    <div id="wctb_tour_data" class="panel woocommerce_options_panel">

        <div class="options_group">
            <p class="form-field">
                <label><?php esc_html_e( 'Tour Dates', 'wc-tour-booking' ); ?></label>
            </p>
            <div id="wctb-dates-wrapper">
                <?php foreach ( $dates as $i => $date ) : ?>
                <div class="wctb-date-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px;">
                    <label><?php esc_html_e( 'Start', 'wc-tour-booking' ); ?>
                        <input type="date" name="wctb_dates[<?php echo esc_attr( $i ); ?>][start]"
                               value="<?php echo esc_attr( $date['start'] ); ?>" class="short" />
                    </label>
                    <label><?php esc_html_e( 'End', 'wc-tour-booking' ); ?>
                        <input type="date" name="wctb_dates[<?php echo esc_attr( $i ); ?>][end]"
                               value="<?php echo esc_attr( $date['end'] ); ?>" class="short" />
                    </label>
                    <button type="button" class="button wctb-remove-date">&times; <?php esc_html_e( 'Remove', 'wc-tour-booking' ); ?></button>
                </div>
                <?php endforeach; ?>
            </div>
            <p>
                <button type="button" class="button wctb-add-date">
                    + <?php esc_html_e( 'Add Date Range', 'wc-tour-booking' ); ?>
                </button>
            </p>
        </div>

        <div class="options_group">
            <?php
            woocommerce_wp_text_input( [
                'id'          => '_wctb_max_travelers',
                'label'       => __( 'Maximum Travelers', 'wc-tour-booking' ),
                'type'        => 'number',
                'custom_attributes' => [ 'min' => '1', 'step' => '1' ],
                'value'       => get_post_meta( $product_id, '_wctb_max_travelers', true ),
                'desc_tip'    => true,
                'description' => __( 'Total seat capacity for this tour.', 'wc-tour-booking' ),
            ] );

            woocommerce_wp_text_input( [
                'id'          => '_wctb_base_price',
                'label'       => __( 'Tour Base Price', 'wc-tour-booking' ),
                'type'        => 'number',
                'custom_attributes' => [ 'min' => '0', 'step' => '0.01' ],
                'value'       => get_post_meta( $product_id, '_wctb_base_price', true ),
                'desc_tip'    => true,
                'description' => __( 'Price per traveler. This syncs with the WooCommerce regular price.', 'wc-tour-booking' ),
            ] );

            woocommerce_wp_text_input( [
                'id'          => '_wctb_single_supplement',
                'label'       => __( 'Single Room Supplement', 'wc-tour-booking' ),
                'type'        => 'number',
                'custom_attributes' => [ 'min' => '0', 'step' => '0.01' ],
                'value'       => get_post_meta( $product_id, '_wctb_single_supplement', true ),
                'desc_tip'    => true,
                'description' => __( 'Extra charge per single room selected.', 'wc-tour-booking' ),
            ] );
            ?>
        </div>

        <div class="options_group">
            <?php
            woocommerce_wp_checkbox( [
                'id'          => '_wctb_enable_room_selection',
                'label'       => __( 'Enable Room Selection', 'wc-tour-booking' ),
                'value'       => get_post_meta( $product_id, '_wctb_enable_room_selection', true ),
                'description' => __( 'Allow travelers to choose between shared and single room.', 'wc-tour-booking' ),
            ] );

            woocommerce_wp_select( [
                'id'      => '_wctb_button_type',
                'label'   => __( 'Button Type Override', 'wc-tour-booking' ),
                'value'   => get_post_meta( $product_id, '_wctb_button_type', true ) ?: 'auto',
                'options' => [
                    'auto'     => __( 'Auto (based on availability)', 'wc-tour-booking' ),
                    'book_now' => __( 'Book Now', 'wc-tour-booking' ),
                    'waitlist' => __( 'Waitlist', 'wc-tour-booking' ),
                    'inquiry'  => __( 'Inquiry', 'wc-tour-booking' ),
                ],
                'desc_tip'    => true,
                'description' => __( 'Override automatic button logic if needed.', 'wc-tour-booking' ),
            ] );
            ?>
        </div>

    </div>
    <?php
}

// ─── Save product fields ──────────────────────────────────────────────────────
add_action( 'woocommerce_process_product_meta', 'wctb_save_product_fields' );
function wctb_save_product_fields( $product_id ) {

    // Dates
    $dates_input = isset( $_POST['wctb_dates'] ) ? (array) $_POST['wctb_dates'] : [];
    $dates_clean = [];
    foreach ( $dates_input as $d ) {
        $start = sanitize_text_field( $d['start'] ?? '' );
        $end   = sanitize_text_field( $d['end'] ?? '' );
        if ( $start && $end ) {
            $dates_clean[] = [ 'start' => $start, 'end' => $end ];
        }
    }
    update_post_meta( $product_id, '_wctb_dates', wp_json_encode( $dates_clean ) );

    // Numeric fields
    $numeric_fields = [
        '_wctb_max_travelers',
        '_wctb_base_price',
        '_wctb_single_supplement',
    ];
    foreach ( $numeric_fields as $key ) {
        $field = str_replace( '_wctb_', '', $key );
        if ( isset( $_POST[ $key ] ) ) {
            update_post_meta( $product_id, $key, wc_format_decimal( sanitize_text_field( $_POST[ $key ] ) ) );
        }
    }

    // Sync base price → WooCommerce regular price
    if ( isset( $_POST['_wctb_base_price'] ) ) {
        update_post_meta( $product_id, '_regular_price', wc_format_decimal( sanitize_text_field( $_POST['_wctb_base_price'] ) ) );
        update_post_meta( $product_id, '_price', wc_format_decimal( sanitize_text_field( $_POST['_wctb_base_price'] ) ) );
    }

    // Checkbox
    $room_selection = isset( $_POST['_wctb_enable_room_selection'] ) ? 'yes' : 'no';
    update_post_meta( $product_id, '_wctb_enable_room_selection', $room_selection );

    // Select
    if ( isset( $_POST['_wctb_button_type'] ) ) {
        update_post_meta( $product_id, '_wctb_button_type', sanitize_text_field( $_POST['_wctb_button_type'] ) );
    }
}

// ─── Admin JS for dynamic date rows ──────────────────────────────────────────
add_action( 'admin_enqueue_scripts', 'wctb_product_admin_js' );
function wctb_product_admin_js( $hook ) {
    if ( $hook !== 'post.php' && $hook !== 'post-new.php' ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'product' ) return;

    $product_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
    $dates_raw  = $product_id ? get_post_meta( $product_id, '_wctb_dates', true ) : '';
    $date_count = $dates_raw ? count( json_decode( $dates_raw, true ) ?: [] ) : 1;

    wp_register_script( 'wctb-admin', false, [ 'jquery' ], null, true );
    wp_enqueue_script( 'wctb-admin' );

    $label_start  = esc_js( __( 'Start',  'wc-tour-booking' ) );
    $label_end    = esc_js( __( 'End',    'wc-tour-booking' ) );
    $label_remove = esc_js( __( 'Remove', 'wc-tour-booking' ) );
    $idx          = (int) $date_count;

    wp_add_inline_script( 'wctb-admin', <<<JS
(function($){
    var idx = {$idx};

    $(document).on('click', '.wctb-add-date', function(){
        var row = '<div class="wctb-date-row" style="display:flex;gap:10px;align-items:center;margin-bottom:8px;flex-wrap:wrap;">'
            + '<label style="display:flex;align-items:center;gap:6px;">{$label_start} <input type="date" name="wctb_dates[' + idx + '][start]" class="short" style="width:160px;" /></label>'
            + '<label style="display:flex;align-items:center;gap:6px;">{$label_end}   <input type="date" name="wctb_dates[' + idx + '][end]"   class="short" style="width:160px;" /></label>'
            + '<button type="button" class="button wctb-remove-date">&times; {$label_remove}</button>'
            + '</div>';
        $('#wctb-dates-wrapper').append(row);
        idx++;
    });

    $(document).on('click', '.wctb-remove-date', function(){
        $(this).closest('.wctb-date-row').remove();
    });
})(jQuery);
JS
    );
}

// ─── Admin CSS to ensure date inputs render correctly ─────────────────────────
add_action( 'admin_head', 'wctb_product_admin_css' );
function wctb_product_admin_css() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'product' ) return;
    ?>
    <style>
    #wctb_tour_data .wctb-date-row {
        display: flex !important;
        flex-wrap: wrap;
        gap: 12px;
        align-items: center;
        margin-bottom: 10px;
        padding: 8px 12px;
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
    }
    #wctb_tour_data .wctb-date-row label {
        display: flex !important;
        align-items: center;
        gap: 8px;
        font-weight: 500;
        margin: 0;
        padding: 0;
        width: auto !important;
        float: none !important;
        clear: none !important;
    }
    #wctb_tour_data .wctb-date-row input[type="date"] {
        display: inline-block !important;
        visibility: visible !important;
        opacity: 1 !important;
        width: 160px !important;
        height: 32px !important;
        padding: 4px 8px !important;
        border: 1px solid #ccc !important;
        border-radius: 4px !important;
        background: #fff !important;
        font-size: 13px !important;
        margin: 0 !important;
        box-sizing: border-box !important;
    }
    #wctb_tour_data .wctb-date-row input[type="date"]:focus {
        border-color: #2271b1 !important;
        box-shadow: 0 0 0 1px #2271b1 !important;
        outline: none !important;
    }
    #wctb_tour_data #wctb-dates-wrapper {
        padding: 0 12px 8px;
    }
    #wctb_tour_data .wctb-add-date {
        margin-left: 12px;
        margin-bottom: 10px;
    }
    </style>
    <?php
}
