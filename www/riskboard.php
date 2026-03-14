<?php
require_once 'config.php';
$pageId = 'riskboard';

$db = getDB();
try {
    $stmt = $db->query("SELECT patient_id, patient_name, gender, age, highest_risk_score, highest_risk_level, total_analyses, latest_analysis FROM vw_ai_risk_leaderboard LIMIT 1000");
    $rows = $stmt->fetchAll();
    $cols = !empty($rows) ? array_keys($rows[0]) : [];
} catch (PDOException $e) {
    $rows = []; $cols = []; $error = $e->getMessage();
}

// --- Stats ---
$totalPatients  = count($rows);
$avgRisk        = $totalPatients ? round(array_sum(array_column($rows, 'highest_risk_score')) / $totalPatients, 2) : 0;
$totalAnalyses  = array_sum(array_column($rows, 'total_analyses'));

// Risk level distribution
$levelCounts = [];
foreach ($rows as $r) {
    $l = $r['highest_risk_level'] ?? 'Unknown';
    $levelCounts[$l] = ($levelCounts[$l] ?? 0) + 1;
}

// Top 10 by risk score for chart
$top10       = array_slice($rows, 0, 10);
$top10Names  = array_column($top10, 'patient_name');
$top10Scores = array_map('floatval', array_column($top10, 'highest_risk_score'));

require 'layout.php';
?>

<style>
  :root { --accent-color: var(--red); }
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

  /* Risk level badges */
  .risk-badge {
    display: inline-block;
    font-family: var(--mono);
    font-size: 10px;
    font-weight: 600;
    padding: 3px 8px;
    border-radius: 6px;
    text-transform: uppercase;
    letter-spacing: .5px;
  }
  .risk-critical { background: rgba(255,107,122,0.15); color: #ff6b7a; border: 1px solid rgba(255,107,122,0.3); }
  .risk-high     { background: rgba(245,197,66,0.15);  color: #f5c542; border: 1px solid rgba(245,197,66,0.3); }
  .risk-medium   { background: rgba(0,229,255,0.10);   color: #00e5ff; border: 1px solid rgba(0,229,255,0.25); }
  .risk-low      { background: rgba(45,212,160,0.10);  color: #2dd4a0; border: 1px solid rgba(45,212,160,0.25); }
  .risk-unknown  { background: rgba(90,100,128,0.15);  color: #5a6480; border: 1px solid rgba(90,100,128,0.3); }

  /* Rank column */
  .rank-cell { color: var(--muted); font-family: var(--mono); font-size: 11px; }
  .rank-cell.top1 { color: #f5c542; font-weight: 700; }
  .rank-cell.top2 { color: #b0bec5; font-weight: 700; }
  .rank-cell.top3 { color: #cd7f32; font-weight: 700; }

  /* Score bar */
  .score-wrap { display: flex; align-items: center; gap: 8px; }
  .score-bar-track { flex: 1; height: 4px; background: var(--border2); border-radius: 2px; min-width: 60px; }
  .score-bar-fill  { height: 100%; border-radius: 2px; background: linear-gradient(90deg, rgba(255,107,122,0.5), rgba(255,107,122,1)); }
  .score-val { font-family: var(--mono); font-size: 11px; color: var(--red); width: 36px; text-align: right; flex-shrink: 0; }
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
  <div class="topbar" style="--accent-color:var(--red)">
    <div><div class="page-title">AI Risk <em>Leaderboard</em></div></div>
    <span class="view-pill" style="color:var(--red);border-color:rgba(255,107,122,0.25);background:rgba(255,107,122,0.06)">vw_ai_risk_leaderboard</span>
    <div class="topbar-right">
      <span class="row-stat"><?= number_format($totalPatients) ?> patients</span>
    </div>
  </div>

  <div class="page-content" style="--accent-color:var(--red)">

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
        <div class="stat-label">Avg Risk Score</div>
        <div class="stat-val accent"><?= $avgRisk ?></div>
      </div>
      <div class="stat-box">
        <div class="stat-label">Total Analyses</div>
        <div class="stat-val accent"><?= number_format($totalAnalyses) ?></div>
      </div>
      <?php if ($levelCounts): ?>
      <?php $topLevel = array_key_first($levelCounts); ?>
      <div class="stat-box">
        <div class="stat-label">Most Common Level</div>
        <div class="stat-val" style="font-size:18px;padding-top:4px"><?= htmlspecialchars($topLevel) ?></div>
      </div>
      <?php endif; ?>
    </div>



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
              <th>Gender</th>
              <th>Age</th>
              <th>Risk Score</th>
              <th>Risk Level</th>
              <th>Analyses</th>
              <th>Latest Analysis</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $i => $row):
              $rank  = $i + 1;
              $rankClass = $rank === 1 ? 'top1' : ($rank === 2 ? 'top2' : ($rank === 3 ? 'top3' : ''));
              $level = strtolower($row['highest_risk_level'] ?? 'unknown');
              $levelClass = match(true) {
                str_contains($level, 'critical') => 'risk-critical',
                str_contains($level, 'high')     => 'risk-high',
                str_contains($level, 'medium')   => 'risk-medium',
                str_contains($level, 'low')      => 'risk-low',
                default                          => 'risk-unknown',
              };
              $score   = (float)($row['highest_risk_score'] ?? 0);
              $maxScore = 100;
              $pct     = min(100, round($score / $maxScore * 100));
            ?>
            <tr>
              <td><span class="rank-cell <?= $rankClass ?>"><?= $rank === 1 ? '1' : ($rank === 2 ? '2' : ($rank === 3 ? '3' : $rank)) ?></span></td>
              <td style="font-weight:600"><?= htmlspecialchars($row['patient_name']) ?></td>
              <td><?= htmlspecialchars($row['gender'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['age'] ?? '—') ?></td>
              <td>
                <div class="score-wrap">
                  <div class="score-bar-track"><div class="score-bar-fill" style="width:<?= $pct ?>%"></div></div>
                  <span class="score-val"><?= $score ?></span>
                </div>
              </td>
              <td><span class="risk-badge <?= $levelClass ?>"><?= htmlspecialchars($row['highest_risk_level'] ?? '—') ?></span></td>
              <td><?= htmlspecialchars($row['total_analyses'] ?? '—') ?></td>
              <td><?= htmlspecialchars($row['latest_analysis'] ?? '—') ?></td>
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



function filterTable(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#main-table tbody tr').forEach(tr => {
    tr.style.display = tr.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
</script>
</html>