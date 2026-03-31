/**
 * WC Tour Booking – Product Page JS
 * Handles: per-date Book Now, Waitlist, Check Availability, and Begin Custom Journey.
 */
(function ($) {
    'use strict';

    var d = wctb_data;

    /* ─── Helpers ─────────────────────────────────────────────────── */
    function showModal(id) { 
        var $modal = $('#' + id);
        
        $('body').addClass('wctb-modal-open');

        // Keep modal flex layout on open and force a paint so blur is visible immediately.
        $modal.stop(true, true).css('display', 'flex').hide().fadeIn(200);
        if ($modal[0]) {
            void $modal[0].offsetHeight;
        }
    }

    function showMessage($el, msg, type) {
        $el.removeClass('wctb-message--success wctb-message--error')
           .addClass('wctb-message--' + type).html(msg).show();
    }

    function isValidPhone(phone) {
        // Allow digits, spaces, +, -, (, ) — min 7 digits
        var digits = phone.replace(/\D/g, '');
        return /^[0-9+\-\s().]+$/.test(phone) && digits.length >= 7 && digits.length <= 15;
    }

    /* ─── Book Now (per-date row) ──────────────────────────────────── */
    $(document).on('click', '.wctb-btn-book-now', function () {
        var $btn      = $(this);
        var date      = $btn.data('date');
        var productId = $btn.data('product') || d.product_id;

        $btn.prop('disabled', true).text(d.i18n.adding);

        $.post(d.ajax_url, {
            action:             'woocommerce_add_to_cart',
            product_id:         productId,
            quantity:           1,
            wctb_selected_date: date,
        })
        .always(function () {
            window.location.href = d.checkout_url;
        });
    });

    /* ─── Join Waitlist (per-date row) ────────────────────────────── */
    $(document).on('click', '.wctb-btn-waitlist', function () {
        var date = $(this).data('date') || '';
        $('#wctb-waitlist-date').val(date);


        if (date.indexOf(' to ') !== -1) {
            var parts = date.split(' to ');

            var formatDate = function (dateStr) {
                var d = new Date(dateStr);
                var month = String(d.getMonth() + 1).padStart(2, '0');  // Leading zero (MM)
                var day   = String(d.getDate()).padStart(2, '0');         // Leading zero (DD)
                var year  = d.getFullYear();                              // 4-digit year (YYYY)
                return month + '/' + day + '/' + year;
            };

            var departureFormatted = formatDate(parts[0].trim());  // 7/1/2026
            var returnFormatted    = formatDate(parts[1].trim());  // 8/1/2026

            $(".wctb-waitlist-departure-date").text(departureFormatted);
            $(".wctb-waitlist-return-date").text(returnFormatted);
        }

        showModal('wctb-waitlist-modal');
    });

    $(document).on('click', '#wctb-wl-submit', function () {
        var firstName = $('#wctb-wl-first-name').val().trim();
        var lastName  = $('#wctb-wl-last-name').val().trim();
        var email     = $('#wctb-wl-email').val().trim();
        var phone     = $('#wctb-wl-phone').val().trim();
        var travelers = parseInt($('#wctb-wl-travelers').val()) || 1;
        var message   = $('#wctb-wl-message-text').val().trim();
        var date      = $('#wctb-waitlist-date').val();
        var $feedback = $('#wctb-wl-message');
        var contact_method = $('#wctb-wl-contact-method').val().trim();
        var announcements = $('#wctb-wl-announcements').is(':checked');



        if (!firstName || !lastName || !email || !phone || !contact_method) { showMessage($feedback, d.i18n.fill_required, 'error'); return; }
        if (!isValidPhone(phone)) { showMessage($feedback, d.i18n.invalid_phone || 'Please enter a valid phone number.', 'error'); return; }
        if (travelers < 1) { showMessage($feedback, d.i18n.invalid_travelers || 'Number of travelers must be at least 1.', 'error'); return; }

        $(this).prop('disabled', true);
        $.post(d.ajax_url, {
            action:     'wctb_submit_waitlist',
            nonce:      d.waitlist_nonce,
            product_id: d.product_id,
            first_name: firstName,
            last_name:  lastName,
            email:      email,
            phone:      phone,
            travelers:  travelers,
            message:    message,
            date:       date,
            contact_method: contact_method,
            announcements: announcements
            
        }, function (r) {
            showMessage($feedback, r.data.message, r.success ? 'success' : 'error');
            // empty value
            $('#wctb-wl-message-text, #wctb-wl-first-name, #wctb-wl-last-name, #wctb-wl-email, #wctb-wl-phone, #wctb-wl-travelers, #wctb-wl-message-text, #wctb-waitlist-date').val('');


        }).fail(function () {
            showMessage($feedback, d.i18n.error, 'error');
        }).always(function () {
            $('#wctb-wl-submit').prop('disabled', false);
        });
    });

    /* ─── Check Availability / Inquiry (per-date row) ─────────────── */
    $(document).on('click', '.wctb-btn-inquiry', function () {
        var date = $(this).data('date') || '';
        $('#wctb-inquiry-date').val(date);

        if (date.indexOf(' to ') !== -1) {
            var parts = date.split(' to ');

              var formatDate = function (dateStr) {
                var d = new Date(dateStr);
                var month = String(d.getMonth() + 1).padStart(2, '0');  // Leading zero (MM)
                var day   = String(d.getDate()).padStart(2, '0');         // Leading zero (DD)
                var year  = d.getFullYear();                              // 4-digit year (YYYY)
                return month + '/' + day + '/' + year;
            };

            var departureFormatted = formatDate(parts[0].trim());  // 7/1/2026
            var returnFormatted    = formatDate(parts[1].trim());  // 8/1/2026

            $(".wctb-waitlist-departure-date").text(departureFormatted);
            $(".wctb-waitlist-return-date").text(returnFormatted);
        }

        showModal('wctb-inquiry-modal');
    });

    $(document).on('click', '#wctb-inq-submit', function () {
        var firstName = $('#wctb-inq-first-name').val().trim();
        var lastName  = $('#wctb-inq-last-name').val().trim();
        var email     = $('#wctb-inq-email').val().trim();
        var phone     = $('#wctb-inq-phone').val().trim();
        var travelers = parseInt($('#wctb-inq-travelers').val()) || 1;
        var message   = $('#wctb-inq-message').val().trim();
        var date      = $('#wctb-inquiry-date').val();
        var $msg      = $('#wctb-inq-message-feedback');
        var contact_method = $('#wctb-inq-contact-method').val().trim();
        var announcements = $('#wctb-inq-announcements').is(':checked');

        if (!firstName || !lastName || !email || !phone || !contact_method) { showMessage($msg, d.i18n.fill_required, 'error'); return; }
        if (!isValidPhone(phone)) { showMessage($msg, d.i18n.invalid_phone || 'Please enter a valid phone number.', 'error'); return; }
        if (travelers < 1) { showMessage($msg, d.i18n.invalid_travelers || 'Number of travelers must be at least 1.', 'error'); return; }

        $(this).prop('disabled', true);
        $.post(d.ajax_url, {
            action:     'wctb_submit_inquiry',
            nonce:      d.inquiry_nonce,
            product_id: d.product_id,
            first_name: firstName,
            last_name:  lastName,
            email:      email,
            phone:      phone,
            travelers:  travelers,
            message:    message,
            date:       date,
            contact_method: contact_method,
            announcements: announcements
        }, function (r) {
            showMessage($msg, r.data.message, r.success ? 'success' : 'error');
            // empty value
            $('#wctb-inq-first-name, #wctb-inq-last-name, #wctb-inq-email, #wctb-inq-phone, #wctb-inq-travelers, #wctb-inq-message, #wctb-inquiry-date').val('');

        }).fail(function () {
            showMessage($msg, d.i18n.error, 'error');
        }).always(function () {
            $('#wctb-inq-submit').prop('disabled', false);
        });
    });

    /* ─── Begin Custom Journey (per-date row) ─────────────────────── */
    $(document).on('click', '.wctb-btn-custom-journey', function () {
        var date = $(this).data('date') || '';
        $('#wctb-cj-travel-date').val(date);
        showModal('wctb-custom-journey-modal');
    });

    $(document).on('click', '#wctb-cj-submit', function () {
        var firstName     = $('#wctb-cj-first-name').val().trim();
        var lastName      = $('#wctb-cj-last-name').val().trim();
        var phone         = $('#wctb-cj-phone').val().trim();
        var contactMethod = $('#wctb-cj-contact-method').val().trim();
        var destination   = $('#wctb-cj-destination').val().trim();
        var email = $('#wctb-cj-email').val().trim();
        var priorities    = $('#wctb-cj-priorities').val().trim();
        var travelStyle   = $('#wctb-cj-travel-style').val().trim();
        var budget        = $('#wctb-cj-budget').val().trim();
        var travelers     = parseInt($('#wctb-cj-travelers').val()) || 1;
        var travelWhen    = $('#wctb-cj-travel-when').val().trim();
        var notes         = $('#wctb-cj-notes').val().trim();
        var travelDate    = $('#wctb-cj-travel-date').val();
        var $msg          = $('#wctb-cj-message-feedback');

        if (!firstName || !lastName || !phone || !contactMethod || !destination || !email || !travelers || !budget || !travelWhen || !notes ) {
            showMessage($msg, d.i18n.fill_required, 'error');
            return;
        }

          if (!isValidPhone(phone)) { showMessage($msg, d.i18n.invalid_phone || 'Please enter a valid phone number.', 'error'); return; }

          if (travelers < 1) { showMessage($msg, d.i18n.invalid_travelers || 'Number of travelers must be at least 1.', 'error'); return; }


        $(this).prop('disabled', true);
        $.post(d.ajax_url, {
            action:          'wctb_submit_custom_journey',
            nonce:           d.journey_nonce,
            product_id:      d.product_id,
            first_name:      firstName,
            last_name:       lastName,
            phone:           phone,
            contact_method:  contactMethod,
            destination:     destination,
            priorities:      priorities,
            travel_style:    travelStyle,
            budget:          budget,
            travelers:       travelers,
            travel_when:     travelWhen,
            notes:           notes,
            travel_date:     travelDate,
            email:           email
        }, function (r) {
            showMessage($msg, r.data.message, r.success ? 'success' : 'error');
            // empty value
            $('#wctb-cj-first-name, #wctb-cj-last-name, #wctb-cj-phone, #wctb-cj-contact-method, #wctb-cj-destination, #wctb-cj-priorities, #wctb-cj-travel-style, #wctb-cj-budget, #wctb-cj-travelers, #wctb-cj-travel-when, #wctb-cj-notes').val('');

        }).fail(function () {
            showMessage($msg, d.i18n.error, 'error');
        }).always(function () {
            $('#wctb-cj-submit').prop('disabled', false);
        });
    });

    /* ─── Close modals ────────────────────────────────────────────── */
    $(document).on('click', '.wctb-modal__close, .wctb-modal__overlay', function () {
        $(this).closest('.wctb-modal').fadeOut(200);
        $('body').removeClass('wctb-modal-open');
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            $('.wctb-modal:visible').fadeOut(200);
            $('body').removeClass('wctb-modal-open');
        }
    });

})(jQuery);
