<?php
require_once __DIR__ . '/auth.php';
admin_require_login();

$pdo  = db();
$flash = '';

// Every state-changing POST must carry a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        http_response_code(403);
        $flash = 'Security check failed (CSRF). Please reload the page and try again.';
        $_SERVER['REQUEST_METHOD'] = 'GET'; // skip the handlers below, just re-render
    }
}

/* ---------- Handle POST: add new call ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    try {
        // Targets: a free-form list ("1030, 1045, 1062"). The first number is
        // stored as target_price (T1) for R:R; the full string goes to `targets`.
        $targetsText = trim($_POST['targets'] ?? '');
        $targetNum   = null;
        if ($targetsText !== '' && preg_match('/\d+(?:\.\d+)?/', $targetsText, $tm)) {
            $targetNum = floatval($tm[0]);
        } elseif (($_POST['target_price'] ?? '') !== '') {
            $targetNum = floatval($_POST['target_price']);
            if ($targetsText === '') $targetsText = (string) $targetNum;
        }

        // Stop-loss: same shape as targets — a free list, first number is the
        // primary stop for R:R, the full string is display-only.
        $slText = trim($_POST['stop_losses'] ?? '');
        $slNum  = null;
        if ($slText !== '' && preg_match('/\d+(?:\.\d+)?/', $slText, $sm)) {
            $slNum = floatval($sm[0]);
        }

        // Outcome: post a FRESH (open) call, or an already-achieved/closed one in
        // one shot. A non-open status with an exit price computes PnL immediately
        // and the call shows on the public Performance track record.
        // Same progress vocabulary as Update status: running / live_N / all /
        // stop / closed. Partial (live_N) stays open with N targets hit; "all"
        // closes to Performance with exit defaulting to the final target.
        $prog = $_POST['post_status'] ?? 'running';
        $entryVal = floatval($_POST['entry_price'] ?? 0);
        $isBuy = ($_POST['call_action'] ?? 'BUY') === 'BUY';
        preg_match_all('/\d+(?:\.\d+)?/', $targetsText, $ptm);
        $tlPost = array_map('floatval', $ptm[0]);
        $Np = count($tlPost);
        $exitInput = ($_POST['post_exit'] ?? '') !== '' && preg_match('/\d+(?:\.\d+)?/', $_POST['post_exit'], $em)
                   ? floatval($em[0]) : null;

        $postStatus = 'open'; $thitPost = 0; $exitVal = null; $exitAt = null; $pnlVal = null;
        if ($prog === 'running') {
            $postStatus = 'open'; $thitPost = 0;
        } elseif (strpos($prog, 'live_') === 0) {
            $postStatus = 'open'; $thitPost = min(max(intval(substr($prog, 5)), 0), $Np);
        } elseif ($prog === 'all') {
            $postStatus = 'target_hit'; $thitPost = $Np;
            $exitVal = $exitInput !== null ? $exitInput : ($Np > 0 ? end($tlPost) : null);
        } elseif ($prog === 'stop') {
            $postStatus = 'stop_hit'; $exitVal = $exitInput !== null ? $exitInput : $slNum;
        } elseif ($prog === 'closed') {
            $postStatus = 'closed'; $exitVal = $exitInput;
        }
        if ($exitVal !== null && $entryVal > 0) {
            $pnlVal = $isBuy ? round(($exitVal - $entryVal) / $entryVal * 100, 2)
                             : round(($entryVal - $exitVal) / $entryVal * 100, 2);
            $exitAt = date('Y-m-d H:i:s');
        }

        // Normalise the symbol: trim + collapse internal whitespace so
        // "AKUM  DRUGS" and "AKUM DRUGS" are the same key (defeats space-typo dupes).
        $symbolUp  = strtoupper(preg_replace('/\s+/', ' ', trim($_POST['symbol'] ?? '')));
        $actionVal = $_POST['call_action'] ?? 'BUY';

        // UPSERT — don't create a duplicate row when the team re-pastes the SAME
        // running call to advance its progress (1st target → 2nd target → …).
        // If an OPEN call already exists for this symbol+side, update THAT row
        // (advance targets, refresh levels) and float it to the top via posted_at.
        // A genuinely new position is only created when no open call exists; once
        // a call closes (target_hit / stop_hit / closed) it's locked as track
        // record, so a fresh paste of the same symbol then starts a new row.
        $look = $pdo->prepare(
            "SELECT id FROM calls WHERE symbol = :s AND action = :a AND status = 'open'
             ORDER BY posted_at DESC LIMIT 1"
        );
        $look->execute([':s' => $symbolUp, ':a' => $actionVal]);
        $existingId = $look->fetchColumn();

        $vals = [
            ':call_type'    => $_POST['call_type'] ?? 'intraday',
            ':action'       => $actionVal,
            ':symbol'       => $symbolUp,
            ':company_name' => trim($_POST['company_name'] ?? '') ?: null,
            ':entry'        => $entryVal,
            ':target'       => $targetNum,
            ':targets'      => $targetsText !== '' ? $targetsText : null,
            ':thit'         => $thitPost,
            ':stop'         => $slNum,
            ':stop_losses'  => $slText !== '' ? $slText : null,
            ':thesis'       => trim($_POST['thesis'] ?? ''),
            ':is_public'    => 1,   // every posted call publishes — no hidden-by-accident trap
            ':created_by'   => admin_user() ?: null,
            ':status'       => $postStatus,
            ':exit'         => $exitVal,
            ':exit_at'      => $exitAt,
            ':pnl'          => $pnlVal,
        ];

        if ($existingId) {
            $vals[':id'] = $existingId;
            $pdo->prepare(
                'UPDATE calls SET call_type=:call_type, action=:action, symbol=:symbol, company_name=:company_name,
                        entry_price=:entry, target_price=:target, targets=:targets, targets_hit=:thit,
                        stop_loss=:stop, stop_losses=:stop_losses, thesis=:thesis, is_public=:is_public,
                        created_by=:created_by, status=:status, exit_price=:exit, exit_at=:exit_at,
                        pnl_pct=:pnl, posted_at=NOW()
                 WHERE id=:id'
            )->execute($vals);
            $flash = 'Same call advanced (#' . $existingId . ') — updated existing row, no duplicate.';
        } else {
            // No OPEN call to advance → this would be a NEW row. Authoritative,
            // server-side duplicate guard (the JS confirm is only a friendly
            // pre-warning and is bypassable / stale on double-click). Block when an
            // identical ALREADY-CLOSED call exists — same symbol+side, entry within
            // a paisa, and the SAME target SET and stop SET (order-insensitive).
            // The team can still force a genuine re-post (dup_force=1), which the JS
            // sets when the user explicitly clicks "Post anyway".
            $force = ($_POST['dup_force'] ?? '') === '1';
            $dupId = null; $dupAt = null; $dupStatus = null;
            if (!$force) {
                $tkey = num_set_key($targetsText !== '' ? $targetsText : (string) $targetNum);
                $skey = num_set_key($slText);
                $cand = $pdo->prepare(
                    "SELECT id, entry_price, targets, target_price, stop_losses, stop_loss, status, posted_at
                     FROM calls WHERE symbol = :s AND action = :a
                       AND status IN ('target_hit','stop_hit','closed')
                     ORDER BY posted_at DESC LIMIT 30"
                );
                $cand->execute([':s' => $symbolUp, ':a' => $actionVal]);
                foreach ($cand->fetchAll() as $d) {
                    $dT = num_set_key(!empty($d['targets']) ? $d['targets'] : (string) $d['target_price']);
                    $dS = num_set_key(!empty($d['stop_losses']) ? $d['stop_losses'] : (string) $d['stop_loss']);
                    if (abs(floatval($d['entry_price']) - $entryVal) < 0.005 && $dT === $tkey && $dS === $skey) {
                        $dupId = $d['id']; $dupAt = $d['posted_at']; $dupStatus = $d['status']; break;
                    }
                }
            }
            if ($dupId) {
                $when = date('d M Y', strtotime($dupAt));
                $res  = $dupStatus === 'target_hit' ? 'Target Achieved'
                      : ($dupStatus === 'stop_hit' ? 'Stop-loss Hit' : 'Closed');
                $flash = '⚠️ Already recorded (#' . $dupId . ', ' . $res . ' on ' . $when . ') — '
                       . 'same entry, targets and stop-loss. NOT posted again to avoid a duplicate. '
                       . 'If it really is a fresh trade, change a price or use “Post anyway”.';
            } else {
                $pdo->prepare(
                    'INSERT INTO calls (call_type, action, symbol, company_name, entry_price, target_price, targets, targets_hit, stop_loss, stop_losses, thesis, is_public, created_by, status, exit_price, exit_at, pnl_pct)
                     VALUES (:call_type, :action, :symbol, :company_name, :entry, :target, :targets, :thit, :stop, :stop_losses, :thesis, :is_public, :created_by, :status, :exit, :exit_at, :pnl)'
                )->execute($vals);
                $flash = 'Call posted successfully (#' . $pdo->lastInsertId() . ').';
            }
        }
    } catch (PDOException $e) {
        error_log('admin add call failed: ' . $e->getMessage());
        $flash = 'Could not post the call. Please try again.';
    }
}

/* ---------- Handle POST: close / mark a call ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    try {
        $id   = intval($_POST['id']);
        $prog = $_POST['progress'] ?? 'running';
        $pub  = isset($_POST['is_public']) ? 1 : 0;
        $exitInput = ($_POST['exit_price'] ?? '') !== '' ? floatval($_POST['exit_price']) : null;

        // Pull the call so a single "progress" choice can derive status, targets_hit and exit.
        $row = $pdo->prepare('SELECT action, entry_price, targets, target_price, stop_loss FROM calls WHERE id = :id');
        $row->execute([':id' => $id]);
        $r = $row->fetch();
        $tl = call_targets_list($r ?: []);
        $N  = count($tl);

        $status = 'open'; $thit = 0; $exit = null;
        if ($prog === 'running') {
            $status = 'open'; $thit = 0;
        } elseif (strpos($prog, 'live_') === 0) {
            // Nth target achieved but the call is still running → stays in Live.
            $status = 'open'; $thit = min(max(intval(substr($prog, 5)), 0), $N);
        } elseif ($prog === 'all') {
            $status = 'target_hit'; $thit = $N;
            $exit = $exitInput !== null ? $exitInput : ($N > 0 ? end($tl) : null);  // default exit = final target
        } elseif ($prog === 'stop') {
            $status = 'stop_hit';
            $exit = $exitInput !== null ? $exitInput : ($r && $r['stop_loss'] !== null ? floatval($r['stop_loss']) : null);
        } elseif ($prog === 'closed') {
            $status = 'closed'; $exit = $exitInput;
        } elseif ($prog === 'cancelled') {
            $status = 'cancelled';
        }

        // Auto-compute PnL% from entry vs exit when a closing exit is known.
        $pnl = null;
        if ($exit !== null && $r && $r['entry_price'] > 0) {
            $pnl = $r['action'] === 'BUY'
                ? round(($exit - $r['entry_price']) / $r['entry_price'] * 100, 2)
                : round(($r['entry_price'] - $exit) / $r['entry_price'] * 100, 2);
        }

        $pdo->prepare(
            'UPDATE calls SET status = :status, exit_price = :exit, exit_at = :exit_at, pnl_pct = :pnl, is_public = :pub, targets_hit = :thit
             WHERE id = :id'
        )->execute([
            ':status'  => $status,
            ':exit'    => $exit,
            ':exit_at' => $exit !== null ? date('Y-m-d H:i:s') : null,
            ':pnl'     => $pnl,
            ':pub'     => $pub,
            ':thit'    => $thit,
            ':id'      => $id,
        ]);
        $flash = 'Call updated.';
    } catch (PDOException $e) {
        error_log('admin update_status failed: ' . $e->getMessage());
        $flash = 'Could not update the call. Please try again.';
    }
}

/* ---------- Handle POST: mark as shared to WhatsApp ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_shared') {
    $pdo->prepare('UPDATE calls SET shared_to_wa_at = NOW() WHERE id = :id')
        ->execute([':id' => intval($_POST['id'])]);
    $flash = 'Marked as shared.';
}

/* ---------- Handle POST: delete a call ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $pdo->prepare('DELETE FROM calls WHERE id = :id')
        ->execute([':id' => intval($_POST['id'])]);
    $flash = 'Call deleted.';
}

/* ---------- Fetch recent calls (with date filter) ---------- */
// Filter on the call's posted date. range = all|today|week|custom.
// 'today'/'week' computed in IST so the boundary matches the team's clock.
$range = in_array($_GET['range'] ?? '', ['all','today','week','custom'], true) ? $_GET['range'] : 'all';
$fromIn = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '')) ? $_GET['from'] : '';
$toIn   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to']   ?? '')) ? $_GET['to']   : '';

