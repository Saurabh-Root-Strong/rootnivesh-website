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
        $postStatus = in_array($_POST['post_status'] ?? 'open', ['open','target_hit','stop_hit','closed'], true)
                    ? $_POST['post_status'] : 'open';
        $entryVal = floatval($_POST['entry_price'] ?? 0);
        $exitVal = null; $exitAt = null; $pnlVal = null;
        if ($postStatus !== 'open') {
            if (($_POST['post_exit'] ?? '') !== '' && preg_match('/\d+(?:\.\d+)?/', $_POST['post_exit'], $em)) {
                $exitVal = floatval($em[0]);
            }
            if ($exitVal !== null && $entryVal > 0) {
                $isBuy  = ($_POST['call_action'] ?? 'BUY') === 'BUY';
                $pnlVal = $isBuy ? round(($exitVal - $entryVal) / $entryVal * 100, 2)
                                 : round(($entryVal - $exitVal) / $entryVal * 100, 2);
                $exitAt = date('Y-m-d H:i:s');
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO calls (call_type, action, symbol, company_name, entry_price, target_price, targets, stop_loss, stop_losses, thesis, is_public, created_by, status, exit_price, exit_at, pnl_pct)
             VALUES (:call_type, :action, :symbol, :company_name, :entry, :target, :targets, :stop, :stop_losses, :thesis, :is_public, :created_by, :status, :exit, :exit_at, :pnl)'
        );
        $stmt->execute([
            ':call_type'    => $_POST['call_type'] ?? 'intraday',
            ':action'       => $_POST['call_action'] ?? 'BUY',
            ':symbol'       => strtoupper(trim($_POST['symbol'] ?? '')),
            ':company_name' => trim($_POST['company_name'] ?? '') ?: null,
            ':entry'        => $entryVal,
            ':target'       => $targetNum,
            ':targets'      => $targetsText !== '' ? $targetsText : null,
            ':stop'         => $slNum,
            ':stop_losses'  => $slText !== '' ? $slText : null,
            ':thesis'       => trim($_POST['thesis'] ?? ''),
            ':is_public'    => 1,   // every posted call publishes — no hidden-by-accident trap
            ':created_by'   => admin_user() ?: null,
            ':status'       => $postStatus,
            ':exit'         => $exitVal,
            ':exit_at'      => $exitAt,
            ':pnl'          => $pnlVal,
        ]);
        $flash = 'Call posted successfully (#' . $pdo->lastInsertId() . ').';
    } catch (PDOException $e) {
        error_log('admin add call failed: ' . $e->getMessage());
        $flash = 'Could not post the call. Please try again.';
    }
}

