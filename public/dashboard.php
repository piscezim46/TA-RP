<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Basic access check (adjust roles as needed)
if (!isset($_SESSION['user'])) {
  http_response_code(403);
  echo 'Access denied.';
  exit;
}
$user = $_SESSION['user'];
$role = strtolower($user['role'] ?? '');
$userDept = trim((string)($user['department'] ?? ''));

// Page chrome
$activePage = 'dashboard';
$pageTitle = 'Dashboard';
if (file_exists(__DIR__ . '/../includes/header.php')) include __DIR__ . '/../includes/header.php';
if (file_exists(__DIR__ . '/../includes/navbar.php')) include __DIR__ . '/../includes/navbar.php';

// Resolve Open status id (fallback to 1)
$openStatusId = 1;
try {
  if ($res = $conn->query("SELECT status_id FROM positions_status WHERE LOWER(status_name) = 'open' LIMIT 1")) {
    if ($row = $res->fetch_assoc()) $openStatusId = (int)$row['status_id'];
    $res->free();
  }
} catch (Throwable $e) {
  // fallback to default 1 silently
}

// Build SQL for Open positions
$rows = [];
$totalOpen = 0;
$sql = "
  SELECT
    p.id,
    p.title,
    p.department,
    p.team,
    p.manager_name,
    p.openings,
    p.created_at,
    p.status_id,
    COALESCE(s.status_name, '') AS status_name
  FROM positions p
  LEFT JOIN positions_status s ON p.status_id = s.status_id
  WHERE p.status_id = ? /* Open only */
";
$types = 'i';
$params = [ $openStatusId ];

// Non-admins see only their department
if ($role !== 'admin') {
  $sql .= " AND p.department = ? ";
  $types .= 's';
  $params[] = $userDept;
}

$sql .= " ORDER BY p.created_at DESC LIMIT 100";

try {
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $rows[] = $r;
    $stmt->close();
  }
  $totalOpen = count($rows);
  } catch (Throwable $e) {
  // Render a soft error and continue with empty list
  echo '<div class="alert-error"><strong>Dashboard error:</strong> '.htmlspecialchars($e->getMessage()).'</div>';
}

// Helper: readable age
function ago_days($tsStr) {
  if (!$tsStr) return '—';
  $ts = strtotime($tsStr);
  if ($ts === false) return '—';
  $diff = time() - $ts;
  if ($diff < 3600) return max(1, (int)floor($diff/60)).'m';
  if ($diff < 86400) return (int)floor($diff/3600).'h';
  return (int)floor($diff/86400).'d';
}
?>
<?php
// Prepare applicant tickets and interview tickets data for dashboard cards.
// Rules: include tickets that are active by status (not terminal) OR created/interviewed within the last 30 days.
$monthAgo = date('Y-m-d H:i:s', strtotime('-30 days'));
// Terminal status name keywords (lowercase) to consider a ticket non-active
$terminalApplicantNames = [ 'closed','rejected','hired','complete','completed' ];
$terminalInterviewNames = [ 'cancelled','completed' ];

$applicants = [];
try {
  $sqlA = "SELECT a.applicant_id, a.full_name, a.status_id, COALESCE(s.status_name,'') AS status_name, a.created_at FROM applicants a LEFT JOIN applicants_status s ON a.status_id = s.status_id WHERE (LOWER(COALESCE(s.status_name,'')) NOT IN ('" . implode("','", array_map('addslashes', $terminalApplicantNames)) . "') ) OR a.created_at >= ? ORDER BY a.created_at DESC LIMIT 500";
  $stmtA = $conn->prepare($sqlA);
  if ($stmtA) {
    $stmtA->bind_param('s', $monthAgo);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($r = $resA->fetch_assoc()) $applicants[] = $r;
    $stmtA->close();
  }
} catch (Throwable $_) { }

$interviews = [];
try {
  $sqlI = "SELECT i.id, i.applicant_id, COALESCE(u.name,'') AS applicant_name, i.interview_datetime, i.status_id, COALESCE(s.name,'') AS status_name, i.created_at FROM interviews i LEFT JOIN interview_statuses s ON i.status_id = s.id LEFT JOIN applicants a ON a.applicant_id = i.applicant_id LEFT JOIN users u ON i.created_by = u.id WHERE (LOWER(COALESCE(s.name,'')) NOT IN ('" . implode("','", array_map('addslashes', $terminalInterviewNames)) . "') ) OR (i.interview_datetime >= ?) ORDER BY i.interview_datetime DESC LIMIT 500";
  $stmtI = $conn->prepare($sqlI);
  if ($stmtI) {
    $stmtI->bind_param('s', $monthAgo);
    $stmtI->execute();
    $resI = $stmtI->get_result();
    while ($r = $resI->fetch_assoc()) $interviews[] = $r;
    $stmtI->close();
  }
} catch (Throwable $_) { }

