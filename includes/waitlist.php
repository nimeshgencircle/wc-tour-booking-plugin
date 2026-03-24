<?php
/**
 * Waitlist – CPT-based system
 * Each submission is a 'wctb_waitlist' post; approving creates a WC order and
 * emails the customer a direct pay link.
 */

defined( 'ABSPATH' ) || exit;

// ─── Register CPT ─────────────────────────────────────────────────────────────
add_action( 'init', 'wctb_register_waitlist_cpt' );
function wctb_register_waitlist_cpt() {
    register_post_type( 'wctb_waitlist', [
        'labels'          => [
            'name'               => __( 'Waitlist',            'wc-tour-booking' ),
            'singular_name'      => __( 'Waitlist Entry',      'wc-tour-booking' ),
            'menu_name'          => __( 'Waitlist',            'wc-tour-booking' ),
            'all_items'          => __( 'All Waitlist Entries', 'wc-tour-booking' ),
            'edit_item'          => __( 'Edit Entry',           'wc-tour-booking' ),
            'view_item'          => __( 'View Entry',           'wc-tour-booking' ),
            'search_items'       => __( 'Search Entries',       'wc-tour-booking' ),
            'not_found'          => __( 'No entries found.',    'wc-tour-booking' ),
            'not_found_in_trash' => __( 'No entries in Trash.', 'wc-tour-booking' ),
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'woocommerce',
        'show_in_rest'    => false,
        'supports'        => [ 'title' ],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ] );

     register_post_type( 'wctb_availability', [
        'labels'          => [
            'name'               => __( 'Availability',            'wc-tour-booking' ),
            'singular_name'      => __( 'Availability Entry',      'wc-tour-booking' ),
            'menu_name'          => __( 'Availability',            'wc-tour-booking' ),
            'all_items'          => __( 'All availability Entries', 'wc-tour-booking' ),
            'edit_item'          => __( 'Edit Entry',           'wc-tour-booking' ),
            'view_item'          => __( 'View Entry',           'wc-tour-booking' ),
            'search_items'       => __( 'Search Entries',       'wc-tour-booking' ),
            'not_found'          => __( 'No entries found.',    'wc-tour-booking' ),
            'not_found_in_trash' => __( 'No entries in Trash.', 'wc-tour-booking' ),
        ],
        'public'          => false,
        'show_ui'         => true,
        'show_in_menu'    => 'woocommerce',
        'show_in_rest'    => false,
        'supports'        => [ 'title' ],
        'capability_type' => 'post',
        'map_meta_cap'    => true,
    ] );

}

// Hide the "Slug" and default "Custom Fields" meta boxes from the edit screen.
add_action( 'do_meta_boxes', 'wctb_waitlist_remove_default_boxes' );
function wctb_waitlist_remove_default_boxes() {
    remove_meta_box( 'slugdiv',    'wctb_waitlist',     'normal' );
    remove_meta_box( 'postcustom', 'wctb_waitlist',     'normal' );
    remove_meta_box( 'slugdiv',    'wctb_availability', 'normal' );
    remove_meta_box( 'postcustom', 'wctb_availability', 'normal' );
}

// ─── AJAX: Frontend form submission ──────────────────────────────────────────
add_action( 'wp_ajax_wctb_submit_waitlist',        'wctb_handle_waitlist_submit' );
add_action( 'wp_ajax_nopriv_wctb_submit_waitlist', 'wctb_handle_waitlist_submit' );

function wctb_handle_waitlist_submit() {
    check_ajax_referer( 'wctb_waitlist_nonce', 'nonce' );

    $product_id  = absint(                    $_POST['product_id']  ?? 0   );
    $first_name  = sanitize_text_field(       $_POST['first_name']  ?? ''  );
    $last_name   = sanitize_text_field(       $_POST['last_name']   ?? ''  );
    $email       = sanitize_email(            $_POST['email']       ?? ''  );
    $phone       = sanitize_text_field(       $_POST['phone']       ?? ''  );
    $travelers   = absint(                    $_POST['travelers']   ?? 1   );
    $message     = sanitize_textarea_field(   $_POST['message']     ?? ''  );
    $travel_date = sanitize_text_field(       $_POST['date']        ?? ''  );
    $contact_method = sanitize_text_field(   $_POST['contact_method']     ?? ''  );
    $announcements = sanitize_text_field(   $_POST['announcements']     ?? ''  );



    if ( ! $product_id || ! $first_name || ! $last_name || ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => __( 'Please fill in all required fields.', 'wc-tour-booking' ) ] );
    }

    $product  = wc_get_product( $product_id );
    $tour_name = $product ? $product->get_name() : "Product #{$product_id}";

    $post_id = wp_insert_post( [
        'post_type'   => 'wctb_waitlist',
        'post_title'  => "{$first_name} {$last_name} – {$tour_name}",
        'post_status' => 'publish',
    ] );

    if ( is_wp_error( $post_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Could not save your request. Please try again.', 'wc-tour-booking' ) ] );
    }

    $meta = [
        '_wctb_wl_product_id'  => $product_id,
        '_wctb_wl_travel_date' => $travel_date,
        '_wctb_wl_first_name'  => $first_name,
        '_wctb_wl_last_name'   => $last_name,
        '_wctb_wl_email'       => $email,
        '_wctb_wl_phone'       => $phone,
        '_wctb_wl_travelers'   => $travelers,
        '_wctb_wl_message'     => $message,
        '_wctb_wl_contact_method' => $contact_method,   
        '_wctb_announcements'=> $announcements,    
        '_wctb_wl_status'      => 'pending',
    ];
    foreach ( $meta as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
    }

    $mailer = WC()->mailer();
 
    $subject = sprintf( __( '[%s] New Waitlist Submission – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), $tour_name );

    // Your message content
    $message = sprintf(
            "Name: %s %s\nEmail: %s\nPhone: %s\nTravelers: %d\nTravel Date: %s\nTour: %s\nMessage: %s\n\nManage: %s",
            $first_name, $last_name, $email, $phone, $travelers, $travel_date, $tour_name, $message,
            admin_url( 'edit.php?post_type=wctb_waitlist' )
        );

    // Wrap message with WooCommerce template (header + footer)
    $wrapped_message = $mailer->wrap_message($subject, $message);

    // Send email
    $mailer->send(get_option( 'admin_email' ), $subject, $wrapped_message);

    // Notify admin
    /*
    wp_mail(
        get_option( 'admin_email' ),
        sprintf( __( '[%s] New Waitlist Submission – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), $tour_name ),
        sprintf(
            "Name: %s %s\nEmail: %s\nPhone: %s\nTravelers: %d\nTravel Date: %s\nTour: %s\nMessage: %s\n\nManage: %s",
            $first_name, $last_name, $email, $phone, $travelers, $travel_date, $tour_name, $message,
            admin_url( 'edit.php?post_type=wctb_waitlist' )
        )
    );

    // Confirmation to user
    wp_mail(
        $email,
        sprintf( __( '[%s] Waitlist Confirmation – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), $tour_name ),
        sprintf(
            __( "Hi %s,\n\nThank you for joining the waitlist for %s.\nWe will notify you as soon as a spot becomes available.\n\nBest regards,\n%s", 'wc-tour-booking' ),
            $first_name, $tour_name, get_bloginfo( 'name' )
        )
    );*/

    $subject = sprintf( __( '[%s] Waitlist Confirmation – %s', 'wc-tour-booking' ), get_bloginfo( 'name' ), $tour_name );

    // Your message content
    $message = sprintf(
            __( "Hi %s,\n\nThank you for joining the waitlist for %s.\nWe will notify you as soon as a spot becomes available.\n\nBest regards,\n%s", 'wc-tour-booking' ),
            $first_name, $tour_name, get_bloginfo( 'name' )
        );

    // Wrap message with WooCommerce template (header + footer)
    $wrapped_message = $mailer->wrap_message($subject, $message);

    // Send email
    $mailer->send($email, $subject, $wrapped_message);

    wp_send_json_success( [ 'message' => __( "You've been added to the waitlist! We'll contact you if a spot opens up.", 'wc-tour-booking' ) ] );
}

// ─── Admin: custom list columns ───────────────────────────────────────────────
add_filter( 'manage_wctb_waitlist_posts_columns', 'wctb_waitlist_columns' );
function wctb_waitlist_columns( $columns ) {
    return [
        'cb'               => '<input type="checkbox">',
        'title'            => __( 'Customer',    'wc-tour-booking' ),
        'wctb_tour'        => __( 'Tour',         'wc-tour-booking' ),
        'wctb_email'       => __( 'Email',        'wc-tour-booking' ),
        'wctb_travelers'   => __( 'Travelers',    'wc-tour-booking' ),
        'wctb_travel_date' => __( 'Travel Date',  'wc-tour-booking' ),
        'wctb_status'      => __( 'Status',       'wc-tour-booking' ),
        'wctb_order'       => __( 'Order',        'wc-tour-booking' ),
        'date'             => __( 'Submitted',    'wc-tour-booking' ),
    ];
}

add_action( 'manage_wctb_waitlist_posts_custom_column', 'wctb_waitlist_column_content', 10, 2 );
function wctb_waitlist_column_content( $column, $post_id ) {
    switch ( $column ) {

        case 'wctb_tour':
            $pid  = (int) get_post_meta( $post_id, '_wctb_wl_product_id', true );
            $prod = $pid ? wc_get_product( $pid ) : false;
            echo $prod
                ? '<a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html( $prod->get_name() ) . '</a>'
                : esc_html( "#{$pid}" );
            break;

        case 'wctb_email':
            echo esc_html( get_post_meta( $post_id, '_wctb_wl_email', true ) );
            break;

        case 'wctb_travelers':
            echo esc_html( get_post_meta( $post_id, '_wctb_wl_travelers', true ) );
            break;

        case 'wctb_travel_date':
            echo esc_html( get_post_meta( $post_id, '_wctb_wl_travel_date', true ) ?: '—' );
            break;

        case 'wctb_status':
            $status = get_post_meta( $post_id, '_wctb_wl_status', true ) ?: 'pending';
            $map    = [
                'pending'  => [ '#fff3cd', '#856404' ],
                'approved' => [ '#d4edda', '#155724' ],
                'rejected' => [ '#f8d7da', '#721c24' ],
            ];
            [ $bg, $color ] = $map[ $status ] ?? [ '#eee', '#333' ];
            printf(
                '<span style="background:%s;color:%s;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;">%s</span>',
                esc_attr( $bg ), esc_attr( $color ), esc_html( ucfirst( $status ) )
            );
            break;

        case 'wctb_order':
            $order_id = get_post_meta( $post_id, '_wctb_wl_order_id', true );
            echo $order_id
                ? '<a href="' . esc_url( admin_url( "post.php?post={$order_id}&action=edit" ) ) . '">#' . (int) $order_id . '</a>'
                : '—';
            break;
    }
}

add_filter( 'manage_edit-wctb_waitlist_sortable_columns', 'wctb_waitlist_sortable_columns' );
function wctb_waitlist_sortable_columns( $columns ) {
    $columns['wctb_status'] = 'wctb_status';
    return $columns;
}

// ─── Admin: meta box ──────────────────────────────────────────────────────────
add_action( 'add_meta_boxes', 'wctb_waitlist_add_meta_boxes' );
function wctb_waitlist_add_meta_boxes() {
    add_meta_box(
        'wctb_waitlist_details',
        __( 'Waitlist Entry Details', 'wc-tour-booking' ),
        'wctb_waitlist_meta_box_html',
        'wctb_waitlist',
        'normal',
        'high'
    );
}

function wctb_waitlist_meta_box_html( $post ) {
    wp_nonce_field( 'wctb_waitlist_meta_save', 'wctb_waitlist_meta_nonce' );

    $product_id = (int) get_post_meta( $post->ID, '_wctb_wl_product_id', true );
    $product    = $product_id ? wc_get_product( $product_id ) : false;

    if ( $product ) {
        echo '<p><strong>' . esc_html__( 'Tour:', 'wc-tour-booking' ) . '</strong> '
            . '<a href="' . esc_url( get_edit_post_link( $product_id ) ) . '" target="_blank">'
            . esc_html( $product->get_name() ) . '</a></p><hr>';
    }

    $editable_fields = [
        '_wctb_wl_travel_date' => [ 'label' => __( 'Travel Date',         'wc-tour-booking' ), 'type' => 'text'     ],
        '_wctb_wl_first_name'  => [ 'label' => __( 'First Name',          'wc-tour-booking' ), 'type' => 'text'     ],
        '_wctb_wl_last_name'   => [ 'label' => __( 'Last Name',           'wc-tour-booking' ), 'type' => 'text'     ],
        '_wctb_wl_email'       => [ 'label' => __( 'Email Address',       'wc-tour-booking' ), 'type' => 'email'    ],
        '_wctb_wl_phone'       => [ 'label' => __( 'Phone Number',        'wc-tour-booking' ), 'type' => 'tel'      ],
        '_wctb_wl_travelers'   => [ 'label' => __( 'Number of Travelers', 'wc-tour-booking' ), 'type' => 'number'   ],
        '_wctb_wl_message'     => [ 'label' => __( 'Message',             'wc-tour-booking' ), 'type' => 'textarea' ],
        '_wctb_wl_contact_method' => [ 'label' => __( 'Contact Method',             'wc-tour-booking' ), 'type' => 'text'     ],
        '_wctb_announcements'=> [ 'label' => __( 'Is Announcements',             'wc-tour-booking' ), 'type' => 'text' ],
    ];

    echo '<table class="form-table"><tbody>';

    foreach ( $editable_fields as $key => $cfg ) {
        $value = get_post_meta( $post->ID, $key, true );
        echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $cfg['label'] ) . '</label></th><td>';
        if ( $cfg['type'] === 'textarea' ) {
            echo '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" class="large-text" rows="4">'
                . esc_textarea( $value ) . '</textarea>';
        } else {
            echo '<input type="' . esc_attr( $cfg['type'] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" '
                . 'value="' . esc_attr( $value ) . '" class="regular-text">';
        }
        echo '</td></tr>';
    }

    // Status select
    $status = get_post_meta( $post->ID, '_wctb_wl_status', true ) ?: 'pending';
    $statuses = [
        'pending'  => __( 'Pending',  'wc-tour-booking' ),
        'approved' => __( 'Approved', 'wc-tour-booking' ),
        'rejected' => __( 'Rejected', 'wc-tour-booking' ),
    ];
    echo '<tr><th scope="row"><label for="_wctb_wl_status">' . esc_html__( 'Status', 'wc-tour-booking' ) . '</label></th><td>';
    echo '<select id="_wctb_wl_status" name="_wctb_wl_status">';
    foreach ( $statuses as $val => $label ) {
        printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $status, $val, false ), esc_html( $label ) );
    }
    echo '</select>';
    echo '<p class="description">'
        . esc_html__( 'Setting to "Approved" automatically creates a WooCommerce order and emails the customer a payment link.', 'wc-tour-booking' )
        . '</p>';
    echo '</td></tr>';

    // Linked order (read-only)
    $order_id = get_post_meta( $post->ID, '_wctb_wl_order_id', true );
    if ( $order_id ) {
        echo '<tr><th scope="row">' . esc_html__( 'WC Order', 'wc-tour-booking' ) . '</th><td>'
            . '<a href="' . esc_url( admin_url( "post.php?post={$order_id}&action=edit" ) ) . '" target="_blank">'
            . esc_html__( 'Order #', 'wc-tour-booking' ) . esc_html( $order_id ) . '</a>'
            . '</td></tr>';
    }

    echo '</tbody></table>';
}

