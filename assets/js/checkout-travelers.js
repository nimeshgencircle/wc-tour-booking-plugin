/**
 * WC Tour Booking – Checkout Traveler Form + Room Pairing Logic
 *
 * ROOM PAIRING RULES
 * ─────────────────────────────────────────────────────────────
 * Travelers are paired sequentially by index:
 *   Pair A : traveler 0 ↔ traveler 1
 *   Pair B : traveler 2 ↔ traveler 3
 *   Pair C : traveler 4 ↔ traveler 5  … and so on.
 *
 * Shared Room  → included in base price; pair shares a room.
 * Single Room  → supplement added; if one traveler in a pair
 *                selects Single, the partner is AUTOMATICALLY
 *                upgraded to Single too (cascade rule).
 *
 * Visual feedback
 * ─────────────────────────────────────────────────────────────
 * • Each traveler block shows a "Pair A / Pair B …" badge.
 * • After any room change the badge colour reflects the state:
 *     – shared  → grey
 *     – single  → amber (supplement applies)
 *     – auto-upgraded partner → amber + "(auto)" note
 * • Pricing summary updates live.
 */
(function ($) {
    'use strict';

    /* ── Guard ────────────────────────────────────────────────────── */
    var c = wctb_checkout;
    if (!c || !c.tour_items || !c.tour_items.length) return;

    /* ── Pair helpers ─────────────────────────────────────────────── */

    /**
     * Given a 0-based traveler index, return their pair index
     * and partner index.
     *
     * pairIndex : 0-based pair number  (0 = Pair A, 1 = Pair B …)
     * partner   : index of the other traveler in the same pair
     * slot      : 0 = first in pair,  1 = second in pair
     *
     * Pairing: 0↔1, 2↔3, 4↔5, 6↔7 …
     */
    function pairInfo(idx) {
        var pairIndex = Math.floor(idx / 2);
        var slot      = idx % 2;
        var partner   = slot === 0 ? idx + 1 : idx - 1;
        return { pairIndex: pairIndex, slot: slot, partner: partner };
    }

    /** 'A', 'B', 'C' … from 0-based pair index */
    function pairLabel(pairIndex) {
        return String.fromCharCode(65 + pairIndex); // 65 = 'A'
    }

    /* ── State ────────────────────────────────────────────────────── */
    // state[pid] = { count: Number, travelers: Array<{…}> }
    var state = {};
    c.tour_items.forEach(function (tour) {
        // On order-pay the initial count comes from the existing order quantity.
        // Clamp to available seats so a stale quantity can't exceed current capacity.
        var maxAvail = tour.available > 0 ? Math.min(tour.max_travelers, tour.available) : 1;
        var initial  = Math.min(tour.initial_count || 1, maxAvail);
        // Pre-initialise traveler state so room_type defaults to 'shared'
        // for all paired slots (odd last traveler is corrected by enforceSoloSingle)
        var travelers = [];
        for (var t = 0; t < initial; t++) {
            travelers.push({ room_type: 'shared', auto_upgraded: false, room_preference: 'queen' });
        }
        state[tour.product_id] = { count: initial, travelers: travelers };
        enforceSoloSingle(tour.product_id);
    });

    /* ── Utilities ────────────────────────────────────────────────── */
    function fmt(amount) {
        return c.currency + parseFloat(amount).toLocaleString('en-US', {
            minimumFractionDigits: 2, maximumFractionDigits: 2
        });
    }

    /**
     * Debounce — returns a version of fn that only fires after `wait` ms
     * of inactivity. Used to batch rapid changes before hitting the server.
     */
    function debounce(fn, wait) {
        var timer;
        return function () {
            var ctx  = this;
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, wait);
        };
    }

    /**
     * Tell WooCommerce to refresh the order review (right-hand totals column).
     * We debounce to 400 ms so rapid room/count changes batch into one request.
     */
    // On order-pay there is no order-review block, so skip the WC AJAX refresh.
    var triggerWooUpdate = debounce(function () {
        if (!c.is_order_pay) {
            $(document.body).trigger('update_checkout');
        }
    }, 400);

    function parseDateRange(dateStr) {
        if (!dateStr) return { departure: '', returnDate: '' };
        var parts = dateStr.split(' to ');
        return { departure: parts[0] || '', returnDate: parts[1] || '' };
    }

    function formatDate(ymd) {
        if (!ymd) return '';
        var d = new Date(ymd + 'T00:00:00');
        if (isNaN(d)) return ymd;
        return ('0' + (d.getMonth() + 1)).slice(-2) + '/' +
               ('0' + d.getDate()).slice(-2) + '/' +
               d.getFullYear();
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    var escAttr = escHtml;

    /* ── Room pairing cascade ──────────────────────────────────────── */

    /**
     * Called whenever a room <select> changes.
     * Enforces the cascade rule: if traveler N picks Single,
     * their partner is automatically set to Single too.
     *
     * @param {number} pid   product id
     * @param {number} idx   0-based traveler index that changed
     * @param {string} newRoom  'shared' | 'single'
     */
    function applyRoomCascade(pid, idx, newRoom) {
        var st      = state[pid];
        var count   = st.count;
        var info    = pairInfo(idx);
        var partner = info.partner;

        // Update the changed traveler in state
        if (!st.travelers[idx]) st.travelers[idx] = {};
        st.travelers[idx].room_type = newRoom;
        st.travelers[idx].auto_upgraded = false;

        // Partner always mirrors the lead — spec: second traveler is locked to lead's choice
        if (partner < count) {
            if (!st.travelers[partner]) st.travelers[partner] = {};
            st.travelers[partner].room_type     = newRoom;
            st.travelers[partner].auto_upgraded = (newRoom === 'single');

            // Sync the DOM for the partner
            syncRoomDOM(pid, partner);
        }
    }

    /**
     * When the traveler count is odd the last traveler has no room partner
     * and must always be Single. This also covers count = 1.
     * Call after any count change and during initial render.
     */
    function enforceSoloSingle(pid) {
        var count = state[pid].count;
        if (count % 2 === 0) return; // even count → all paired, nothing to do
        var soloIdx = count - 1;
        if (!state[pid].travelers[soloIdx]) state[pid].travelers[soloIdx] = {};
        state[pid].travelers[soloIdx].room_type     = 'single';
        state[pid].travelers[soloIdx].auto_upgraded = false; // not auto — it's forced
    }

    /**
     * Push the state room_type for traveler[idx] back into the DOM
     * select element and refresh the pair badge.
     */
    function syncRoomDOM(pid, idx) {
        var st       = state[pid];
        var traveler = st.travelers[idx] || {};
        var roomType = traveler.room_type || 'shared';
        var auto     = traveler.auto_upgraded || false;
        var info     = pairInfo(idx);

        var $block = $('#wctb-t-' + pid + '-' + idx);

        if (info.slot === 1) {
            // Second traveler: update hidden input + locked display text
            $block.find('.wctb-co-room').val(roomType);
            var tour = c.tour_items.find(function (t) { return t.product_id == pid; });
            var sharingLabel = roomType === 'single'
                ? c.i18n.single_room + (tour && tour.supplement > 0 ? ' (+' + fmt(tour.supplement) + ')' : '')
                : c.i18n.shared_room;
            var lockNote = roomType === 'single'
                ? '(forced)'
                : '(with Traveler ' + (info.partner + 1) + ')';
            $('#wctb-sharing-locked-' + pid + '-' + idx).html(
                escHtml(sharingLabel) + ' <em class="wctb-auto-note">' + escHtml(lockNote) + '</em>'
            );
        } else {
            // Lead traveler: update select value silently
            $block.find('.wctb-co-room').val(roomType);
        }

        // Refresh the pair badge
        refreshPairBadge(pid, idx, roomType, auto);

        // Sync room-preference visibility
        var $rpWrap = $('#wctb-rpw-' + pid + '-' + idx);
        var info    = pairInfo(idx);
        if (info.slot === 0) {
            // Lead: show pref field only when shared
            if (roomType === 'shared') { $rpWrap.show(); } else { $rpWrap.hide(); }
        } else {
            // Second: show pref mirror only when shared
            if (roomType === 'shared') { $rpWrap.show(); } else { $rpWrap.hide(); }
            // Also refresh mirror text to reflect current lead choice
            syncRoomPrefMirror(pid, idx);
        }
    }

    /**
     * Update the read-only room-preference mirror for a second-slot traveler.
     * partnerIdx must be slot 1 (the non-lead traveler).
     */
    function syncRoomPrefMirror(pid, partnerIdx) {
        var leadIdx  = pairInfo(partnerIdx).partner;
        var leadPref = (state[pid].travelers[leadIdx] || {}).room_preference || 'queen';
        var i18n     = c.i18n;
        var label    = leadPref === 'twin' ? i18n.twin_beds : i18n.queen_bed;
        $('#wctb-rp-' + pid + '-' + partnerIdx).html(
            escHtml(label) + ' <em class="wctb-auto-note">(same as Traveler ' + (leadIdx + 1) + ')</em>'
        );
    }

    /**
     * Rebuild / refresh the pair badge inside a traveler block.
     */
    function refreshPairBadge(pid, idx, roomType, isAuto) {
        var $block = $('#wctb-t-' + pid + '-' + idx);
        var info   = pairInfo(idx);
        var label  = 'Pair ' + pairLabel(info.pairIndex);
        var mod    = roomType === 'single' ? 'wctb-badge--single' : 'wctb-badge--shared';
        var autoNote = (isAuto && roomType === 'single') ? ' <em class="wctb-auto-note">(auto)</em>' : '';

        $block.find('.wctb-pair-badge')
              .attr('class', 'wctb-pair-badge ' + mod)
              .html(escHtml(label) + autoNote);
    }

    /**
     * Refresh ALL pair badges for a product (called after count changes).
     */
    function refreshAllBadges(pid) {
        var st = state[pid];
        for (var i = 0; i < st.count; i++) {
            var t = st.travelers[i] || {};
            refreshPairBadge(pid, i, t.room_type || 'shared', t.auto_upgraded || false);
        }
    }

    /* ── Pricing ──────────────────────────────────────────────────── */

    function calcTotal(pid) {
        var tour    = c.tour_items.find(function (t) { return t.product_id == pid; });
        if (!tour) return 0;
        var st      = state[pid];
        var count   = st.count;
        var singles = 0;
        for (var i = 0; i < count; i++) {
            if ((st.travelers[i] || {}).room_type === 'single') singles++;
        }
        return (tour.base_price * count) + (tour.supplement * singles);
    }

    function updatePricingDisplay(pid) {
        var tour   = c.tour_items.find(function (t) { return t.product_id == pid; });
        if (!tour) return;

        var total    = calcTotal(pid);
        var singles  = 0;
        var st       = state[pid];
        for (var i = 0; i < st.count; i++) {
            if ((st.travelers[i] || {}).room_type === 'single') singles++;
        }

        $('#wctb-total-' + pid).text(fmt(total));

        // Show/hide supplement line
        /*
        var $suppLine = $('#wctb-supp-line-' + pid);
        if (singles > 0 && tour.supplement > 0) {
            var suppTotal = tour.supplement * singles;
            $suppLine.html(
                '<span>' + escHtml(c.i18n.supplement_note) + singles + ' × ' + fmt(tour.supplement) + '</span>' +
                '<span>' + fmt(suppTotal) + '</span>'
            ).show();
        } else {
            $suppLine.hide();
        }*/
    }

    /* ── DOM builders ─────────────────────────────────────────────── */

    function buildTourSection(tour) {
        var pid   = tour.product_id;
        var st    = state[pid];
        var dates = parseDateRange(tour.date);
        var i18n  = c.i18n;

        /* Header */
        var html =
            '<div class="wctb-co-header">' +
                '<div class="wctb-co-tour-name">' + escHtml(tour.name) + '</div>' +
                '<div class="wctb-co-dates">' +
                    (dates.departure  ? '<span><strong>' + i18n.departure_date + '</strong>' + escHtml(formatDate(dates.departure)) + '</span>' : '') +
                    (dates.returnDate ? '<span><strong>' + i18n.return_date    + '</strong>' + escHtml(formatDate(dates.returnDate)) + '</span>' : '') +
                '</div>' +
            '</div>';

        /* Top bar */
        // Cap the dropdown at available seats for this specific date.
        var maxOpts = (tour.available > 0)
            ? Math.min(tour.max_travelers, tour.available)
            : 0;

        html +=
            '<div class="wctb-co-topbar">' +
                '<div class="wctb-co-count-wrap">';

        if (maxOpts === 0) {
            html += '<p class="wctb-fully-booked">' + escHtml(c.i18n.fully_booked || 'This date is fully booked.') + '</p>';
        } else {
            html +=
                '<label class="wctb-co-count-label" for="wctb-count-' + pid + '">' + i18n.num_travelers + '</label>' +
                '<select id="wctb-count-' + pid + '" class="wctb-co-count" data-pid="' + pid + '">';

            for (var n = 1; n <= maxOpts; n++) {
                html += '<option value="' + n + '"' + (n === st.count ? ' selected' : '') + '>' + n + '</option>';
            }

            html += '</select>';
        }

        html +=     '</div>' +
                '<div class="wctb-co-pricing-bar">' +
                    '<div class="wctb-co-price-lines">' +
                        '<div class="wctb-co-price-base">' +
                            '<span>' + i18n.pricing_summary + '</span>' +
                            '<span>' + fmt(tour.base_price) + ' ' + i18n.per_person + '</span>' +
                        '</div>' +
                        '<div class="wctb-co-price-supp" id="wctb-supp-line-' + pid + '" style="display:none;"></div>' +
                    '</div>' +
                    '<div class="wctb-co-total-wrap">' +
                        '<span class="wctb-co-total-label">Total</span>' +
                        '<span class="wctb-co-total" id="wctb-total-' + pid + '">' + fmt(tour.base_price * st.count) + '</span>' +
                    '</div>' +
                '</div>' +
            '</div>';

        /* Traveler blocks */
        html += '<div class="wctb-co-forms" id="wctb-forms-' + pid + '">';
        for (var i = 0; i < st.count; i++) {
            html += buildTravelerBlock(tour, i, st.travelers[i] || {});
        }
        html += '</div>';

        /* Room pairing legend (only shown if room selection enabled) */
        if (tour.room_enabled) {
            html +=
                '<div class="wctb-co-room-legend" id="wctb-legend-' + pid + '">' +
                    buildRoomLegend(pid, st.count) +
                '</div>';
        }

        return html;
    }

    function buildTravelerBlock(tour, idx, saved) {
        var pid      = tour.product_id;
        var i18n     = c.i18n;
        var info     = pairInfo(idx);
        var label    = 'Pair ' + pairLabel(info.pairIndex);
        var roomType = saved.room_type || 'shared';
        var isAuto   = saved.auto_upgraded || false;
        var mod      = roomType === 'single' ? 'wctb-badge--single' : 'wctb-badge--shared';
        var autoNote = (isAuto && roomType === 'single') ? ' <em class="wctb-auto-note">(auto)</em>' : '';

        // Solo = last traveler when count is odd (including count = 1). Always Single.
        var isSolo   = (idx === state[pid].count - 1) && (state[pid].count % 2 !== 0);

        var roomHtml = '';
        var roomPrefHtml = '';
        if (tour.room_enabled) {
            if (isSolo) {
                // Solo traveler has no room partner — always charged Single supplement.
                // Render as hidden input so the value is serialized without showing a dropdown.
                roomHtml = '<input type="hidden" class="wctb-co-room" ' +
                               'id="wctb-room-' + pid + '-' + idx + '" ' +
                               'data-pid="' + pid + '" data-idx="' + idx + '" value="single">';
            } else if (info.slot === 0) {
                // Lead traveler: interactive select — free choice
                roomHtml =
                    '<div class="wctb-co-field wctb-co-field--room">' +
                        '<label class="wctb-co-label">' + i18n.sharing_pref + '</label>' +
                        '<div class="wctb-room-select-wrap">' +
                            '<select class="wctb-co-input wctb-co-room" ' +
                                    'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                                    'id="wctb-room-' + pid + '-' + idx + '">' +
                                '<option value="shared"' + (roomType === 'shared' ? ' selected' : '') + '>' +
                                    i18n.shared_room + '</option>' +
                                '<option value="single"' + (roomType === 'single' ? ' selected' : '') + '>' +
                                    i18n.single_room + (tour.supplement > 0 ? ' (+' + fmt(tour.supplement) + ')' : '') +
                                '</option>' +
                            '</select>' +
                        '</div>' +
                    '</div>';
            } else {
                // Second traveler (slot 1): locked — always mirrors lead
                var leadIdx   = info.partner;
                var sharingLabel = roomType === 'single'
                    ? i18n.single_room + (tour.supplement > 0 ? ' (+' + fmt(tour.supplement) + ')' : '')
                    : i18n.shared_room;
                var lockNote  = roomType === 'single'
                    ? '(forced)'
                    : '(with Traveler ' + (leadIdx + 1) + ')';
                roomHtml =
                    '<input type="hidden" class="wctb-co-room" ' +
                        'id="wctb-room-' + pid + '-' + idx + '" ' +
                        'data-pid="' + pid + '" data-idx="' + idx + '" value="' + escAttr(roomType) + '">' +
                    '<div class="wctb-co-field wctb-co-field--room">' +
                        '<label class="wctb-co-label">' + i18n.sharing_pref + '</label>' +
                        '<div class="wctb-sharing-pref-locked" id="wctb-sharing-locked-' + pid + '-' + idx + '">' +
                            escHtml(sharingLabel) + ' <em class="wctb-auto-note">' + escHtml(lockNote) + '</em>' +
                        '</div>' +
                    '</div>';
            }

            // ── Room Preference — shown for all non-solo travelers ────────────
            if (!isSolo) {
                var rpStyle = roomType === 'shared' ? '' : ' style="display:none;"';
                if (info.slot === 0) {
                    // Lead traveler: editable select
                    var roomPref = saved.room_preference || 'queen';
                    roomPrefHtml =
                        '<div class="wctb-co-field wctb-co-field--room-pref" id="wctb-rpw-' + pid + '-' + idx + '"' + rpStyle + '>' +
                            '<label class="wctb-co-label" for="wctb-rp-' + pid + '-' + idx + '">' + escHtml(i18n.room_pref) + '</label>' +
                            '<select class="wctb-co-input wctb-co-room-pref" ' +
                                    'id="wctb-rp-' + pid + '-' + idx + '" ' +
                                    'data-pid="' + pid + '" data-idx="' + idx + '">' +
                                '<option value="queen"' + (roomPref === 'queen' ? ' selected' : '') + '>' + escHtml(i18n.queen_bed) + '</option>' +
                                '<option value="twin"'  + (roomPref === 'twin'  ? ' selected' : '') + '>' + escHtml(i18n.twin_beds)  + '</option>' +
                            '</select>' +
                        '</div>';
                } else {
                    // Second traveler: read-only mirror of lead's choice
                    var leadPref = (state[pid].travelers[info.partner] || {}).room_preference || 'queen';
                    var prefLabel = leadPref === 'twin' ? i18n.twin_beds : i18n.queen_bed;
                    roomPrefHtml =
                        '<div class="wctb-co-field wctb-co-field--room-pref" id="wctb-rpw-' + pid + '-' + idx + '"' + rpStyle + '>' +
                            '<label class="wctb-co-label">' + escHtml(i18n.room_pref) + '</label>' +
                            '<div class="wctb-room-pref-mirror" id="wctb-rp-' + pid + '-' + idx + '">' +
                                escHtml(prefLabel) + ' <em class="wctb-auto-note">(same as Traveler ' + (info.partner + 1) + ')</em>' +
                            '</div>' +
                        '</div>';
                }
            }
        }

        var travel_numbe = idx + 1;

        return '<div class="wctb-co-traveler" id="wctb-t-' + pid + '-' + idx + '" data-idx="' + idx + '" data-pid="' + pid + '">' +
            '<div class="wctb-co-traveler-heading">' +
                '<span class="wctb-co-traveler-num">' + i18n.traveler + ' ' + (idx + 1) + '</span>' +
                (tour.room_enabled
                    ? '<span class="wctb-pair-badge ' + mod + '" title="Room pairing">' + escHtml(label) + autoNote + '</span>'
                    : '') +
            '</div>' +
            '<div class="wctb-co-grid">' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-fn-' + pid + '-' + idx + '">' + idx + i18n.first_name + ' <span class="req">*</span></label>' +
                    '<input type="text" id="wctb-fn-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-first-name" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.first_name || '') + '" ' +
                           'placeholder="' + escAttr( i18n.traveler+' '+ travel_numbe + ' ' + i18n.first_name) + '" autocomplete="given-name" />' +
                '</div>' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-ln-' + pid + '-' + idx + '">' + i18n.last_name + ' <span class="req">*</span></label>' +
                    '<input type="text" id="wctb-ln-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-last-name" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.last_name || '') + '" ' +
                           'placeholder="' + escAttr(i18n.traveler+' '+ travel_numbe + ' ' +i18n.last_name) + '" autocomplete="family-name" />' +
                '</div>' +
                '<div class="wctb-co-field wctb-co-field--gender">' +
                    '<label class="wctb-co-label">' + escHtml(i18n.gender) + ' <span class="req">*</span></label>' +
                    '<div class="wctb-gender-group">' +
                        '<label class="wctb-gender-option">' +
                            '<input type="radio" class="wctb-t-gender" name="wctb-gender-' + pid + '-' + idx + '" ' +
                                   'data-pid="' + pid + '" data-idx="' + idx + '" value="M"' + (saved.gender === 'M' ? ' checked' : '') + '>' +
                            '<span class="wctb-gender-box">M</span>' +
                        '</label>' +
                        '<label class="wctb-gender-option">' +
                            '<input type="radio" class="wctb-t-gender" name="wctb-gender-' + pid + '-' + idx + '" ' +
                                   'data-pid="' + pid + '" data-idx="' + idx + '" value="F"' + (saved.gender === 'F' ? ' checked' : '') + '>' +
                            '<span class="wctb-gender-box">F</span>' +
                        '</label>' +
                    '</div>' +
                '</div>' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-dob-' + pid + '-' + idx + '">' + escHtml(i18n.dob) + ' <span class="req">*</span></label>' +
                    '<div class="wctb-dob-wrap">' +
                        '<input type="text" id="wctb-dob-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-dob" ' +
                               'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                               'value="' + escAttr(saved.dob || '') + '" ' +
                               'placeholder="YYYY/DD/MM" readonly />' +
                    '</div>' +
                '</div>' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-em-' + pid + '-' + idx + '">' + i18n.email + ' <span class="req">*</span></label>' +
                    '<input type="email" id="wctb-em-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-email" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.email || '') + '" ' +
                           'placeholder="' + escAttr(i18n.email) + '" autocomplete="email" />' +
                '</div>' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-ph-' + pid + '-' + idx + '">' + i18n.phone + ' <span class="req">*</span></label>' +
                    '<input type="tel" id="wctb-ph-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-phone" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.phone || '') + '" ' +
                           'placeholder="' + escAttr(i18n.phone) + '" autocomplete="tel" />' +
                '</div>' +
                roomHtml +
                roomPrefHtml +
            '</div>' +
        '</div>';
    }

    /** Build the room-pairing legend summary below all traveler blocks */
    function buildRoomLegend(pid, count) {
        if (count < 2) return '';
        var st   = state[pid];
        var html = '<div class="wctb-legend-title">Room Pairings</div><div class="wctb-legend-pairs">';

        for (var i = 0; i < count; i += 2) {
            var tA    = st.travelers[i]   || {};
            var tB    = st.travelers[i+1] || {};
            var rA    = tA.room_type || 'shared';
            var rB    = tB.room_type || 'shared';
            var label = pairLabel(Math.floor(i / 2));

            // Both single or both shared
            var roomStatus = (rA === 'single' || rB === 'single') ? 'single' : 'shared';
            var statusText = roomStatus === 'single' ? 'Private' : 'Shared';
            var cls        = 'wctb-legend-pair wctb-legend-pair--' + roomStatus;

            var nameA = (tA.first_name || 'Traveler ' + (i+1)).trim();
            var nameB = i + 1 < count ? (tB.first_name || 'Traveler ' + (i+2)).trim() : '—';

            html +=
                '<div class="' + cls + '">' +
                    '<span class="wctb-legend-pair-label">Pair ' + label + '</span>' +
                    '<span class="wctb-legend-pair-names">' +
                        escHtml(nameA) + ' &harr; ' + escHtml(nameB) +
                    '</span>' +
                    '<span class="wctb-legend-pair-room">' + statusText + '</span>' +
                '</div>';
        }

        html += '</div>';
        return html;
    }

    function refreshLegend(pid) {
        var tour = c.tour_items.find(function (t) { return t.product_id == pid; });
        if (!tour || !tour.room_enabled) return;
        $('#wctb-legend-' + pid).html(buildRoomLegend(pid, state[pid].count));
    }

    /* ── Date picker ──────────────────────────────────────────────── */

    function initDatepickers() {
        $('.wctb-t-dob').each(function () {
            if ($(this).hasClass('wctb-dob-ready')) return; // already initialised
            $(this).addClass('wctb-dob-ready');

            var $input = $(this);
            var pid    = $input.data('pid');
            var idx    = parseInt($input.data('idx'));

            $input.datepicker({
                dateFormat:   'yy/dd/mm',
                changeMonth:  true,
                changeYear:   true,
                yearRange:    '-100:+0',
                maxDate:      new Date(),
                showButtonPanel: false,
                onSelect: function (dateText) {
                    if (!state[pid].travelers[idx]) state[pid].travelers[idx] = {};
                    state[pid].travelers[idx].dob = dateText;
                    serializeToHidden();
                }
            });
        });
    }

    /* ── Render all sections ──────────────────────────────────────── */

    function renderAll() {
        var $wrap = $('#wctb-checkout-travelers-wrap');
        if (!$wrap.length) return;

        // Enforce solo-single in state before building HTML so pricing is correct
        c.tour_items.forEach(function (tour) {
            enforceSoloSingle(tour.product_id);
        });

        var html = '<div class="wctb-co-wrap">';
        c.tour_items.forEach(function (tour) {
            html +=
                '<div class="wctb-co-section" data-pid="' + tour.product_id + '">' +
                    buildTourSection(tour) +
                '</div>';
        });
        html += '</div>';

        $wrap.html(html);
        serializeToHidden();
        initDatepickers();

        // Refresh pricing display now that solo-single state is set
        c.tour_items.forEach(function (tour) {
            updatePricingDisplay(tour.product_id);
        });
    }

    /* ── Collect DOM → state → hidden field ───────────────────────── */

    function collectFromDOM() {
        c.tour_items.forEach(function (tour) {
            var pid   = tour.product_id;
            var count = state[pid].count;

            for (var i = 0; i < count; i++) {
                var $b = $('#wctb-t-' + pid + '-' + i);
                if (!state[pid].travelers[i]) state[pid].travelers[i] = {};

                state[pid].travelers[i].first_name = $b.find('.wctb-t-first-name').val() || '';
                state[pid].travelers[i].last_name  = $b.find('.wctb-t-last-name').val()  || '';
                state[pid].travelers[i].gender     = $b.find('.wctb-t-gender:checked').val() || '';
                state[pid].travelers[i].dob        = $b.find('.wctb-t-dob').val()        || '';
                state[pid].travelers[i].email      = $b.find('.wctb-t-email').val()       || '';
                state[pid].travelers[i].phone      = $b.find('.wctb-t-phone').val()       || '';
                // room_type is managed by applyRoomCascade, only read if not already set
                if (!state[pid].travelers[i].room_type) {
                    state[pid].travelers[i].room_type = $b.find('.wctb-co-room').val() || 'shared';
                }
                // room_preference: only lead travelers (slot 0) have the select; sync to partner
                if (pairInfo(i).slot === 0) {
                    var prefVal = $b.find('.wctb-co-room-pref').val() || state[pid].travelers[i].room_preference || 'queen';
                    state[pid].travelers[i].room_preference = prefVal;
                    var partnerI = pairInfo(i).partner;
                    if (partnerI < count) {
                        if (!state[pid].travelers[partnerI]) state[pid].travelers[partnerI] = {};
                        state[pid].travelers[partnerI].room_preference = prefVal;
                    }
                }
            }
        });
    }

    function serializeToHidden() {
        collectFromDOM();

        var payload = c.tour_items.map(function (tour) {
            return {
                product_id: tour.product_id,
                travelers:  state[tour.product_id].travelers.slice(0, state[tour.product_id].count),
            };
        });

        $('#wctb_checkout_travelers_data').val(JSON.stringify(payload));
    }

    /* ── Event delegation ─────────────────────────────────────────── */

    /** Traveler count select changed */
    $(document).on('change', '.wctb-co-count', function () {
        var pid   = $(this).data('pid');
        var count = parseInt($(this).val()) || 1;
        state[pid].count = count;

        collectFromDOM();

        // Force last traveler to single when count is odd (no room partner)
        enforceSoloSingle(pid);

        // Rebuild forms
        var tour  = c.tour_items.find(function (t) { return t.product_id == pid; });
        var $forms = $('#wctb-forms-' + pid);
        var html   = '';
        for (var i = 0; i < count; i++) {
            html += buildTravelerBlock(tour, i, state[pid].travelers[i] || {});
        }
        $forms.html(html);
        initDatepickers();

        // Re-apply cascade for all paired travelers after count change
        // (skip the solo last traveler — enforceSoloSingle already handled it)
        var pairedCount = count % 2 === 0 ? count : count - 1;
        for (var j = 0; j < pairedCount; j++) {
            var t = state[pid].travelers[j] || {};
            if (t.room_type === 'single') {
                applyRoomCascade(pid, j, 'single');
            }
        }

        refreshAllBadges(pid);
        updatePricingDisplay(pid);
        refreshLegend(pid);
        serializeToHidden();

        // Refresh WooCommerce order review totals (right column)
        triggerWooUpdate();
    });

    /**
     * Room select changed — CORE pairing handler.
     * 1. Read new value.
     * 2. Run cascade (may update partner).
     * 3. Refresh badges for both traveler and partner.
     * 4. Update pricing + legend.
     */
    $(document).on('change', '.wctb-co-room', function () {
        var $sel    = $(this);
        var pid     = $sel.data('pid');
        var idx     = parseInt($sel.data('idx'));
        var newRoom = $sel.val();

        // Only lead travelers (slot 0) drive sharing preference — partner is always locked
        if (pairInfo(idx).slot !== 0) return;

        // Run cascade — updates state for idx and partner
        applyRoomCascade(pid, idx, newRoom);

        // Refresh badge for changed traveler
        var t    = state[pid].travelers[idx] || {};
        var info = pairInfo(idx);
        refreshPairBadge(pid, idx, t.room_type || 'shared', t.auto_upgraded || false);

        // Show/hide Room Preference for changed traveler
        var $rpWrap = $('#wctb-rpw-' + pid + '-' + idx);
        if (info.slot === 0 && (t.room_type || 'shared') === 'shared') {
            $rpWrap.show();
        } else {
            $rpWrap.hide();
        }

        // Refresh badge and room-pref visibility for partner
        var partner = info.partner;
        if (partner < state[pid].count) {
            var tp = state[pid].travelers[partner] || {};
            refreshPairBadge(pid, partner, tp.room_type || 'shared', tp.auto_upgraded || false);

            var $rpWrapP = $('#wctb-rpw-' + pid + '-' + partner);
            if ((tp.room_type || 'shared') === 'shared') {
                $rpWrapP.show();
                syncRoomPrefMirror(pid, partner);
            } else {
                $rpWrapP.hide();
            }
        }

        updatePricingDisplay(pid);
        refreshLegend(pid);
        serializeToHidden();

        // Refresh WooCommerce order review totals (right column)
        triggerWooUpdate();
    });

    /**
     * Room Preference select changed (lead traveler only).
     * Updates state and syncs the partner's read-only mirror.
     */
    $(document).on('change', '.wctb-co-room-pref', function () {
        var $sel    = $(this);
        var pid     = $sel.data('pid');
        var idx     = parseInt($sel.data('idx'));
        var pref    = $sel.val();

        if (!state[pid].travelers[idx]) state[pid].travelers[idx] = {};
        state[pid].travelers[idx].room_preference = pref;

        // Mirror to partner (slot 1) and update their read-only display
        var partner = pairInfo(idx).partner;
        if (partner < state[pid].count) {
            if (!state[pid].travelers[partner]) state[pid].travelers[partner] = {};
            state[pid].travelers[partner].room_preference = pref;
            syncRoomPrefMirror(pid, partner);
        }

        serializeToHidden();
    });

    /** Gender radio changed → serialize */
    $(document).on('change', '.wctb-t-gender', function () {
        var pid = $(this).data('pid');
        var idx = parseInt($(this).data('idx'));
        if (!state[pid].travelers[idx]) state[pid].travelers[idx] = {};
        state[pid].travelers[idx].gender = $(this).val();
        serializeToHidden();
    });

    /** All text/number inputs → serialize on change */
    $(document).on('input change', '.wctb-co-input', function () {
        // Refresh legend names when first_name changes
        if ($(this).hasClass('wctb-t-first-name')) {
            var pid = $(this).data('pid');
            var idx = parseInt($(this).data('idx'));
            if (!state[pid].travelers[idx]) state[pid].travelers[idx] = {};
            state[pid].travelers[idx].first_name = $(this).val();
            refreshLegend(pid);
        }
        serializeToHidden();
    });

    /** Validate + serialize before WooCommerce submits the order */
    $(document).on('click', '#place_order', function () {
        collectFromDOM();

        for (var i = 0; i < c.tour_items.length; i++) {
            var pid      = c.tour_items[i].product_id;
            var count    = state[pid].count;
            var travelers = state[pid].travelers;

            var dobRegex = /^\d{4}\/\d{2}\/\d{2}$/;
            for (var j = 0; j < count; j++) {
                var t = travelers[j] || {};
                if (!t.first_name || !t.last_name || !t.email) {
                    alert(c.i18n.fill_required);
                    var $wrap = $('#wctb-checkout-travelers-wrap');
                    if ($wrap.length) {
                        $wrap[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    return false;
                }
                if (!t.gender) {
                    alert(c.i18n.gender_required);
                    var $wrap = $('#wctb-checkout-travelers-wrap');
                    if ($wrap.length) {
                        $wrap[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    return false;
                }
                if (!t.dob || !dobRegex.test(t.dob)) {
                    alert(c.i18n.dob_invalid);
                    var $wrap = $('#wctb-checkout-travelers-wrap');
                    if ($wrap.length) {
                        $wrap[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                    return false;
                }
            }
        }

        serializeToHidden();
    });

    /* ── Init ─────────────────────────────────────────────────────── */
    $(function () {
        renderAll();
    });

})(jQuery);