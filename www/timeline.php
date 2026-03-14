<?php
require_once 'config.php';
$pageId = 'timeline';

$db = getDB();
try {
    $stmt = $db->query("SELECT record_id, visit_date, patient_name, doctor_name, symptoms, notes, diagnosis_name, severity_level, test_type, result_value, test_date, prescription_date FROM vw_medical_timeline ORDER BY visit_date DESC LIMIT 1000");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

// --- Stats ---
$totalRecords   = count($rows);
$uniquePatients = count(array_unique(array_column($rows, 'patient_name')));
$uniqueDoctors  = count(array_filter(array_unique(array_column($rows, 'doctor_name'))));

// Visits by month
$byMonth = [];
foreach ($rows as $r) {
    if (!empty($r['visit_date'])) {
        $m = date('M Y', strtotime($r['visit_date']));
        $byMonth[$m] = ($byMonth[$m] ?? 0) + 1;
    }
}
ksort($byMonth);

// Severity breakdown
$severityCounts = [];
foreach ($rows as $r) {
    $s = $r['severity_level'] ?? 'Unknown';
    $severityCounts[$s] = ($severityCounts[$s] ?? 0) + 1;
}
arsort($severityCounts);

require 'layout.php';
?>

<style>
  :root { --accent-color: var(--purple); }
  .main-wrap { overflow: hidden; }
  .page-content {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 73px);
    overflow-y: auto;
    padding: 20px 36px !important;
  }
  .stats-strip  { flex-shrink: 0; }
  .content-grid { flex-shrink: 0; }
  .table-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 300px; }
  .scroll-x { flex: 1; overflow: auto; min-height: 0; max-height: none !important; }

  /* Severity badges */
  .sev-badge {
    display: inline-block;
    font-family: var(--mono);
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: .5px;
  }
  .sev-critical { background: rgba(255,107,122,0.15); color: #ff6b7a; border: 1px solid rgba(255,107,122,0.3); }
  .sev-severe   { background: rgba(255,107,122,0.15); color: #ff6b7a; border: 1px solid rgba(255,107,122,0.3); }
  .sev-high     { background: rgba(245,197,66,0.15);  color: #f5c542; border: 1px solid rgba(245,197,66,0.3); }
  .sev-moderate { background: rgba(0,229,255,0.10);   color: #00e5ff; border: 1px solid rgba(0,229,255,0.25); }
  .sev-mild     { background: rgba(45,212,160,0.10);  color: #2dd4a0; border: 1px solid rgba(45,212,160,0.25); }
  .sev-low      { background: rgba(45,212,160,0.10);  color: #2dd4a0; border: 1px solid rgba(45,212,160,0.25); }
  .sev-unknown  { background: rgba(90,100,128,0.15);  color: #5a6480; border: 1px solid rgba(90,100,128,0.3); }

  /* Truncate long text cells */
  .truncate { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
  <div class="topbar" style="--accent-color:var(--purple)">
    <div><div class="page-title">Medical <em>Timeline</em></div></div>
    <span class="view-pill" style="color:var(--purple);border-color:rgba(167,139,250,0.25);background:rgba(167,139,250,0.06)">vw_medical_timeline</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format($totalRecords) ?> records</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--purple)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip" style="margin-bottom:16px">
      <div class="stat-box">
        <div class="stat-label">Total Records</div>
        <div class="stat-val accent"><?= number_format($totalRecords) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Unique Patients</div>
        <div class="stat-val accent"><?= number_format($uniquePatients) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Unique Doctors</div>
        <div class="stat-val accent"><?= number_format($uniqueDoctors) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Severity Types</div>
        <div class="stat-val accent"><?= count($severityCounts) ?></div>
      </div>
    </div>

    <!-- Charts -->
    <?php if ($byMonth): ?>
    <div style="margin-bottom:16px">
      <div class="chart-card">
        <div class="chart-card-title">Visits by Month</div>
        <div class="chart-wrap"><canvas id="chartMonth"></canvas></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <?php if (!empty($rows)): ?>
    <div class="table-section">
      <div class="table-topbar">
        <span class="table-topbar-title">All Records</span>
        <input class="search-box" type="text" placeholder="Search records..." oninput="filterTable(this.value)">
      </div>
      <div class="scroll-x">
        <table id="main-table">
          <thead>
            <tr>
              <th onclick="sortTable(0)">Visit Date</th>
              <th onclick="sortTable(1)">Patient</th>
              <th onclick="sortTable(2)">Doctor</th>
              <th onclick="sortTable(3)">Diagnosis</th>
              <th onclick="sortTable(4)">Severity</th>
              <th onclick="sortTable(5)">Symptoms</th>
              <th onclick="sortTable(6)">Test Type</th>
              <th onclick="sortTable(7)">Result</th>
              <th onclick="sortTable(8)">Test Date</th>
              <th onclick="sortTable(9)">Rx Date</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row):
              $sev = strtolower($row['severity_level'] ?? 'unknown');
              $sevClass = match(true) {
                str_contains($sev, 'critical') => 'sev-critical',
                str_contains($sev, 'severe')   => 'sev-severe',
                str_contains($sev, 'high')     => 'sev-high',
                str_contains($sev, 'moderate') => 'sev-moderate',
                str_contains($sev, 'mild')     => 'sev-mild',
                str_contains($sev, 'low')      => 'sev-low',
                default                        => 'sev-unknown',
              };
            ?>
            <tr>
              <td><?= htmlspecialchars($row['visit_date'] ?? '—') ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($row['patient_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['doctor_name'] ?? '—') ?></td>
              <td class="truncate" title="<?= htmlspecialchars($row['diagnosis_name'] ?? '') ?>"><?= htmlspecialchars($row['diagnosis_name'] ?? '—') ?></td>
              <td><?php if ($row['severity_level']): ?><span class="sev-badge <?= $sevClass ?>"><?= htmlspecialchars($row['severity_level']) ?></span><?php else: ?>—<?php endif; ?></td>
              <td class="truncate" title="<?= htmlspecialchars($row['symptoms'] ?? '') ?>"><?= htmlspecialchars($row['symptoms'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['test_type'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['result_value'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['test_date'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['prescription_date'] ?? '—') ?></td>
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

<?php if ($byMonth): ?>
new Chart(document.getElementById('chartMonth'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($byMonth)) ?>,
    datasets: [{
      label: 'Visits',
      data: <?= json_encode(array_values($byMonth)) ?>,
      backgroundColor: 'rgba(167,139,250,0.18)',
      borderColor: 'rgba(167,139,250,0.80)',
      borderWidth: 1, borderRadius: 4
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
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