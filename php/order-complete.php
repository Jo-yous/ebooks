<?php
// ══════════════════════════════════════════════
//  NOT NOTHING — Order Complete Handler
//  Called by JavaScript after PayPal captures
//  the payment. Logs the order + sends emails.
// ══════════════════════════════════════════════

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit(); }

require_once __DIR__ . '/config.php';

// ── Read JSON body ───────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { http_response_code(400); echo json_encode(['error' => 'Invalid JSON']); exit(); }

$order_id    = htmlspecialchars($body['order_id']    ?? '');
$payer_name  = htmlspecialchars($body['payer_name']  ?? '');
$payer_email = htmlspecialchars($body['payer_email'] ?? '');
$buyer_email = htmlspecialchars($body['buyer_email'] ?? '');
$format      = htmlspecialchars($body['format']      ?? '');
$amount      = floatval($body['amount'] ?? 0);
$status      = htmlspecialchars($body['status']      ?? '');

// Basic validation
if (!$order_id || !$format || !$amount) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields']);
    exit();
}

$contact_email = $buyer_email ?: $payer_email;

// ── Save to Database ─────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare("
        INSERT INTO orders (paypal_order_id, payer_name, payer_email, buyer_email, format, amount, status, created_at)
        VALUES (:order_id, :payer_name, :payer_email, :buyer_email, :format, :amount, :status, NOW())
    ");
    $stmt->execute([
        ':order_id'    => $order_id,
        ':payer_name'  => $payer_name,
        ':payer_email' => $payer_email,
        ':buyer_email' => $buyer_email,
        ':format'      => $format,
        ':amount'      => $amount,
        ':status'      => $status,
    ]);
    $db_id = $pdo->lastInsertId();

} catch (PDOException $e) {
    // Log but don't block — payment already went through
    error_log('DB error: ' . $e->getMessage());
    $db_id = 0;
}

// ── Format labels & eBook delivery message ───
$format_labels = [
    'ebook'     => 'eBook (Digital Download)',
    'paperback' => 'Paperback (Physical)',
    'hardcover' => 'Hardcover (Physical)',
];
$format_label = $format_labels[$format] ?? ucfirst($format);

$delivery_msg = ($format === 'ebook')
    ? "Your eBook will be delivered to your email within 24 hours."
    : "Your physical book will be shipped to the address confirmed via PayPal. Please allow 5-10 business days for delivery.";

// ── Send confirmation email to buyer ────────
if ($contact_email) {
    $buyer_subject = "Your order: " . BOOK_TITLE;
    $buyer_body = "Hi {$payer_name},\n\n"
        . "Thank you for purchasing " . BOOK_TITLE . "!\n\n"
        . "Order Details:\n"
        . "─────────────────────────\n"
        . "Order ID:  {$order_id}\n"
        . "Format:    {$format_label}\n"
        . "Amount:    \${$amount} USD\n"
        . "Status:    COMPLETED\n"
        . "─────────────────────────\n\n"
        . $delivery_msg . "\n\n"
        . "If you have any questions, reply to this email.\n\n"
        . "Thank you,\n" . SELLER_NAME . "\n" . SITE_URL;

    $buyer_headers = "From: " . SELLER_NAME . " <" . FROM_EMAIL . ">\r\n"
        . "Reply-To: " . SELLER_EMAIL . "\r\n"
        . "X-Mailer: PHP/" . phpversion();

    @mail($contact_email, $buyer_subject, $buyer_body, $buyer_headers);
}

// ── Notify seller ────────────────────────────
$seller_subject = "💰 New Sale! {$format_label} — \${$amount}";
$seller_body = "You just made a sale!\n\n"
    . "Buyer:     {$payer_name}\n"
    . "Email:     {$contact_email}\n"
    . "Format:    {$format_label}\n"
    . "Amount:    \${$amount} USD\n"
    . "PayPal ID: {$order_id}\n"
    . "DB Row:    #{$db_id}\n"
    . "Time:      " . date('Y-m-d H:i:s') . " UTC\n\n"
    . ($format !== 'ebook' ? "⚠️ Physical order — you need to ship this!\n" : "✅ eBook — send the download link.\n");

$seller_headers = "From: orders <" . FROM_EMAIL . ">\r\n"
    . "X-Mailer: PHP/" . phpversion();

@mail(SELLER_EMAIL, $seller_subject, $seller_body, $seller_headers);

// ── Respond ──────────────────────────────────
echo json_encode(['success' => true, 'order_id' => $order_id, 'db_id' => $db_id]);
