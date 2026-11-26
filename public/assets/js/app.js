/* ad-manage front-end helpers (loaded on every page by layouts/app). */

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
