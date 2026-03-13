/**
 * WC Tour Booking – Product Page JS
 * Handles: date selection, add-to-cart redirect, waitlist popup, inquiry popup.
 * Traveler form is now handled entirely on the checkout page.
 */
(function ($) {
    'use strict';

    var d = wctb_data;

    /* ─── Helpers ─────────────────────────────────────────────────── */
    function showModal(id)  { $('#' + id).fadeIn(200);  $('body').addClass('wctb-modal-open'); }
    function hideModal(id)  { $('#' + id).fadeOut(200); $('body').removeClass('wctb-modal-open'); }

    function showMessage($el, msg, type) {
        $el.removeClass('wctb-message--success wctb-message--error')
           .addClass('wctb-message--' + type).html(msg).show();
    }

    /* ─── Book Now → Add to cart then go to checkout ──────────────── */
    $(document).on('click', '#wctb-book-now-btn', function () {
        var date = $('#wctb_selected_date').val();
        if (!date) { alert(d.i18n.select_date); return; }

        var $btn = $(this);
        $btn.prop('disabled', true).text(d.i18n.adding);

        $.post(d.ajax_url, {
            action:             'woocommerce_add_to_cart',
            product_id:         d.product_id,
            quantity:           1,
            wctb_selected_date: date,
        })
        .always(function () {
            // Always redirect to checkout regardless of AJAX response shape
            window.location.href = d.checkout_url;
        });
    });

    /* ─── Waitlist popup ──────────────────────────────────────────── */
    $(document).on('click', '#wctb-waitlist-btn', function () { showModal('wctb-waitlist-modal'); });

    $(document).on('click', '#wctb-wl-submit', function () {
        var name      = $('#wctb-wl-name').val().trim();
        var email     = $('#wctb-wl-email').val().trim();
        var phone     = $('#wctb-wl-phone').val().trim();
        var travelers = parseInt($('#wctb-wl-travelers').val()) || 1;
        var $msg      = $('#wctb-wl-message');

        if (!name || !email) { showMessage($msg, d.i18n.fill_required, 'error'); return; }

        $(this).prop('disabled', true);
        $.post(d.ajax_url, {
            action: 'wctb_submit_waitlist', nonce: d.waitlist_nonce,
            product_id: d.product_id, name: name, email: email, phone: phone, travelers: travelers,
        }, function (r) {
            showMessage($msg, r.success ? r.data.message : r.data.message, r.success ? 'success' : 'error');
        }).fail(function () { showMessage($msg, d.i18n.error, 'error'); })
          .always(function () { $('#wctb-wl-submit').prop('disabled', false); });
    });

    /* ─── Inquiry popup ───────────────────────────────────────────── */
    $(document).on('click', '#wctb-inquiry-btn', function () { showModal('wctb-inquiry-modal'); });

    $(document).on('click', '#wctb-inq-submit', function () {
        var name    = $('#wctb-inq-name').val().trim();
        var email   = $('#wctb-inq-email').val().trim();
        var $msg    = $('#wctb-inq-message-feedback');

        if (!name || !email) { showMessage($msg, d.i18n.fill_required, 'error'); return; }

        $(this).prop('disabled', true);
        $.post(d.ajax_url, {
            action: 'wctb_submit_inquiry', nonce: d.inquiry_nonce,
            product_id: d.product_id, name: name,
            email: email,
            phone: $('#wctb-inq-phone').val().trim(),
            message: $('#wctb-inq-message').val().trim(),
        }, function (r) {
            showMessage($msg, r.success ? r.data.message : r.data.message, r.success ? 'success' : 'error');
        }).fail(function () { showMessage($msg, d.i18n.error, 'error'); })
          .always(function () { $('#wctb-inq-submit').prop('disabled', false); });
    });

    /* ─── Close modals ────────────────────────────────────────────── */
    $(document).on('click', '.wctb-modal__close, .wctb-modal__overlay', function () {
        $(this).closest('.wctb-modal').fadeOut(200);
        $('body').removeClass('wctb-modal-open');
    });
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') { $('.wctb-modal:visible').fadeOut(200); $('body').removeClass('wctb-modal-open'); }
    });

})(jQuery);
