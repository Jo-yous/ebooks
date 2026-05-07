<?php
// ══════════════════════════════════════════════
//  NOT NOTHING — Analytics Tracker
//  Logs unique visits per day and their country.
// ══════════════════════════════════════════════

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Get IP address safely
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Use the first IP in the list if there are multiple
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$ip = getClientIP();
$today = date('Y-m-d');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Check if this IP already visited today
    $stmt = $pdo->prepare("SELECT id FROM page_views WHERE ip_address = :ip AND visit_date = :today");
    $stmt->execute([':ip' => $ip, ':today' => $today]);
    
    if ($stmt->rowCount() === 0) {
        // New visitor today! Let's get their country
        $country = 'Unknown';
        
        // Don't query localhost for country
        if ($ip !== '127.0.0.1' && $ip !== '::1') {
            $ch = curl_init("http://ip-api.com/json/{$ip}?fields=country");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second timeout so we don't block
            $response = curl_exec($ch);
            curl_close($ch);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['country']) && !empty($data['country'])) {
                    $country = $data['country'];
                }
            }
        }

        // Save to DB
        $insert = $pdo->prepare("INSERT IGNORE INTO page_views (ip_address, country, visit_date, created_at) VALUES (:ip, :country, :today, NOW())");
        $insert->execute([
            ':ip' => $ip,
            ':country' => $country,
            ':today' => $today
        ]);
        
        echo json_encode(['status' => 'logged', 'country' => $country]);
    } else {
        echo json_encode(['status' => 'already_logged_today']);
    }

} catch (PDOException $e) {
    // Fail silently so we don't break the frontend
    error_log('Tracking error: ' . $e->getMessage());
    echo json_encode(['status' => 'error']);
}