// ─── Admin: save meta ─────────────────────────────────────────────────────────
add_action( 'save_post_wctb_waitlist', 'wctb_save_waitlist_post_meta', 10, 2 );
function wctb_save_waitlist_post_meta( $post_id, $post ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
    if ( ! isset( $_POST['wctb_waitlist_meta_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['wctb_waitlist_meta_nonce'], 'wctb_waitlist_meta_save' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields = [
        '_wctb_wl_travel_date',
        '_wctb_wl_first_name',
        '_wctb_wl_last_name',
        '_wctb_wl_email',
        '_wctb_wl_phone',
    ];
    foreach ( $text_fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
        }
    }

    if ( isset( $_POST['_wctb_wl_travelers'] ) ) {
        update_post_meta( $post_id, '_wctb_wl_travelers', absint( $_POST['_wctb_wl_travelers'] ) );
    }
    if ( isset( $_POST['_wctb_wl_message'] ) ) {
        update_post_meta( $post_id, '_wctb_wl_message', sanitize_textarea_field( $_POST['_wctb_wl_message'] ) );
    }

    $new_status = sanitize_text_field( $_POST['_wctb_wl_status'] ?? 'pending' );
    if ( ! in_array( $new_status, [ 'pending', 'approved', 'rejected' ], true ) ) {
        $new_status = 'pending';
    }
    $old_status = get_post_meta( $post_id, '_wctb_wl_status', true );
    update_post_meta( $post_id, '_wctb_wl_status', $new_status );

    // Create WC order on first approval
    if ( $new_status === 'approved' && $old_status !== 'approved' ) {
        $existing_order = get_post_meta( $post_id, '_wctb_wl_order_id', true );
        if ( ! $existing_order ) {
            wctb_create_order_from_waitlist( $post_id );
        }
    }
}

// ─── Create WC order from waitlist entry ──────────────────────────────────────
function wctb_create_order_from_waitlist( int $post_id ) {
    $product_id  = (int) get_post_meta( $post_id, '_wctb_wl_product_id',  true );
    $first_name  =       get_post_meta( $post_id, '_wctb_wl_first_name',  true );
    $last_name   =       get_post_meta( $post_id, '_wctb_wl_last_name',   true );
    $email       =       get_post_meta( $post_id, '_wctb_wl_email',       true );
    $phone       =       get_post_meta( $post_id, '_wctb_wl_phone',       true );
    $travelers   = max( 1, (int) get_post_meta( $post_id, '_wctb_wl_travelers', true ) );
    $travelers   = 1;
    $travel_date =       get_post_meta( $post_id, '_wctb_wl_travel_date', true );

    $product = wc_get_product( $product_id );
    if ( ! $product ) return;

    $order = wc_create_order( [ 'customer_id' => 0 ] );
    $order->add_product( $product, $travelers );

    $address = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
    ];
    $order->set_address( $address, 'billing' );
    $order->set_address( $address, 'shipping' );

    // Store the tour date as order meta for reference
    if ( $travel_date ) {
        $order->update_meta_data( '_wctb_travel_date', $travel_date );
    }

    $order->set_status( 'pending' );
    $order->calculate_totals();
    $order->save();

    update_post_meta( $post_id, '_wctb_wl_order_id', $order->get_id() );

    wctb_send_waitlist_approval_email( $post_id, $order );
}

