<?php
// views/components/debug.php
// Wird nur angezeigt wenn DEBUG=true in index.php UND ?debug=1 in der URL

if (!defined('DEBUG') || !DEBUG || empty($_GET['debug'])) {
    return;
}

$log      = $GLOBALS['_api_debug_log'] ?? [];
$total_ms = array_sum(array_column($log, 'ms'));
?>
<div id="debug-panel" style="
    margin: 32px 0 0;
    font-family: 'Cascadia Code', 'Consolas', monospace;
    font-size: 12px;
">
  <!-- Header -->
  <div style="
      background: #1a1a2e;
      color: #fff;
      padding: 10px 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-radius: 8px 8px 0 0;
  ">
    <span style="font-weight:700; letter-spacing:.05em;">
      🛠 DEBUG — <?= count($log) ?> API-Call<?= count($log) !== 1 ? 's' : '' ?>
    </span>
    <span style="color:#7090b0;">
      Gesamt: <strong style="color:#56d364;"><?= $total_ms ?> ms</strong>
    </span>
  </div>

  <?php foreach ($log as $i => $entry): ?>
  <!-- Call-Block -->
  <div style="
      background: #f8fafc;
      border: 1px solid #e0e6f0;
      border-top: none;
      <?= $i === count($log) - 1 ? 'border-radius: 0 0 8px 8px;' : '' ?>
  ">
    <!-- Call-Header -->
    <div style="
        background: #242d4a;
        color: #c8d8f0;
        padding: 7px 18px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    ">
      <span>
        <span style="color:#7090b0;">#<?= $i + 1 ?></span>
        <strong style="color:#79b8ff; margin-left:10px;">
          POST <?= htmlspecialchars(BASE_URL . $entry['endpoint']) ?>
        </strong>
      </span>
      <span>
        <?php
        $badge_color = $entry['http'] === 200 ? '#56d364' : '#ff6b6b';
        ?>
        <span style="color:<?= $badge_color ?>; font-weight:700;">
          HTTP <?= $entry['http'] ?>
        </span>
        <span style="color:#7090b0; margin-left:14px;"><?= $entry['ms'] ?> ms</span>
        <?php if ($entry['count'] !== null): ?>
          <span style="color:#f0a050; margin-left:14px;">
            <?= $entry['count'] ?> Datensatz<?= $entry['count'] !== 1 ? 'e' : '' ?>
          </span>
        <?php endif; ?>
      </span>
    </div>

    <!-- Request / Response -->
    <div style="display:flex; gap:0; border-top: 1px solid #e0e6f0;">

      <!-- Request -->
      <div style="flex:1; padding:14px 18px; border-right:1px solid #e0e6f0;">
        <div style="
            font-size:10px; font-weight:700; letter-spacing:.08em;
            text-transform:uppercase; color:#7080a0; margin-bottom:8px;
        ">Request-Body</div>
        <pre style="margin:0; color:#1a1a2e; white-space:pre-wrap; word-break:break-all;"><?=
          htmlspecialchars(json_encode($entry['request'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
        ?></pre>
      </div>

      <!-- Response -->
      <div style="flex:1; padding:14px 18px;">
        <div style="
            font-size:10px; font-weight:700; letter-spacing:.08em;
            text-transform:uppercase; color:#7080a0; margin-bottom:8px;
        ">
          Response
          <?php if ($entry['count'] !== null): ?>
            <span style="color:#f0a050; font-weight:400; text-transform:none;">
              (erster Datensatz)
            </span>
          <?php endif; ?>
        </div>
        <?php
        // Bei Listen nur ersten Datensatz zeigen -- nicht alles
        $preview = $entry['response'];
        if (isset($preview['data']['data']) && is_array($preview['data']['data'])) {
            $preview = ['data' => ['data' => [$preview['data']['data'][0] ?? []]], '...' => '(gekuerzt)'];
        } elseif (isset($preview['data']) && is_array($preview['data']) && count($preview['data']) > 1) {
            $preview = ['data' => [$preview['data'][0] ?? []], '...' => '(gekuerzt)'];
        }
        ?>
        <pre style="margin:0; color:#1a1a2e; white-space:pre-wrap; word-break:break-all;"><?=
          htmlspecialchars(json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
        ?></pre>
      </div>

    </div>
  </div>
  <?php endforeach; ?>

  <?php if (empty($log)): ?>
  <div style="
      background:#fff8e0; border:1px solid #e0e6f0; border-top:none;
      padding:14px 18px; color:#7a6010; border-radius:0 0 8px 8px;
  ">
    Keine API-Calls aufgezeichnet. Ist <code>DEBUG = true</code> in <code>index.php</code> gesetzt?
  </div>
  <?php endif; ?>

</div>