// Helper counts (active by status OR within timeframe will be computed client-side)
$applicantCount = count($applicants);
$interviewCount = count($interviews);
?>
<?php
// Compute counts per timeframe (Today / Last Week / Last Month)
$todayStart = date('Y-m-d 00:00:00');
$weekAgo = date('Y-m-d H:i:s', strtotime('-7 days'));
$monthAgo = date('Y-m-d H:i:s', strtotime('-30 days'));

$ranges = [ 'today' => $todayStart, 'week' => $weekAgo, 'month' => $monthAgo ];

$terminalApplicantNames = [ 'closed','rejected','hired','complete','completed' ];
$terminalInterviewNames = [ 'cancelled','completed' ];

$positionCounts = ['today'=>0,'week'=>0,'month'=>0];
$applicantCounts = ['today'=>0,'week'=>0,'month'=>0];
$interviewCounts = ['today'=>0,'week'=>0,'month'=>0];

// Positions: count by creation date within the timeframe
foreach ($ranges as $k => $start) {
  try {
    $sqlP = "SELECT COUNT(*) AS c FROM positions p WHERE p.created_at >= ?";
    if ($role !== 'admin') $sqlP .= " AND p.department = ?";
    $stmtP = $conn->prepare($sqlP);
    if ($stmtP) {
      if ($role !== 'admin') {
        $stmtP->bind_param('ss', $start, $userDept);
      } else {
        $stmtP->bind_param('s', $start);
      }
      $stmtP->execute();
      $r = $stmtP->get_result()->fetch_assoc();
      $positionCounts[$k] = isset($r['c']) ? (int)$r['c'] : 0;
      $stmtP->close();
    }
  } catch (Throwable $_) { $positionCounts[$k] = 0; }
}

// Applicants: count by creation date within the timeframe
foreach ($ranges as $k => $start) {
  try {
    $sqlA = "SELECT COUNT(*) AS c FROM applicants a WHERE a.created_at >= ?";
    $stmtA = $conn->prepare($sqlA);
    if ($stmtA) {
      $stmtA->bind_param('s', $start);
      $stmtA->execute();
      $r = $stmtA->get_result()->fetch_assoc();
      $applicantCounts[$k] = isset($r['c']) ? (int)$r['c'] : 0;
      $stmtA->close();
    }
  } catch (Throwable $_) { $applicantCounts[$k] = 0; }
}

// Interviews: count by interview date within the timeframe
foreach ($ranges as $k => $start) {
  try {
    $sqlI = "SELECT COUNT(*) AS c FROM interviews i WHERE i.interview_datetime >= ?";
    $stmtI = $conn->prepare($sqlI);
    if ($stmtI) {
      $stmtI->bind_param('s', $start);
      $stmtI->execute();
      $r = $stmtI->get_result()->fetch_assoc();
      $interviewCounts[$k] = isset($r['c']) ? (int)$r['c'] : 0;
      $stmtI->close();
    }
  } catch (Throwable $_) { $interviewCounts[$k] = 0; }
}

// Additional aggregates for the requested dashboard
// Positions overview
$totalPositions = 0; $activePositions = 0; $positionsInApproval = 0; $closedPositions = 0;
try {
  $q = 'SELECT COUNT(*) AS c FROM positions' . ($role !== 'admin' ? " WHERE department = '" . $conn->real_escape_string($userDept) . "'" : '');
  $r = $conn->query($q); if ($r) { $row = $r->fetch_assoc(); $totalPositions = (int)($row['c'] ?? 0); $r->free(); }

  // Active = positions_status = 'open'
  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM positions p LEFT JOIN positions_status s ON p.status_id = s.status_id WHERE LOWER(COALESCE(s.status_name,'')) = 'open'" . ($role !== 'admin' ? " AND p.department = ?" : ''));
  if ($stmt) {
    if ($role !== 'admin') { $stmt->bind_param('s', $userDept); }
    $stmt->execute(); $res = $stmt->get_result(); $r = $res->fetch_assoc(); $activePositions = (int)($r['c'] ?? 0); $stmt->close(); }

  // In Approval = name contains 'approval'
  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM positions p LEFT JOIN positions_status s ON p.status_id = s.status_id WHERE LOWER(COALESCE(s.status_name,'')) LIKE '%approval%'" . ($role !== 'admin' ? " AND p.department = ?" : ''));
  if ($stmt) { if ($role !== 'admin') $stmt->bind_param('s', $userDept); $stmt->execute(); $res = $stmt->get_result(); $r = $res->fetch_assoc(); $positionsInApproval = (int)($r['c'] ?? 0); $stmt->close(); }

  // Closed: common keywords
  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM positions p LEFT JOIN positions_status s ON p.status_id = s.status_id WHERE LOWER(COALESCE(s.status_name,'')) IN ('closed','filled','cancelled')" . ($role !== 'admin' ? " AND p.department = ?" : ''));
  if ($stmt) { if ($role !== 'admin') $stmt->bind_param('s', $userDept); $stmt->execute(); $res = $stmt->get_result(); $r = $res->fetch_assoc(); $closedPositions = (int)($r['c'] ?? 0); $stmt->close(); }
} catch (Throwable $_) {}