$ist   = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
$today = $ist->format('Y-m-d');
$dFrom = $dTo = '';
if ($range === 'today') {
    $dFrom = $dTo = $today;
} elseif ($range === 'week') {
    // Week = Monday..today (IST).
    $mon = (clone $ist);
    $dow = (int)$mon->format('N');            // 1=Mon..7=Sun
    if ($dow > 1) $mon->modify('-' . ($dow - 1) . ' days');
    $dFrom = $mon->format('Y-m-d');
    $dTo   = $today;
} elseif ($range === 'custom') {
    $dFrom = $fromIn;
    $dTo   = $toIn;
}

$where = '';
$params = [];
if ($dFrom !== '') { $where .= ' AND DATE(posted_at) >= :dfrom'; $params[':dfrom'] = $dFrom; }
if ($dTo   !== '') { $where .= ' AND DATE(posted_at) <= :dto';   $params[':dto']   = $dTo; }

$stmt = $pdo->prepare('SELECT * FROM calls WHERE 1=1' . $where . ' ORDER BY posted_at DESC LIMIT 50');
$stmt->execute($params);
$calls = $stmt->fetchAll();

/* ---------- Target helpers (parse list, ordinals, progress dropdown) ---------- */
function call_targets_list($c) {
    $raw = !empty($c['targets']) ? $c['targets']
         : (isset($c['target_price']) && $c['target_price'] !== null ? (string) $c['target_price'] : '');
    preg_match_all('/\d+(?:\.\d+)?/', $raw, $m);
    return array_map('floatval', $m[0]);
}
/* Order-insensitive numeric SET key — "535, 550, 562" and "562,550,535" and
   "535,  550 , 562" all collapse to the same "535,550,562". Used to detect a
   duplicate call regardless of how the targets/stops were typed or ordered. */