// ─── Approval email with pay link ─────────────────────────────────────────────
function wctb_send_waitlist_approval_email( int $post_id, $order ) {
    $first_name  =       get_post_meta( $post_id, '_wctb_wl_first_name',  true );
    $email       =       get_post_meta( $post_id, '_wctb_wl_email',       true );
    $travel_date =       get_post_meta( $post_id, '_wctb_wl_travel_date', true );
    $travelers   = (int) get_post_meta( $post_id, '_wctb_wl_travelers',   true );
    $product_id  = (int) get_post_meta( $post_id, '_wctb_wl_product_id',  true );

    $product  = wc_get_product( $product_id );
    $tour     = $product ? $product->get_name() : '';
    $pay_url  = $order->get_checkout_payment_url();
    $order_id = $order->get_id();
    $total    = strip_tags( $order->get_formatted_order_total() );
    $site     = get_bloginfo( 'name' );

        $mailer = WC()->mailer();


    $subject = sprintf(
        __( '[%s] Your waitlist spot is confirmed! – Order #%d', 'wc-tour-booking' ),
        $site, $order_id
    );

    $message = sprintf(
        __( "Hi %s,\n\n"
          . "Great news! Your waitlist request has been approved.\n\n"
          . "─────────────────────────\n"
          . "BOOKING SUMMARY\n"
          . "─────────────────────────\n"
          . "Tour:         %s\n"
          . "Travel Date:  %s\n"
          . "Travelers:    %d\n"
          . "Order #:      %d\n"
          . "Total:        %s\n"
          . "─────────────────────────\n\n"
          . "To confirm your booking please complete your payment:\n"
          . "%s\n\n"
          . "This link is unique to your order — please do not share it.\n\n"
          . "Thank you for choosing %s!\n",
          'wc-tour-booking' ),
        $first_name, $tour, $travel_date ?: '—', $travelers,
        $order_id, $total, $pay_url, $site
    );

     $wrapped_message = $mailer->wrap_message($subject, $message);

    // Send email
    $mailer->send($email, $subject, $wrapped_message);


    //wp_mail( $email, $subject, $message );

}