// Applicants overview counts per stage
$totalApplicants = 0; $screeningPending = 0; $shortlisted = 0; $hrInterviews = 0; $managerInterviews = 0; $rejected = 0; $hired = 0;
try {
  $r = $conn->query('SELECT COUNT(*) AS c FROM applicants'); if ($r) { $totalApplicants = (int)(($r->fetch_assoc())['c'] ?? 0); $r->free(); }
  $get = function($pattern){ global $conn; $sql = "SELECT COUNT(*) AS c FROM applicants a LEFT JOIN applicants_status s ON a.status_id = s.status_id WHERE LOWER(COALESCE(s.status_name,'')) LIKE ?"; $stmt = $conn->prepare($sql); if (!$stmt) return 0; $like = '%' . $pattern . '%'; $stmt->bind_param('s', $like); $stmt->execute(); $res = $stmt->get_result(); $r = $res->fetch_assoc(); $stmt->close(); return (int)($r['c'] ?? 0); };
  $screeningPending = $get('screen');
  $shortlisted = $get('shortlist');
  $hrInterviews = $get('hr interview');
  if (!$hrInterviews) $hrInterviews = $get('hr_interview');
  $managerInterviews = $get('manager interview');
  if (!$managerInterviews) $managerInterviews = $get('manager_interview');
  $rejected = $get('reject');
  $hired = $get('hire');
} catch (Throwable $_) {}

// Interviews summary
$totalInterviews = 0; $todaysInterviews = 0; $weekInterviews = 0;
try {
  $r = $conn->query('SELECT COUNT(*) AS c FROM interviews'); if ($r) { $totalInterviews = (int)(($r->fetch_assoc())['c'] ?? 0); $r->free(); }
  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM interviews WHERE DATE(interview_datetime) = CURDATE()"); if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $todaysInterviews = (int)($r['c'] ?? 0); $stmt->close(); }
  $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM interviews WHERE interview_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)"); if ($stmt) { $stmt->execute(); $r = $stmt->get_result()->fetch_assoc(); $weekInterviews = (int)($r['c'] ?? 0); $stmt->close(); }
} catch (Throwable $_) {}

// Activity feed: merge recent events from positions (created), applicants_status_history, interviews (created)
$events = [];
try {
  // Positions created
  $sql = "SELECT p.created_at AS ts, COALESCE(u.name,'System') AS user_name, CONCAT('New position created: ', p.title) AS message FROM positions p LEFT JOIN users u ON p.created_by = u.id" . ($role !== 'admin' ? " WHERE p.department = '" . $conn->real_escape_string($userDept) . "'" : '') . " ORDER BY p.created_at DESC LIMIT 10";
  $res = $conn->query($sql); if ($res) { while ($r = $res->fetch_assoc()) $events[] = $r; $res->free(); }
  // Applicant status history
  $sql = "SELECT h.updated_at AS ts, COALESCE(u.name,'System') AS user_name, CONCAT('Applicant #', h.applicant_id, ' moved to ', COALESCE(s.status_name,'')) AS message, h.reason FROM applicants_status_history h LEFT JOIN users u ON h.updated_by = u.id LEFT JOIN applicants_status s ON h.status_id = s.status_id ORDER BY h.updated_at DESC LIMIT 10";
  $res = $conn->query($sql); if ($res) { while ($r = $res->fetch_assoc()) $events[] = $r; $res->free(); }
  // Interviews created/scheduled
  $sql = "SELECT i.created_at AS ts, COALESCE(u.name,'System') AS user_name, CONCAT('Interview scheduled for Applicant #', i.applicant_id, ' @ ', COALESCE(i.interview_datetime,'')) AS message FROM interviews i LEFT JOIN users u ON i.created_by = u.id ORDER BY i.created_at DESC LIMIT 10";
  $res = $conn->query($sql); if ($res) { while ($r = $res->fetch_assoc()) $events[] = $r; $res->free(); }
} catch (Throwable $_) {}