function num_set_key($s) {
    preg_match_all('/\d+(?:\.\d+)?/', (string) $s, $m);
    $n = array_map('floatval', $m[0]);
    sort($n, SORT_NUMERIC);
    return implode(',', $n);
}
function ordinal($n) {
    $suf = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($suf[($v - 20) % 10] ?? $suf[$v] ?? $suf[0]);
}
/* One plain-language dropdown that encodes both targets_hit and status. */
function progress_select_html($c) {
    $tl = call_targets_list($c);
    $N  = count($tl);
    $cur = 'running';
    if ($c['status'] === 'open')             $cur = intval($c['targets_hit']) > 0 ? 'live_' . intval($c['targets_hit']) : 'running';
    elseif ($c['status'] === 'target_hit')   $cur = 'all';
    elseif ($c['status'] === 'stop_hit')     $cur = 'stop';
    elseif ($c['status'] === 'closed')       $cur = 'closed';
    elseif ($c['status'] === 'cancelled')    $cur = 'cancelled';
    $sel = function ($v) use ($cur) { return $v === $cur ? ' selected' : ''; };

    $html  = '<select name="progress">';
    $html .= '<option value="running"' . $sel('running') . '>Still running — no target hit</option>';
    // Intermediate targets keep the call LIVE (only "All" closes it).
    for ($k = 1; $k < $N; $k++) {
        $html .= '<option value="live_' . $k . '"' . $sel('live_' . $k) . '>'
               . ordinal($k) . ' Target Achieved — still live</option>';
    }
    $html .= '<option value="all"' . $sel('all') . '>✅ All ' . ($N > 0 ? $N . ' ' : '') . 'Targets Achieved → Performance</option>';
    $html .= '<option value="stop"' . $sel('stop') . '>🛑 Stop-loss Hit → Performance</option>';
    $html .= '<option value="closed"' . $sel('closed') . '>☑️ Closed manually → Performance</option>';
    $html .= '<option value="cancelled"' . $sel('cancelled') . '>✖ Cancelled (hide)</option>';
    $html .= '</select>';
    return $html;
}

