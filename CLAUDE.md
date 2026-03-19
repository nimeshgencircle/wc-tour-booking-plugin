# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

WordPress plugin that extends WooCommerce for tour booking. Requires WordPress 5.8+, PHP 7.4+, and WooCommerce 6.0+.

**No build system** — no npm, no Composer, no Makefile. The plugin runs as-is within a WordPress installation.

## Development Setup

- Place directory in `/wp-content/plugins/` of a WordPress installation with WooCommerce active
- Activate via WordPress admin → Plugins
- Plugin creates two custom post type(CPT) : `wctb_waitlist`, `wctb_availability` and  `wctb_custom_journey`
- Global settings: WooCommerce → Tour Settings
- Per-product config: Product edit → "Tour Settings" tab

## Architecture

**Entry point:** `wc-tour-booking.php` — defines constants (`WCTB_VERSION`, `WCTB_DIR`, `WCTB_URL`, `WCTB_BASENAME`), requires all includes via `wctb_init()` on `plugins_loaded` hook (priority 20), and creates DB tables on activation.

**Module breakdown in `includes/`:**

| File | Responsibility |
|------|----------------|
| `product-fields.php` | "Tour Settings" tab in WC product editor — dates (JSON in `_wctb_dates`), capacity, pricing, room toggles |
| `frontend.php` | Product page display: date selector, capacity bar, Book Now / Waitlist / Inquiry buttons, modal popups |
| `checkout-handler.php` | Captures traveler data into WC session; recalculates price on `woocommerce_checkout_update_order_review`; saves to order item meta |
| `room-logic.php` | Room pairing algorithm (travelers paired 0↔1, 2↔3, etc.) and price formula: `(base × count) + (supplement × singles)` |
| `seat-availability.php` | Counts confirmed travelers from orders (processing/completed/on-hold); determines button type (book/waitlist/inquiry) |
| `waitlist.php` | AJAX handler + admin page for waitlist entries; email notifications; status workflow (pending → approved/rejected) |
| `inquiry.php` | AJAX handler + admin page for inquiries; email notification to admin |
| `order-meta.php` | Renders traveler table in WC admin order detail view; hides raw meta from default display |
| `email-handler.php` | Injects traveler table into WC order confirmation emails (HTML + plain text) |
| `admin-settings.php` | Global settings page (currency symbol, max travelers per booking) |

**Frontend assets:**
- `assets/css/frontend.css` — all frontend styles
- `assets/js/frontend.js` — product page interactions (date selection, modal handlers)
- `assets/js/checkout-travelers.js` — dynamic traveler form builder on checkout page

## Key Patterns

- All DB queries use `$wpdb->insert()` / `$wpdb->prepare()` (no raw SQL interpolation)
- Form submissions protected with WordPress nonces; inputs sanitized with `sanitize_text_field()`, `sanitize_email()`, etc.
- Traveler data flows: product page → cart item meta → WC session → order item meta
- Button type (`book`/`waitlist`/`inquiry`) is determined by `wctb_get_button_type()` in `seat-availability.php`, which checks capacity override on the product first, then live availability
- Room pairing cascade rule: if either traveler in a pair selects "single room", both are charged the supplement
- Text domain: `wc-tour-booking` for all i18n strings
