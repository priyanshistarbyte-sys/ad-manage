/* Amaira — app.js */

// ── Tag Input widget ──
// Call with: initTagInput(wrapId, textInputId, hiddenInputId, initialValues[])
function initTagInput(wrapId, textInputId, hiddenId, initial) {
    const wrap   = document.getElementById(wrapId);
    const input  = document.getElementById(textInputId);
    const hidden = document.getElementById(hiddenId);
    if (!wrap || !input || !hidden) return;

    // Build tag set from initial values already in the hidden field
    const tags = new Set(
        (hidden.value || '').split(',').map(s => s.trim()).filter(Boolean)
    );

    function renderTags() {
        // Remove existing pills (keep the text input)
        wrap.querySelectorAll('.tag-pill').forEach(p => p.remove());
        tags.forEach(tag => {
            const pill = document.createElement('span');
            pill.className = 'tag-pill';
            pill.innerHTML = `${tag}<button type="button" class="tag-remove" data-value="${tag}">×</button>`;
            pill.querySelector('.tag-remove').addEventListener('click', () => {
                tags.delete(tag);
                renderTags();
            });
            wrap.insertBefore(pill, input);
        });
        hidden.value = [...tags].join(',');
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function addTag(val) {
        val = val.trim().replace(/,+$/, '');
        if (val) { tags.add(val); renderTags(); }
        input.value = '';
    }

    input.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            addTag(input.value);
        }
        // Backspace on empty input removes last tag
        if (e.key === 'Backspace' && input.value === '' && tags.size > 0) {
            const last = [...tags].pop();
            tags.delete(last);
            renderTags();
        }
    });

    input.addEventListener('blur', () => { if (input.value.trim()) addTag(input.value); });

    // Click anywhere on wrap focuses the input
    wrap.addEventListener('click', () => input.focus());

    renderTags(); // render any pre-existing tags (edit modal)
}

// ── Searchable select (combobox): filter text input + hidden value + option list ──
function initSearchableSelect(wrap) {
    const hidden = wrap.querySelector('.ss-value');
    const search = wrap.querySelector('.ss-search');
    const list   = wrap.querySelector('.ss-list');
    if (!hidden || !search || !list) return;
    const opts = Array.from(list.querySelectorAll('.ss-opt'));

    const open  = () => { list.style.display = 'block'; };
    const close = () => { list.style.display = 'none'; };

    function currentLabel() {
        const cur = opts.find(o => (o.dataset.id || '') === (hidden.value || ''));
        return cur ? (cur.dataset.label || cur.textContent.trim()) : '';
    }
    function selectOpt(opt) {
        hidden.value = opt.dataset.id || '';
        search.value = opt.dataset.label || opt.textContent.trim();
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    }
    function filter() {
        const q = search.value.trim().toLowerCase();
        opts.forEach(o => { o.style.display = o.textContent.toLowerCase().includes(q) ? 'block' : 'none'; });
    }

    search.addEventListener('focus', () => { search.select(); open(); filter(); });
    search.addEventListener('input', () => { open(); filter(); });
    search.addEventListener('keydown', e => {
        if (e.key === 'Enter')      { e.preventDefault(); const v = opts.find(o => o.style.display !== 'none'); if (v) selectOpt(v); }
        else if (e.key === 'Escape'){ search.value = currentLabel(); close(); }
    });
    opts.forEach(o => o.addEventListener('mousedown', e => { e.preventDefault(); selectOpt(o); }));
    document.addEventListener('click', e => { if (!wrap.contains(e.target)) { search.value = currentLabel(); close(); } });

    if (currentLabel()) search.value = currentLabel();
}

// Programmatically set a combobox to a domain id (used by "Add subdomain" buttons)
window.setSearchableSelect = function (wrap, id) {
    const hidden = wrap.querySelector('.ss-value');
    const search = wrap.querySelector('.ss-search');
    const opt = Array.from(wrap.querySelectorAll('.ss-opt')).find(o => (o.dataset.id || '') === String(id || ''));
    if (hidden) { hidden.value = id || ''; hidden.dispatchEvent(new Event('change', { bubbles: true })); }
    if (search) search.value = opt ? (opt.dataset.label || opt.textContent.trim()) : '';
};