/* ---------- Risk:reward + potential gain% from entry / T1 / stop ---------- */
function call_rr_gain($c) {
    $entry = floatval($c['entry_price']);
    $t  = $c['target_price'] !== null ? floatval($c['target_price']) : null;
    $sl = $c['stop_loss']    !== null ? floatval($c['stop_loss'])    : null;
    $ex = $c['exit_price']   !== null ? floatval($c['exit_price'])   : null;
    if ($entry <= 0) return [null, null];
    $isBuy = $c['action'] === 'BUY';
    // Planned target gain% (potential to T1).
    $gain = $t !== null ? ($isBuy ? ($t - $entry) : ($entry - $t)) / $entry * 100 : null;
    // R:R: reward from the realized exit if the call is closed, else the planned target.
    $rewardBase = $ex !== null ? $ex : $t;
    $rr = null;
    if ($rewardBase !== null && $sl !== null) {
        $reward = $isBuy ? ($rewardBase - $entry) : ($entry - $rewardBase);
        $risk   = $isBuy ? ($entry - $sl) : ($sl - $entry);
        if ($risk > 0) $rr = $reward / $risk;
    }
    return [$rr, $gain];
}

/* ---------- Build a pre-filled WhatsApp share message for a call ---------- */
function build_wa_message($c) {
    $arrow = $c['action'] === 'BUY' ? '🟢' : '🔴';
    $msg  = $arrow . ' *' . strtoupper($c['call_type']) . " CALL*\n\n";
    $msg .= '*' . $c['action'] . '*: ' . $c['symbol'];
    if (!empty($c['company_name'])) $msg .= ' (' . $c['company_name'] . ')';
    $msg .= "\n";
    $msg .= 'Entry: ₹' . number_format(floatval($c['entry_price']), 2) . "\n";
    if (!empty($c['targets']))            $msg .= 'Targets: ' . $c['targets'] . "\n";
    elseif ($c['target_price'] !== null)  $msg .= 'Target: ₹' . number_format(floatval($c['target_price']), 2) . "\n";
    if (!empty($c['stop_losses']))        $msg .= 'Stop-Loss: ' . $c['stop_losses'] . "\n";
    elseif ($c['stop_loss'] !== null)     $msg .= 'Stop-Loss: ₹' . number_format(floatval($c['stop_loss']), 2) . "\n";
    if (!empty($c['thesis']))        $msg .= "\nThesis: " . $c['thesis'] . "\n";
    $msg .= "\n— RootNivesh Research\nSEBI Reg. No. " . SEBI_REG;
    $msg .= "\n*Investments are subject to market risk. Past performance is not indicative of future results.*";
    return $msg;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>RootNivesh Admin — Calls</title>
<link rel="stylesheet" href="admin.css">
</head>
<body class="admin-body">
<div class="admin-layout">
  <?php $ADMIN_PAGE = 'calls'; include __DIR__ . '/_sidebar.php'; ?>
  <div class="admin-content">
  <div class="admin-topbar"><h1>Calls</h1></div>

  <main class="admin-main">

    <?php if ($flash): ?>
      <div class="admin-flash"><?php echo htmlspecialchars($flash); ?></div>
    <?php endif; ?>

    <!-- ===== QUICK PASTE FROM WHATSAPP ===== -->
    <section class="admin-card">
      <h2>Quick add from WhatsApp</h2>
      <p style="color:#8A9BB0; font-size:13px; margin:0 0 10px">
        Paste the message you sent to the group, hit <strong>Parse</strong>, then check the
        fields below before posting. Recognises lines like
        <em>“BUY RELIANCE Entry 2450 Target 2500 SL 2420”</em>.
      </p>
      <textarea id="pasteBox" rows="4" class="admin-paste"
                placeholder="BUY RELIANCE&#10;Entry 2450&#10;Target 2500&#10;SL 2420"></textarea>
      <div style="margin-top:8px; display:flex; gap:8px; align-items:center; flex-wrap:wrap">
        <button type="button" class="admin-btn" onclick="parsePaste()">⤓ Parse into form</button>
        <span id="parseMsg" style="font-size:13px; color:#8A9BB0"></span>
      </div>
    </section>

    <!-- ===== ADD NEW CALL ===== -->
    <section class="admin-card">
      <h2>Post a new call</h2>
      <form id="addCallForm" method="post" class="admin-form">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="dup_force" id="dupForce" value="0">
        <div class="admin-row">
          <label>Type
            <select name="call_type">
              <option value="intraday">Intraday</option>
              <option value="swing">Swing</option>
              <option value="positional">Positional</option>
              <option value="longterm">Long-term</option>
              <option value="fno">F&amp;O</option>
            </select>
          </label>
          <label>Action
            <select name="call_action">
              <option value="BUY">BUY</option>
              <option value="SELL">SELL</option>
            </select>
          </label>
          <label>Symbol *
            <input type="text" name="symbol" required placeholder="e.g. RELIANCE" style="text-transform:uppercase">
          </label>
        </div>
        <div class="admin-row">
          <label>Company name
            <input type="text" name="company_name" placeholder="Optional, e.g. Reliance Industries Ltd">
          </label>
          <label>Entry *
            <input type="number" step="0.01" name="entry_price" required>
          </label>
        </div>
        <div class="admin-row">
          <label>Targets (T1, T2, T3…)
            <input type="text" name="targets" id="targetsInput" placeholder="e.g. 1030, 1045, 1062" oninput="rebuildPostStatus()">
          </label>
          <label>Stop-Loss(es)
            <input type="text" name="stop_losses" placeholder="e.g. 987  or  987, 980">
          </label>
        </div>
        <div class="admin-row">
          <label>Where does this call go? *
            <select name="post_status" id="postStatus" onchange="togglePostExit()">
              <!-- Partial "Nth Target Achieved" options are injected by JS from the Targets field. -->
              <option value="running">🆕 Fresh Call → Calls page (live, nothing hit yet)</option>
              <option value="all">✅ All Targets Achieved → Performance (win)</option>
              <option value="stop">🛑 Stop-loss Hit → Performance (loss)</option>
              <option value="closed">☑️ Closed manually → Performance</option>
            </select>
          </label>
          <label id="postExitWrap" style="display:none">Booked / avg exit ₹
            <input type="number" step="0.01" name="post_exit" placeholder="price you exited at, e.g. 1062">
          </label>
        </div>
        <p style="color:#8A9BB0; font-size:12px; margin:-4px 0 10px">
          Every call you post is <strong>published automatically</strong>. Just pick where it
          goes: <strong>Fresh Call</strong> for a new running trade, or <strong>Target
          Achieved</strong> (with the exit price) to publish a completed winner to the
          Performance track record.
        </p>
        <label>Thesis
          <textarea name="thesis" rows="3" placeholder="Why this call — research thesis. Will be included in the WhatsApp share."></textarea>
        </label>
        <button type="submit" class="admin-btn">Post call</button>
      </form>
    </section>

    <!-- ===== RECENT CALLS ===== -->
    <section class="admin-card">
      <h2>Recent calls (<?php echo count($calls); ?>)</h2>

      <!-- Date filter: Today / This Week / Custom -->
      <form method="get" class="admin-datefilter" id="callDateFilter">
        <div class="admin-datefilter-tabs">
          <a href="?range=all"   class="admin-chip<?php echo $range==='all'  ?' active':''; ?>">All</a>
          <a href="?range=today" class="admin-chip<?php echo $range==='today'?' active':''; ?>">Today</a>
          <a href="?range=week"  class="admin-chip<?php echo $range==='week' ?' active':''; ?>">This Week</a>
          <button type="button" id="customChip" class="admin-chip<?php echo $range==='custom'?' active':''; ?>" onclick="var b=document.getElementById('customRange');b.hidden=!b.hidden;this.classList.toggle('active',!b.hidden)">Custom Date</button>
        </div>
        <div id="customRange" class="admin-datefilter-custom" hidden>
          <input type="hidden" name="range" value="custom">
          <label>From <input type="date" name="from" value="<?php echo htmlspecialchars($dFrom ?: $today); ?>"></label>
          <label>To <input type="date" name="to" value="<?php echo htmlspecialchars($dTo ?: $today); ?>"></label>
          <button type="submit" class="admin-btn admin-btn-secondary">Apply</button>
        </div>
      </form>

      <?php if (empty($calls)): ?>
        <p style="color:#8A9BB0"><?php echo $range==='all' ? 'No calls posted yet. Add your first call above.' : 'No calls in this date range.'; ?></p>
      <?php else: foreach ($calls as $c):
        $waMsg = build_wa_message($c);
        $waUrl = 'https://wa.me/' . WA_NUMBER . '?text=' . rawurlencode($waMsg);
        $statusClass = $c['status'] === 'open' ? 'open' : ($c['status'] === 'target_hit' ? 'win' : ($c['status'] === 'stop_hit' ? 'loss' : 'closed'));
      ?>
        <div class="admin-call <?php echo $statusClass; ?>">
          <div class="admin-call-head">
            <span class="admin-call-action <?php echo strtolower($c['action']); ?>"><?php echo htmlspecialchars($c['action']); ?></span>
            <span class="admin-call-symbol"><?php echo htmlspecialchars($c['symbol']); ?></span>
            <span class="admin-call-type"><?php echo strtoupper(htmlspecialchars($c['call_type'])); ?></span>
            <span class="admin-call-status status-<?php echo $statusClass; ?>"><?php echo htmlspecialchars(strtoupper(str_replace('_', ' ', $c['status']))); ?></span>
            <?php if (!$c['is_public']): ?><span class="admin-call-status status-loss" title="Hidden from the public site">🔒 PRIVATE</span><?php endif; ?>
            <span class="admin-call-date"><?php echo date('d M Y, H:i', strtotime($c['posted_at'])); ?><?php if (!empty($c['created_by'])): ?> · by <?php echo htmlspecialchars($c['created_by']); ?><?php endif; ?></span>
          </div>
          <div class="admin-call-prices">
            Entry ₹<?php echo number_format($c['entry_price'], 2); ?>
            <?php if (!empty($c['targets'])): ?>· Targets <?php echo htmlspecialchars($c['targets']); ?><?php elseif ($c['target_price'] !== null): ?>· Target ₹<?php echo number_format($c['target_price'], 2); ?><?php endif; ?>
            <?php if (!empty($c['stop_losses'])): ?>· SL <?php echo htmlspecialchars($c['stop_losses']); ?><?php elseif ($c['stop_loss'] !== null): ?>· SL ₹<?php echo number_format($c['stop_loss'], 2); ?><?php endif; ?>
            <?php if (!empty($c['targets_hit']) && $c['status'] === 'open'): ?>· <strong style="color:#3FB950"><?php echo intval($c['targets_hit']); ?> target<?php echo intval($c['targets_hit']) > 1 ? 's' : ''; ?> hit</strong><?php endif; ?>
            <?php list($rr, $gain) = call_rr_gain($c); ?>
            <?php if ($rr   !== null): ?>· R:R 1:<?php echo number_format($rr, 2); ?><?php endif; ?>
            <?php if ($gain !== null): ?>· Tgt <?php echo ($gain >= 0 ? '+' : '') . number_format($gain, 2); ?>%<?php endif; ?>
            <?php if ($c['exit_price']   !== null): ?>· Exit ₹<?php echo number_format($c['exit_price'], 2); ?><?php endif; ?>
            <?php if ($c['pnl_pct']      !== null): ?>· PnL <strong><?php echo ($c['pnl_pct'] >= 0 ? '+' : '') . number_format($c['pnl_pct'], 2); ?>%</strong><?php endif; ?>
          </div>
          <?php if (!empty($c['thesis'])): ?>
            <div class="admin-call-thesis"><?php echo nl2br(htmlspecialchars($c['thesis'])); ?></div>
          <?php endif; ?>
          <div class="admin-call-actions">
            <a class="admin-btn admin-btn-wa" target="_blank" rel="noopener" href="<?php echo htmlspecialchars($waUrl); ?>"
               onclick="markShared(<?php echo $c['id']; ?>)">
              📤 Share to WhatsApp
            </a>
            <?php if (!empty($c['shared_to_wa_at'])): ?>
              <span class="admin-call-shared">✓ Shared <?php echo date('d M, H:i', strtotime($c['shared_to_wa_at'])); ?></span>
            <?php endif; ?>

            <details style="display:inline-block; margin-left:8px">
              <summary class="admin-btn admin-btn-secondary">Update status</summary>
              <form method="post" class="admin-inline-form">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                <?php echo progress_select_html($c); ?>
                <input type="number" step="0.01" name="exit_price" placeholder="Exit ₹ (optional)" title="Optional — overrides the auto exit (final target for All Achieved, the stop for Stop-loss Hit). Use a blended average if you booked partials." value="<?php echo $c['exit_price'] !== null ? htmlspecialchars($c['exit_price']) : ''; ?>">
                <label class="admin-check" style="margin:0 6px"><input type="checkbox" name="is_public" <?php echo $c['is_public'] ? 'checked' : ''; ?>><span>Public</span></label>
                <button type="submit" class="admin-btn admin-btn-secondary">Save</button>
              </form>
            </details>

            <form method="post" style="display:inline-block; margin-left:8px"
                  onsubmit="return confirm('Delete this call permanently? This cannot be undone.');">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
              <button type="submit" class="admin-btn admin-btn-danger">🗑 Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </section>

  </main>

  <form id="markSharedForm" method="post" style="display:none">
    <input type="hidden" name="action" value="mark_shared">
    <input type="hidden" name="id" id="markSharedId">
  </form>

  <script>
    /* ---- WhatsApp message -> form fields ----
       Heuristic, never auto-submits. Pulls action, symbol, entry, target, SL
       from free-form text. The user always reviews the form before posting. */
    function parsePaste() {
      const raw = (document.getElementById('pasteBox').value || '').trim();
      const msg = document.getElementById('parseMsg');
      if (!raw) { msg.textContent = 'Nothing to parse — paste a message first.'; return; }
      const form = document.getElementById('addCallForm');
      const set  = (name, val) => { const el = form.elements[name]; if (el && val != null && val !== '') el.value = val; };
      const num  = (s) => { if (!s) return ''; const n = parseFloat(String(s).replace(/[,₹\s]/g, '')); return isNaN(n) ? '' : n; };

      // Normalise so one grammar handles both labelled ("Entry -> ₹ 1006")
      // and inline ("BUY RELIANCE Entry 2450") styles: unify arrows -> ":",
      // strip markdown/quotes, collapse spaces but KEEP newlines.
      const text = raw
        .replace(/[→➤➜➔]/g, '->')   // → ➤ ➜ ➔
        .replace(/\s*[-=]?>\s*/g, ': ')                 // ->  =>  >   become ": "
        .replace(/[*_`"]+/g, ' ')                       // markdown + quotes
        .replace(/[ \t]+/g, ' ');
      const found = [];

      const firstNum = (s) => { const m = (s || '').match(/\d[\d,]*(?:\.\d+)?/); return m ? num(m[0]) : ''; };
      // Value on a "Label : value" line. Requires the colon, so headers like
      // "Stock Analysis (3-4 Weeks)" don't masquerade as the Stock field.
      const labelled = (labels) => {
        const m = text.match(new RegExp('^[\\s>*_-]*(?:' + labels + ')\\b\\s*:\\s*([^\\n]+)', 'im'));
        return m ? m[1].trim() : '';
      };
      // Looser fallback for price fields with no colon ("Entry 2450").
      const loose = (labels) => {
        const m = text.match(new RegExp('\\b(?:' + labels + ')\\b\\s*:?\\s*([^\\n]*)', 'i'));
        return m ? m[1].trim() : '';
      };

      // Side / action
      const sideRaw = labelled('side|action|call') || text;
      let action = /\b(sell|short)\b/i.test(sideRaw) ? 'SELL'
                 : /\b(buy|long)\b/i.test(sideRaw)   ? 'BUY' : '';
      if (action) { set('call_action', action); found.push('side'); }

      // Stock / symbol — labelled only (company name, any case, may be multi-word).
      let stock = labelled('stock|scrip|share|symbol|company|name');
      stock = stock.replace(/\b(buy|sell|long|short)\b.*$/i, '').trim();
      if (!stock) {  // fallback: first ALL-CAPS ticker
        const sw = new Set(['BUY','SELL','LONG','SHORT','ENTRY','TARGET','TGT','TP','SL','CMP','NSE','BSE','CE','PE','FUT','STOCK','SIDE','NOTE','T1','T2']);
        stock = (text.match(/\b[A-Z][A-Z&]{1,14}\b/g) || []).find(w => !sw.has(w)) || '';
      }
      if (stock) {
        set('symbol', stock.toUpperCase().slice(0, 20));
        set('company_name', stock);
        found.push('symbol');
      }

      const avg = (arr) => Math.round(arr.reduce((a, b) => a + b, 0) / arr.length * 100) / 100;

      // Pull a list of numbers where COMMA (and range dashes / "to" / & / +) is
      // the separator — one number per chunk. This keeps every target distinct
      // ("1030,1045,1062" -> [1030, 1045, 1062]) instead of merging digits when
      // there are no spaces after the commas.
      const numList = (s) => (s || '')
        .split(/\s*(?:,|\/|–|—|\bto\b|&|\+|\s-\s|-)\s*/i)
        .map(chunk => { const m = String(chunk).match(/\d[\d.]*/); return m ? parseFloat(m[0]) : NaN; })
        .filter(v => !isNaN(v));

      // Entry — average ALL numbers on the line (range, comma-list, "add" etc.).
      const entryRaw = labelled('entry|buy above|buy|bought|cmp|price|@|buy zone|range') || loose('entry|cmp|price');
      const eList = numList(entryRaw);
      const entry = eList.length ? avg(eList) : '';

      // Targets — capture ALL, comma-separated.
      const tLine = labelled('targets?|tgt|tp|t1|t2|t3') || loose('targets?|tgt|tp');
      const tList = numList(tLine);

      // Stop-loss — capture ALL (list, like targets).
      const slLine = labelled('stop ?loss(?:es)?|stoploss|sl|s/l') || loose('stop ?loss|stoploss|sl');
      const slList = numList(slLine);

      if (entry !== '') {
        set('entry_price', entry);
        found.push(eList.length > 1 ? 'entry(avg)' : 'entry');
      }
      if (tList.length) {
        set('targets', tList.join(', '));
        found.push(tList.length > 1 ? 'targets(' + tList.length + ')' : 'target');
      }
      if (slList.length) {
        set('stop_losses', slList.join(', '));
        found.push(slList.length > 1 ? 'SL(' + slList.length + ')' : 'SL');
      }

      // Timeframe (anywhere) -> call_type dropdown
      const tfm = text.match(/(\d+\s*(?:[-to]+\s*\d+)?)\s*(day|week|month|year)s?/i);
      const tfWord = /\b(intraday|btst|swing|positional|long ?term)\b/i.exec(text);
      let ct = '';
      if (tfm) {
        const unit = tfm[2].toLowerCase();
        ct = unit === 'day' ? 'intraday' : unit === 'week' ? 'swing'
           : unit === 'month' ? 'positional' : 'longterm';
      } else if (tfWord) {
        const w = tfWord[1].toLowerCase().replace(/\s/g, '');
        ct = w === 'btst' ? 'intraday' : w === 'longterm' ? 'longterm' : w;
      }
      if (ct) { set('call_type', ct); found.push('timeframe'); }

      // Rebuild the outcome dropdown from the now-filled targets (adds live_N options).
      rebuildPostStatus();

      // Outcome detection — an "achieved/booked" message posts a winner directly.
      if (/\b(achiev|booked|profit\s*booked|target\s*hit|tgt\s*hit|all\s*targets?|sl\s*hit|stop\s*hit)\b/i.test(text)) {
        const stopHit = /\b(sl\s*hit|stop\s*hit|stopped\s*out)\b/i.test(text);
        set('post_status', stopHit ? 'stop' : 'all');
        const exitM = text.match(/\b(?:booked|exit|exited|done|closed?)\s*(?:at|@|:|near)?\s*₹?\s*(\d[\d,]*(?:\.\d+)?)/i);
        if (exitM) set('post_exit', num(exitM[1]));
        found.push(stopHit ? 'outcome:SL-hit' : 'outcome:achieved');
        togglePostExit();
      }

      // Keep the full original message as the thesis (extra targets, notes, etc.).
      set('thesis', raw);

      msg.textContent = found.length
        ? '✓ Filled: ' + found.join(', ') + '. Review the fields, then Post call.'
        : 'Could not auto-detect fields — fill the form manually.';
      msg.style.color = found.length ? '#3FB950' : '#E5A50A';
      form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Exit price only matters for a CLOSING outcome (all / stop / closed);
    // hide it for fresh or partial-live calls.
    function togglePostExit() {
      const sel = document.getElementById('postStatus');
      const wrap = document.getElementById('postExitWrap');
      if (!sel || !wrap) return;
      const v = sel.value;
      wrap.style.display = (v === 'all' || v === 'stop' || v === 'closed') ? '' : 'none';

      // "All Targets Achieved" → the realized exit is the FINAL target (last
      // comma value). Auto-fill it so the team doesn't retype; they can still
      // override with a blended/actual exit. Only fill when the field is empty
      // so a manually-typed or parsed exit is never clobbered.
      const exit = document.querySelector('#postExitWrap input[name="post_exit"]');
      const tIn  = document.getElementById('targetsInput');
      if (v === 'all' && exit && tIn && exit.value.trim() === '') {
        const parts = tIn.value.split(',').map(s => { const m = s.match(/\d[\d.]*/); return m ? m[0] : ''; }).filter(Boolean);
        if (parts.length) exit.value = parts[parts.length - 1];
      }
    }

    // Rebuild the "Where does this call go?" dropdown from the Targets field so
    // the team can post a call that has ALREADY hit its 1st/2nd/… target.
    function ordinalJs(n) {
      const s = ['th', 'st', 'nd', 'rd'], v = n % 100;
      return n + (s[(v - 20) % 10] || s[v] || s[0]);
    }
    function rebuildPostStatus() {
      const sel = document.getElementById('postStatus');
      const tIn = document.getElementById('targetsInput');
      if (!sel || !tIn) return;
      // Count targets by COMMA separation (the user's mental model): each
      // comma-separated chunk that holds a number is one target. Works for any
      // count — 3, 4, 5… — and with or without spaces after the commas.
      const nums = tIn.value.split(',').map(s => s.trim()).filter(s => /\d/.test(s)).length;
      const keep = sel.value;
      let html = '<option value="running">🆕 Fresh Call → Calls page (live, nothing hit yet)</option>';
      for (let k = 1; k < nums; k++) {
        html += `<option value="live_${k}">${ordinalJs(k)} Target Achieved → stays on Live Calls</option>`;
      }
      html += '<option value="all">✅ All ' + (nums > 0 ? nums + ' ' : '') + 'Targets Achieved → Performance (win)</option>';
      html += '<option value="stop">🛑 Stop-loss Hit → Performance (loss)</option>';
      html += '<option value="closed">☑️ Closed manually → Performance</option>';
      sel.innerHTML = html;
      // Restore prior selection if it still exists.
      if ([...sel.options].some(o => o.value === keep)) sel.value = keep;
      togglePostExit();
    }

    /* ---- Duplicate-call guard ----
       The team sometimes re-posts a call they already closed (same stock, same
       entry/targets/SL) by mistake — that creates a duplicate winner on the
       Performance page. Before submitting, compare the form against recent calls
       and, if an identical ALREADY-CLOSED call exists, warn the admin. (Re-posting
       an OPEN call to advance its target is handled server-side by the upsert, so
       it is intentionally NOT flagged here.) */
    const RECENT_CALLS = <?php echo json_encode(array_map(function ($c) {
        return [
            'symbol'    => $c['symbol'],
            'action'    => $c['action'],
            'entry'     => (float) $c['entry_price'],
            'targets'   => !empty($c['targets']) ? $c['targets'] : ($c['target_price'] !== null ? (string) $c['target_price'] : ''),
            'sl'        => !empty($c['stop_losses']) ? $c['stop_losses'] : ($c['stop_loss'] !== null ? (string) $c['stop_loss'] : ''),
            'status'    => $c['status'],
            'posted_at' => $c['posted_at'],
        ];
    }, $calls)); ?>;
    (function () {
      const form = document.getElementById('addCallForm');
      if (!form) return;
      // Order-insensitive numeric SET key (sorted) so reversed/respaced lists match.
      const key = s => (String(s || '').match(/\d[\d.]*/g) || []).map(parseFloat).sort((a, b) => a - b).join(',');
      const relAge = iso => {
        const d = new Date((iso || '').replace(' ', 'T'));
        if (isNaN(d)) return { ago: '', when: iso };
        const when = d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: '2-digit' });
        const days = Math.floor((Date.now() - d.getTime()) / 86400000);
        return { ago: days <= 0 ? 'today' : days === 1 ? 'yesterday' : days + ' days back', when };
      };
      form.addEventListener('submit', function (e) {
        const forceEl = document.getElementById('dupForce');
        if (forceEl) forceEl.value = '0';            // reset every attempt
        const sym   = (form.elements['symbol'].value || '').replace(/\s+/g, ' ').trim().toUpperCase();
        const act   = form.elements['call_action'].value;
        const entry = parseFloat(form.elements['entry_price'].value || '0');
        const tk = key(form.elements['targets'].value);
        const sk = key(form.elements['stop_losses'].value);

        // (A) DUPLICATE of an already-closed call → warn, allow explicit override.
        const dup = RECENT_CALLS.find(c =>
          c.symbol === sym && c.action === act &&
          Math.abs(c.entry - entry) < 0.005 &&
          key(c.targets) === tk && key(c.sl) === sk &&
          (c.status === 'target_hit' || c.status === 'stop_hit' || c.status === 'closed'));
        if (dup) {
          const { ago, when } = relAge(dup.posted_at);
          const res = dup.status === 'target_hit' ? 'Target Achieved'
                    : dup.status === 'stop_hit'   ? 'Stop-loss Hit' : 'Closed';
          const ok = confirm(
            '⚠️ Already recorded — looks like a DUPLICATE.\n\n' +
            sym + ' (' + act + ') — entry ₹' + entry + ', targets ' + (dup.targets || '—') + '\n' +
            'was already posted ' + (ago ? ago + ' (' + when + ')' : 'on ' + when) +
            ' and is marked "' + res + '".\n\n' +
            'Same entry, targets and stop-loss are already in the database. ' +
            'Posting again will create a duplicate on the Performance page.\n\nPost anyway?'
          );
          if (!ok) { e.preventDefault(); return; }
          if (forceEl) forceEl.value = '1';          // user confirmed → bypass server block
        } else {
          // (B) An OPEN call on the same stock+side exists with DIFFERENT levels:
          // posting will UPDATE (overwrite) that running call, not create a new one.
          // Heads-up so a genuine second idea / fat-finger doesn't silently clobber it.
          const open = RECENT_CALLS.find(c =>
            c.symbol === sym && c.action === act && c.status === 'open' &&
            (Math.abs(c.entry - entry) >= 0.005 || key(c.targets) !== tk || key(c.sl) !== sk));
          if (open) {
            const ok = confirm(
              'ℹ️ A LIVE ' + act + ' call on ' + sym + ' is already running ' +
              '(entry ₹' + open.entry + ', targets ' + (open.targets || '—') + ').\n\n' +
              'Posting will UPDATE that same running call to these new values, ' +
              'not create a second one.\n\nContinue?'
            );
            if (!ok) { e.preventDefault(); return; }
          }
        }

        // Passed the gates → submitting. Disable the button so a double-click
        // can't fire a second identical POST. (Deferred so the form still submits.)
        const btn = form.querySelector('button[type="submit"]');
        if (btn) setTimeout(() => { btn.disabled = true; btn.textContent = 'Posting…'; }, 0);
      });
    })();

    const CSRF_TOKEN = <?php echo json_encode(csrf_token()); ?>;
    // When admin clicks Share-to-WA, fire-and-forget mark_shared in the background.
    function markShared(id) {
      const fd = new FormData();
      fd.append('action', 'mark_shared');
      fd.append('id', id);
      fd.append('csrf', CSRF_TOKEN);
      // Don't block the WhatsApp window from opening — just send.
      fetch(window.location.href, { method: 'POST', body: fd, credentials: 'same-origin' })
        .catch(() => {});
    }
  </script>
  </div><!-- /.admin-content -->
</div><!-- /.admin-layout -->
</body>
</html>
