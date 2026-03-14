<?php
require_once 'config.php';
$pageId = 'frequent';

$db = getDB();
try {
    $stmt = $db->query("SELECT patient_id, patient_name, phone, visit_count, first_visit, latest_visit FROM vw_frequent_patients LIMIT 1000");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

// --- Stats ---
$totalPatients = count($rows);
$totalVisits   = array_sum(array_column($rows, 'visit_count'));
$avgVisits     = $totalPatients ? round($totalVisits / $totalPatients, 1) : 0;
$maxVisits     = $totalPatients ? max(array_column($rows, 'visit_count')) : 0;

// Top 10 for chart
$top10      = array_slice($rows, 0, 10);
$top10Names = array_column($top10, 'patient_name');
$top10Visits = array_map('intval', array_column($top10, 'visit_count'));

// Visit count buckets for distribution chart
$buckets = ['2–3' => 0, '4–6' => 0, '7–10' => 0, '11–20' => 0, '21+' => 0];
foreach ($rows as $r) {
    $v = (int)$r['visit_count'];
    if ($v <= 3)       $buckets['2–3']++;
    elseif ($v <= 6)   $buckets['4–6']++;
    elseif ($v <= 10)  $buckets['7–10']++;
    elseif ($v <= 20)  $buckets['11–20']++;
    else               $buckets['21+']++;
}

require 'layout.php';
?>

<style>
  :root { --accent-color: var(--blue); }
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

  /* Visit count bar */
  .visit-wrap { display: flex; align-items: center; gap: 8px; }
  .visit-bar-track { flex: 1; height: 4px; background: var(--border2); border-radius: 2px; min-width: 60px; }
  .visit-bar-fill  { height: 100%; border-radius: 2px; background: linear-gradient(90deg, rgba(77,159,255,0.5), rgba(77,159,255,1)); }
  .visit-val { font-family: var(--mono); font-size: 11px; color: var(--blue); width: 28px; text-align: right; flex-shrink: 0; }

  /* Rank */
  .rank-cell { color: var(--muted); font-family: var(--mono); font-size: 11px; }
  .rank-cell.top1 { color: #f5c542; font-weight: 700; }
  .rank-cell.top2 { color: #b0bec5; font-weight: 700; }
  .rank-cell.top3 { color: #cd7f32; font-weight: 700; }
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
  <div class="topbar" style="--accent-color:var(--blue)">
    <div><div class="page-title">Frequent <em>Patients</em></div></div>
    <span class="view-pill" style="color:var(--blue);border-color:rgba(77,159,255,0.25);background:rgba(77,159,255,0.06)">vw_frequent_patients</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format($totalPatients) ?> patients</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--blue)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip" style="margin-bottom:16px">
      <div class="stat-box">
        <div class="stat-label">Frequent Patients</div>
        <div class="stat-val accent"><?= number_format($totalPatients) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Avg Visits</div>
        <div class="stat-val accent"><?= $avgVisits ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Most Visits</div>
        <div class="stat-val accent"><?= $maxVisits ?></div>
      </div>
    </div>

    <!-- Charts -->
    <?php if ($top10Names): ?>
    <div style="margin-bottom:16px">
      <div class="chart-card">
        <div class="chart-card-title">Top 10 Most Frequent Patients</div>
        <div class="chart-wrap"><canvas id="chartTop10"></canvas></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <?php if (!empty($rows)): ?>
    <div class="table-section">
      <div class="table-topbar">
        <span class="table-topbar-title">All Records</span>
        <input class="search-box" type="text" placeholder="Search patients..." oninput="filterTable(this.value)">
      </div>
      <div class="scroll-x">
        <table id="main-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Patient Name</th>
              <th>Phone</th>
              <th>Visit Count</th>
              <th>First Visit</th>
              <th>Latest Visit</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $row):
              $rank = $i + 1;
              $rankClass = $rank === 1 ? 'top1' : ($rank === 2 ? 'top2' : ($rank === 3 ? 'top3' : ''));
              $visits = (int)$row['visit_count'];
              $pct = $maxVisits > 0 ? round($visits / $maxVisits * 100) : 0;
            ?>
            <tr>
              <td><span class="rank-cell <?= $rankClass ?>"><?= $rank ?></span></td>
              <td style="font-weight:600"><?= htmlspecialchars($row['patient_name']) ?></td>
              <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
              <td style="font-family:var(--mono);font-size:12px"><?= $visits ?></td>
              <td><?= htmlspecialchars($row['first_visit'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['latest_visit'] ?? '—') ?></td>
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

<?php if ($top10Names): ?>
// Top 10 horizontal bar
new Chart(document.getElementById('chartTop10'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($top10Names) ?>,
    datasets: [{
      data: <?= json_encode($top10Visits) ?>,
      backgroundColor: 'rgba(77,159,255,0.18)',
      borderColor: 'rgba(77,159,255,0.80)',
      borderWidth: 1, borderRadius: 4
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