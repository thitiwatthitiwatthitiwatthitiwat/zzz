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

// --- Chart data using exact columns ---
$genderCounts = [];
$ageBuckets   = ['0-17'=>0,'18-34'=>0,'35-54'=>0,'55-74'=>0,'75+'=>0];
$bmiCounts    = ['Underweight'=>0,'Normal'=>0,'Overweight'=>0,'Obese'=>0,'Unknown'=>0];

foreach ($rows as $r) {
    // gender
    if (!empty($r['gender'])) {
        $g = ucfirst(strtolower($r['gender']));
        $genderCounts[$g] = ($genderCounts[$g] ?? 0) + 1;
    }
    // age
    if (isset($r['age']) && is_numeric($r['age'])) {
        $age = (int)$r['age'];
        if ($age < 18)      $ageBuckets['0-17']++;
        elseif ($age < 35)  $ageBuckets['18-34']++;
        elseif ($age < 55)  $ageBuckets['35-54']++;
        elseif ($age < 75)  $ageBuckets['55-74']++;
        else                $ageBuckets['75+']++;
    }
    // bmi_category
    if (!empty($r['bmi_category'])) {
        $cat = $r['bmi_category'];
        if (isset($bmiCounts[$cat])) $bmiCounts[$cat]++;
        else $bmiCounts['Unknown']++;
    }
}

// Stats
$totalPatients = count($rows);
$avgAge        = $totalPatients ? round(array_sum(array_column($rows, 'age')) / $totalPatients, 1) : 0;
$avgBmi        = $totalPatients ? round(array_sum(array_filter(array_column($rows, 'bmi'))) / $totalPatients, 1) : 0;
$totalVisits   = array_sum(array_column($rows, 'total_visits'));

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

  /* BMI badge */
  .bmi-badge {
    display: inline-block;
    font-family: var(--mono);
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: .5px;
  }
  .bmi-underweight { background: rgba(77,159,255,0.15);  color: #4d9fff; border: 1px solid rgba(77,159,255,0.3); }
  .bmi-normal      { background: rgba(45,212,160,0.15);  color: #2dd4a0; border: 1px solid rgba(45,212,160,0.3); }
  .bmi-overweight  { background: rgba(245,197,66,0.15);  color: #f5c542; border: 1px solid rgba(245,197,66,0.3); }
  .bmi-obese       { background: rgba(255,107,122,0.15); color: #ff6b7a; border: 1px solid rgba(255,107,122,0.3); }
  .bmi-unknown     { background: rgba(90,100,128,0.15);  color: #5a6480; border: 1px solid rgba(90,100,128,0.3); }
</style>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo" style="cursor:pointer" onclick="location.reload()">
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
    <div><div class="page-title">Patient <em>Profile</em></div></div>
    <span class="view-pill" style="color:var(--purple);border-color:rgba(167,139,250,0.25);background:rgba(167,139,250,0.06)">vw_patient_profile</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format($totalPatients) ?> patients</span>
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
        <div class="stat-val accent"><?= number_format($totalPatients) ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Avg Age</div>
        <div class="stat-val accent"><?= $avgAge ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Avg BMI</div>
        <div class="stat-val accent"><?= $avgBmi ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Total Visits</div>
        <div class="stat-val accent"><?= number_format($totalVisits) ?></div>
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
      <?php if (array_sum(array_diff_key($bmiCounts, ['Unknown'=>0])) > 0): ?>
      <div class="chart-card">
        <div class="chart-card-title">BMI Category</div>
        <div class="chart-wrap"><canvas id="chartBmi"></canvas></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Table -->
    <?php if (!empty($rows)): ?>
    <div class="table-section">
      <div class="table-topbar">
        <span class="table-topbar-title">All Records</span>
        <input class="search-box" type="text" placeholder="Search table..."
               oninput="filterTable(this.value)"
               onfocus="this.style.borderColor='var(--purple)'"
               onblur="this.style.borderColor='var(--border2)'">
      </div>
      <div class="scroll-x">
        <table id="main-table">
          <thead>
            <tr>
              <th onclick="sortTable(0)">ID</th>
              <th onclick="sortTable(1)">Name</th>
              <th onclick="sortTable(2)">Gender</th>
              <th onclick="sortTable(3)">Date of Birth</th>
              <th onclick="sortTable(4)">Age</th>
              <th onclick="sortTable(5)">Blood Type</th>
              <th onclick="sortTable(6)">Phone</th>
              <th onclick="sortTable(7)">Height (cm)</th>
              <th onclick="sortTable(8)">Weight (kg)</th>
              <th onclick="sortTable(9)">BMI</th>
              <th onclick="sortTable(10)">BMI Category</th>
              <th onclick="sortTable(11)">Total Visits</th>
              <th onclick="sortTable(12)">Last Visit</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row):
              $bmiCat = $row['bmi_category'] ?? 'Unknown';
              $bmiClass = match(strtolower($bmiCat)) {
                'underweight' => 'bmi-underweight',
                'normal'      => 'bmi-normal',
                'overweight'  => 'bmi-overweight',
                'obese'       => 'bmi-obese',
                default       => 'bmi-unknown',
              };
            ?>
            <tr>
              <td><?= htmlspecialchars($row['patient_id'] ?? '—') ?></td>
              <td style="font-weight:600"><?= htmlspecialchars($row['patient_name'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['gender'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['date_of_birth'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['age'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['blood_type'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['phone'] ?? '—') ?></td>
              <td><?= ($row['Height']===null||$row['Height']==='') ? '<span class="null-val">—</span>' : htmlspecialchars($row['Height']) ?></td>
              <td><?= ($row['Weight']===null||$row['Weight']==='') ? '<span class="null-val">—</span>' : htmlspecialchars($row['Weight']) ?></td>
              <td><?= ($row['bmi']===null||$row['bmi']==='') ? '<span class="null-val">—</span>' : htmlspecialchars($row['bmi']) ?></td>
              <td><span class="bmi-badge <?= $bmiClass ?>"><?= htmlspecialchars($bmiCat) ?></span></td>
              <td><?= htmlspecialchars($row['total_visits'] ?? '0') ?></td>
              <td><?= ($row['last_visit_date']===null||$row['last_visit_date']==='') ? '<span class="null-val">—</span>' : htmlspecialchars($row['last_visit_date']) ?></td>
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

<?php if ($genderCounts): ?>
new Chart(document.getElementById('chartGender'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($genderCounts)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($genderCounts)) ?>,
      backgroundColor: ['rgba(167,139,250,0.75)','rgba(0,229,255,0.65)','rgba(45,212,160,0.65)'],
      borderColor: '#111520', borderWidth: 2
    }]
  },
  options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right', labels:{ color:tickColor, font:{family:'DM Mono',size:11}, boxWidth:12 } } } }
});
<?php endif; ?>

<?php if (array_sum(array_diff_key($bmiCounts, ['Unknown'=>0])) > 0): ?>
new Chart(document.getElementById('chartBmi'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($bmiCounts)) ?>,
    datasets: [{
      data: <?= json_encode(array_values($bmiCounts)) ?>,
      backgroundColor: [
        'rgba(77,159,255,0.75)',
        'rgba(45,212,160,0.75)',
        'rgba(245,197,66,0.75)',
        'rgba(255,107,122,0.75)',
        'rgba(90,100,128,0.55)',
      ],
      borderColor: '#111520', borderWidth: 2
    }]
  },
  options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right', labels:{ color:tickColor, font:{family:'DM Mono',size:11}, boxWidth:12 } } } }
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