// Initialise both modals if present
document.addEventListener('DOMContentLoaded', () => {
    // Meta campaign tag inputs
    initTagInput('addTagWrap',  'addTagInput',  'addCampaignValue',  []);
    initTagInput('editTagWrap', 'editTagInput', 'editCampaignValue', []);

    // GAM URL tag inputs (a link can map to several GAM sites)
    initTagInput('addGamWrap',  'addGamInput',  'addGamValue',  []);
    initTagInput('editGamWrap', 'editGamInput', 'editGamValue', []);

    // Searchable domain pickers
    document.querySelectorAll('.ss-wrap').forEach(initSearchableSelect);

    // Validate at least one campaign AND one GAM site before submitting
    ['addForm', 'editForm'].forEach(fId => {
        const form = document.getElementById(fId);
        if (!form) return;
        form.addEventListener('submit', e => {
            const camp = form.querySelector('[name="meta_campaign"]');
            const gam  = form.querySelector('[name="gam_url"]');
            if (!camp || !camp.value.trim()) {
                e.preventDefault();
                alert('Please add at least one Meta campaign name.');
            } else if (!gam || !gam.value.trim()) {
                e.preventDefault();
                alert('Please add at least one GAM URL / site host.');
            }
        });
    });
});

// ── Date picker navigation (dashboard) ──
const datePicker = document.getElementById('datePicker');
if (datePicker) {
    datePicker.addEventListener('change', function () {
        const url = new URL(window.location.href);
        url.searchParams.set('date', this.value);
        window.location.href = url.toString();
    });
}

// ── User filter navigation (dashboard + monthly, admin) ──
document.querySelectorAll('.js-user-filter').forEach(function (sel) {
    sel.addEventListener('change', function () {
        const url = new URL(window.location.href);
        if (this.value) url.searchParams.set('user', this.value);
        else url.searchParams.delete('user');
        window.location.href = url.toString();
    });
});

// ── ADX network filter navigation (dashboard + monthly) ──
document.querySelectorAll('.js-adx-filter').forEach(function (sel) {
    sel.addEventListener('change', function () {
        const url = new URL(window.location.href);
        if (this.value) url.searchParams.set('adx', this.value);
        else url.searchParams.delete('adx');
        window.location.href = url.toString();
    });
});

// ── Upload drag-and-drop ──
document.querySelectorAll('.upload-zone').forEach(zone => {
    const input = zone.querySelector('input[type="file"]');

    zone.addEventListener('click', () => input && input.click());

    zone.addEventListener('dragover', e => {
        e.preventDefault();
        zone.classList.add('drag-over');
    });
    zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('drag-over');
        if (input && e.dataTransfer.files.length) {
            input.files = e.dataTransfer.files;
            zone.querySelector('.file-name') && (zone.querySelector('.file-name').textContent = e.dataTransfer.files[0].name);
        }
    });

    if (input) {
        input.addEventListener('change', function () {
            const label = zone.querySelector('.file-name');
            if (label && this.files[0]) label.textContent = this.files[0].name;
        });
    }
});

// ── Delete confirm ──
document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', function (e) {
        if (!confirm(this.dataset.confirm)) e.preventDefault();
    });
});