// Sort events by timestamp desc and keep top 10
usort($events, function($a,$b){ $ta = strtotime($a['ts']); $tb = strtotime($b['ts']); return $tb <=> $ta; });
$events = array_slice($events, 0, 10);

?>
<link rel="stylesheet" href="assets/css/notify.css">
<script src="assets/js/notify.js"></script>
<link rel="stylesheet" href="styles/dashboard.css">

<main class="content-area">
  <div class="dashboard-container">
    <div id="summaryStack" class="summary-stack">
    <!-- Positions Card -->
    <div class="table-card">
      <div class="hdr"><h3>Position Summary</h3>
        <div class="actions">
          <div class="action-group">
            <button type="button" class="timeframe-btn" data-target="positions" data-range="today" title="Today">Today</button>
            <button type="button" class="timeframe-btn" data-target="positions" data-range="week" title="Last Week">Last Week</button>
            <button type="button" class="timeframe-btn" data-target="positions" data-range="month" title="Last Month">Last Month</button>
          </div>
          <a href="view_positions.php" class="btn-ghost">View All</a>
        </div>
      </div>
      <div class="inner">
        <div class="summary-row"><div>Total Positions</div><div id="positionsTotal" class="value"><?= (int)$totalPositions ?></div></div>
        <div class="status-cards">
          <div class="status-card"><div class="label">Active</div><div class="value val-success"><?= (int)$activePositions ?></div></div>
          <div class="status-card"><div class="label">In Approval</div><div class="value val-warning"><?= (int)$positionsInApproval ?></div></div>
          <div class="status-card"><div class="label">Closed</div><div class="value val-muted"><?= (int)$closedPositions ?></div></div>
        </div>
        <div class="recent-list">
          <div class="small-muted">Recent Positions</div>
          <?php if (!empty($rows)): ?>
            <ul class="recent-grid">
              <?php foreach (array_slice($rows, 0, 6) as $rp): ?>
                <li class="recent-item">
                  <div class="row">
                    <div class="meta"><strong><?= htmlspecialchars($rp['title'] ?? '') ?></strong><div class="small-muted"><?= htmlspecialchars($rp['department'] ?? '—') ?><?= $rp['team'] ? ' / '.htmlspecialchars($rp['team']) : '' ?></div></div>
                    <div class="meta muted-right">By <?= htmlspecialchars($rp['manager_name'] ?? 'Unassigned') ?><div class="small-muted"><?= htmlspecialchars(ago_days($rp['created_at'] ?? '')) ?> ago</div></div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="empty">No positions to show.</div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Applicants Card (split left/right inside same card) -->
    <div class="table-card">
      <div class="hdr"><h3>Applicants Summary</h3>
        <div class="actions">
          <div class="action-group">
            <button type="button" class="timeframe-btn" data-target="applicants" data-range="today" title="Today">Today</button>
            <button type="button" class="timeframe-btn" data-target="applicants" data-range="week" title="Last Week">Last Week</button>
            <button type="button" class="timeframe-btn" data-target="applicants" data-range="month" title="Last Month">Last Month</button>
          </div>
          <a href="applicants.php" class="btn-ghost">View All</a>
        </div>
      </div>
      <div class="inner full-height">
        <div class="card-main">
          <div class="summary-row"><div>Total Applicants</div><div id="applicantsTotalSummary" class="value"><?= (int)$totalApplicants ?></div></div>
          <div class="two-col">
          <div class="col-flex">
            <div class="status-stack">
              <div class="status-card"><div class="label">Screening Pending</div><div class="value val-primary"><span id="status-screening"><?= (int)$screeningPending ?></span></div></div>
              <div class="status-card"><div class="label">Shortlisted</div><div class="value val-purple"><span id="status-shortlisted"><?= (int)$shortlisted ?></span></div></div>
              <div class="status-card"><div class="label">HR Interviews</div><div class="value val-indigo"><span id="status-hr"><?= (int)$hrInterviews ?></span></div></div>
              <div class="status-card"><div class="label">Manager Interviews</div><div class="value val-teal"><span id="status-manager"><?= (int)$managerInterviews ?></span></div></div>
              <div class="status-card"><div class="label">Rejected</div><div class="value val-danger"><span id="status-rejected"><?= (int)$rejected ?></span></div></div>
              <div class="status-card"><div class="label">Hired</div><div class="value val-success"><span id="status-hired"><?= (int)$hired ?></span></div></div>
            </div>
          </div>
          <div style="flex:1 1 0; min-height:0; display:flex; flex-direction:column;">
            <div class="small-muted">Recent Applicants <span id="recentApplicantsCount" class="small-muted">(<?php echo min(12, count($applicants)); ?>)</span></div>
            <div id="recentApplicantsList" class="recent-list">
              <?php if (!empty($applicants)): ?>
                    <ul class="recent-grid-single">
                      <?php foreach (array_slice($applicants, 0, 12) as $ap): ?>
                        <li class="row">
                          <div class="row-ellipsis"><strong class="name-strong"><?= htmlspecialchars($ap['full_name'] ?? ('Applicant #'.($ap['applicant_id']??''))) ?></strong><div class="small-muted">Status: <?= htmlspecialchars($ap['status_name'] ?? '—') ?></div></div>
                          <div class="muted-right"><?= htmlspecialchars($ap['created_at'] ?? '') ?> <div class="small-muted"><?= htmlspecialchars(ago_days($ap['created_at'] ?? '')) ?> ago</div></div>
                        </li>
                      <?php endforeach; ?>
                    </ul>
              <?php else: ?>
                <div class="empty">No applicants to show.</div>
              <?php endif; ?>
            </div>
          </div>
          </div>
        </div>
        <div class="summary-row progress-footer" style="margin-top:8px;">
          <?php
            $totalForBar = max(1, $totalApplicants);
            $pScreen = round($screeningPending / $totalForBar * 100);
            $pShort = round($shortlisted / $totalForBar * 100);
            $pHR = round($hrInterviews / $totalForBar * 100);
            $pMgr = round($managerInterviews / $totalForBar * 100);
            $pRej = round($rejected / $totalForBar * 100);
            $pHire = round($hired / $totalForBar * 100);
          ?>
          <div style="width:100%">
              <div class="progress-track">
              <div id="progress-screen" title="Screening <?= $pScreen ?>%" class="progress-seg" style="width:<?= $pScreen ?>%;background:#3b82f6;"></div>
              <div id="progress-short" title="Shortlisted <?= $pShort ?>%" class="progress-seg" style="width:<?= $pShort ?>%;background:#7c3aed;"></div>
              <div id="progress-hr" title="HR <?= $pHR ?>%" class="progress-seg" style="width:<?= $pHR ?>%;background:#8b5cf6;"></div>
              <div id="progress-mgr" title="Manager <?= $pMgr ?>%" class="progress-seg" style="width:<?= $pMgr ?>%;background:#06b6d4;"></div>
              <div id="progress-rej" title="Rejected <?= $pRej ?>%" class="progress-seg" style="width:<?= $pRej ?>%;background:#ef4444;"></div>
              <div id="progress-hire" title="Hired <?= $pHire ?>%" class="progress-seg" style="width:<?= $pHire ?>%;background:#10b981;"></div>
            </div>
            <div class="progress-legend">
              <span>Screen</span><span>Shortlist</span><span>HR</span><span>Mgr</span><span>Rejected</span><span>Hired</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Interviews Card -->
    <div class="table-card">
      <div class="hdr"><h3>Interviews at a Glance</h3>
        <div class="actions">
          <div class="action-group">
            <button type="button" class="timeframe-btn" data-target="interviews" data-range="today" title="Today">Today</button>
            <button type="button" class="timeframe-btn" data-target="interviews" data-range="week" title="Last Week">Last Week</button>
            <button type="button" class="timeframe-btn" data-target="interviews" data-range="month" title="Last Month">Last Month</button>
          </div>
          <a href="interviews.php" class="btn-ghost">View All</a>
        </div>
      </div>
      <div class="inner">
        <div class="summary-row"><div>Total Interviews</div><div id="interviewsTotalSummary" class="value"><?= (int)$totalInterviews ?></div></div>
        <div class="summary-row"><div>Today's Interviews</div><div class="value" style="color:#10b981;"><?= (int)$todaysInterviews ?></div></div>
        <div class="summary-row"><div>This Week</div><div class="value" style="color:#3b82f6;"><?= (int)$weekInterviews ?></div></div>
        <div style="padding-top:8px;">
          <div class="small-muted">Upcoming / Recent Interviews</div>
          <div id="recentInterviewsList" class="recent-list">
          <?php if (!empty($interviews)): ?>
            <ul class="recent-grid">
              <?php foreach (array_slice($interviews, 0, 12) as $iv): ?>
                <?php $dt = $iv['interview_datetime'] ?? $iv['created_at']; $age = htmlspecialchars(ago_days($iv['interview_datetime'] ?? $iv['created_at'] ?? '')); ?>
                <li class="recent-item">
                  <div class="row">
                    <div class="row-ellipsis">
                      <strong class="name-strong">Applicant #<?= htmlspecialchars($iv['applicant_id'] ?? '') ?></strong>
                      <div class="small-muted"><?= htmlspecialchars($iv['status_name'] ?? '') ?></div>
                    </div>
                    <div class="muted-right muted-right-compact">
                      <?= $dt ? htmlspecialchars($dt) : '—' ?>
                      <div class="small-muted"><?= htmlspecialchars($iv['applicant_name'] ?? '') ?></div>
                      <div class="small-muted muted-top"><?= $age ?> ago</div>
                    </div>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php else: ?>
            <div class="empty">No interviews to show.</div>
          <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
