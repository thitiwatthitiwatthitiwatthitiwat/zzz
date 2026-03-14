<?php
require_once 'config.php';
$pageId = 'appointments';

$db = getDB();
try {
    $stmt = $db->query("SELECT * FROM vw_appointments_detail LIMIT 1000");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

// --- Build chart data ---
// Chart 1: count by status (if column exists)
$statusCounts = [];
$dateCounts   = [];
foreach ($rows as $r) {
    // status chart
    if (isset($r['status'])) {
        $s = $r['status'] ?? 'Unknown';
        $statusCounts[$s] = ($statusCounts[$s] ?? 0) + 1;
    }
    // date/month chart — look for any date-like column
    foreach (['appointment_date','date','created_at','visit_date'] as $dc) {
        if (isset($r[$dc]) && $r[$dc]) {
            $month = date('M Y', strtotime($r[$dc]));
            $dateCounts[$month] = ($dateCounts[$month] ?? 0) + 1;
            break;
        }
    }
}
arsort($statusCounts);
ksort($dateCounts);

require 'layout.php';
?>

<!-- Sidebar accent color override -->
<style>
  :root { --accent-color: var(--cyan); }
  .nav-item[href="appointments.php"] { --accent-color: var(--cyan); }
</style>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">Hospital<em>DB</em></div>
    <div class="logo-sub">Supabase Analytics</div>
  </div>
  <div class="sidebar-section-label">Views</div>
  <?php foreach ($nav as $id => $n): ?>
  <a href="<?= $n['file'] ?>"
     class="nav-item <?= $id === $pageId ? 'active' : '' ?>"
     style="<?= $id === $pageId ? '--accent-color:'.$n['color'].';' : '' ?>">
    <?= $n['label'] ?>
    <span class="nav-view-tag"><?= $n['view'] ?></span>
  </a>
  <?php endforeach; ?>
  <div class="sidebar-footer">
    <div class="conn-badge"><span class="conn-dot"></span>Supabase Connected</div>
  </div>
</aside>

<!-- Main -->
<div class="main-wrap">
  <div class="topbar" style="--accent-color:var(--cyan)">
    <div>
      <div class="page-title">Appointments <em>Detail</em></div>
    </div>
    <span class="view-pill" style="color:var(--cyan);border-color:rgba(0,229,255,0.25);background:rgba(0,229,255,0.06)">vw_appointments_detail</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format(count($rows)) ?> rows</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--cyan)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip">
      <div class="stat-box">
        <div class="stat-label">Total Records</div>
        <div class="stat-val accent"><?= number_format(count($rows)) ?></div>
      </div>
      <?php if ($statusCounts): ?>
      <?php $topStatus = array_key_first($statusCounts); ?>
      <div class="stat-box">
        <div class="stat-label">Top Status</div>
        <div class="stat-val" style="font-size:18px"><?= htmlspecialchars($topStatus) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Unique Statuses</div>
        <div class="stat-val accent"><?= count($statusCounts) ?></div>
      </div>
      <?php endif; ?>
      <div class="stat-box">
        <div class="stat-label">Columns</div>
        <div class="stat-val accent"><?= count($cols) ?></div>
      </div>
    </div>

    <!-- Charts -->
    <?php if ($statusCounts || $dateCounts): ?>
    <div class="content-grid">
      <?php if ($statusCounts): ?>
      <div class="chart-card">
        <div class="chart-card-title">Appointments by Status</div>
        <div class="chart-wrap"><canvas id="chartStatus"></canvas></div>
      </div>
      <?php endif; ?>
      <?php if ($dateCounts): ?>
      <div class="chart-card">
        <div class="chart-card-title">Appointments by Month</div>
        <div class="chart-wrap"><canvas id="chartDate"></canvas></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <?php if (!empty($rows)): ?>
    <div class="table-section">
      <div class="table-topbar">
        <span class="table-topbar-title">All Records</span>
        <input class="search-box" type="text" placeholder="Search table..." oninput="filterTable(this.value)">
      </div>
      <div class="scroll-x">
        <table id="main-table">
          <thead>
            <tr><?php foreach ($cols as $i => $col): ?><th onclick="sortTable(<?= $i ?>)"><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
              <?php foreach ($cols as $col): $v = $row[$col]; ?>
              <td><?= ($v===null||$v==='') ? '<span class="null-val">—</span>' : htmlspecialchars((string)$v) ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
// Charts
const chartDefaults = {
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { labels: { color: '#5a6480', font: { family: 'DM Mono', size: 11 }, boxWidth: 12 } } },
  scales: {
    x: { ticks: { color: '#5a6480', font: { family: 'DM Mono', size: 10 } }, grid: { color: '#1c2236' } },
    y: { ticks: { color: '#5a6480', font: { family: 'DM Mono', size: 10 } }, grid: { color: '#1c2236' } }
  }
};

<?php if ($statusCounts): ?>
new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($statusCounts)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($statusCounts)) ?>,
      backgroundColor: ['rgba(0,229,255,0.7)','rgba(167,139,250,0.7)','rgba(45,212,160,0.7)','rgba(245,197,66,0.7)','rgba(255,107,122,0.7)'],
      borderColor: '#111520', borderWidth: 2
    }]
  },
  options: { responsive:true, maintainAspectRatio:false, plugins: { legend: { position:'right', labels:{ color:'#5a6480', font:{family:'DM Mono',size:11}, boxWidth:12 } } } }
});
<?php endif; ?>

<?php if ($dateCounts): ?>
new Chart(document.getElementById('chartDate'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($dateCounts)) ?>,
    datasets: [{
      label: 'Appointments',
      data: <?= json_encode(array_values($dateCounts)) ?>,
      backgroundColor: 'rgba(0,229,255,0.15)',
      borderColor: 'rgba(0,229,255,0.7)',
      borderWidth: 1, borderRadius: 4
    }]
  },
  options: { ...chartDefaults }
});
<?php endif; ?>

// Search
function filterTable(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#main-table tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// Sort
let sortDir = {};
function sortTable(col) {
  const tb = document.querySelector('#main-table tbody');
  const rows = Array.from(tb.querySelectorAll('tr'));
  sortDir[col] = !sortDir[col];
  rows.sort((a, b) => {
    const av = a.cells[col]?.textContent.trim() || '';
    const bv = b.cells[col]?.textContent.trim() || '';
    const n = parseFloat(av) - parseFloat(bv);
    if (!isNaN(n)) return sortDir[col] ? n : -n;
    return sortDir[col] ? av.localeCompare(bv) : bv.localeCompare(av);
  });
  rows.forEach(r => tb.appendChild(r));
}
</script>
</html>