// ── Row Edit Modal (Dashboard) ──
(function () {
    const ROOT_URL  = document.documentElement.dataset.rootUrl || '';
    const modalEl   = document.getElementById('rowEditModal');
    if (!modalEl) return;

    const modal        = new bootstrap.Modal(modalEl);
    const linkNameEl   = document.getElementById('editModalLinkName');
    const currentGamEl = document.getElementById('editCurrentGam');
    const currentMetaEl= document.getElementById('editCurrentMeta');
    const gamInput     = document.getElementById('editGamUsd');
    const metaInput    = document.getElementById('editMetaSpend');
    const saveBtn      = document.getElementById('editModalSave');
    const errorEl      = document.getElementById('editModalError');

    let activeRow = null;

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = 'flex';
    }
    function clearError() {
        errorEl.style.display = 'none';
    }

    // Toggle campaign breakdown sub-row on Meta Spend cell click
    document.addEventListener('click', e => {
        const cell = e.target.closest('.camp-toggle-cell');
        if (!cell) return;
        const target = document.getElementById(cell.dataset.target);
        if (!target) return;
        const open    = target.style.display !== 'none';
        target.style.display = open ? 'none' : 'table-row';
        const chevron = cell.querySelector('.camp-chevron');
        if (chevron) chevron.style.transform = open ? '' : 'rotate(180deg)';
        cell.style.cursor = 'pointer';
        cell.style.opacity = open ? '1' : '0.85';
    });

    // Open modal when clicking ✎ button
    document.addEventListener('click', e => {
        const btn = e.target.closest('.btn-row-edit');
        if (!btn) return;

        activeRow = btn.closest('tr');
        const gamUsd  = parseFloat(activeRow.dataset.gamUsd  || 0);
        const spend   = parseFloat(activeRow.dataset.metaSpend || 0);

        linkNameEl.textContent   = activeRow.dataset.linkName || '';
        currentGamEl.textContent = '$' + gamUsd.toFixed(2);
        currentMetaEl.textContent= '₹' + spend.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});

        gamInput.value  = gamUsd  > 0 ? gamUsd.toFixed(2)  : '';
        metaInput.value = spend   > 0 ? spend.toFixed(2)   : '';
        clearError();
        modal.show();

        // Focus first input after modal animates in
        modalEl.addEventListener('shown.bs.modal', () => gamInput.focus(), {once: true});
    });

    // Save
    saveBtn.addEventListener('click', async () => {
        if (!activeRow) return;
        clearError();

        const gamVal  = gamInput.value.trim();
        const metaVal = metaInput.value.trim();

        if (gamVal === '' && metaVal === '') {
            showError('Enter at least one value to save.'); return;
        }
        if (gamVal  !== '' && (isNaN(+gamVal)  || +gamVal  < 0)) {
            showError('GAM Revenue must be a positive number.'); return;
        }
        if (metaVal !== '' && (isNaN(+metaVal) || +metaVal < 0)) {
            showError('Meta Spend must be a positive number.'); return;
        }

        saveBtn.disabled  = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';

        try {
            const body = new URLSearchParams({
                date           : activeRow.dataset.date,
                user_id        : activeRow.dataset.userId || '',
                gam_sites      : activeRow.dataset.gamSites,
                meta_campaigns : activeRow.dataset.metaCampaigns,
                gam_usd        : gamVal  !== '' ? gamVal  : '',
                meta_spend     : metaVal !== '' ? metaVal : '',
            });

            const res  = await fetch(ROOT_URL + '/save_value', { method: 'POST', body });
            const data = await res.json();

            if (!data.ok) { showError('Save failed: ' + data.msg); return; }

            // Update cells in the row
            const usdCell = activeRow.querySelector('.cell-gam-usd');
            usdCell.textContent = data.gam_usd_fmt;
            usdCell.className   = 'cell-gam-usd c-green';
            usdCell.style.fontSize = '11px';

            activeRow.querySelector('.cell-gam-inr').textContent  = data.gam_inr_fmt;
            activeRow.querySelector('.cell-gam-inr').className     = 'cell-gam-inr c-green';

            const spendCell = activeRow.querySelector('.cell-meta-spend');
            spendCell.textContent = data.spend_fmt;
            spendCell.className   = 'cell-meta-spend c-orange';

            const spendUsdCell = activeRow.querySelector('.cell-meta-usd');
            if (spendUsdCell) spendUsdCell.textContent = data.spend_usd_fmt;

            activeRow.querySelector('.cell-gst').textContent   = data.gst_fmt;
            activeRow.querySelector('.cell-gst').className      = 'cell-gst c-gold';
            activeRow.querySelector('.cell-cost').textContent  = data.cost_fmt;

            const plCell = activeRow.querySelector('.cell-netpl');
            plCell.textContent      = data.netpl_fmt;
            plCell.className        = 'cell-netpl ' + (data.netpl >= 0 ? 'c-green' : 'c-red');
            plCell.style.fontWeight = '700';

            const mgnCell = activeRow.querySelector('.cell-margin');
            mgnCell.textContent = data.margin_fmt;
            mgnCell.className   = 'cell-margin ' + ((data.margin ?? 0) >= 0 ? 'c-green' : 'c-red');

            const statusCell = activeRow.querySelector('.cell-status');
            statusCell.innerHTML = data.netpl >= 0
                ? '<span class="badge-profit">✓ Profit</span>'
                : '<span class="badge-loss">✗ Loss</span>';

            // Update row class & data attributes
            activeRow.classList.remove('row-nodata', 'row-profit', 'row-loss');
            activeRow.classList.add(data.netpl >= 0 ? 'row-profit' : 'row-loss');
            activeRow.dataset.gamUsd    = data.gam_usd;
            activeRow.dataset.metaSpend = metaVal !== '' ? metaVal : activeRow.dataset.metaSpend;

            modal.hide();

        } catch (err) {
            showError('Network error: ' + err.message);
        } finally {
            saveBtn.disabled  = false;
            saveBtn.innerHTML = '<i class="bi bi-floppy"></i> Save Changes';
        }
    });

    // Clear active row when modal closes
    modalEl.addEventListener('hidden.bs.modal', () => { activeRow = null; });
})();