// ═══════════════════════════════════════════════════════════════════════════════
// AVAILABILITY CPT — Admin workflow (mirrors Waitlist above)
// Meta key prefix: _wctb_av_
// ═══════════════════════════════════════════════════════════════════════════════

// ─── Admin: custom list columns ───────────────────────────────────────────────
add_filter( 'manage_wctb_availability_posts_columns', 'wctb_availability_columns' );
function wctb_availability_columns( $columns ) {
    return [
        'cb'               => '<input type="checkbox">',
        'title'            => __( 'Customer',    'wc-tour-booking' ),
        'wctb_tour'        => __( 'Tour',         'wc-tour-booking' ),
        'wctb_email'       => __( 'Email',        'wc-tour-booking' ),
        'wctb_travelers'   => __( 'Travelers',    'wc-tour-booking' ),
        'wctb_travel_date' => __( 'Travel Date',  'wc-tour-booking' ),
        'wctb_status'      => __( 'Status',       'wc-tour-booking' ),
        'wctb_order'       => __( 'Order',        'wc-tour-booking' ),
        'date'             => __( 'Submitted',    'wc-tour-booking' ),
    ];
}

add_action( 'manage_wctb_availability_posts_custom_column', 'wctb_availability_column_content', 10, 2 );
function wctb_availability_column_content( $column, $post_id ) {
    switch ( $column ) {

        case 'wctb_tour':
            $pid  = (int) get_post_meta( $post_id, '_wctb_av_product_id', true );
            $prod = $pid ? wc_get_product( $pid ) : false;
            echo $prod
                ? '<a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html( $prod->get_name() ) . '</a>'
                : esc_html( "#{$pid}" );
            break;

        case 'wctb_email':
            echo esc_html( get_post_meta( $post_id, '_wctb_av_email', true ) );
            break;

        case 'wctb_travelers':
            echo esc_html( get_post_meta( $post_id, '_wctb_av_travelers', true ) );
            break;

        case 'wctb_travel_date':
            echo esc_html( get_post_meta( $post_id, '_wctb_av_travel_date', true ) ?: '—' );
            break;

        case 'wctb_status':
            $status = get_post_meta( $post_id, '_wctb_av_status', true ) ?: 'pending';
            $map    = [
                'pending'  => [ '#fff3cd', '#856404' ],
                'approved' => [ '#d4edda', '#155724' ],
                'rejected' => [ '#f8d7da', '#721c24' ],
            ];
            [ $bg, $color ] = $map[ $status ] ?? [ '#eee', '#333' ];
            printf(
                '<span style="background:%s;color:%s;padding:2px 10px;border-radius:20px;font-size:12px;font-weight:600;">%s</span>',
                esc_attr( $bg ), esc_attr( $color ), esc_html( ucfirst( $status ) )
            );
            break;

        case 'wctb_order':
            $order_id = get_post_meta( $post_id, '_wctb_av_order_id', true );
            echo $order_id
                ? '<a href="' . esc_url( admin_url( "post.php?post={$order_id}&action=edit" ) ) . '">#' . (int) $order_id . '</a>'
                : '—';
            break;
    }
}

