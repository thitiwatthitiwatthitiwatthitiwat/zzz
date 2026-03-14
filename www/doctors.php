<?php
require_once 'config.php';
$pageId = 'doctors';

$db = getDB();
try {
    $stmt = $db->query("SELECT * FROM vw_doctor_workload LIMIT 1000");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

// Count specialties using exact column
$specialtyCounts = [];
foreach ($rows as $r) {
    if (!empty($r['specialization'])) {
        $s = $r['specialization'];
        $specialtyCounts[$s] = ($specialtyCounts[$s] ?? 0) + 1;
    }
}

// Top 10 doctors by total_appointments for chart
$chartRows = $rows;
usort($chartRows, fn($a,$b) => (int)$b['total_appointments'] - (int)$a['total_appointments']);
$chartRows = array_slice($chartRows, 0, 10);
$chartNames   = array_column($chartRows, 'doctor_name');
$chartAppts   = array_map('intval', array_column($chartRows, 'total_appointments'));
$chartPats    = array_map('intval', array_column($chartRows, 'unique_patients'));
$chartRx      = array_map('intval', array_column($chartRows, 'total_prescriptions'));

$totalAppts = array_sum(array_column($rows, 'total_appointments'));
$totalRx    = array_sum(array_column($rows, 'total_prescriptions'));

require 'layout.php';
?>

<style>
  :root { --accent-color: var(--green); }
  .main-wrap { overflow: hidden; }
  .page-content {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 73px);
    overflow-y: auto;
    padding: 20px 36px !important;
  }
  .stats-strip { flex-shrink: 0; }
  .content-grid { flex-shrink: 0; }
  .table-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 300px; }
  .scroll-x { flex: 1; overflow: auto; min-height: 0; max-height: none !important; }
</style>

<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-mark">Hospital<em>DB</em></div>
    <div class="logo-sub">Supabase Analytics</div>
  </div>
  <div class="sidebar-section-label">Views</div>
  <?php foreach ($nav as $id => $n): ?>
  <a href="<?= $n['file'] ?>" class="nav-item <?= $id === $pageId ? 'active' : '' ?>"
     style="<?= $id === $pageId ? '--accent-color:'.$n['color'].';' : '' ?>">
    <?= $n['label'] ?>
    <span class="nav-view-tag"><?= $n['view'] ?></span>
  </a>
  <?php endforeach; ?>
  <div class="sidebar-footer">
    <div class="conn-badge"><span class="conn-dot"></span>Supabase Connected</div>
  </div>
</aside>

<div class="main-wrap">
  <div class="topbar" style="--accent-color:var(--green)">
    <div><div class="page-title">Doctor <em>Workload</em></div></div>
    <span class="view-pill" style="color:var(--green);border-color:rgba(45,212,160,0.25);background:rgba(45,212,160,0.06)">vw_doctor_workload</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format(count($rows)) ?> rows</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--green)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip" style="margin-bottom:16px">
      <div class="stat-box">
        <div class="stat-label">Total Doctors</div>
        <div class="stat-val accent"><?= number_format(count($rows)) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Specialties</div>
        <div class="stat-val accent"><?= count($specialtyCounts) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Total Appointments</div>
        <div class="stat-val accent"><?= number_format($totalAppts) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Total Prescriptions</div>
        <div class="stat-val accent"><?= number_format($totalRx) ?></div>
      </div>
    </div>

    <!-- Charts -->
    <?php if ($chartNames): ?>
    <div class="content-grid" style="margin-bottom:16px">
      <div class="chart-card">
        <div class="chart-card-title">Top 10 — Appointments per Doctor</div>
        <div class="chart-wrap"><canvas id="chartAppts"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Top 10 — Prescriptions per Doctor</div>
        <div class="chart-wrap"><canvas id="chartRx"></canvas></div>
      </div>
    </div>
    <?php endif; ?>

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
    <?php elseif (!isset($error)): ?>
      <div class="table-section">
        <div style="padding:60px;text-align:center;color:var(--muted);font-size:14px;font-family:var(--mono)">No data found in this view.</div>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
const monoFont  = { family:'DM Mono', size:10 };
const gridColor = '#1c2236';
const tickColor = '#5a6480';

<?php if ($chartNames): ?>
const chartNames = <?= json_encode($chartNames) ?>;
const chartAppts = <?= json_encode($chartAppts) ?>;
const chartRx    = <?= json_encode($chartRx) ?>;

const sharedOptions = {
  indexAxis: 'y',
  responsive: true, maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor} },
    y: { ticks:{color:tickColor,font:monoFont,size:9}, grid:{color:'transparent'} }
  }
};

new Chart(document.getElementById('chartAppts'), {
  type: 'bar',
  data: {
    labels: chartNames,
    datasets: [{
      data: chartAppts,
      backgroundColor: 'rgba(45,212,160,0.2)',
      borderColor: 'rgba(45,212,160,0.8)',
      borderWidth: 1, borderRadius: 4
    }]
  },
  options: sharedOptions
});

new Chart(document.getElementById('chartRx'), {
  type: 'bar',
  data: {
    labels: chartNames,
    datasets: [{
      data: chartRx,
      backgroundColor: 'rgba(77,159,255,0.2)',
      borderColor: 'rgba(77,159,255,0.8)',
      borderWidth: 1, borderRadius: 4
    }]
  },
  options: sharedOptions
});
<?php endif; ?>

function filterTable(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#main-table tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
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