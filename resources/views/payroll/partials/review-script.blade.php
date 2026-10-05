{{-- The behaviour behind the approval table, shared by HR, Finance and MD.

     Three near-identical copies of this had already drifted: HR re-totalled
     every money column as people were ticked off a run, Finance and MD only
     re-totalled net. Whoever is approving, the figures have to move the same
     way, so there is one copy.

     Only confirmApprove() stays in each screen: what it warns about, and what
     it says will happen, genuinely differs by stage. --}}
<script>
/* Which columns have a footer total, in column order. Set by the table
   partial, because only it knows which deduction parts the run turned out
   to carry. */
const totalCols = window.reviewTotalCols || ['basic', 'gross', 'other', 'ded', 'net'];

function toggleAll(master) {
    document.querySelectorAll('.slip-checkbox').forEach(cb => cb.checked = master.checked);
    updateCount();
}

function updateCount() {
    const all     = document.querySelectorAll('.slip-checkbox');
    const checked = [...document.querySelectorAll('.slip-checkbox:checked')];

    const selected = document.getElementById('selectedCount');
    const counter  = document.getElementById('footerCount');
    if (selected) selected.textContent = checked.length;
    if (counter)  counter.textContent  = checked.length;

    /* Every money column, not just net: the point of the breakdown is that
       the approver can see what the PAYE and NSSF bill comes to for the
       people actually being paid, after the withheld are taken out. */
    const sums = Object.fromEntries(totalCols.map(c => [c, 0]));

    checked.forEach(cb => {
        const d = cb.closest('tr').dataset;
        totalCols.forEach(c => { sums[c] += parseFloat(d[c] || 0); });
    });

    totalCols.forEach(c => {
        const el = document.getElementById('foot-' + c);
        if (el) el.textContent = Math.round(sums[c]).toLocaleString();
    });

    const fmt = 'UGX ' + Math.round(sums.net || 0).toLocaleString();
    const totalNet  = document.getElementById('totalNet');
    const footerNet = document.getElementById('footerNet');
    if (totalNet)  totalNet.textContent  = fmt;
    if (footerNet) footerNet.textContent = fmt;

    all.forEach(cb => {
        const row = cb.closest('tr');
        row.classList.toggle('opacity-40', !cb.checked);
        row.classList.toggle('bg-red-50',  !cb.checked);
        row.classList.toggle('hover:bg-red-50', !cb.checked);
    });

    const master = document.getElementById('selectAll');
    if (master) {
        master.indeterminate = checked.length > 0 && checked.length < all.length;
        master.checked = checked.length === all.length;
    }
}

function filterTable() {
    const name = (document.getElementById('searchInput').value || '').toLowerCase();
    const dept = document.getElementById('deptFilter').value;

    document.querySelectorAll('.employee-row').forEach(row => {
        const matchName = !name || row.dataset.name.includes(name) || row.dataset.emp.includes(name);
        const matchDept = !dept || row.dataset.dept === dept;
        row.style.display = (matchName && matchDept) ? '' : 'none';
    });
}

function showOnlyDeselected() {
    document.querySelectorAll('.employee-row').forEach(row => {
        row.style.display = row.querySelector('.slip-checkbox').checked ? 'none' : '';
    });
}

function showAll() {
    document.getElementById('searchInput').value = '';
    document.getElementById('deptFilter').value  = '';
    document.querySelectorAll('.employee-row').forEach(row => row.style.display = '');
}

/* The run may arrive with people already withheld at an earlier stage, so the
   totals must be right before anybody touches anything. */
document.addEventListener('DOMContentLoaded', updateCount);
</script>
