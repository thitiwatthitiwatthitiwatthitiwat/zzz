<?php
require_once 'config.php';
$pageId = 'depworkload';

$db = getDB();
try {
    $stmt = $db->query("SELECT department_name, location, total_doctors, total_appointments, unique_patients, appointments_per_doctor FROM vw_department_workload");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

$totalDepartments  = count($rows);
$totalDoctors      = array_sum(array_column($rows, 'total_doctors'));
$totalAppointments = array_sum(array_column($rows, 'total_appointments'));
$totalPatients     = array_sum(array_column($rows, 'unique_patients'));

$chartLabels  = array_column($rows, 'department_name');
$chartAppts   = array_map('intval', array_column($rows, 'total_appointments'));
$chartDoctors = array_map('intval', array_column($rows, 'total_doctors'));
$chartPats    = array_map('intval', array_column($rows, 'unique_patients'));
$maxAppts     = $chartAppts ? max($chartAppts) : 1;

require 'layout.php';
?>

<style>
  :root { --accent-color: var(--gold); }
  .main-wrap { overflow: hidden; }
  .page-content {
    display: flex; flex-direction: column;
    height: calc(100vh - 73px);
    overflow-y: auto;
    padding: 20px 36px !important;
  }
  .stats-strip  { flex-shrink: 0; }
  .content-grid { flex-shrink: 0; }
  .table-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 300px; }
  .scroll-x { flex: 1; overflow: auto; min-height: 0; max-height: none !important; }

  /* Appt bar */
  .bar-wrap  { display: flex; align-items: center; gap: 8px; }
  .bar-track { flex: 1; height: 5px; background: var(--border2); border-radius: 3px; min-width: 80px; }
  .bar-fill  { height: 100%; border-radius: 3px; background: linear-gradient(90deg, rgba(245,197,66,0.5), rgba(245,197,66,1)); }
  .bar-val   { font-family: var(--mono); font-size: 11px; color: var(--gold); width: 40px; text-align: right; flex-shrink: 0; }
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
  <div class="topbar" style="--accent-color:var(--gold)">
    <div><div class="page-title">Department <em>Workload</em></div></div>
    <span class="view-pill" style="color:var(--gold);border-color:rgba(245,197,66,0.25);background:rgba(245,197,66,0.06)">vw_department_workload</span>
    <div class="topbar-right">
      <span class="row-stat"><?= $totalDepartments ?> departments</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--gold)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip" style="margin-bottom:16px">
      <div class="stat-box">
        <div class="stat-label">Departments</div>
        <div class="stat-val accent"><?= $totalDepartments ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Total Doctors</div>
        <div class="stat-val accent"><?= number_format($totalDoctors) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Total Appointments</div>
        <div class="stat-val accent"><?= number_format($totalAppointments) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Unique Patients</div>
        <div class="stat-val accent"><?= number_format($totalPatients) ?></div>
      </div>
    </div>

    <!-- Charts -->
    <?php if ($chartLabels): ?>
    <div class="content-grid" style="margin-bottom:16px">
      <div class="chart-card">
        <div class="chart-card-title">Appointments per Department</div>
        <div class="chart-wrap"><canvas id="chartAppts"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Doctors vs Patients per Department</div>
        <div class="chart-wrap"><canvas id="chartDoctorPat"></canvas></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <?php if (!empty($rows)): ?>
    <div class="table-section">
      <div class="table-topbar">
        <span class="table-topbar-title">All Records</span>
        <input class="search-box" type="text" placeholder="Search departments..." oninput="filterTable(this.value)">
      </div>
      <div class="scroll-x">
        <table id="main-table">
          <thead>
            <tr>
              <th onclick="sortTable(0)">Department</th>
              <th onclick="sortTable(1)">Location</th>
              <th onclick="sortTable(2)">Doctors</th>
              <th onclick="sortTable(3)">Appointments</th>
              <th onclick="sortTable(4)">Unique Patients</th>
              <th onclick="sortTable(5)">Appts / Doctor</th>
              <th>Load</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row):
              $appts = (int)$row['total_appointments'];
              $pct   = $maxAppts > 0 ? round($appts / $maxAppts * 100) : 0;
            ?>
            <tr>
              <td style="font-weight:600"><?= htmlspecialchars($row['department_name'] ?? '—') ?></td>
              <td style="color:var(--muted)"><?= htmlspecialchars($row['location'] ?? '—') ?></td>
              <td style="font-family:var(--mono)"><?= number_format($row['total_doctors']) ?></td>
              <td style="font-family:var(--mono)"><?= number_format($appts) ?></td>
              <td style="font-family:var(--mono)"><?= number_format($row['unique_patients']) ?></td>
              <td style="font-family:var(--mono);color:var(--gold)"><?= $row['appointments_per_doctor'] ?></td>
              <td>
                <div class="bar-wrap">
                  <div class="bar-track"><div class="bar-fill" style="width:<?= $pct ?>%"></div></div>
                  <span class="bar-val"><?= $pct ?>%</span>
                </div>
              </td>
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
const monoFont  = { family:'DM Mono', size:10 };
const gridColor = '#1c2236';
const tickColor = '#5a6480';

<?php if ($chartLabels): ?>
const labels  = <?= json_encode($chartLabels) ?>;
const appts   = <?= json_encode($chartAppts) ?>;
const doctors = <?= json_encode($chartDoctors) ?>;
const pats    = <?= json_encode($chartPats) ?>;

new Chart(document.getElementById('chartAppts'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      data: appts,
      backgroundColor: 'rgba(245,197,66,0.20)',
      borderColor: 'rgba(245,197,66,0.85)',
      borderWidth: 1, borderRadius: 5
    }]
  },
  options: {
    indexAxis: 'y',
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: {
      x: { ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor}, beginAtZero: true },
      y: { ticks:{color:tickColor,font:{family:'DM Mono',size:9}}, grid:{color:'transparent'} }
    }
  }
});

new Chart(document.getElementById('chartDoctorPat'), {
  type: 'bar',
  data: {
    labels,
    datasets: [
      {
        label: 'Doctors',
        data: doctors,
        backgroundColor: 'rgba(245,197,66,0.25)',
        borderColor: 'rgba(245,197,66,0.85)',
        borderWidth: 1, borderRadius: 4
      },
      {
        label: 'Unique Patients',
        data: pats,
        backgroundColor: 'rgba(77,159,255,0.20)',
        borderColor: 'rgba(77,159,255,0.80)',
        borderWidth: 1, borderRadius: 4
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { labels: { color: tickColor, font: { family:'DM Mono', size:11 }, boxWidth: 12 } } },
    scales: {
      x: { ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor} },
      y: { ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor}, beginAtZero: true }
    }
  }
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