// Row click -> go to Positions page and open the ticket there (simple redirect)
// OpenPositions table removed; no row click behaviour required here.

// Open "Create Position" directly from dashboard if button is visible
document.getElementById('openCreatePositionBtn')?.addEventListener('click', function(e){
  e.preventDefault();
  // Best UX: jump to positions page and open the modal there (reuses existing code)
  window.location.href = 'view_positions.php?openCreate=1';
});

/* Adjust layout to visible navbar (top) and sidebar (left) */
(function(){
  function q(sel){ return document.querySelector(sel); }
  function pickSidebar(){
    return q('#sidebar') || q('.sidebar') || q('.side-nav') || q('.sidenav') || q('nav[aria-label="Sidebar"]');
  }
  function pickTopbar(){
    // prefer explicit top bars, then generic header if it's fixed
    const cands = ['#topbar', '.topbar', 'header.navbar', '.navbar', 'header[role="banner"]', 'header'];
    for (const sel of cands) {
      const el = q(sel);
      if (!el) continue;
      const cs = getComputedStyle(el);
      if (cs.position === 'fixed' || cs.position === 'sticky' || sel !== 'header') return el;
    }
    return null;
  }
  function px(n){ return Math.max(0, Math.round(n || 0)) + 'px'; }
  function adjust(){
    const sb = pickSidebar();
    const tb = pickTopbar();
    const sbW = sb ? sb.getBoundingClientRect().width : 0;
    const tbH = tb ? tb.getBoundingClientRect().height : 0;
    // NOTE: removed per-page CSS variable overrides to keep sidebar layout centralized in styles/layout.css
    // If you need dynamic syncing of measured topbar/sidebar sizes, add a single centralized script
    // (e.g., in includes/header.php) instead of per-page assignments.
  }
  window.addEventListener('load', adjust);
  window.addEventListener('resize', adjust);
  // in case sidebar animates in after load
  setTimeout(adjust, 50); setTimeout(adjust, 300);
})();
</script>
<script>
// Embed server-provided data into JS for client-side filtering and in-place summaries
const DASH_APPLICANTS = <?php echo json_encode($applicants, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const DASH_INTERVIEWS = <?php echo json_encode($interviews, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const DASH_POSITION_COUNTS = <?php echo json_encode($positionCounts, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const DASH_APPLICANT_COUNTS = <?php echo json_encode($applicantCounts, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;
const DASH_INTERVIEW_COUNTS = <?php echo json_encode($interviewCounts, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;

function parseDate(val){ const t = val ? new Date(val) : null; return (t && !isNaN(t)) ? t : null; }
function fmtWhen(val){ const d = parseDate(val); if (!d) return '—'; return d.toLocaleString(); }

// small helper for safe text insertion in templates
function escapeHtml(str){ if (str === null || str === undefined) return ''; return String(str).replace(/[&<>"'`]/g, function(s){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#96;'})[s]; }); }

function filterByRange(rows, key, range){
  const now = new Date();
  let start = new Date();
  if (range === 'today') { start.setHours(0,0,0,0); }
  else if (range === 'week') { start.setDate(start.getDate() - 7); start.setHours(0,0,0,0); }
  else { start.setMonth(start.getMonth() - 1); start.setHours(0,0,0,0); }
  return rows.filter(r=>{
    try{
      // Match server-side semantics: strict date-based filtering
      if (key === 'applicants') {
        const dt = parseDate(r.created_at);
        return dt && dt >= start;
      }
      if (key === 'interviews') {
        const dt = parseDate(r.interview_datetime);
        return dt && dt >= start;
      }
    }catch(e){ return false; }
    return false;
  });
}

function renderPositions(range){
  const totalEl = document.getElementById('positionsTotal');
  if (totalEl) {
    const v = (DASH_POSITION_COUNTS && DASH_POSITION_COUNTS[range]) ? DASH_POSITION_COUNTS[range] : 0;
    totalEl.textContent = String(v);
  }
}

function renderApplicants(range){
  // update ticket list and summary total
  const rows = filterByRange(DASH_APPLICANTS, 'applicants', range || 'today');
  // Update summary total (server-provided counts object or derived)
  const summaryEl = document.getElementById('applicantsTotalSummary');
  if (summaryEl) {
    const sv = (DASH_APPLICANT_COUNTS && DASH_APPLICANT_COUNTS[range]) ? DASH_APPLICANT_COUNTS[range] : rows.length;
    summaryEl.textContent = String(sv);
  }
  // Render recent applicants into the recentApplicantsList container
  const listEl = document.getElementById('recentApplicantsList');
  if (!listEl) return;
  if (!rows.length) {
    listEl.innerHTML = '<div class="empty">No applicants for this range.</div>';
  } else {
    const items = rows.slice(0, 12).map(r=>{
    const name = r.full_name || ('Applicant #' + (r.applicant_id||''));
    const when = r.created_at ? new Date(r.created_at).toLocaleString() : '—';
    const ago = r.created_at ? (new Date() - new Date(r.created_at) < 86400*1000 ? Math.max(1, Math.floor((new Date() - new Date(r.created_at))/60000)) + 'm' : (()=>{ const d = Math.floor((new Date() - new Date(r.created_at))/86400000); return d + 'd';})() ) : '';
    return '<li class="list-item-compact">'
           + '<div class="row-ellipsis"><strong class="name-strong">'+escapeHtml(name)+'</strong><div class="small-muted">Status: '+escapeHtml(r.status_name||'—')+'</div></div>'
           + '<div class="muted-right">'+escapeHtml(when)+' <div class="small-muted small-muted-sm">'+escapeHtml(ago)+' ago</div></div>'
           + '</li>';
  }).join('');
    listEl.innerHTML = '<ul style="list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(1,1fr);gap:8px;">'+items+'</ul>';
  }
  // update displayed count (always update even when zero rows)
  const countEl = document.getElementById('recentApplicantsCount');
  if (countEl) {
    const shown = Math.min(rows.length, 12);
    countEl.textContent = '(' + String(shown) + ')';
  }

  // Compute per-status counts for the filtered rows and update status cards + progress bar
  (function updateStatusAndProgress(){
    const counts = { screen:0, shortlist:0, hr:0, mgr:0, rejected:0, hired:0 };
    rows.forEach(r=>{
      const s = (r.status_name || '').toString().toLowerCase();
      if (s.includes('hire')) counts.hired++;
      else if (s.includes('reject')) counts.rejected++;
      else if (s.includes('manager interview') || s.includes('manager_interview') || s.includes('manager')) counts.mgr++;
      else if (s.includes('hr interview') || s.includes('hr_interview') || (s.includes('hr') && s.includes('interview'))) counts.hr++;
      else if (s.includes('shortlist') || s.includes('shortlisted')) counts.shortlist++;
      else if (s.includes('screen') || s === '') counts.screen++;
      else counts.screen++; // default bucket
    });

    // Update status DOM elements
    const elMap = {
      screen: document.getElementById('status-screening'),
      shortlist: document.getElementById('status-shortlisted'),
      hr: document.getElementById('status-hr'),
      mgr: document.getElementById('status-manager'),
      rejected: document.getElementById('status-rejected'),
      hired: document.getElementById('status-hired')
    };
    Object.keys(elMap).forEach(k=>{ if (elMap[k]) elMap[k].textContent = String(counts[k] || 0); });

    // Update progress bar segments (percent of filtered rows)
    const total = rows.length;
    const segMap = {
      screen: document.getElementById('progress-screen'),
      shortlist: document.getElementById('progress-short'),
      hr: document.getElementById('progress-hr'),
      mgr: document.getElementById('progress-mgr'),
      rejected: document.getElementById('progress-rej'),
      hired: document.getElementById('progress-hire')
    };
    if (!total) {
      Object.values(segMap).forEach(el=>{ if (el) { el.style.width = '0%'; el.title = ''; } });
    } else {
      Object.keys(segMap).forEach(k=>{
        const el = segMap[k];
        if (!el) return;
        const pct = Math.round((counts[k] || 0) / total * 100);
        el.style.width = pct + '%';
        el.title = (k.charAt(0).toUpperCase() + k.slice(1)) + ' ' + String(pct) + '%';
      });
    }
  })();
}

function renderInterviews(range){
  // update ticket list and summary total
  const rows = filterByRange(DASH_INTERVIEWS, 'interviews', range || 'today');
  const summaryEl = document.getElementById('interviewsTotalSummary'); if (summaryEl) {
    const sv = (DASH_INTERVIEW_COUNTS && DASH_INTERVIEW_COUNTS[range]) ? DASH_INTERVIEW_COUNTS[range] : rows.length;
    summaryEl.textContent = String(sv);
  }
  const listEl = document.getElementById('recentInterviewsList'); if (!listEl) return;
  if (!rows.length) { listEl.innerHTML = '<div class="empty">No interviews for this range.</div>'; return; }
  const items = rows.slice(0, 12).map(r=>{
    const when = r.interview_datetime || r.created_at || '';
    const dt = parseDate(when);
    let ago = '';
    if (dt) {
      const diff = new Date() - dt;
      if (diff < 86400*1000) ago = Math.max(1, Math.floor(diff/60000)) + 'm'; else ago = Math.floor(diff/86400000) + 'd';
    }
    const applicantName = r.applicant_name || '';
    const status = r.status_name || '';
    // Build row so status appears under the Applicant # label (same structure as applicants list)
    return '<li class="list-item-compact-col">'
      + '<div class="row-ellipsis"><strong class="name-strong">'+escapeHtml('Applicant #' + (r.applicant_id||''))+'</strong><div class="small-muted">'+escapeHtml(status)+'</div></div>'
      + '<div class="muted-right muted-right-compact">'+escapeHtml(when)
      + (applicantName ? '<div class="small-muted">'+escapeHtml(applicantName)+'</div>' : '')
      + '<div class="small-muted small-muted-sm muted-top">'+escapeHtml(ago ? (ago + ' ago') : '')+'</div>'
      + '</div>'
      + '</li>';
  }).join('');
  listEl.innerHTML = '<ul style="list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(2,1fr);gap:8px;">'+items+'</ul>';
}

// Wire timeframe buttons
// Use event delegation for timeframe buttons — more robust if buttons are re-rendered.
document.addEventListener('click', function (e) {
  const btn = e.target.closest && e.target.closest('.timeframe-btn');
  if (!btn) return;
  e.preventDefault();
  const target = btn.getAttribute('data-target');
  const range = btn.getAttribute('data-range') || 'today';
  // call appropriate renderer
  if (target === 'applicants') renderApplicants(range);
  else if (target === 'interviews') renderInterviews(range);
  else if (target === 'positions') renderPositions(range);
  // toggle active state for the target group
  try { document.querySelectorAll('.timeframe-btn[data-target="'+target+'"]').forEach(b=>b.classList.remove('active')); } catch(e){}
  btn.classList.add('active');
});

// Ensure default 'today' buttons are set active on load for each target group
['positions','applicants','interviews'].forEach(function(t){
  const b = document.querySelector('.timeframe-btn[data-target="'+t+'"][data-range="today"]');
  if (b) b.classList.add('active');
});

// Row click handlers: open applicant/interview fragment
document.getElementById('applicantTicketsBody')?.addEventListener('click', function(e){ const tr = e.target.closest('tr[data-applicant-id]'); if (!tr) return; const id = tr.getAttribute('data-applicant-id'); if (!id) return; window.location.href = 'get_applicant.php?applicant_id=' + encodeURIComponent(id); });
document.getElementById('interviewTicketsBody')?.addEventListener('click', function(e){ const tr = e.target.closest('tr[data-interview-id]'); if (!tr) return; const id = tr.getAttribute('data-interview-id'); if (!id) return; // open parent applicant
  const row = DASH_INTERVIEWS.find(x=>String(x.id) === String(id)); if (row && row.applicant_id) window.location.href = 'get_applicant.php?applicant_id=' + encodeURIComponent(row.applicant_id); else window.location.href = 'get_applicant.php';
});

// Initial render: default to Today
renderPositions('today'); renderApplicants('today'); renderInterviews('today');
</script>