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

    $product_id  = absint(                  $_POST['product_id']  ?? 0  );
    $first_name  = sanitize_text_field(     $_POST['first_name']  ?? '' );
    $last_name   = sanitize_text_field(     $_POST['last_name']   ?? '' );
    $email       = sanitize_email(          $_POST['email']       ?? '' );
    $phone       = sanitize_text_field(     $_POST['phone']       ?? '' );
    $travelers   = absint(                  $_POST['travelers']   ?? 1  );
    $message     = sanitize_textarea_field( $_POST['message']     ?? '' );
    $travel_date = sanitize_text_field(     $_POST['date']        ?? '' );
    $contact_method = sanitize_text_field( $_POST['contact_method'] ?? '' );
    $announcements   = sanitize_text_field( $_POST['announcements'] ?? '' );


    if ( ! $product_id || ! $first_name || ! $last_name || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'Please fill in all required fields.', 'wc-tour-booking' ) ] );
    }

    $product   = wc_get_product( $product_id );
    $tour_name = $product ? $product->get_name() : "Product #{$product_id}";

    $post_id = wp_insert_post( [
        'post_type'   => 'wctb_availability',
        'post_title'  => "{$first_name} {$last_name} – {$tour_name}",
        'post_status' => 'publish',
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Could not save your request. Please try again.', 'wc-tour-booking' ) ] );
    }

    $meta = [
        '_wctb_av_product_id'  => $product_id,
        '_wctb_av_travel_date' => $travel_date,
        '_wctb_av_first_name'  => $first_name,
        '_wctb_av_last_name'   => $last_name,
        '_wctb_av_email'       => $email,
        '_wctb_av_phone'       => $phone,
        '_wctb_av_travelers'   => $travelers,
        '_wctb_av_message'     => $message,
        '_wctb_av_status'      => 'pending',
        '_wctb_av_contact_method' => $contact_method,
        '_wctb_av_announcements' => $announcements,
    ];
    foreach ( $meta as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
    }

    $mailer = WC()->mailer();

    // Email subject
    $subject = sprintf( __( '[%s] New Availability Request – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), $tour_name );

    // Your message content
    $message = sprintf(
            "Name: %s %s\nEmail: %s\nPhone: %s\nTravelers: %d\nTravel Date: %s\nTour: %s\nMessage: %s\n\nManage: %s",
            $first_name, $last_name, $email, $phone, $travelers, $travel_date, $tour_name, $message,
            admin_url( 'edit.php?post_type=wctb_availability' )
        );

    // Wrap message with WooCommerce template (header + footer)
    $wrapped_message = $mailer->wrap_message($subject, $message);

    // Notify admin
    $mailer->send(get_option( 'admin_email' ), $subject, $wrapped_message);
 
    // Confirmation to customer

    $subject = sprintf( __( '[%s] Availability Request Received – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), $tour_name );
 
    $message = sprintf(
            __( "Hi %s,\n\nThank you for your availability request for %s.\nWe will review your request and get back to you shortly.\n\nBest regards,\n%s", 'wc-tour-booking' ),
            $first_name, $tour_name, get_bloginfo( 'name' )
        );
 
    $wrapped_message = $mailer->wrap_message($subject, $message);
    $mailer->send($email, $subject, $wrapped_message);
 

    wp_send_json_success( [ 'message' => __( "Thank you! Your request has been received. We'll be in touch shortly.", 'wc-tour-booking' ) ] );
}

// ─── Custom Journey CPT ────────────────────────────────────────────────────────
add_action( 'init', 'wctb_register_custom_journey_cpt' );
function wctb_register_custom_journey_cpt() {
    register_post_status( 'wctb_in_review', [
        'label'                     => _x( 'In Review', 'post status', 'wc-tour-booking' ),
        'label_count'               => _n_noop( 'In Review <span class="count">(%s)</span>', 'In Review <span class="count">(%s)</span>', 'wc-tour-booking' ),
        'public'                    => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
    ] );

    register_post_status( 'wctb_approved', [
        'label'                     => _x( 'Approved', 'post status', 'wc-tour-booking' ),
        'label_count'               => _n_noop( 'Approved <span class="count">(%s)</span>', 'Approved <span class="count">(%s)</span>', 'wc-tour-booking' ),
        'public'                    => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
    ] );

    register_post_type( 'wctb_custom_journey', [
        'labels' => [
            'name'               => __( 'Custom Journeys',        'wc-tour-booking' ),
            'singular_name'      => __( 'Custom Journey',         'wc-tour-booking' ),
            'menu_name'          => __( 'Custom Journeys',        'wc-tour-booking' ),
            'edit_item'          => __( 'Edit Custom Journey',    'wc-tour-booking' ),
            'view_item'          => __( 'View Custom Journey',    'wc-tour-booking' ),
            'search_items'       => __( 'Search Custom Journeys', 'wc-tour-booking' ),
            'not_found'          => __( 'No custom journeys found.',        'wc-tour-booking' ),
            'not_found_in_trash' => __( 'No custom journeys in trash.',     'wc-tour-booking' ),
        ],
        'public'            => false,
        'show_ui'           => true,
        'show_in_menu'      => 'woocommerce',
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => false,
        'supports'          => [ 'title' ],
        'capability_type'   => 'post',
        'map_meta_cap'      => true,
    ] );
}

// ─── Custom Journey: admin columns ────────────────────────────────────────────
add_filter( 'manage_wctb_custom_journey_posts_columns', 'wctb_cj_admin_columns' );
function wctb_cj_admin_columns( $cols ) {
    return [
        'cb'          => '<input type="checkbox">',
        'title'       => __( 'Name',        'wc-tour-booking' ),
        'destination' => __( 'Destination', 'wc-tour-booking' ),
        'travelers'   => __( 'Travelers',   'wc-tour-booking' ),
        'travel_when' => __( 'Travel When', 'wc-tour-booking' ),
        'budget'      => __( 'Budget',      'wc-tour-booking' ),
        'cj_status'   => __( 'Status',      'wc-tour-booking' ),
        'date'        => __( 'Submitted',   'wc-tour-booking' ),
    ];
}

add_action( 'manage_wctb_custom_journey_posts_custom_column', 'wctb_cj_admin_column_content', 10, 2 );
function wctb_cj_admin_column_content( $col, $post_id ) {
    switch ( $col ) {
        case 'destination':
            echo esc_html( get_post_meta( $post_id, '_wctb_cj_destination', true ) ?: '—' );
            break;
        case 'travelers':
            echo esc_html( get_post_meta( $post_id, '_wctb_cj_travelers', true ) ?: '—' );
            break;
        case 'travel_when':
            echo esc_html( get_post_meta( $post_id, '_wctb_cj_travel_when', true ) ?: '—' );
            break;
        case 'budget':
            echo esc_html( get_post_meta( $post_id, '_wctb_cj_budget', true ) ?: '—' );
            break;
        case 'cj_status':
            $status_obj = get_post_status_object( get_post_status( $post_id ) );
            echo $status_obj ? esc_html( $status_obj->label ) : '—';
            break;
    }
}

// ─── Custom Journey: inject custom statuses into editor dropdown ──────────────
add_action( 'admin_footer-post.php',     'wctb_cj_inject_status_options' );
add_action( 'admin_footer-post-new.php', 'wctb_cj_inject_status_options' );
function wctb_cj_inject_status_options() {
    global $post;
    if ( ! $post || 'wctb_custom_journey' !== $post->post_type ) return;

    $custom_statuses = [
        'wctb_in_review' => __( 'In Review', 'wc-tour-booking' ),
        'wctb_approved'  => __( 'Approved',  'wc-tour-booking' ),
    ];
    ?>
<script>
    jQuery(function ($) {
        <
        ?
        php foreach($custom_statuses as $status => $label):
            $selected = $post - > post_status === $status ? ' selected="selected"' : ''; ? >
        $('#post_status').append(
            '<option value="<?php echo esc_js( $status ); ?>"<?php echo $selected; ?>><?php echo esc_js( $label ); ?></option>'
        ); <
        ?
        php
        if ($post - > post_status === $status): ? >
            $('#post-status-display').text('<?php echo esc_js( $label ); ?>'); <
        ?
        php endif; ? >
        <
        ?
        php endforeach; ? >
    });
</script>
<?php
}

// ─── Custom Journey: meta boxes ───────────────────────────────────────────────
add_action( 'add_meta_boxes', 'wctb_cj_register_meta_boxes' );
function wctb_cj_register_meta_boxes() {
    add_meta_box(
        'wctb_cj_details',
        __( 'Journey Details', 'wc-tour-booking' ),
        'wctb_cj_details_meta_box',
        'wctb_custom_journey',
        'normal',
        'high'
    );
}

function wctb_cj_details_meta_box( $post ) {
    wp_nonce_field( 'wctb_cj_save_meta', 'wctb_cj_meta_nonce' );

    $fields = [
        'wctb_cj_first_name'     => [ __( 'First Name',                     'wc-tour-booking' ), 'text'     ],
        'wctb_cj_last_name'      => [ __( 'Last Name',                      'wc-tour-booking' ), 'text'     ],
        'wctb_cj_phone'          => [ __( 'Phone Number',                   'wc-tour-booking' ), 'text'     ],
        'wctb_cj_contact_method' => [ __( 'Method of Contact',              'wc-tour-booking' ), 'text'     ],
        'wctb_cj_destination'    => [ __( 'Where would you like to travel?', 'wc-tour-booking' ), 'text'     ],
        'wctb_cj_budget'         => [ __( 'Daily Budget',                   'wc-tour-booking' ), 'text'     ],
        'wctb_cj_travelers'      => [ __( 'Number of Travelers',            'wc-tour-booking' ), 'number'   ],
        'wctb_cj_travel_when'    => [ __( 'When are you looking to travel?', 'wc-tour-booking' ), 'text'     ],
        'wctb_cj_travel_date'    => [ __( 'Related Travel Date',            'wc-tour-booking' ), 'text'     ],
        'wctb_cj_product_id'     => [ __( 'Related Product / Tour ID',      'wc-tour-booking' ), 'number'   ],
        'wctb_cj_priorities'     => [ __( 'Top 3 Priorities',               'wc-tour-booking' ), 'textarea' ],
        'wctb_cj_travel_style'   => [ __( 'When I travel I…',               'wc-tour-booking' ), 'textarea' ],
        'wctb_cj_notes'          => [ __( 'Anything else we should know?',  'wc-tour-booking' ), 'textarea' ],

    ];
    ?>
<style>
    .wctb-mb-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 24px;
    }

    .wctb-mb-grid .wctb-mb-full {
        grid-column: 1 / -1;
    }

    .wctb-mb-grid label {
        display: block;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .wctb-mb-grid input[type=text],
    .wctb-mb-grid input[type=number],
    .wctb-mb-grid textarea {
        width: 100%;
        box-sizing: border-box;
    }
</style>
<div class="wctb-mb-grid">
    <?php foreach ( $fields as $key => $info ) :
        list( $label, $type ) = $info;
        $value = get_post_meta( $post->ID, '_' . $key, true );
        $full  = ( $type === 'textarea' ) ? ' wctb-mb-full' : '';
        ?>
    <div class="<?php echo $full ? 'wctb-mb-full' : ''; ?>">
        <label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
        <?php if ( $type === 'textarea' ) : ?>
        <textarea id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>"
            rows="3"><?php echo esc_textarea( $value ); ?></textarea>
        <?php else : ?>
        <input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $key ); ?>"
            name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php
}

add_action( 'save_post_wctb_custom_journey', 'wctb_cj_save_meta_box', 10, 2 );
function wctb_cj_save_meta_box( $post_id, $post ) {
    if ( ! isset( $_POST['wctb_cj_meta_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['wctb_cj_meta_nonce'], 'wctb_cj_save_meta' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields     = [ 'wctb_cj_first_name', 'wctb_cj_last_name', 'wctb_cj_phone',
                          'wctb_cj_contact_method', 'wctb_cj_destination',
                          'wctb_cj_budget', 'wctb_cj_travel_when', 'wctb_cj_travel_date' ];
    $textarea_fields = [ 'wctb_cj_priorities', 'wctb_cj_travel_style', 'wctb_cj_notes' ];
    $int_fields      = [ 'wctb_cj_travelers', 'wctb_cj_product_id' ];

    foreach ( $text_fields as $key ) {
        update_post_meta( $post_id, '_' . $key, sanitize_text_field( $_POST[ $key ] ?? '' ) );
    }
    foreach ( $textarea_fields as $key ) {
        update_post_meta( $post_id, '_' . $key, sanitize_textarea_field( $_POST[ $key ] ?? '' ) );
    }
    foreach ( $int_fields as $key ) {
        update_post_meta( $post_id, '_' . $key, absint( $_POST[ $key ] ?? 0 ) );
    }
}

// ─── Custom Journey AJAX ──────────────────────────────────────────────────────
add_action( 'wp_ajax_wctb_submit_custom_journey',        'wctb_handle_custom_journey_submit' );
add_action( 'wp_ajax_nopriv_wctb_submit_custom_journey', 'wctb_handle_custom_journey_submit' );

function wctb_handle_custom_journey_submit() {
    check_ajax_referer( 'wctb_journey_nonce', 'nonce' );

    $product_id     = absint(                  $_POST['product_id']     ?? 0  );
    $first_name     = sanitize_text_field(     $_POST['first_name']     ?? '' );
    $last_name      = sanitize_text_field(     $_POST['last_name']      ?? '' );
    $phone          = sanitize_text_field(     $_POST['phone']          ?? '' );
    $contact_method = sanitize_text_field(     $_POST['contact_method'] ?? '' );
    $destination    = sanitize_text_field(     $_POST['destination']    ?? '' );
    $priorities     = sanitize_textarea_field( $_POST['priorities']     ?? '' );
    $travel_style   = sanitize_textarea_field( $_POST['travel_style']   ?? '' );
    $budget         = sanitize_text_field(     $_POST['budget']         ?? '' );
    $travelers      = absint(                  $_POST['travelers']      ?? 1  );
    $travel_when    = sanitize_text_field(     $_POST['travel_when']    ?? '' );
    $notes          = sanitize_textarea_field( $_POST['notes']          ?? '' );
    $travel_date    = sanitize_text_field(     $_POST['travel_date']    ?? '' );

    if ( ! $first_name || ! $last_name || ! $phone || ! $contact_method || ! $destination ) {
        wp_send_json_error( [ 'message' => __( 'Please fill in all required fields.', 'wc-tour-booking' ) ] );
    }

    $product   = $product_id ? wc_get_product( $product_id ) : null;
    $tour_name = $product ? $product->get_name() : ( $product_id ? "Product #{$product_id}" : '' );

    $post_id = wp_insert_post( [
        'post_type'   => 'wctb_custom_journey',
        'post_title'  => "{$first_name} {$last_name}" . ( $tour_name ? " – {$tour_name}" : '' ),
        'post_status' => 'draft',
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Could not save your request. Please try again.', 'wc-tour-booking' ) ] );
    }

    $meta = [
        '_wctb_cj_first_name'     => $first_name,
        '_wctb_cj_last_name'      => $last_name,
        '_wctb_cj_phone'          => $phone,
        '_wctb_cj_contact_method' => $contact_method,
        '_wctb_cj_destination'    => $destination,
        '_wctb_cj_priorities'     => $priorities,
        '_wctb_cj_travel_style'   => $travel_style,
        '_wctb_cj_budget'         => $budget,
        '_wctb_cj_travelers'      => $travelers,
        '_wctb_cj_travel_when'    => $travel_when,
        '_wctb_cj_notes'          => $notes,
        '_wctb_cj_travel_date'    => $travel_date,
        '_wctb_cj_product_id'     => $product_id,
    ];
    foreach ( $meta as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
    }

    // Notify admin
    /*
    wp_mail(
        get_option( 'admin_email' ),
        sprintf( __( '[%s] New Custom Journey Request – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), "{$first_name} {$last_name}" ),
        sprintf(
            "Name: %s %s\nPhone: %s\nContact: %s\nDestination: %s\nPriorities: %s\nTravel Style: %s\nBudget: %s\nTravelers: %d\nTravel When: %s\nNotes: %s\nTravel Date: %s\nTour: %s\n\nManage: %s",
            $first_name, $last_name, $phone, $contact_method, $destination,
            $priorities, $travel_style, $budget, $travelers, $travel_when,
            $notes, $travel_date, $tour_name,
            admin_url( 'edit.php?post_type=wctb_custom_journey' )
        )
    );*/

    $mailer = WC()->mailer();

    // Email subject
    $subject = sprintf( __( '[%s] New Custom Journey Request – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), "{$first_name} {$last_name}" );

    // Your message content
    $message = sprintf(
            "Name: %s %s\nPhone: %s\nContact: %s\nDestination: %s\nPriorities: %s\nTravel Style: %s\nBudget: %s\nTravelers: %d\nTravel When: %s\nNotes: %s\nTravel Date: %s\nTour: %s\n\nManage: %s",
            $first_name, $last_name, $phone, $contact_method, $destination,
            $priorities, $travel_style, $budget, $travelers, $travel_when,
            $notes, $travel_date, $tour_name,
            admin_url( 'edit.php?post_type=wctb_custom_journey' )
        );

    // Wrap message with WooCommerce template (header + footer)
    $wrapped_message = $mailer->wrap_message($subject, $message);

    // Send email
    $mailer->send(get_option( 'admin_email' ), $subject, $wrapped_message);


    wp_send_json_success( [ 'message' => __( "Thank you! We've received your request and will be in touch soon.", 'wc-tour-booking' ) ] );
}

 