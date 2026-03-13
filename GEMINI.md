# GEMINI.md: AI Assistant Context

This file provides context to the Gemini AI assistant to help it understand the project structure, conventions, and goals.

## Project Overview

This is a **WooCommerce Tour Booking plugin** for WordPress. Its purpose is to extend the functionality of WooCommerce to allow the sale of tours. It provides a comprehensive set of features for managing and selling tours, including:

*   **Custom Product Fields:** Adds a "Tour Settings" panel to the WooCommerce product editor for configuring tour-specific data.
*   **Tour Management:** Allows administrators to set tour dates, maximum number of travelers, pricing (including single room supplements), and enable/disable room selection.
*   **Frontend Display:** On the product page, it displays tour information, including dates and seat availability, and provides a dynamic booking button.
*   **Booking Logic:** The booking button intelligently changes to "Book Now", "Join Waitlist", or "Make an Inquiry" based on tour availability.
*   **Checkout Integration:** Gathers traveler information on the checkout page and saves it as order metadata.
*   **Custom Database Tables:** Uses custom tables (`wp_wctb_waitlist`, `wp_wctb_inquiry`) to manage waitlist and inquiry submissions.

## Technologies Used

*   **PHP:** The primary language for the plugin's logic, following WordPress and WooCommerce development standards.
*   **JavaScript (jQuery):** Used for frontend interactivity on the product and checkout pages, as well as for dynamic fields in the WordPress admin.
*   **CSS:** For styling the frontend components.
*   **HTML:** For structuring the plugin's output.

## Project Structure

The project follows a standard WordPress plugin structure:

*   `wc-tour-booking.php`: The main plugin file. It handles initialization, dependency checks, and includes the other necessary files.
*   `includes/`: This directory contains the core functionality of the plugin, with each file responsible for a specific feature:
    *   `admin-settings.php`: Manages the global plugin settings page.
    *   `product-fields.php`: Adds and manages the custom fields for tour products in the WordPress admin.
    *   `frontend.php`: Handles the display of tour information and the booking interface on the product and checkout pages.
    *   `checkout-handler.php`: Processes and saves traveler data during checkout.
    *   `email-handler.php`: Manages email notifications related to bookings.
    *   `room-logic.php`: Contains the logic for room assignments and pricing.
    *   `seat-availability.php`: Calculates and manages the number of available seats for a tour.
    *   `waitlist.php`: Handles the waitlist functionality.
    *   `inquiry.php`: Handles the inquiry functionality.
    *   `order-meta.php`: Manages the display of tour-related metadata on the order details page.
*   `assets/`: Contains the static assets for the plugin:
    *   `css/`: Stylesheets for the frontend.
    *   `js/`: JavaScript files for the frontend, checkout, and admin areas.
*   `README.txt`: A standard WordPress plugin readme file.

## Development Conventions

*   **File Organization:** The code is organized into feature-specific files within the `includes` directory, promoting modularity and maintainability.
*   **WordPress & WooCommerce APIs:** The plugin makes extensive use of WordPress and WooCommerce hooks (actions and filters) to integrate its functionality.
*   **Database Interaction:** Uses the global `$wpdb` object for database queries and `dbDelta` for creating/updating custom tables.
*   **Security:** Utilizes WordPress nonces for form submissions and sanitizes user input.
*   **Localization:** The plugin is text-domain-ready for translation.

## Building and Running

This is a WordPress plugin and should be installed and run within a WordPress environment.

1.  **Installation:**
    *   Place the `wc-tour-booking-plugin` directory into the `wp-content/plugins/` directory of a WordPress installation.
    *   Ensure that WooCommerce is installed and activated.
2.  **Activation:**
    *   Navigate to the "Plugins" page in the WordPress admin dashboard.
    *   Activate the "WooCommerce Tour Booking" plugin.
3.  **Usage:**
    *   Create or edit a WooCommerce product.
    *   In the "Product data" section, you will find a "Tour Settings" tab where you can configure the tour details.
    *   Global settings for the plugin can be found under "WooCommerce > Tour Settings".

There are no build steps required for this plugin. It can be used as-is.