// ── Table sorting ──
(function () {
    const table = document.getElementById('dashTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    let sortCol = null, sortDir = 1;

    function cellValue(row, colIndex, type) {
        const cell = row.cells[colIndex];
        if (!cell) return '';
        const text = cell.innerText.trim();
        if (type === 'num') {
            const clean = text.replace(/[₹$,%\s]/g, '').replace(/[()]/g, '');
            const n = parseFloat(clean);
            return isNaN(n) ? -Infinity : n;
        }
        return text.toLowerCase();
    }

    function sortTable(th) {
        // Block sort while any cell is being edited
        if (table.querySelector('.cell-editing')) return;

        const col  = parseInt(th.dataset.col, 10);
        const type = th.dataset.sort;

        sortDir = (sortCol === col) ? sortDir * -1 : (type === 'num' ? -1 : 1);
        sortCol = col;

        table.querySelectorAll('thead th').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
        th.classList.add(sortDir === 1 ? 'sort-asc' : 'sort-desc');

        // Sort only the main rows; each campaign-breakdown sub-row must travel
        // with its parent so it opens under the right row after sorting.
        const rows = Array.from(tbody.querySelectorAll('tr:not(.camp-sub-row)'));
        rows.sort((a, b) => {
            if (a.classList.contains('row-nodata') && !b.classList.contains('row-nodata')) return 1;
            if (!a.classList.contains('row-nodata') && b.classList.contains('row-nodata')) return -1;
            const av = cellValue(a, col, type);
            const bv = cellValue(b, col, type);
            return av < bv ? -sortDir : av > bv ? sortDir : 0;
        });
        rows.forEach(r => {
            tbody.appendChild(r);
            const cell = r.querySelector('.camp-toggle-cell[data-target]');
            const sub  = cell && document.getElementById(cell.dataset.target);
            if (sub) tbody.appendChild(sub);
        });
    }

    // Sort ONLY when clicking the .sort-btn span, not the entire <th>
    table.querySelectorAll('thead th .sort-btn').forEach(btn => {
        btn.addEventListener('click', e => {
            e.stopPropagation(); // prevent any parent listeners
            sortTable(btn.closest('th'));
        });
    });
})();

// ── CSV export (dashboard): exports the table exactly as shown ──
(function () {
    const table = document.getElementById('dashTable');
    const btn   = document.getElementById('dashExportBtn');
    const domBtn = document.getElementById('dashDomainsBtn');
    if (!table) return;

    function csvCell(text) {
        text = (text || '').replace(/\s+/g, ' ').trim();
        return /[",\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
    }

    // Split a cell into [main ₹ text, USD sub-line text]. Cells carrying a small
    // USD sub-line (.cell-meta-usd) get their $ value returned separately so we can
    // put it in the row BELOW (a separate cell, same column) instead of together.
    function splitCell(c) {
        const usdEl = c.querySelector('.cell-meta-usd');
        if (!usdEl) return [(c.innerText || '').replace(/\s+/g, ' ').trim(), ''];
        const usdText = usdEl.innerText.replace(/\s+/g, ' ').trim();
        const clone = c.cloneNode(true);
        clone.querySelector('.cell-meta-usd').remove();
        return [clone.innerText.replace(/\s+/g, ' ').trim(), usdText];
    }

    // Trigger a client-side download of CSV text (BOM for Excel's UTF-8).
    function downloadCsv(text, filename) {
        const blob = new Blob(['﻿' + text], { type: 'text/csv;charset=utf-8;' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
    }

    // Full host (subdomain included) of a URL — scheme/path stripped, nothing reduced.
    function hostOf(url) {
        return (url || '').toLowerCase().trim().replace(/^https?:\/\//, '').split('/')[0];
    }

    // Iterate the visible main rows (skips breakdown + notes-filtered-out rows).
    function eachVisibleRow(fn) {
        Array.from(table.tBodies[0].rows).forEach(row => {
            if (row.classList.contains('camp-sub-row')) return;
            if (row.style.display === 'none') return;
            fn(row);
        });
    }

    // Header index of the "GAM URL" column, so we can rewrite just that cell.
    function gamColIndex() {
        const heads = Array.from(table.tHead.rows[0].cells);
        return heads.findIndex(h => /gam\s*url/i.test(h.innerText));
    }

    // Build the full table CSV. When gamAsLastDomain is true, the GAM URL cell is
    // replaced by the registrable domain of the link's LAST GAM URL (a link may
    // map to several GAM URLs).
    function buildCsv(gamAsLastDomain) {
        const heads  = Array.from(table.tHead.rows[0].cells);
        const cols   = heads.length - 1;              // drop the trailing Edit column
        const gamCol = gamAsLastDomain ? gamColIndex() : -1;
        const lines  = [heads.slice(0, cols).map(h => csvCell(h.innerText.replace('⇅', ''))).join(',')];

        // Emit a row, plus (if any cell had a USD sub-line) an extra row below with
        // just those $ values in their own column — so ₹ is up, $ is down.
        function pushRow(mainVals, belowVals) {
            lines.push(mainVals.join(','));
            if (belowVals.some(v => v !== '')) lines.push(belowVals.join(','));
        }

        eachVisibleRow(row => {
            const cells = Array.from(row.cells).slice(0, cols);
            if (!cells.length) return;
            const below = Array(cols).fill('');
            const vals = cells.map((c, idx) => {
                if (idx === gamCol) {
                    let sites = [];
                    try { sites = JSON.parse(row.dataset.gamSites || '[]'); } catch (e) { sites = []; }
                    return csvCell(sites.length ? hostOf(sites[sites.length - 1]) : '');
                }
                const [main, usd] = splitCell(c);
                if (usd) below[idx] = csvCell(usd);
                return csvCell(main);
            });
            pushRow(vals, below);
        });

        // TOTALS footer — expand colspans so columns stay aligned.
        const foot = table.tFoot && table.tFoot.rows[0];
        if (foot) {
            const out = [];
            const below = [];
            Array.from(foot.cells).forEach(c => {
                const [main, usd] = splitCell(c);
                out.push(csvCell(main));
                below.push(usd ? csvCell(usd) : '');
                for (let k = 1; k < (c.colSpan || 1); k++) { out.push(''); below.push(''); }
            });
            pushRow(out.slice(0, cols), below.slice(0, cols));
        }
        return lines.join('\r\n');
    }

    if (btn) btn.addEventListener('click', () =>
        downloadCsv(buildCsv(false), 'dashboard-' + (btn.dataset.date || 'export') + '.csv'));

    // Full data, but the GAM URL column shows only the last GAM URL (full host).
    if (domBtn) domBtn.addEventListener('click', () =>
        downloadCsv(buildCsv(true), 'dashboard-last-gam-url-' + (domBtn.dataset.date || 'export') + '.csv'));
})();

// ── Notes filter (dashboard): searchable multi-select over the link table ──
(function () {
    const wrap  = document.getElementById('noteFilter');
    const table = document.getElementById('dashTable');
    if (!wrap || !table) return;

    const btn    = document.getElementById('noteFilterBtn');
    const panel  = document.getElementById('noteFilterPanel');
    const label  = document.getElementById('noteFilterLabel');
    const search = document.getElementById('noteFilterSearch');
    const list   = document.getElementById('noteFilterList');
    const tbody  = table.querySelector('tbody');
    const boxes  = () => Array.from(list.querySelectorAll('input[type=checkbox]'));

    function openPanel()  { panel.style.display = 'block'; search.value = ''; filterOptions(); search.focus(); }
    function closePanel() { panel.style.display = 'none'; }

    btn.addEventListener('click', e => {
        e.stopPropagation();
        panel.style.display === 'none' ? openPanel() : closePanel();
    });
    document.addEventListener('click', e => { if (!wrap.contains(e.target)) closePanel(); });

    // Search box filters the list of note options (not the table).
    function filterOptions() {
        const q = search.value.trim().toLowerCase();
        list.querySelectorAll('.note-opt').forEach(o => {
            o.style.display = o.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }
    search.addEventListener('input', filterOptions);

    const allBtn  = document.getElementById('noteFilterAll');
    const noneBtn = document.getElementById('noteFilterNone');
    // "Select all" only ticks the options currently visible under the search.
    allBtn.addEventListener('click', () => {
        boxes().forEach(b => { if (b.closest('.note-opt').style.display !== 'none') b.checked = true; });
        applyFilter();
    });
    noneBtn.addEventListener('click', () => { boxes().forEach(b => b.checked = false); applyFilter(); });
    list.addEventListener('change', applyFilter);

    // Checked notes filter the table rows (empty selection = show all).
    function applyFilter() {
        const selected = new Set(boxes().filter(b => b.checked).map(b => b.value));
        const active   = selected.size > 0;
        label.textContent = !active ? 'All' : (selected.size + ' selected');

        Array.from(tbody.children).forEach(tr => {
            if (tr.classList.contains('camp-sub-row')) return; // handled via its parent
            const note = (tr.getAttribute('data-note') || '').trim();
            const key  = note === '' ? '__empty__' : note;
            const show = !active || selected.has(key);
            tr.style.display = show ? '' : 'none';

            const sub = tr.nextElementSibling;
            if (sub && sub.classList.contains('camp-sub-row') && !show) sub.style.display = 'none';
        });
    }
})();

// ── Flash auto-hide ──
setTimeout(() => {
    document.querySelectorAll('.alert-custom').forEach(a => {
        a.style.transition = 'opacity .5s';
        a.style.opacity = '0';
        setTimeout(() => a.remove(), 500);
    });
}, 4000);

// ── Generic column sorting for any <table class="sortable"> ──
// Click a header to sort; click again to flip asc/desc. A cell's data-sort
// attribute (if present) is used instead of its text. Delegated on document so
// tables injected later (e.g. the History/Country drill-down modal) work too.
(function () {
    function sortKey(td) {
        if (!td) return '';
        const raw = td.hasAttribute('data-sort') ? td.getAttribute('data-sort') : td.innerText;
        return raw.trim();
    }

    function asNumber(s) {
        if (s === '' || s === '—' || s === '-') return null;
        const clean = s.replace(/[₹$,%\s▲]/g, '').replace('▼', '-');
        return /^-?\d+(\.\d+)?$/.test(clean) ? parseFloat(clean) : NaN;
    }

    document.addEventListener('click', function (e) {
        const th = e.target.closest('table.sortable > thead > tr > th');
        if (!th || th.textContent.trim() === '') return;

        const table = th.closest('table');
        const tbody = table.tBodies[0];
        if (!tbody) return;
        const rows = Array.from(tbody.rows);
        // Nothing to sort (or only the "no data" colspan row)
        if (rows.length < 2 || rows.some(r => r.cells.length === 1 && r.cells[0].colSpan > 1)) return;

        const col   = th.cellIndex;
        const keys  = rows.map(r => sortKey(r.cells[col]));
        const nums  = keys.map(asNumber);
        const isNum = nums.every(n => n === null || !isNaN(n)) && nums.some(n => n !== null);

        // Numbers start high→low, text starts A→Z; repeat clicks toggle.
        let dir;
        if (th.classList.contains('sort-desc')) dir = 1;
        else if (th.classList.contains('sort-asc')) dir = -1;
        else dir = isNum ? -1 : 1;

        table.querySelectorAll('thead th').forEach(h => h.classList.remove('sort-asc', 'sort-desc'));
        th.classList.add(dir === 1 ? 'sort-asc' : 'sort-desc');

        const order = rows.map((r, i) => i);
        order.sort((a, b) => {
            if (isNum) {
                const x = nums[a], y = nums[b];
                if (x === null && y === null) return a - b;
                if (x === null) return 1;   // blanks always last
                if (y === null) return -1;
                return (x - y) * dir || a - b;
            }
            return keys[a].localeCompare(keys[b], undefined, { numeric: true, sensitivity: 'base' }) * dir || a - b;
        });
        order.forEach(i => tbody.appendChild(rows[i]));
    });
})();