/* ---------- Handle POST: close / mark a call ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    try {
        $allowedStatus = ['open','target_hit','stop_hit','closed','cancelled'];
        $status = in_array($_POST['status'] ?? '', $allowedStatus, true) ? $_POST['status'] : 'open';
        $stmt = $pdo->prepare(
            'UPDATE calls SET status = :status, exit_price = :exit, exit_at = :exit_at, pnl_pct = :pnl, is_public = :pub, targets_hit = :thit
             WHERE id = :id'
        );
        $pub  = isset($_POST['is_public']) ? 1 : 0;
        $thit = max(0, intval($_POST['targets_hit'] ?? 0));
        $exit = $_POST['exit_price'] !== '' ? floatval($_POST['exit_price']) : null;
        // Auto-compute PnL% from entry vs exit when both known.
        $pnl = null;
        if ($exit !== null) {
            $row = $pdo->prepare('SELECT action, entry_price FROM calls WHERE id = :id');
            $row->execute([':id' => intval($_POST['id'])]);
            $r = $row->fetch();
            if ($r && $r['entry_price'] > 0) {
                $pnl = $r['action'] === 'BUY'
                    ? round(($exit - $r['entry_price']) / $r['entry_price'] * 100, 2)
                    : round(($r['entry_price'] - $exit) / $r['entry_price'] * 100, 2);
            }
        }
        $stmt->execute([
            ':status'  => $status,
            ':exit'    => $exit,
            ':exit_at' => $exit !== null ? date('Y-m-d H:i:s') : null,
            ':pnl'     => $pnl,
            ':pub'     => $pub,
            ':thit'    => $thit,
            ':id'      => intval($_POST['id']),
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

/* ---------- Fetch recent calls ---------- */
$calls = $pdo->query('SELECT * FROM calls ORDER BY posted_at DESC LIMIT 50')->fetchAll();

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
  <header class="admin-header">
    <h1>RootNivesh Admin</h1>
    <span class="admin-link">
      Signed in as <strong><?php echo htmlspecialchars(admin_display()); ?></strong> (<?php echo htmlspecialchars(admin_role()); ?>)
      &nbsp;·&nbsp;
      <?php if (admin_is_owner()): ?><a href="users.php" class="admin-link">Team</a> &nbsp;·&nbsp; <?php endif; ?>
      <a href="logout.php" class="admin-link">Logout</a>
    </span>
  </header>

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
            <input type="text" name="targets" placeholder="e.g. 1030, 1045, 1062">
          </label>
          <label>Stop-Loss(es)
            <input type="text" name="stop_losses" placeholder="e.g. 987  or  987, 980">
          </label>
        </div>
        <div class="admin-row">
          <label>Where does this call go? *
            <select name="post_status" id="postStatus" onchange="togglePostExit()">
              <option value="open">🆕 Fresh Call → shows on the Calls page (live)</option>
              <option value="target_hit">✅ Target Achieved → shows on Performance (win)</option>
              <option value="stop_hit">🛑 Stop-loss Hit → shows on Performance (loss)</option>
              <option value="closed">☑️ Closed manually → shows on Performance</option>
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
      <?php if (empty($calls)): ?>
        <p style="color:#8A9BB0">No calls posted yet. Add your first call above.</p>
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
                <select name="status">
                  <option value="open"        <?php if ($c['status']==='open')        echo 'selected'; ?>>Open</option>
                  <option value="target_hit"  <?php if ($c['status']==='target_hit')  echo 'selected'; ?>>Target hit</option>
                  <option value="stop_hit"    <?php if ($c['status']==='stop_hit')    echo 'selected'; ?>>Stop hit</option>
                  <option value="closed"      <?php if ($c['status']==='closed')      echo 'selected'; ?>>Closed</option>
                  <option value="cancelled"   <?php if ($c['status']==='cancelled')   echo 'selected'; ?>>Cancelled</option>
                </select>
                <input type="number" min="0" step="1" name="targets_hit" placeholder="Targets hit" title="How many targets achieved so far (e.g. 1 = first target hit, call still running)" style="width:90px" value="<?php echo intval($c['targets_hit'] ?? 0); ?>">
                <input type="number" step="0.01" name="exit_price" placeholder="Avg exit ₹" title="If you booked partials across targets, enter the blended average exit price" value="<?php echo $c['exit_price'] !== null ? htmlspecialchars($c['exit_price']) : ''; ?>">
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

      // Entry — average ALL numbers on the line (range, comma-list, "add" etc.).
      const entryRaw = labelled('entry|buy above|buy|bought|cmp|price|@|buy zone|range') || loose('entry|cmp|price');
      const eList = (entryRaw.match(/\d[\d,]*(?:\.\d+)?/g) || []).map(num).filter(v => v !== '');
      const entry = eList.length ? avg(eList) : '';

      // Targets — capture ALL.
      const tLine = labelled('targets?|tgt|tp|t1|t2|t3') || loose('targets?|tgt|tp');
      const tList = (tLine.match(/\d[\d,]*(?:\.\d+)?/g) || []).map(num).filter(v => v !== '');

      // Stop-loss — capture ALL (list, like targets).
      const slLine = labelled('stop ?loss(?:es)?|stoploss|sl|s/l') || loose('stop ?loss|stoploss|sl');
      const slList = (slLine.match(/\d[\d,]*(?:\.\d+)?/g) || []).map(num).filter(v => v !== '');

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

      // Outcome detection — an "achieved/booked" message posts a winner directly.
      if (/\b(achiev|booked|profit\s*booked|target\s*hit|tgt\s*hit|all\s*targets?|sl\s*hit|stop\s*hit)\b/i.test(text)) {
        const stopHit = /\b(sl\s*hit|stop\s*hit|stopped\s*out)\b/i.test(text);
        set('post_status', stopHit ? 'stop_hit' : 'target_hit');
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

    // Show the exit-price field only when the call is being posted as a
    // completed outcome (target/stop/closed), hide it for a fresh open call.
    function togglePostExit() {
      const sel = document.getElementById('postStatus');
      const wrap = document.getElementById('postExitWrap');
      if (sel && wrap) wrap.style.display = (sel.value === 'open') ? 'none' : '';
    }

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
</body>
</html>
