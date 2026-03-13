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
        state[tour.product_id] = { count: 1, travelers: [] };
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
    var triggerWooUpdate = debounce(function () {
        $(document.body).trigger('update_checkout');
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

        // Only cascade if the partner exists
        if (partner < count) {
            if (!st.travelers[partner]) st.travelers[partner] = {};

            if (newRoom === 'single') {
                // Cascade: force partner to single
                st.travelers[partner].room_type = 'single';
                st.travelers[partner].auto_upgraded = true;
            } else {
                // Reverted to shared: un-auto the partner only if they
                // were auto-upgraded (user may have manually set them)
                if (st.travelers[partner].auto_upgraded) {
                    st.travelers[partner].room_type = 'shared';
                    st.travelers[partner].auto_upgraded = false;
                }
            }

            // Sync the DOM for the partner
            syncRoomDOM(pid, partner);
        }
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

        var $block  = $('#wctb-t-' + pid + '-' + idx);
        var $select = $block.find('.wctb-co-room');

        // Update select value silently (no re-trigger)
        $select.val(roomType);

        // Refresh the pair badge
        refreshPairBadge(pid, idx, roomType, auto);
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
        var $suppLine = $('#wctb-supp-line-' + pid);
        if (singles > 0 && tour.supplement > 0) {
            var suppTotal = tour.supplement * singles;
            $suppLine.html(
                '<span>' + escHtml(c.i18n.supplement_note) + singles + ' × ' + fmt(tour.supplement) + '</span>' +
                '<span>' + fmt(suppTotal) + '</span>'
            ).show();
        } else {
            $suppLine.hide();
        }
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
        html +=
            '<div class="wctb-co-topbar">' +
                '<div class="wctb-co-count-wrap">' +
                    '<label class="wctb-co-count-label" for="wctb-count-' + pid + '">' + i18n.num_travelers + '</label>' +
                    '<select id="wctb-count-' + pid + '" class="wctb-co-count" data-pid="' + pid + '">';

        for (var n = 1; n <= tour.max_travelers; n++) {
            html += '<option value="' + n + '"' + (n === st.count ? ' selected' : '') + '>' + n + '</option>';
        }

        html +=     '</select>' +
                '</div>' +
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

        var roomHtml = '';
        if (tour.room_enabled) {
            roomHtml =
                '<div class="wctb-co-field wctb-co-field--room">' +
                    '<label class="wctb-co-label">' + i18n.room_pref + '</label>' +
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
        }

        return '<div class="wctb-co-traveler" id="wctb-t-' + pid + '-' + idx + '" data-idx="' + idx + '" data-pid="' + pid + '">' +
            '<div class="wctb-co-traveler-heading">' +
                '<span class="wctb-co-traveler-num">' + i18n.traveler + ' ' + (idx + 1) + '</span>' +
                (tour.room_enabled
                    ? '<span class="wctb-pair-badge ' + mod + '" title="Room pairing">' + escHtml(label) + autoNote + '</span>'
                    : '') +
            '</div>' +
            '<div class="wctb-co-grid">' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-fn-' + pid + '-' + idx + '">' + i18n.first_name + ' <span class="req">*</span></label>' +
                    '<input type="text" id="wctb-fn-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-first-name" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.first_name || '') + '" ' +
                           'placeholder="' + escAttr(i18n.first_name) + '" autocomplete="given-name" />' +
                '</div>' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-ln-' + pid + '-' + idx + '">' + i18n.last_name + ' <span class="req">*</span></label>' +
                    '<input type="text" id="wctb-ln-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-last-name" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.last_name || '') + '" ' +
                           'placeholder="' + escAttr(i18n.last_name) + '" autocomplete="family-name" />' +
                '</div>' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-em-' + pid + '-' + idx + '">' + i18n.email + ' <span class="req">*</span></label>' +
                    '<input type="email" id="wctb-em-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-email" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.email || '') + '" ' +
                           'placeholder="' + escAttr(i18n.email) + '" autocomplete="email" />' +
                '</div>' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-ph-' + pid + '-' + idx + '">' + i18n.phone + '</label>' +
                    '<input type="tel" id="wctb-ph-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-phone" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.phone || '') + '" ' +
                           'placeholder="' + escAttr(i18n.phone) + '" autocomplete="tel" />' +
                '</div>' +
                '<div class="wctb-co-field">' +
                    '<label class="wctb-co-label" for="wctb-ag-' + pid + '-' + idx + '">' + i18n.age + '</label>' +
                    '<input type="number" id="wctb-ag-' + pid + '-' + idx + '" class="wctb-co-input wctb-t-age" ' +
                           'data-pid="' + pid + '" data-idx="' + idx + '" ' +
                           'value="' + escAttr(saved.age || '') + '" ' +
                           'placeholder="' + escAttr(i18n.age) + '" min="1" max="120" />' +
                '</div>' +
                roomHtml +
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

    /* ── Render all sections ──────────────────────────────────────── */

    function renderAll() {
        var $wrap = $('#wctb-checkout-travelers-wrap');
        if (!$wrap.length) return;

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
                state[pid].travelers[i].email      = $b.find('.wctb-t-email').val()       || '';
                state[pid].travelers[i].phone      = $b.find('.wctb-t-phone').val()       || '';
                state[pid].travelers[i].age        = $b.find('.wctb-t-age').val()         || '';
                // room_type is managed by applyRoomCascade, only read if not already set
                if (!state[pid].travelers[i].room_type) {
                    state[pid].travelers[i].room_type = $b.find('.wctb-co-room').val() || 'shared';
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
        var prev  = state[pid].count;
        state[pid].count = count;

        collectFromDOM();

        // Rebuild forms
        var tour  = c.tour_items.find(function (t) { return t.product_id == pid; });
        var $forms = $('#wctb-forms-' + pid);
        var html   = '';
        for (var i = 0; i < count; i++) {
            html += buildTravelerBlock(tour, i, state[pid].travelers[i] || {});
        }
        $forms.html(html);

        // Re-apply cascade for all pairs after count change
        for (var j = 0; j < count; j++) {
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

        // Run cascade — updates state for idx and partner
        applyRoomCascade(pid, idx, newRoom);

        // Refresh badge for changed traveler
        var t = state[pid].travelers[idx] || {};
        refreshPairBadge(pid, idx, t.room_type || 'shared', t.auto_upgraded || false);

        // Refresh badge for partner
        var partner = pairInfo(idx).partner;
        if (partner < state[pid].count) {
            var tp = state[pid].travelers[partner] || {};
            refreshPairBadge(pid, partner, tp.room_type || 'shared', tp.auto_upgraded || false);
        }

        updatePricingDisplay(pid);
        refreshLegend(pid);
        serializeToHidden();

        // Refresh WooCommerce order review totals (right column)
        triggerWooUpdate();
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
            }
        }

        serializeToHidden();
    });

    /* ── Init ─────────────────────────────────────────────────────── */
    $(function () {
        renderAll();
    });

})(jQuery);
