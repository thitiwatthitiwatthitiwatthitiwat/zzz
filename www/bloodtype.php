<?php
require_once 'config.php';
$pageId = 'bloodtype';

$db = getDB();
try {
    $stmt = $db->query("SELECT blood_type, total_patients, percentage FROM vw_blood_type_distribution");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

// --- Chart data (exact columns) ---
$bloodLabels = [];
$bloodCounts = [];
$bloodPct    = [];

foreach ($rows as $r) {
    $bloodLabels[] = $r['blood_type'];
    $bloodCounts[] = (int)$r['total_patients'];
    $bloodPct[]    = (float)$r['percentage'];
}

$totalPatients = array_sum($bloodCounts);
$maxIdx = $bloodCounts ? array_search(max($bloodCounts), $bloodCounts) : 0;
$minIdx = $bloodCounts ? array_search(min($bloodCounts), $bloodCounts) : 0;

require 'layout.php';
?>

<style>:root { --accent-color: var(--red); }</style>

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
  <div class="topbar" style="--accent-color:var(--red)">
    <div><div class="page-title">Blood Type <em>Distribution</em></div></div>
    <span class="view-pill" style="color:var(--red);border-color:rgba(255,107,122,0.25);background:rgba(255,107,122,0.06)">vw_blood_type_distribution</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format($totalPatients) ?> patients</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--red)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip">
      <div class="stat-box">
        <div class="stat-label">Total Patients</div>
        <div class="stat-val accent"><?= number_format($totalPatients) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Blood Types</div>
        <div class="stat-val accent"><?= count($bloodLabels) ?></div>
      </div>
      <?php if ($bloodLabels): ?>
      <div class="stat-box">
        <div class="stat-label">Most Common</div>
        <div class="stat-val accent"><?= htmlspecialchars($bloodLabels[$maxIdx]) ?></div>
        <div style="font-family:var(--mono);font-size:10px;color:var(--muted);margin-top:4px"><?= $bloodPct[$maxIdx] ?>%</div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Least Common</div>
        <div class="stat-val" style="font-size:22px"><?= htmlspecialchars($bloodLabels[$minIdx]) ?></div>
        <div style="font-family:var(--mono);font-size:10px;color:var(--muted);margin-top:4px"><?= $bloodPct[$minIdx] ?>%</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Charts -->
    <?php if ($bloodLabels): ?>
    <div class="content-grid">
      <div class="chart-card">
        <div class="chart-card-title">Patient Count by Blood Type</div>
        <div class="chart-wrap"><canvas id="chartBar"></canvas></div>
      </div>
      <div class="chart-card">
        <div class="chart-card-title">Percentage Distribution</div>
        <div class="chart-wrap"><canvas id="chartDoughnut"></canvas></div>
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
    <?php endif; ?>
  </div>
</div>

<script>
const monoFont  = { family:'DM Mono', size:10 };
const gridColor = '#1c2236';
const tickColor = '#5a6480';

const bloodColors = [
  'rgba(255,107,122,0.80)',
  'rgba(255,150,100,0.75)',
  'rgba(245,197,66,0.75)',
  'rgba(45,212,160,0.70)',
  'rgba(0,229,255,0.70)',
  'rgba(77,159,255,0.70)',
  'rgba(167,139,250,0.70)',
  'rgba(255,107,180,0.70)',
];

<?php if ($bloodLabels): ?>
const labels     = <?= json_encode($bloodLabels) ?>;
const counts     = <?= json_encode($bloodCounts) ?>;
const percentages = <?= json_encode($bloodPct) ?>;

// Bar chart — patient count
new Chart(document.getElementById('chartBar'), {
  type: 'bar',
  data: {
    labels,
    datasets: [{
      label: 'Patients',
      data: counts,
      backgroundColor: bloodColors,
      borderColor: bloodColors.map(c => c.replace(/[\d.]+\)$/, '1)')),
      borderWidth: 1,
      borderRadius: 6
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          label: ctx => {
            const pct = percentages[ctx.dataIndex];
            return ` ${ctx.parsed.y.toLocaleString()} patients (${pct}%)`;
          }
        }
      }
    },
    scales: {
      x: { ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor} },
      y: { ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor} }
    }
  }
});

// Doughnut chart — percentage
new Chart(document.getElementById('chartDoughnut'), {
  type: 'doughnut',
  data: {
    labels,
    datasets: [{
      data: percentages,
      backgroundColor: bloodColors,
      borderColor: '#111520',
      borderWidth: 2
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'right',
        labels: { color: tickColor, font:{family:'DM Mono',size:11}, boxWidth: 12,
          generateLabels: chart => chart.data.labels.map((label, i) => ({
            text: `${label}  ${percentages[i]}%`,
            fillStyle: bloodColors[i],
            strokeStyle: '#111520',
            lineWidth: 2,
            index: i
          }))
        }
      },
      tooltip: {
        callbacks: {
          label: ctx => ` ${ctx.label}: ${ctx.parsed}% (${counts[ctx.dataIndex].toLocaleString()} patients)`
        }
      }
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