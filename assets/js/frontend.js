/**
 * WC Tour Booking – Product Page JS
 * Handles: per-date Book Now, Waitlist, Check Availability, and Begin Custom Journey.
 */
(function ($) {
    'use strict';

    var d = wctb_data;

    /* ─── Helpers ─────────────────────────────────────────────────── */
    function showModal(id) { $('#' + id).fadeIn(200); $('body').addClass('wctb-modal-open'); }

    function showMessage($el, msg, type) {
        $el.removeClass('wctb-message--success wctb-message--error')
           .addClass('wctb-message--' + type).html(msg).show();
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

        if (!firstName || !lastName || !email) { showMessage($feedback, d.i18n.fill_required, 'error'); return; }

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
        }, function (r) {
            showMessage($feedback, r.data.message, r.success ? 'success' : 'error');
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

        if (!firstName || !lastName || !email) { showMessage($msg, d.i18n.fill_required, 'error'); return; }

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
        }, function (r) {
            showMessage($msg, r.data.message, r.success ? 'success' : 'error');
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
        var priorities    = $('#wctb-cj-priorities').val().trim();
        var travelStyle   = $('#wctb-cj-travel-style').val().trim();
        var budget        = $('#wctb-cj-budget').val().trim();
        var travelers     = parseInt($('#wctb-cj-travelers').val()) || 1;
        var travelWhen    = $('#wctb-cj-travel-when').val().trim();
        var notes         = $('#wctb-cj-notes').val().trim();
        var travelDate    = $('#wctb-cj-travel-date').val();
        var $msg          = $('#wctb-cj-message-feedback');

        if (!firstName || !lastName || !phone || !contactMethod || !destination) {
            showMessage($msg, d.i18n.fill_required, 'error');
            return;
        }

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
        }, function (r) {
            showMessage($msg, r.data.message, r.success ? 'success' : 'error');
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
