<?php
/**
 * upload.php — eBook Daily Submission Handler
 * Saves form data to MySQL (globalmedia_ebooks) + stores files in /submissions/
 */

// ── Suppress PHP errors from breaking JSON output ──
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

// ── CORS ──────────────────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['success' => false, 'message' => 'Method not allowed']);
}

// ── DATABASE CONFIG — fill in your AlwaysData credentials ────────────────────
define('DB_HOST', 'mysql-globalmedia.alwaysdata.net');
define('DB_NAME', 'globalmedia_ebooks');
define('DB_USER', 'globalmedia_ebooks');        // ← your AlwaysData username
define('DB_PASS', 'S.2vw82b@BfPtq@'); // ← your AlwaysData DB password

// ── FILE UPLOAD CONFIG ────────────────────────────────────────────────────────
define('UPLOAD_BASE_DIR', __DIR__ . '/submissions/');

$allowedMimes = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$allowedExts = ['pdf', 'doc', 'docx'];

// ── Helpers ───────────────────────────────────────────────────────────────────
function respond(array $data): void
{
    ob_end_clean();
    echo json_encode($data);
    exit;
}

function sanitizeName(string $name): string
{
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
}

// ── Read POST fields ──────────────────────────────────────────────────────────
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$date = trim($_POST['date'] ?? date('Y-m-d'));
$notes = trim($_POST['notes'] ?? '');
$booksJson = $_POST['books'] ?? '[]';
$books = json_decode($booksJson, true);
if (!is_array($books))
    $books = [];

if (!$name)
    respond(['success' => false, 'message' => 'Name is required']);
if (!$phone)
    respond(['success' => false, 'message' => 'Phone is required']);
if (empty($books))
    respond(['success' => false, 'message' => 'At least one book title is required']);

// ── Create upload directory ───────────────────────────────────────────────────
$safeName = sanitizeName($name);
$safeDate = preg_replace('/[^0-9\-]/', '', $date);
$folderName = $safeName . '_' . $safeDate;
$targetDir = UPLOAD_BASE_DIR . $folderName . '/';

if (!is_dir(UPLOAD_BASE_DIR))
    mkdir(UPLOAD_BASE_DIR, 0755, true);
if (!is_dir($targetDir))
    mkdir($targetDir, 0755, true);

// ── Handle file uploads ───────────────────────────────────────────────────────
$uploadedFiles = [];
$errors = [];

if (!empty($_FILES['files'])) {
    $files = $_FILES['files'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 1;

    for ($i = 0; $i < $fileCount; $i++) {
        $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
        $fileTmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
        $fileError = is_array($files['error']) ? $files['error'][$i] : $files['error'];
        $fileSize = is_array($files['size']) ? $files['size'][$i] : $files['size'];

        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "$fileName: upload error $fileError";
            continue;
        }

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            $errors[] = "$fileName: type .$ext not allowed";
            continue;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);
        if (!in_array($realMime, $allowedMimes)) {
            $errors[] = "$fileName: MIME not allowed";
            continue;
        }

        $safeFileName = sanitizeName(pathinfo($fileName, PATHINFO_FILENAME)) . '.' . $ext;
        $destination = $targetDir . $safeFileName;

        if (move_uploaded_file($fileTmp, $destination)) {
            $uploadedFiles[] = [
                'original' => $fileName,
                'saved_as' => $safeFileName,
                'size_mb' => number_format($fileSize / 1048576, 2),
                'path' => 'submissions/' . $folderName . '/' . $safeFileName,
            ];
        } else {
            $errors[] = "$fileName: failed to save";
        }
    }
}

// ── Save to MySQL ─────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Pad books array to 10 slots
    $b = array_pad(array_values($books), 10, null);

    $fileNames = implode('; ', array_column($uploadedFiles, 'original'));
    $folderPath = 'submissions/' . $folderName . '/';

    $stmt = $pdo->prepare("
        INSERT INTO ebook_submissions
            (submission_date, writer_name, phone,
             total_books,
             book_01, book_02, book_03, book_04, book_05,
             book_06, book_07, book_08, book_09, book_10,
             files_uploaded, file_names, folder_path, notes)
        VALUES
            (:date, :name, :phone,
             :total,
             :b1, :b2, :b3, :b4, :b5,
             :b6, :b7, :b8, :b9, :b10,
             :files_count, :file_names, :folder_path, :notes)
    ");

    // ── Duplicate submission check ────────────────────────────────────────────
    // Reject if same writer already submitted the exact same book(s) today
    $checkStmt = $pdo->prepare("
        SELECT id FROM ebook_submissions
        WHERE writer_name = :name
          AND submission_date = :date
          AND book_01 = :b1
        LIMIT 1
    ");
    $checkStmt->execute([':name' => $name, ':date' => $date, ':b1' => $b[0]]);
    if ($checkStmt->fetch()) {
        respond([
            'success' => false,
            'message' => "You already submitted these books today, $name. Use \"Submit More\" if you have new books to add."
        ]);
    }

    $stmt->execute([
        ':date' => $date,
        ':name' => $name,
        ':phone' => $phone,
        ':total' => count($books),
        ':b1' => $b[0],
        ':b2' => $b[1],
        ':b3' => $b[2],
        ':b4' => $b[3],
        ':b5' => $b[4],
        ':b6' => $b[5],
        ':b7' => $b[6],
        ':b8' => $b[7],
        ':b9' => $b[8],
        ':b10' => $b[9],
        ':files_count' => count($uploadedFiles),
        ':file_names' => $fileNames ?: null,
        ':folder_path' => $folderPath,
        ':notes' => $notes ?: null,
    ]);

    respond([
        'success' => true,
        'message' => 'Submission saved to database!',
        'booksCount' => count($books),
        'files' => $uploadedFiles,
        'errors' => $errors,
    ]);

} catch (PDOException $e) {
    respond(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
