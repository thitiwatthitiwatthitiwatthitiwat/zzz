<?php
// layout.php — include at the top of every page
// Usage: set $pageId = 'appointments' | 'patients' before including
$nav = [
    'patients'     => ['label' => 'Patient Profile',        'view' => 'vw_patient_profile',        'file' => 'patients.php',     'color' => '#a78bfa'],
    'frequent'     => ['label' => 'Frequent Patients',        'view' => 'vw_frequent_patients',       'file' => 'frequent.php',     'color' => '#4d9fff'],
    'agerisk'      => ['label' => 'Age Group Risk',          'view' => 'vw_age_group_risk',          'file' => 'agerisk.php',      'color' => '#f5c542'],
    'riskboard'    => ['label' => 'AI Risk Leaderboard',      'view' => 'vw_ai_risk_leaderboard',     'file' => 'riskboard.php',    'color' => '#ff6b7a'],
    'bloodtype'    => ['label' => 'Blood Type Distribution','view' => 'vw_blood_type_distribution', 'file' => 'bloodtype.php',    'color' => '#ff6b7a'],
    'doctors'      => ['label' => 'Doctor Profile',         'view' => 'vw_doctor_workload',         'file' => 'doctors.php',      'color' => '#2dd4a0'],
    'timeline'     => ['label' => 'Medical Timeline',          'view' => 'vw_medical_timeline',        'file' => 'timeline.php',     'color' => '#a78bfa'],
    'testresult'   => ['label' => 'Test Result Summary',      'view' => 'vw_test_result_summary',     'file' => 'testresult.php',   'color' => '#2dd4a0'],
    'appointments' => ['label' => 'Appointments Detail',  'view' => 'vw_appointments_detail', 'file' => 'appointments.php', 'color' => '#00e5ff'],
    'depworkload'  => ['label' => 'Department Workload',       'view' => 'vw_department_workload',     'file' => 'depworkload.php',  'color' => '#f5c542'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $nav[$pageId]['label'] ?> — Hospital DB</title>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Instrument+Serif:ital@0;1&family=Outfit:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
:root {
  --bg:         #080a0f;
  --bg2:        #0d1017;
  --surface:    #111520;
  --surface2:   #161b2c;
  --border:     #1c2236;
  --border2:    #252d44;
  --text:       #dde3f0;
  --muted:      #5a6480;
  --muted2:     #3d4560;
  --cyan:       #00e5ff;
  --purple:     #a78bfa;
  --green:      #2dd4a0;
  --gold:       #f5c542;
  --red:        #ff6b7a;
  --blue:       #4d9fff;
  --sidebar-w:  240px;
  --mono:       'DM Mono', monospace;
  --serif:      'Instrument Serif', serif;
  --sans:       'Outfit', sans-serif;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body { height: 100%; }

body {
  background: var(--bg);
  color: var(--text);
  font-family: var(--sans);
  display: flex;
  min-height: 100vh;
  background-image:
    radial-gradient(ellipse 120% 60% at -10% 50%, rgba(0,229,255,0.03) 0%, transparent 55%),
    radial-gradient(ellipse 80% 80% at 110% 80%, rgba(167,139,250,0.03) 0%, transparent 50%);
}

/* ── Sidebar ── */
.sidebar {
  width: var(--sidebar-w);
  min-height: 100vh;
  background: var(--bg2);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 200;
}
.sidebar-logo {
  padding: 28px 24px 24px;
  border-bottom: 1px solid var(--border);
}
.logo-mark {
  font-family: var(--serif);
  font-size: 22px;
  font-style: italic;
  color: var(--text);
  line-height: 1;
  margin-bottom: 2px;
}
.logo-mark em { color: var(--cyan); font-style: normal; }
.logo-sub {
  font-family: var(--mono);
  font-size: 9px;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 2px;
}
.sidebar-section-label {
  padding: 20px 24px 8px;
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 2px;
  color: var(--muted2);
  font-family: var(--mono);
}
.nav-item {
  display: block;
  margin: 2px 12px;
  padding: 10px 14px;
  border-radius: 10px;
  text-decoration: none;
  color: var(--muted);
  font-size: 13px;
  font-weight: 400;
  transition: all .18s;
  position: relative;
}
.nav-item:hover { color: var(--text); background: var(--surface); }
.nav-item.active {
  color: var(--text);
  background: var(--surface2);
  font-weight: 600;
}
.nav-item.active::before {
  content: '';
  position: absolute; left: 0; top: 50%;
  transform: translateY(-50%);
  width: 3px; height: 60%;
  border-radius: 0 3px 3px 0;
  background: var(--accent-color, var(--cyan));
}
.nav-view-tag {
  display: block;
  font-family: var(--mono);
  font-size: 9px;
  color: var(--muted2);
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.nav-item.active .nav-view-tag { color: var(--muted); }

.sidebar-footer {
  margin-top: auto;
  padding: 16px 20px;
  border-top: 1px solid var(--border);
}
.conn-badge {
  font-family: var(--mono);
  font-size: 10px;
  color: var(--green);
  background: rgba(45,212,160,0.08);
  border: 1px solid rgba(45,212,160,0.18);
  padding: 6px 12px;
  border-radius: 8px;
  display: flex; align-items: center; gap: 7px;
}
.conn-dot {
  width: 6px; height: 6px;
  background: var(--green);
  border-radius: 50%;
  animation: blink 2s infinite;
}
@keyframes blink { 0%,100%{opacity:1}50%{opacity:.25} }

/* ── Main content ── */
.main-wrap {
  margin-left: var(--sidebar-w);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

/* ── Topbar ── */
.topbar {
  padding: 20px 36px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: flex-end;
  gap: 16px;
  background: rgba(8,10,15,0.85);
  backdrop-filter: blur(16px);
  position: sticky; top: 0; z-index: 100;
}
.page-title {
  font-family: var(--serif);
  font-size: 26px;
  font-style: italic;
  color: var(--text);
  line-height: 1;
}
.page-title em { font-style: normal; color: var(--accent-color, var(--cyan)); }
.view-pill {
  font-family: var(--mono);
  font-size: 10px;
  padding: 4px 10px;
  border-radius: 20px;
  border: 1px solid;
  margin-bottom: 3px;
}
.topbar-right { margin-left: auto; display: flex; align-items: center; gap: 10px; }
.row-stat {
  font-family: var(--mono);
  font-size: 11px;
  color: var(--muted);
  background: var(--surface);
  border: 1px solid var(--border);
  padding: 5px 12px;
  border-radius: 8px;
}

/* ── Page content ── */
.page-content { padding: 28px 36px; flex: 1; }

/* ── Stats strip ── */
.stats-strip {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 14px;
  margin-bottom: 28px;
}
.stat-box {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 18px 20px;
  position: relative;
  overflow: hidden;
  animation: fadeUp .4s ease both;
}
.stat-box::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg, transparent, var(--accent-color, var(--cyan)), transparent);
  opacity: .3;
}
@keyframes fadeUp {
  from { opacity:0; transform:translateY(12px); }
  to   { opacity:1; transform:none; }
}
.stat-box:nth-child(2) { animation-delay:.05s }
.stat-box:nth-child(3) { animation-delay:.1s }
.stat-box:nth-child(4) { animation-delay:.15s }
.stat-label { font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: var(--muted); font-weight: 600; margin-bottom: 8px; font-family: var(--mono); }
.stat-val { font-size: 26px; font-weight: 700; font-family: var(--mono); color: var(--text); }
.stat-val.accent { color: var(--accent-color, var(--cyan)); }

/* ── Chart + Table layout ── */
.content-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-bottom: 24px;
}
.chart-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 20px 22px;
  animation: fadeUp .5s ease both;
}
.chart-card:nth-child(2) { animation-delay: .08s; }
.chart-card-title {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: var(--muted);
  font-family: var(--mono);
  margin-bottom: 16px;
}
.chart-wrap { position: relative; height: 200px; }

/* ── Data table ── */
.table-section {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  overflow: hidden;
  animation: fadeUp .55s ease both;
}
.table-topbar {
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
}
.table-topbar-title { font-size: 12px; font-weight: 600; color: var(--text); }
.search-box {
  margin-left: auto;
  background: var(--bg2);
  border: 1px solid var(--border2);
  border-radius: 8px;
  padding: 7px 14px;
  color: var(--text);
  font-family: var(--mono);
  font-size: 11px;
  outline: none;
  width: 220px;
  transition: border-color .2s;
}
.search-box:focus { border-color: var(--accent-color, var(--cyan)); }
.search-box::placeholder { color: var(--muted2); }
.scroll-x { overflow-x: auto; max-height: 420px; overflow-y: auto; }
table { width: 100%; border-collapse: collapse; }
thead { position: sticky; top: 0; z-index: 10; }
thead th {
  padding: 11px 16px;
  text-align: left;
  font-size: 9px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 1.2px;
  color: var(--muted);
  background: var(--bg2);
  border-bottom: 1px solid var(--border);
  white-space: nowrap;
  font-family: var(--mono);
  cursor: pointer;
  user-select: none;
}
thead th:hover { color: var(--text); }
tbody tr { border-bottom: 1px solid var(--border); transition: background .12s; }
tbody tr:last-child { border-bottom: none; }
tbody tr:hover { background: rgba(255,255,255,0.022); }
tbody td {
  padding: 10px 16px;
  font-family: var(--mono);
  font-size: 11px;
  white-space: nowrap;
  color: var(--text);
}
.null-val { color: var(--muted2); }

/* ── Error ── */
.error-box {
  background: rgba(255,107,122,0.07);
  border: 1px solid rgba(255,107,122,0.2);
  border-radius: 12px; padding: 20px 24px;
  color: var(--red); font-family: var(--mono); font-size: 12px;
  margin-bottom: 20px;
}
.error-box strong { display: block; margin-bottom: 4px; font-size: 13px; }
</style>