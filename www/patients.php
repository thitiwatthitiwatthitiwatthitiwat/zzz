<?php
require_once 'config.php';
$pageId = 'patients';

$db = getDB();
try {
    $stmt = $db->query("SELECT * FROM vw_patient_profile LIMIT 1000");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

// --- Build chart data ---
$genderCounts = [];
$ageBuckets   = ['0-17'=>0,'18-34'=>0,'35-54'=>0,'55-74'=>0,'75+'=>0];

foreach ($rows as $r) {
    // gender
    foreach (['gender','sex'] as $gc) {
        if (isset($r[$gc]) && $r[$gc]) {
            $g = ucfirst(strtolower($r[$gc]));
            $genderCounts[$g] = ($genderCounts[$g] ?? 0) + 1;
            break;
        }
    }
    // age
    foreach (['age','patient_age'] as $ac) {
        if (isset($r[$ac]) && is_numeric($r[$ac])) {
            $age = (int)$r[$ac];
            if ($age < 18)      $ageBuckets['0-17']++;
            elseif ($age < 35)  $ageBuckets['18-34']++;
            elseif ($age < 55)  $ageBuckets['35-54']++;
            elseif ($age < 75)  $ageBuckets['55-74']++;
            else                $ageBuckets['75+']++;
            break;
        }
    }
    // dob fallback
    if (array_sum($ageBuckets) === 0) {
        foreach (['date_of_birth','dob','birthdate'] as $dob) {
            if (isset($r[$dob]) && $r[$dob]) {
                $age = (int)date_diff(date_create($r[$dob]), date_create('today'))->y;
                if ($age < 18)     $ageBuckets['0-17']++;
                elseif ($age < 35) $ageBuckets['18-34']++;
                elseif ($age < 55) $ageBuckets['35-54']++;
                elseif ($age < 75) $ageBuckets['55-74']++;
                else               $ageBuckets['75+']++;
                break;
            }
        }
    }
}

require 'layout.php';
?>

<style>
  :root { --accent-color: var(--purple); }
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
  <div class="topbar" style="--accent-color:var(--purple)">
    <div>
      <div class="page-title">Patient <em>Profile</em></div>
    </div>
    <span class="view-pill" style="color:var(--purple);border-color:rgba(167,139,250,0.25);background:rgba(167,139,250,0.06)">vw_patient_profile</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format(count($rows)) ?> rows</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--purple)">

    <?php if (isset($error)): ?>
      <div class="error-box"><strong>Query Error</strong><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-strip">
      <div class="stat-box">
        <div class="stat-label">Total Patients</div>
        <div class="stat-val accent"><?= number_format(count($rows)) ?></div>
      </div>
      <?php if ($genderCounts): ?>
      <?php foreach (array_slice($genderCounts, 0, 2, true) as $g => $c): ?>
      <div class="stat-box">
        <div class="stat-label"><?= htmlspecialchars($g) ?></div>
        <div class="stat-val accent"><?= number_format($c) ?></div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
      <div class="stat-box">
        <div class="stat-label">Columns</div>
        <div class="stat-val accent"><?= count($cols) ?></div>
      </div>
    </div>

    <!-- Charts -->
    <div class="content-grid">
      <?php if ($genderCounts): ?>
      <div class="chart-card">
        <div class="chart-card-title">Gender Distribution</div>
        <div class="chart-wrap"><canvas id="chartGender"></canvas></div>
      </div>
      <?php endif; ?>
      <?php if (array_sum($ageBuckets) > 0): ?>
      <div class="chart-card">
        <div class="chart-card-title">Age Distribution</div>
        <div class="chart-wrap"><canvas id="chartAge"></canvas></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Table -->
    <?php if (!empty($rows)): ?>
    <div class="table-section">
      <div class="table-topbar">
        <span class="table-topbar-title">All Records</span>
        <input class="search-box" type="text" placeholder="Search table..." oninput="filterTable(this.value)"
               style="border-color:var(--border2);" onfocus="this.style.borderColor='var(--purple)'" onblur="this.style.borderColor='var(--border2)'">
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
const monoFont = { family: 'DM Mono', size: 10 };
const gridColor = '#1c2236';
const tickColor = '#5a6480';

<?php if ($genderCounts): ?>
new Chart(document.getElementById('chartGender'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($genderCounts)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($genderCounts)) ?>,
      backgroundColor: ['rgba(167,139,250,0.75)','rgba(0,229,255,0.65)','rgba(45,212,160,0.65)','rgba(245,197,66,0.65)'],
      borderColor: '#111520', borderWidth: 2
    }]
  },
  options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right', labels:{ color:tickColor, font:{family:'DM Mono',size:11}, boxWidth:12 } } } }
});
<?php endif; ?>

<?php if (array_sum($ageBuckets) > 0): ?>
new Chart(document.getElementById('chartAge'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($ageBuckets)) ?>,
    datasets: [{
      label: 'Patients',
      data: <?= json_encode(array_values($ageBuckets)) ?>,
      backgroundColor: 'rgba(167,139,250,0.18)',
      borderColor: 'rgba(167,139,250,0.75)',
      borderWidth: 1, borderRadius: 5
    }]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    plugins:{ legend:{ labels:{ color:tickColor, font:{family:'DM Mono',size:11} } } },
    scales:{
      x:{ ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor} },
      y:{ ticks:{color:tickColor,font:monoFont}, grid:{color:gridColor} }
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