add_filter( 'manage_edit-wctb_availability_sortable_columns', 'wctb_availability_sortable_columns' );
function wctb_availability_sortable_columns( $columns ) {
    $columns['wctb_status'] = 'wctb_status';
    return $columns;
}

// ─── Admin: meta box ──────────────────────────────────────────────────────────
add_action( 'add_meta_boxes', 'wctb_availability_add_meta_boxes' );
function wctb_availability_add_meta_boxes() {
    add_meta_box(
        'wctb_availability_details',
        __( 'Availability Request Details', 'wc-tour-booking' ),
        'wctb_availability_meta_box_html',
        'wctb_availability',
        'normal',
        'high'
    );
}

function wctb_availability_meta_box_html( $post ) {
    wp_nonce_field( 'wctb_availability_meta_save', 'wctb_availability_meta_nonce' );

    $product_id = (int) get_post_meta( $post->ID, '_wctb_av_product_id', true );
    $product    = $product_id ? wc_get_product( $product_id ) : false;

    if ( $product ) {
        echo '<p><strong>' . esc_html__( 'Tour:', 'wc-tour-booking' ) . '</strong> '
            . '<a href="' . esc_url( get_edit_post_link( $product_id ) ) . '" target="_blank">'
            . esc_html( $product->get_name() ) . '</a></p><hr>';
    }

    $editable_fields = [
        '_wctb_av_travel_date' => [ 'label' => __( 'Travel Date',         'wc-tour-booking' ), 'type' => 'text'     ],
        '_wctb_av_first_name'  => [ 'label' => __( 'First Name',          'wc-tour-booking' ), 'type' => 'text'     ],
        '_wctb_av_last_name'   => [ 'label' => __( 'Last Name',           'wc-tour-booking' ), 'type' => 'text'     ],
        '_wctb_av_email'       => [ 'label' => __( 'Email Address',       'wc-tour-booking' ), 'type' => 'email'    ],
        '_wctb_av_phone'       => [ 'label' => __( 'Phone Number',        'wc-tour-booking' ), 'type' => 'tel'      ],
        '_wctb_av_travelers'   => [ 'label' => __( 'Number of Travelers', 'wc-tour-booking' ), 'type' => 'number'   ],
        '_wctb_av_message'     => [ 'label' => __( 'Message',             'wc-tour-booking' ), 'type' => 'textarea' ],
        '_wctb_av_contact_method' => [ 'label' => __( 'Contact Method',             'wc-tour-booking' ), 'type' => 'text'     ],
        '_wctb_av_announcements'=> [ 'label' => __( 'Is Announcements',             'wc-tour-booking' ), 'type' => 'text' ],
    ];

    echo '<table class="form-table"><tbody>';

    foreach ( $editable_fields as $key => $cfg ) {
        $value = get_post_meta( $post->ID, $key, true );
        echo '<tr><th scope="row"><label for="' . esc_attr( $key ) . '">' . esc_html( $cfg['label'] ) . '</label></th><td>';
        if ( $cfg['type'] === 'textarea' ) {
            echo '<textarea id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" class="large-text" rows="4">'
                . esc_textarea( $value ) . '</textarea>';
        } else {
            echo '<input type="' . esc_attr( $cfg['type'] ) . '" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" '
                . 'value="' . esc_attr( $value ) . '" class="regular-text">';
        }
        echo '</td></tr>';
    }

    // Status select
    $status   = get_post_meta( $post->ID, '_wctb_av_status', true ) ?: 'pending';
    $statuses = [
        'pending'  => __( 'Pending',  'wc-tour-booking' ),
        'approved' => __( 'Approved', 'wc-tour-booking' ),
        'rejected' => __( 'Rejected', 'wc-tour-booking' ),
    ];
    echo '<tr><th scope="row"><label for="_wctb_av_status">' . esc_html__( 'Status', 'wc-tour-booking' ) . '</label></th><td>';
    echo '<select id="_wctb_av_status" name="_wctb_av_status">';
    foreach ( $statuses as $val => $label ) {
        printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $status, $val, false ), esc_html( $label ) );
    }
    echo '</select>';
    echo '<p class="description">'
        . esc_html__( 'Setting to "Approved" automatically creates a WooCommerce order and emails the customer a payment link.', 'wc-tour-booking' )
        . '</p>';
    echo '</td></tr>';

    // Linked order (read-only)
    $order_id = get_post_meta( $post->ID, '_wctb_av_order_id', true );
    if ( $order_id ) {
        echo '<tr><th scope="row">' . esc_html__( 'WC Order', 'wc-tour-booking' ) . '</th><td>'
            . '<a href="' . esc_url( admin_url( "post.php?post={$order_id}&action=edit" ) ) . '" target="_blank">'
            . esc_html__( 'Order #', 'wc-tour-booking' ) . esc_html( $order_id ) . '</a>'
            . '</td></tr>';
    }

    echo '</tbody></table>';
}

