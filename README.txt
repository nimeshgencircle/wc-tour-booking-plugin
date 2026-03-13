=== WooCommerce Tour Booking ===
Contributors: yourname
Tags: woocommerce, tour, booking, travelers, room selection
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A WooCommerce extension for complete tour booking: traveler management, room selection, waitlist, and inquiry system.

== Description ==

WooCommerce Tour Booking extends WooCommerce products to support full tour booking functionality.

**Features:**
* Multiple tour date ranges per product
* Maximum traveler capacity with live seat counter
* Book Now / Waitlist / Inquiry button logic (auto or manual override)
* Traveler details form (name, email, phone, age, room preference)
* Shared room auto-pairing (1–2, 3–4, etc.)
* Single room supplement pricing
* Dynamic price calculator in booking popup
* Waitlist system with admin approval workflow
* Inquiry system with admin notification
* Full traveler data stored in WooCommerce order metadata
* Traveler table in order confirmation emails
* Admin pages: Waitlist, Inquiries, and Tour Settings

== Installation ==

1. Upload the `wc-tour-booking-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu
3. Go to any WooCommerce product → **Tour Settings** tab to configure a tour
4. Manage waitlists and inquiries under **WooCommerce > Tour Waitlist / Tour Inquiries**

== Configuration ==

=== Setting up a Tour Product ===
1. Create or edit a WooCommerce Simple product
2. Click the **Tour Settings** tab in the Product Data panel
3. Add one or more date ranges (Start + End)
4. Set **Maximum Travelers**, **Base Price**, and **Single Room Supplement**
5. Toggle **Enable Room Selection** to allow room choice
6. Set **Button Type** to Auto (recommended) or force a specific button

=== Button Logic (Auto mode) ===
* Available seats > 0 → **Book Now** button
* Available seats = 0 → **Waitlist** button
* Manual override → any button type

=== Seat Counting ===
Confirmed travelers are counted from orders with status:
`processing`, `completed`, `on-hold`

== Folder Structure ==

    wc-tour-booking-plugin/
    ├── wc-tour-booking.php          # Main plugin file, constants, boot loader
    ├── includes/
    │   ├── admin-settings.php       # WooCommerce > Tour Settings page
    │   ├── product-fields.php       # Tour Settings tab on product edit page
    │   ├── seat-availability.php    # Seat counting and button type logic
    │   ├── room-logic.php           # Room pairing and price calculation
    │   ├── checkout-handler.php     # Cart data, price override, order meta
    │   ├── waitlist.php             # Waitlist AJAX + admin page
    │   ├── inquiry.php              # Inquiry AJAX + admin page
    │   ├── order-meta.php           # Traveler display in admin order view
    │   ├── email-handler.php        # Traveler table in order emails
    │   └── frontend.php             # Product page hooks, popups, asset enqueue
    ├── assets/
    │   ├── css/frontend.css         # All frontend styles
    │   └── js/frontend.js           # All frontend interactions
    └── README.txt

== Changelog ==

= 1.0.0 =
* Initial release
