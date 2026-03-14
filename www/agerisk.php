<?php
require_once 'config.php';
$pageId = 'agerisk';

$db = getDB();
try {
    $stmt = $db->query("SELECT age_group, patient_count, avg_risk_score, max_risk_score FROM vw_age_group_risk");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

// Chart data — view is already ordered by avg_risk_score desc
// Re-order by age group for display
$ageOrder = ['Under 18', '18–35', '36–55', '56–70', 'Over 70'];
usort($rows, fn($a, $b) => array_search($a['age_group'], $ageOrder) - array_search($b['age_group'], $ageOrder));

$ageGroups    = array_column($rows, 'age_group');
$patientCounts = array_column($rows, 'patient_count');
$avgRisk      = array_column($rows, 'avg_risk_score');
$maxRisk      = array_column($rows, 'max_risk_score');

$totalPatients  = array_sum($patientCounts);
$overallAvgRisk = count($avgRisk) ? round(array_sum($avgRisk) / count($avgRisk), 2) : 0;
$highRiskGroup  = $rows ? $rows[array_search(max($avgRisk), $avgRisk)]['age_group'] : '—';

require 'layout.php';
?>

<style>
  :root { --accent-color: var(--gold); }
  .main-wrap { overflow: hidden; }
  .page-content {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 73px);
    overflow: hidden;
    padding: 20px 36px !important;
  }
  .stats-strip { flex-shrink: 0; }
  .content-grid { flex-shrink: 0; }
  .table-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
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
  <div class="topbar" style="--accent-color:var(--gold)">
    <div><div class="page-title">Age Group <em>Risk</em></div></div>
    <span class="view-pill" style="color:var(--gold);border-color:rgba(245,197,66,0.25);background:rgba(245,197,66,0.06)">vw_age_group_risk</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format($totalPatients) ?> patients</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--gold)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip" style="margin-bottom:16px">
      <div class="stat-box">
        <div class="stat-label">Total Patients</div>
        <div class="stat-val accent"><?= number_format($totalPatients) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Age Groups</div>
        <div class="stat-val accent"><?= count($rows) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Overall Avg Risk</div>
        <div class="stat-val accent"><?= $overallAvgRisk ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Highest Risk Group</div>
        <div class="stat-val" style="font-size:18px;padding-top:4px"><?= htmlspecialchars($highRiskGroup) ?></div>
      </div>
    </div>

    <!-- Charts -->
    <?php if ($ageGroups): ?>
    <div style="margin-bottom:16px">
      <div class="chart-card">
        <div class="chart-card-title">Avg Risk Score by Age Group</div>
        <div class="chart-wrap"><canvas id="chartRisk"></canvas></div>
      </div>
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

<?php if ($ageGroups): ?>
const ageGroups    = <?= json_encode($ageGroups) ?>;
const avgRisk      = <?= json_encode(array_map('floatval', $avgRisk)) ?>;
const maxRisk      = <?= json_encode(array_map('floatval', $maxRisk)) ?>;
const patientCounts = <?= json_encode(array_map('intval', $patientCounts)) ?>;

// Avg risk score — grouped bar with avg + max
new Chart(document.getElementById('chartRisk'), {
  type: 'bar',
  data: {
    labels: ageGroups,
    datasets: [
      {
        label: 'Avg Risk Score',
        data: avgRisk,
        backgroundColor: 'rgba(245,197,66,0.25)',
        borderColor: 'rgba(245,197,66,0.85)',
        borderWidth: 1, borderRadius: 5
      },
      {
        label: 'Max Risk Score',
        data: maxRisk,
        backgroundColor: 'rgba(255,107,122,0.15)',
        borderColor: 'rgba(255,107,122,0.70)',
        borderWidth: 1, borderRadius: 5
      }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { labels: { color: tickColor, font: { family:'DM Mono', size:11 }, boxWidth:12 } } },
    scales: {
      x: { ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor} },
      y: { ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor}, beginAtZero: true }
    }
  }
});

// Patient count bar
new Chart(document.getElementById('chartPatients'), {
  type: 'bar',
  data: {
    labels: ageGroups,
    datasets: [{
      label: 'Patients',
      data: patientCounts,
      backgroundColor: 'rgba(245,197,66,0.18)',
      borderColor: 'rgba(245,197,66,0.75)',
      borderWidth: 1, borderRadius: 5
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