// ─── Admin: save meta ─────────────────────────────────────────────────────────
add_action( 'save_post_wctb_availability', 'wctb_save_availability_post_meta', 10, 2 );
function wctb_save_availability_post_meta( $post_id, $post ) {
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
    if ( ! isset( $_POST['wctb_availability_meta_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['wctb_availability_meta_nonce'], 'wctb_availability_meta_save' ) ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $text_fields = [
        '_wctb_av_travel_date',
        '_wctb_av_first_name',
        '_wctb_av_last_name',
        '_wctb_av_email',
        '_wctb_av_phone',
    ];
    foreach ( $text_fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
        }
    }

    if ( isset( $_POST['_wctb_av_travelers'] ) ) {
        update_post_meta( $post_id, '_wctb_av_travelers', absint( $_POST['_wctb_av_travelers'] ) );
    }
    if ( isset( $_POST['_wctb_av_message'] ) ) {
        update_post_meta( $post_id, '_wctb_av_message', sanitize_textarea_field( $_POST['_wctb_av_message'] ) );
    }

    $new_status = sanitize_text_field( $_POST['_wctb_av_status'] ?? 'pending' );
    if ( ! in_array( $new_status, [ 'pending', 'approved', 'rejected' ], true ) ) {
        $new_status = 'pending';
    }
    $old_status = get_post_meta( $post_id, '_wctb_av_status', true );
    update_post_meta( $post_id, '_wctb_av_status', $new_status );

    // Create WC order on first approval
    if ( $new_status === 'approved' && $old_status !== 'approved' ) {
        $existing_order = get_post_meta( $post_id, '_wctb_av_order_id', true );
        if ( ! $existing_order ) {
            wctb_create_order_from_availability( $post_id );
        }
    }
}

// ─── Create WC order from availability entry ──────────────────────────────────
function wctb_create_order_from_availability( int $post_id ) {
    $product_id  = (int) get_post_meta( $post_id, '_wctb_av_product_id',  true );
    $first_name  =       get_post_meta( $post_id, '_wctb_av_first_name',  true );
    $last_name   =       get_post_meta( $post_id, '_wctb_av_last_name',   true );
    $email       =       get_post_meta( $post_id, '_wctb_av_email',       true );
    $phone       =       get_post_meta( $post_id, '_wctb_av_phone',       true );
    $travelers   = max( 1, (int) get_post_meta( $post_id, '_wctb_av_travelers', true ) );
    $travelers   = 1;
    $travel_date =       get_post_meta( $post_id, '_wctb_av_travel_date', true );

    $product = wc_get_product( $product_id );
    if ( ! $product ) return;

    $order = wc_create_order( [ 'customer_id' => 0 ] );
    $order->add_product( $product, $travelers );

    $address = [
        'first_name' => $first_name,
        'last_name'  => $last_name,
        'email'      => $email,
        'phone'      => $phone,
    ];
    $order->set_address( $address, 'billing' );
    $order->set_address( $address, 'shipping' );

    if ( $travel_date ) {
        $order->update_meta_data( '_wctb_travel_date', $travel_date );
    }

    $order->set_status( 'pending' );
    $order->calculate_totals();
    $order->save();

    update_post_meta( $post_id, '_wctb_av_order_id', $order->get_id() );

    wctb_send_availability_approval_email( $post_id, $order );
}

// ─── Approval email with pay link ─────────────────────────────────────────────
function wctb_send_availability_approval_email( int $post_id, $order ) {
    $first_name  =       get_post_meta( $post_id, '_wctb_av_first_name',  true );
    $email       =       get_post_meta( $post_id, '_wctb_av_email',       true );
    $travel_date =       get_post_meta( $post_id, '_wctb_av_travel_date', true );
    $travelers   = (int) get_post_meta( $post_id, '_wctb_av_travelers',   true );
    $product_id  = (int) get_post_meta( $post_id, '_wctb_av_product_id',  true );

    $product  = wc_get_product( $product_id );
    $tour     = $product ? $product->get_name() : '';
    $pay_url  = $order->get_checkout_payment_url();
    $order_id = $order->get_id();
    $total    = strip_tags( $order->get_formatted_order_total() );
    $site     = get_bloginfo( 'name' );

    $mailer = WC()->mailer();

    $subject = sprintf(
        __( '[%s] Your availability request is confirmed! – Order #%d', 'wc-tour-booking' ),
        $site, $order_id
    );

    $message = sprintf(
        __( "Hi %s,\n\n"
          . "Great news! Your availability request has been approved.\n\n"
          . "─────────────────────────\n"
          . "BOOKING SUMMARY\n"
          . "─────────────────────────\n"
          . "Tour:         %s\n"
          . "Travel Date:  %s\n"
          . "Travelers:    %d\n"
          . "Order #:      %d\n"
          . "Total:        %s\n"
          . "─────────────────────────\n\n"
          . "To confirm your booking please complete your payment:\n"
          . "%s\n\n"
          . "This link is unique to your order — please do not share it.\n\n"
          . "Thank you for choosing %s!\n",
          'wc-tour-booking' ),
        $first_name, $tour, $travel_date ?: '—', $travelers,
        $order_id, $total, $pay_url, $site
    );

    //wp_mail( $email, $subject, $message );
   
    $wrapped_message = $mailer->wrap_message($subject, $message);

    // Send email
    $mailer->send($email, $subject, $wrapped_message);

}