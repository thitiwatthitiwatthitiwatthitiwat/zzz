<?php
require_once 'config.php';
$pageId = 'testresult';

$db = getDB();
try {
    $stmt = $db->query("SELECT test_type, total_tests, unique_records, tests_with_results, tests_missing_results FROM vw_test_result_summary");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

$totalTests      = array_sum(array_column($rows, 'total_tests'));
$totalWithResult = array_sum(array_column($rows, 'tests_with_results'));
$totalMissing    = array_sum(array_column($rows, 'tests_missing_results'));
$testTypes       = count($rows);
$completionRate  = $totalTests > 0 ? round($totalWithResult / $totalTests * 100, 1) : 0;

$chartLabels     = array_column($rows, 'test_type');
$chartTotals     = array_map('intval', array_column($rows, 'total_tests'));
$chartWithResult = array_map('intval', array_column($rows, 'tests_with_results'));
$chartMissing    = array_map('intval', array_column($rows, 'tests_missing_results'));

require 'layout.php';
?>

<style>
  :root { --accent-color: var(--green); }
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

  /* Completion bar */
  .comp-wrap { display: flex; align-items: center; gap: 8px; }
  .comp-track { flex: 1; height: 5px; background: var(--border2); border-radius: 3px; min-width: 80px; }
  .comp-fill  { height: 100%; border-radius: 3px; background: linear-gradient(90deg, rgba(45,212,160,0.5), rgba(45,212,160,1)); }
  .comp-val   { font-family: var(--mono); font-size: 11px; color: var(--green); width: 38px; text-align: right; flex-shrink: 0; }
  .missing-val { color: var(--red); }
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
    <div><div class="page-title">Test Result <em>Summary</em></div></div>
    <span class="view-pill" style="color:var(--green);border-color:rgba(45,212,160,0.25);background:rgba(45,212,160,0.06)">vw_test_result_summary</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format($totalTests) ?> tests</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--green)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip" style="margin-bottom:16px">
      <div class="stat-box">
        <div class="stat-label">Test Types</div>
        <div class="stat-val accent"><?= $testTypes ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Total Tests</div>
        <div class="stat-val accent"><?= number_format($totalTests) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">With Results</div>
        <div class="stat-val accent"><?= number_format($totalWithResult) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Completion Rate</div>
        <div class="stat-val accent"><?= $completionRate ?>%</div>
      </div>
    </div>

    <!-- Charts -->
    <?php if ($chartLabels): ?>
    <div class="content-grid" style="margin-bottom:16px">
      <div class="chart-card">
        <div class="chart-card-title">Total Tests by Type</div>
        <div class="chart-wrap"><canvas id="chartTotal"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Results vs Missing by Type</div>
        <div class="chart-wrap"><canvas id="chartComp"></canvas></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <?php if (!empty($rows)): ?>
    <div class="table-section">
      <div class="table-topbar">
        <span class="table-topbar-title">All Records</span>
        <input class="search-box" type="text" placeholder="Search..." oninput="filterTable(this.value)">
      </div>
      <div class="scroll-x">
        <table id="main-table">
          <thead>
            <tr>
              <th onclick="sortTable(0)">Test Type</th>
              <th onclick="sortTable(1)">Total Tests</th>
              <th onclick="sortTable(2)">Unique Records</th>
              <th onclick="sortTable(3)">With Results</th>
              <th onclick="sortTable(4)">Missing Results</th>
              <th>Completion</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row):
              $total = (int)$row['total_tests'];
              $with  = (int)$row['tests_with_results'];
              $pct   = $total > 0 ? round($with / $total * 100) : 0;
            ?>
            <tr>
              <td style="font-weight:600"><?= htmlspecialchars($row['test_type'] ?? '—') ?></td>
              <td style="font-family:var(--mono)"><?= number_format($total) ?></td>
              <td style="font-family:var(--mono)"><?= number_format($row['unique_records']) ?></td>
              <td style="font-family:var(--mono);color:var(--green)"><?= number_format($with) ?></td>
              <td style="font-family:var(--mono)" class="<?= (int)$row['tests_missing_results'] > 0 ? 'missing-val' : '' ?>"><?= number_format($row['tests_missing_results']) ?></td>
              <td>
                <div class="comp-wrap">
                  <div class="comp-track"><div class="comp-fill" style="width:<?= $pct ?>%"></div></div>
                  <span class="comp-val"><?= $pct ?>%</span>
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
const labels       = <?= json_encode($chartLabels) ?>;
const totals       = <?= json_encode($chartTotals) ?>;
const withResult   = <?= json_encode($chartWithResult) ?>;
const missing      = <?= json_encode($chartMissing) ?>;

new Chart(document.getElementById('chartTotal'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      data: totals,
      backgroundColor: 'rgba(45,212,160,0.18)',
      borderColor: 'rgba(45,212,160,0.80)',
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

new Chart(document.getElementById('chartComp'), {
  type: 'bar',
  data: {
    labels,
    datasets: [
      {
        label: 'With Results',
        data: withResult,
        backgroundColor: 'rgba(45,212,160,0.25)',
        borderColor: 'rgba(45,212,160,0.80)',
        borderWidth: 1, borderRadius: 4
      },
      {
        label: 'Missing',
        data: missing,
        backgroundColor: 'rgba(255,107,122,0.20)',
        borderColor: 'rgba(255,107,122,0.75)',
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
