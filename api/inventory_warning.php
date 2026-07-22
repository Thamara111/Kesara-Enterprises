<?php
/**
 * Inventory Pressure Warning Email
 * Sends a warning email to admins, supplier managers, and finance managers
 * with a summary of critical / low-stock / out-of-stock items.
 * Intended to be triggered via cron or an external scheduler.
 */

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../src/Mailer.php';

header('Content-Type: application/json');

// Only allow POST to trigger the email, acting as a webhook
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

if (!$pdo) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Database unavailable.']);
    exit;
}

// ── 1. Fetch all warning-level inventory items ────────────────────────────────
// Join inventory with products, fetching anything where quantity is at or below the restock_min
try {
    $stmt = $pdo->query("
        SELECT i.id, p.name AS product_name, p.sku,
               i.size, i.colour, i.quantity AS stock, i.restock_min AS thresh
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE i.quantity <= i.restock_min
        ORDER BY (i.quantity / NULLIF(i.restock_min,0)) ASC
    ");
    $items = $stmt->fetchAll();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch inventory: ' . $e->getMessage()]);
    exit;
}

// Exit early if the inventory is healthy and no items are under their thresholds
if (empty($items)) {
    echo json_encode(['status' => 'ok', 'message' => 'No inventory pressure items found. No email sent.']);
    exit;
}

// ── 2. Fetch recipients: admin, supplier_manager, and finance_manager roles ───
// Get the contact details of the staff responsible for inventory management
try {
    $recipientStmt = $pdo->prepare("
        SELECT username, email, role
        FROM admins
        WHERE role IN ('admin', 'supplier_manager', 'finance_manager')
          AND email IS NOT NULL AND email <> ''
    ");
    $recipientStmt->execute();
    $recipients = $recipientStmt->fetchAll();
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to fetch recipients: ' . $e->getMessage()]);
    exit;
}

if (empty($recipients)) {
    echo json_encode(['status' => 'error', 'message' => 'No admin, supply manager, or finance manager email addresses found in the system.']);
    exit;
}

// ── 3. Categorise items ───────────────────────────────────────────────────────
// Sort items into three buckets for the email summary: Out of Stock, Critical, Low Stock
$out_of_stock = [];
$critical     = [];
$low_stock    = [];

foreach ($items as $item) {
    // Calculate percentage remaining vs the restock threshold
    $pct = $item['thresh'] > 0 ? round(($item['stock'] / $item['thresh']) * 100) : 0;
    $item['pct'] = $pct;
    // Format a readable product label including its variant data (color, size)
    $label = $item['product_name'] . ' · ' . $item['colour'] . ' · ' . $item['size'];
    $item['label'] = $label;

    if ($item['stock'] == 0) {
        $out_of_stock[] = $item;
    } elseif ($pct <= 15) { // If stock is 15% or less of the threshold, it's critical
        $critical[] = $item;
    } else {
        $low_stock[] = $item;
    }
}

$now = date('d M Y, H:i');
$total = count($items);

// ── 4. Build HTML email body ──────────────────────────────────────────────────
function buildRows(array $items, string $color, string $badgeBg, string $badgeText, string $statusLabel): string {
    $html = '';
    foreach ($items as $item) {
        $pctDisplay = $item['stock'] == 0 ? '0' : $item['pct'];
        $html .= "
        <tr>
            <td style=\"padding:10px 14px;border-bottom:1px solid #f3f4f6;\">
                <div style=\"font-weight:700;color:#111827;font-size:13px;\">{$item['label']}</div>
                <div style=\"color:#6b7280;font-size:11px;margin-top:2px;\">SKU: {$item['sku']}</div>
            </td>
            <td style=\"padding:10px 14px;border-bottom:1px solid #f3f4f6;text-align:center;font-weight:800;color:{$color};font-size:14px;\">{$item['stock']}</td>
            <td style=\"padding:10px 14px;border-bottom:1px solid #f3f4f6;text-align:center;color:#6b7280;font-size:13px;\">{$item['thresh']}</td>
            <td style=\"padding:10px 14px;border-bottom:1px solid #f3f4f6;text-align:center;\">
                <span style=\"display:inline-block;padding:3px 10px;border-radius:999px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;background:{$badgeBg};color:{$badgeText};\">{$statusLabel}</span>
            </td>
        </tr>";
    }
    return $html;
}

$outRows      = buildRows($out_of_stock, '#374151', '#e5e7eb', '#374151', 'Out of Stock');
$criticalRows = buildRows($critical,     '#991b1b', '#fee2e2', '#991b1b', 'Critical');
$lowRows      = buildRows($low_stock,    '#92400e', '#fef3c7', '#92400e', 'Low Stock');

$tableRows = $outRows . $criticalRows . $lowRows;

$emailBody = "
<!DOCTYPE html>
<html lang=\"en\">
<head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"></head>
<body style=\"margin:0;padding:0;background:#f9fafb;font-family:'Segoe UI',Arial,sans-serif;\">
<table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#f9fafb;padding:40px 0;\">
<tr><td align=\"center\">
<table width=\"600\" cellpadding=\"0\" cellspacing=\"0\" style=\"background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);\">

  <!-- Header -->
  <tr>
    <td style=\"background:linear-gradient(135deg,#0F6E56,#0a5040);padding:32px 36px;\">
      <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
        <tr>
          <td>
            <div style=\"font-size:11px;font-weight:700;color:#a7f3d0;text-transform:uppercase;letter-spacing:.1em;margin-bottom:6px;\">Kesara Enterprises · Inventory Alert</div>
            <h1 style=\"margin:0;font-size:22px;font-weight:800;color:#ffffff;line-height:1.3;\">⚠️ Inventory Pressure Warning</h1>
            <p style=\"margin:8px 0 0;color:#a7f3d0;font-size:13px;\">Generated on {$now}</p>
          </td>
          <td align=\"right\" style=\"vertical-align:top;\">
            <div style=\"background:rgba(255,255,255,.15);border-radius:12px;padding:16px 20px;text-align:center;\">
              <div style=\"font-size:32px;font-weight:900;color:#ffffff;line-height:1;\">{$total}</div>
              <div style=\"font-size:10px;font-weight:700;color:#a7f3d0;text-transform:uppercase;letter-spacing:.05em;margin-top:4px;\">Items Flagged</div>
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Summary Tiles -->
  <tr>
    <td style=\"padding:24px 36px 0;\">
      <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\">
        <tr>
          <td width=\"33%\" style=\"padding-right:8px;\">
            <div style=\"background:#fee2e2;border-radius:12px;padding:14px 16px;text-align:center;\">
              <div style=\"font-size:24px;font-weight:900;color:#991b1b;\">" . count($out_of_stock) . "</div>
              <div style=\"font-size:10px;font-weight:700;color:#b91c1c;text-transform:uppercase;letter-spacing:.05em;margin-top:4px;\">Out of Stock</div>
            </div>
          </td>
          <td width=\"33%\" style=\"padding:0 4px;\">
            <div style=\"background:#fee2e2;border-radius:12px;padding:14px 16px;text-align:center;\">
              <div style=\"font-size:24px;font-weight:900;color:#991b1b;\">" . count($critical) . "</div>
              <div style=\"font-size:10px;font-weight:700;color:#b91c1c;text-transform:uppercase;letter-spacing:.05em;margin-top:4px;\">Critical</div>
            </div>
          </td>
          <td width=\"33%\" style=\"padding-left:8px;\">
            <div style=\"background:#fef3c7;border-radius:12px;padding:14px 16px;text-align:center;\">
              <div style=\"font-size:24px;font-weight:900;color:#92400e;\">" . count($low_stock) . "</div>
              <div style=\"font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.05em;margin-top:4px;\">Low Stock</div>
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>

  <!-- Intro -->
  <tr>
    <td style=\"padding:20px 36px 8px;\">
      <p style=\"margin:0;color:#374151;font-size:14px;line-height:1.6;\">
        The following inventory items have fallen to or below their restock threshold and require immediate attention.
        Please review and initiate purchase orders as needed.
      </p>
    </td>
  </tr>

  <!-- Table -->
  <tr>
    <td style=\"padding:0 36px 28px;\">
      <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border:1px solid #e5e7eb;border-radius:12px;overflow:hidden;margin-top:12px;\">
        <thead>
          <tr style=\"background:#f9fafb;\">
            <th style=\"padding:10px 14px;text-align:left;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid #e5e7eb;\">Product / Variant</th>
            <th style=\"padding:10px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid #e5e7eb;\">Stock</th>
            <th style=\"padding:10px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid #e5e7eb;\">Min</th>
            <th style=\"padding:10px 14px;text-align:center;font-size:10px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.08em;border-bottom:1px solid #e5e7eb;\">Status</th>
          </tr>
        </thead>
        <tbody>
          {$tableRows}
        </tbody>
      </table>
    </td>
  </tr>

  <!-- CTA -->
  <tr>
    <td style=\"padding:0 36px 28px;\">
      <div style=\"background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:18px 20px;\">
        <p style=\"margin:0;font-size:13px;color:#065f46;font-weight:600;\">
          💡 Action required: Log in to the Kesara Enterprises admin panel → Inventory to review stock levels and create purchase orders.
        </p>
      </div>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style=\"background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 36px;text-align:center;\">
      <p style=\"margin:0;font-size:11px;color:#9ca3af;\">This is an automated inventory alert from Kesara Enterprises. Do not reply to this email.</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>
";

// ── 5. Send email to each recipient ──────────────────────────────────────────
$sent    = [];
$failed  = [];
$subject = "⚠️ Inventory Pressure Warning — {$total} Items Need Attention ({$now})";

foreach ($recipients as $rec) {
    $ok = \App\Mailer::send($rec['email'], $subject, $emailBody);
    if ($ok) {
        $sent[] = $rec['email'];
    } else {
        $failed[] = $rec['email'];
    }
}

// ── 6. Return result ──────────────────────────────────────────────────────────
echo json_encode([
    'status'       => empty($failed) ? 'success' : (empty($sent) ? 'error' : 'partial'),
    'message'      => empty($failed)
        ? 'Warning email sent to ' . count($sent) . ' recipient(s).'
        : 'Sent to ' . count($sent) . ', failed for ' . count($failed) . ' recipient(s).',
    'sent_to'      => $sent,
    'failed_for'   => $failed,
    'items_flagged'=> $total,
]);
