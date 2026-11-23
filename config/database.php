<?php
/**
 * Database Configuration & Connector
 * Reads settings from .env file or environment variables.
 * Supports MySQL / MariaDB and provides an automatic SQLite fallback
 * so development, WSL, and local testing work seamlessly.
 */

// Set default timezone to Philippine Standard Time
date_default_timezone_set('Asia/Manila');

// Simple .env parser
function load_env($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, '"\'');
            $env[$key] = $value;
            $_ENV[$key] = $value;
        }
    }
    return $env;
}

// Load .env from project root
$envPath = dirname(__DIR__) . '/.env';
$config = load_env($envPath);

$db_connection = strtolower($config['DB_CONNECTION'] ?? 'mysql');
$db_host = $config['DB_HOST'] ?? '127.0.0.1';
$db_port = $config['DB_PORT'] ?? '3306';
$db_name = $config['DB_DATABASE'] ?? 'school_portal';
$db_user = $config['DB_USERNAME'] ?? 'school_user';
$db_pass = $config['DB_PASSWORD'] ?? 'SchoolPass123!';

$db_connected = false;
$db_error_message = '';
$pdo = null;
$active_driver = 'none';

// Attempt MySQL connection if not explicitly configured to sqlite
if ($db_connection !== 'sqlite') {
    try {
        $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 2,
        ];
        $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        $db_connected = true;
        $active_driver = 'mysql';
    } catch (PDOException $e) {
        $db_connected = false;
        $db_error_message = $e->getMessage();
    }
}

// If MySQL is not reachable or SQLite is explicitly requested, fall back to SQLite
if (!$db_connected) {
    try {
        $sqlite_dir = dirname(__DIR__) . '/database';
        if (!is_dir($sqlite_dir)) {
            mkdir($sqlite_dir, 0777, true);
        }
        $sqlite_file = $sqlite_dir . '/school_portal.sqlite';
        $pdo = new PDO("sqlite:" . $sqlite_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA foreign_keys = ON;");
        $db_connected = true;
        $active_driver = 'sqlite';
        $db_error_message = ''; // Cleared because fallback is active
    } catch (PDOException $e) {
        $db_connected = false;
        $db_error_message .= " | SQLite fallback error: " . $e->getMessage();
    }
}

/**
 * Auto-creates schema tables if they do not exist
 */
function ensure_schema_exists(PDO $db, string $driver) {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        if ($driver === 'sqlite') {
            $db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    role TEXT NOT NULL DEFAULT 'announcer',
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    slug TEXT NOT NULL UNIQUE,
                    badge_color TEXT DEFAULT 'primary',
                    icon TEXT DEFAULT 'megaphone',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS announcements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    summary TEXT DEFAULT NULL,
                    content TEXT NOT NULL,
                    category TEXT NOT NULL,
                    category_id INTEGER DEFAULT NULL,
                    image_path TEXT DEFAULT NULL,
                    created_by INTEGER NOT NULL,
                    status TEXT NOT NULL DEFAULT 'pending',
                    rejection_reason TEXT DEFAULT NULL,
                    reviewed_by INTEGER DEFAULT NULL,
                    reviewed_at DATETIME DEFAULT NULL,
                    target_audience TEXT DEFAULT 'All',
                    is_urgent INTEGER DEFAULT 0,
                    is_pinned INTEGER DEFAULT 0,
                    views_count INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
                );

                CREATE TABLE IF NOT EXISTS announcement_images (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    announcement_id INTEGER NOT NULL,
                    image_path TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
                );
            ");
        } else {
            // MySQL tables
            $db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    email VARCHAR(150) NOT NULL UNIQUE,
                    password_hash VARCHAR(255) NOT NULL,
                    role ENUM('admin', 'announcer') NOT NULL DEFAULT 'announcer',
                    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS categories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL UNIQUE,
                    slug VARCHAR(100) NOT NULL UNIQUE,
                    badge_color VARCHAR(50) DEFAULT 'primary',
                    icon VARCHAR(50) DEFAULT 'megaphone',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS announcements (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    summary VARCHAR(350) DEFAULT NULL,
                    content TEXT NOT NULL,
                    category VARCHAR(100) NOT NULL,
                    category_id INT DEFAULT NULL,
                    image_path VARCHAR(255) DEFAULT NULL,
                    created_by INT NOT NULL,
                    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
                    rejection_reason TEXT DEFAULT NULL,
                    reviewed_by INT DEFAULT NULL,
                    reviewed_at DATETIME DEFAULT NULL,
                    target_audience ENUM('All', 'Students', 'Faculty', 'Parents') DEFAULT 'All',
                    is_urgent TINYINT(1) DEFAULT 0,
                    is_pinned TINYINT(1) DEFAULT 0,
                    views_count INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
                    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS announcement_images (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    announcement_id INT NOT NULL,
                    image_path VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_announcement (announcement_id),
                    CONSTRAINT fk_images_announcement FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
        }

        // Ensure organization tables and logo_path columns exist in all drivers
        if ($driver === 'sqlite') {
            $db->exec("
                CREATE TABLE IF NOT EXISTS offices (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    email TEXT DEFAULT NULL,
                    head TEXT DEFAULT NULL,
                    logo_path TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS colleges (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    dean TEXT DEFAULT NULL,
                    logo_path TEXT DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                CREATE TABLE IF NOT EXISTS departments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    code TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    college TEXT NOT NULL,
                    chairperson TEXT DEFAULT NULL,
                    logo_path TEXT DEFAULT NULL,
                    status TEXT NOT NULL DEFAULT 'active',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");
            foreach (['offices', 'colleges', 'departments'] as $tbl) {
                try { $db->exec("ALTER TABLE {$tbl} ADD COLUMN logo_path TEXT DEFAULT NULL"); } catch (Exception $e) {}
            }
        } else {
            $db->exec("
                CREATE TABLE IF NOT EXISTS offices (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    name VARCHAR(150) NOT NULL,
                    email VARCHAR(150) DEFAULT NULL,
                    head VARCHAR(100) DEFAULT NULL,
                    logo_path VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS colleges (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    name VARCHAR(150) NOT NULL,
                    dean VARCHAR(100) DEFAULT NULL,
                    logo_path VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                CREATE TABLE IF NOT EXISTS departments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    name VARCHAR(150) NOT NULL,
                    college VARCHAR(150) NOT NULL,
                    chairperson VARCHAR(100) DEFAULT NULL,
                    logo_path VARCHAR(255) DEFAULT NULL,
                    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            foreach (['offices', 'colleges', 'departments'] as $tbl) {
                try { $db->exec("ALTER TABLE `{$tbl}` ADD COLUMN IF NOT EXISTS `logo_path` VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
            }
            try { $db->exec("ALTER TABLE `departments` ADD COLUMN IF NOT EXISTS `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active'"); } catch (Exception $e) {}
        }

        // Auto-sync legacy single image_path into announcement_images table so photos never get lost
        try {
            $db->exec("
                INSERT INTO announcement_images (announcement_id, image_path, created_at)
                SELECT a.id, a.image_path, COALESCE(a.created_at, NOW())
                FROM announcements a
                LEFT JOIN announcement_images ai ON a.id = ai.announcement_id
                WHERE a.image_path IS NOT NULL AND a.image_path != '' AND ai.id IS NULL
            ");
        } catch (Exception $e) {
            try {
                $db->exec("
                    INSERT INTO announcement_images (announcement_id, image_path, created_at)
                    SELECT a.id, a.image_path, COALESCE(a.created_at, datetime('now'))
                    FROM announcements a
                    LEFT JOIN announcement_images ai ON a.id = ai.announcement_id
                    WHERE a.image_path IS NOT NULL AND a.image_path != '' AND ai.id IS NULL
                ");
            } catch (Exception $e2) {}
        }

        // Check if admin user exists; if none, seed initial data
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        $userCount = (int)$stmt->fetchColumn();
        if ($userCount === 0) {
            $admin_hash = password_hash('AdminPass123!', PASSWORD_DEFAULT);
            $announcer_hash = password_hash('AnnouncerPass123!', PASSWORD_DEFAULT);
            $now = date('Y-m-d H:i:s');

            $db->prepare("INSERT INTO users (id, name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([1, 'System Administrator', 'admin@iscp.edu.ph', $admin_hash, 'admin', 'active', $now]);
            $db->prepare("INSERT INTO users (id, name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([2, 'John Doe (Announcer)', 'announcer@iscp.edu.ph', $announcer_hash, 'announcer', 'active', $now]);
            $db->prepare("INSERT INTO users (id, name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)")
               ->execute([3, 'Prof. Maria Santos', 'maria.santos@iscp.edu.ph', $announcer_hash, 'announcer', 'active', $now]);
            
            // Seed Categories
            $cats = [
                [1, 'Urgent Alert', 'urgent-alert', 'danger', 'alert-triangle'],
                [2, 'Academic Affairs', 'academic-affairs', 'primary', 'book-open'],
                [3, 'Campus Events', 'campus-events', 'success', 'calendar'],
                [4, 'Student Life', 'student-life', 'warning', 'users'],
                [5, 'Admissions & Enrollment', 'admissions', 'info', 'file-text'],
            ];
            foreach ($cats as $c) {
                $db->prepare("INSERT INTO categories (id, name, slug, badge_color, icon) VALUES (?, ?, ?, ?, ?)")
                   ->execute($c);
            }

            // Seed Sample Announcements
            $sample_anns = [
                [
                    1,
                    'Suspension of Classes due to Inclement Weather',
                    'Please be advised that all onsite and online classes are officially suspended today due to heavy rains and localized flooding.',
                    "In accordance with the advisory issued by the City Disaster Risk Reduction and Management Office (CDRRMO), classes at all levels across all campuses are suspended today.\n\nStudents and faculty members are advised to stay indoors and monitor official communication channels for further updates. Stay safe everyone!",
                    'Urgent Alert',
                    1,
                    2,
                    'approved',
                    null,
                    1,
                    date('Y-m-d H:i:s', strtotime('-2 days')),
                    'All',
                    1,
                    1,
                    428,
                    date('Y-m-d H:i:s', strtotime('-2 days'))
                ],
                [
                    2,
                    'Midterm Examination Schedule for 1st Semester A.Y. 2026-2027',
                    'The official exam timetable, permit validation guidelines, and classroom assignments are now available for all departments.',
                    "The Office of the University Registrar announces that Midterm Examinations will commence starting next Monday.\n\nKey Reminders:\n1. Ensure your Examination Permits are validated before Friday.\n2. Bring your valid Student ID card to each testing room.\n3. Strictly observe the university dress code and code of conduct during exam week.\n\nConsult your respective department chairs for specialized laboratory exam schedules.",
                    'Academic Affairs',
                    2,
                    2,
                    'approved',
                    null,
                    1,
                    date('Y-m-d H:i:s', strtotime('-1 day')),
                    'Students',
                    0,
                    1,
                    315,
                    date('Y-m-d H:i:s', strtotime('-1 day'))
                ],
                [
                    3,
                    'Annual University Foundation Week & Sports Fest 2026',
                    'Join us for a week full of sports competitions, academic debates, cultural dances, and the much-awaited University Battle of the Bands!',
                    "Get ready for our Grand Foundation Celebration! The Supreme Student Council together with the Athletics Office invites all colleges to submit their team rosters for Basketball, Volleyball, Chess, Badminton, and Esports.\n\nRegistration booths are open at the Student Activity Center until Wednesday, 5:00 PM.",
                    'Campus Events',
                    3,
                    3,
                    'pending',
                    null,
                    null,
                    null,
                    'All',
                    0,
                    0,
                    0,
                    date('Y-m-d H:i:s', strtotime('-4 hours'))
                ],
                [
                    4,
                    'Call for Submissions: University Literary Folio 2026',
                    'The official university publications office is now accepting poetry, short stories, essays, and digital artwork.',
                    "We welcome submissions from all bona fide students. Theme for this year: \"Voices of Resilience and Hope\".\n\nSubmission Guidelines:\n- Maximum 2,000 words for prose.\n- High-resolution (300 DPI) for illustrations/photos.\n- Submit before the end of the month.",
                    'Student Life',
                    4,
                    2,
                    'pending',
                    null,
                    null,
                    null,
                    'Students',
                    0,
                    0,
                    0,
                    date('Y-m-d H:i:s', strtotime('-2 hours'))
                ],
                [
                    5,
                    'Unofficial Off-Campus Gathering & Party',
                    'Private party happening this Friday night outside school premises.',
                    'Party with free drinks and live DJ. Note: this is not an official school sanctioned event.',
                    'Student Life',
                    4,
                    3,
                    'rejected',
                    'The announcement refers to an unapproved and unofficial commercial event that violates university student handbook guidelines regarding off-campus safety and alcohol.',
                    1,
                    date('Y-m-d H:i:s', strtotime('-1 day')),
                    'Students',
                    0,
                    0,
                    0,
                    date('Y-m-d H:i:s', strtotime('-3 days'))
                ]
            ];

            $insStmt = $db->prepare("
                INSERT INTO announcements 
                (id, title, summary, content, category, category_id, created_by, status, rejection_reason, reviewed_by, reviewed_at, target_audience, is_urgent, is_pinned, views_count, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($sample_anns as $sa) {
                $insStmt->execute([
                    $sa[0], $sa[1], $sa[2], $sa[3], $sa[4], $sa[5], $sa[6], $sa[7],
                    $sa[8], $sa[9], $sa[10], $sa[11], $sa[12], $sa[13], $sa[14], $sa[15], $sa[15]
                ]);
            }
        }
    } catch (Exception $e) {
        error_log("Schema initialization error: " . $e->getMessage());
    }
}

if ($db_connected && $pdo) {
    ensure_schema_exists($pdo, $active_driver);
}

/**
 * Returns active PDO instance or null
 */
function get_db() {
    global $pdo;
    return $pdo;
}

/**
 * Check if database is currently reachable
 */
function is_db_connected() {
    global $db_connected;
    return $db_connected;
}

/**
 * Get active database driver name ('mysql' or 'sqlite')
 */
function get_db_driver() {
    global $active_driver;
    return $active_driver;
}

/**
 * Get error details if connection failed
 */
function get_db_error() {
    global $db_error_message, $db_host, $db_port, $active_driver;
    return [
        'driver' => $active_driver,
        'host' => $db_host,
        'port' => $db_port,
        'message' => $db_error_message
    ];
}

/**
 * Fetch all image paths for a given announcement
 */
function get_announcement_images(int $announcement_id): array {
    $pdo = get_db();
    if (!$pdo || $announcement_id <= 0) return [];

    try {
        $stmt = $pdo->prepare("SELECT id, image_path FROM announcement_images WHERE announcement_id = ? ORDER BY id ASC");
        $stmt->execute([$announcement_id]);
        $rows = $stmt->fetchAll();

        if (!empty($rows)) {
            return $rows;
        }

        // Fallback to legacy single image_path column if present
        $stmtSingle = $pdo->prepare("SELECT image_path FROM announcements WHERE id = ?");
        $stmtSingle->execute([$announcement_id]);
        $single = $stmtSingle->fetchColumn();
        if (!empty($single)) {
            return [['id' => 0, 'image_path' => $single]];
        }
    } catch (Exception $e) {
        error_log("Error fetching announcement images: " . $e->getMessage());
    }

    return [];
}

/**
 * Upload and save multiple images for an announcement
 */
function save_announcement_images(int $announcement_id, array $files_array): array {
    $pdo = get_db();
    if (!$pdo || $announcement_id <= 0 || empty($files_array['name'])) {
        return [];
    }

    $uploadDir = dirname(__DIR__) . '/assets/uploads/announcements/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $saved = [];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/pjpeg', 'image/x-png'];

    // Normalize multiple file arrays if needed
    $names = (array)$files_array['name'];
    $tmps = (array)$files_array['tmp_name'];
    $errors = (array)$files_array['error'];
    $sizes = (array)$files_array['size'];

    for ($i = 0; $i < count($names); $i++) {
        if (($errors[$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmpName = $tmps[$i];
        $origName = $names[$i];
        $size = $sizes[$i];

        if ($size > 15 * 1024 * 1024) { // 15MB max per photo
            continue;
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_exts, true)) {
            continue;
        }

        // Verify real mime type if finfo available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                if ($mime && !in_array($mime, $allowed_mimes, true)) {
                    continue;
                }
            }
        }

        // Generate safe unique filename
        $fileName = 'ann_' . $announcement_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $destPath = $uploadDir . $fileName;

        if (move_uploaded_file($tmpName, $destPath)) {
            @chmod($destPath, 0666);
            $webPath = 'assets/uploads/announcements/' . $fileName;

            // Insert into announcement_images table
            try {
                $ins = $pdo->prepare("INSERT INTO announcement_images (announcement_id, image_path, created_at) VALUES (?, ?, ?)");
                $ins->execute([$announcement_id, $webPath, date('Y-m-d H:i:s')]);
                $saved[] = $webPath;
            } catch (Exception $e) {
                error_log("DB image insert error: " . $e->getMessage());
            }
        } else {
            error_log("Failed to move uploaded file: " . $tmpName . " to " . $destPath);
        }
    }

    // Set first image as primary in announcements.image_path
    if (!empty($saved)) {
        try {
            $upd = $pdo->prepare("UPDATE announcements SET image_path = ? WHERE id = ?");
            $upd->execute([$saved[0], $announcement_id]);
        } catch (Exception $e) {
            // ignore
        }
    }

    return $saved;
}

/**
 * Upload an organization logo (office, college, department)
 * Validates extension, mime type, size, and saves to assets/uploads/logos/
 * Returns array: ['success' => bool, 'path' => ?string, 'error' => ?string]
 */
function upload_organization_logo(array $file, string $prefix = 'logo_'): array {
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($errorCode === UPLOAD_ERR_NO_FILE) {
            return ['success' => false, 'path' => null, 'error' => 'No file uploaded.'];
        }
        $errorMsg = match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE specified in the form.',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary upload directory on server.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Upload failed (error code ' . $errorCode . ').'
        };
        return ['success' => false, 'path' => null, 'error' => $errorMsg];
    }

    $uploadDir = dirname(__DIR__) . '/assets/uploads/logos/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
            return ['success' => false, 'path' => null, 'error' => 'Failed to create logo upload directory.'];
        }
    }

    $size = $file['size'] ?? 0;
    if ($size > 5 * 1024 * 1024) { // 5MB max
        return ['success' => false, 'path' => null, 'error' => 'File size exceeds 5MB limit.'];
    }

    $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    $allowed_mimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts, true)) {
        return ['success' => false, 'path' => null, 'error' => 'Invalid file format (.' . $ext . '). Please upload JPG, PNG, WEBP, GIF, or SVG.'];
    }

    // Check MIME type if available
    if (function_exists('finfo_open') && !empty($file['tmp_name']) && file_exists($file['tmp_name'])) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if ($mime && !in_array($mime, $allowed_mimes, true) && $ext !== 'svg') {
            return ['success' => false, 'path' => null, 'error' => 'Invalid file MIME type (' . $mime . ').'];
        }
    }

    $cleanPrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix);
    if (empty($cleanPrefix)) $cleanPrefix = 'logo_';
    $fileName = $cleanPrefix . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $uploadDir . $fileName;

    if (move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => true, 'path' => 'assets/uploads/logos/' . $fileName, 'error' => null];
    }

    return ['success' => false, 'path' => null, 'error' => 'Unable to save uploaded logo to disk. Check permissions on assets/uploads/logos/.'];
}

/**
 * Retrieve logo path for a university office, college, or department by name
 */
function get_organization_logo(?string $org_name, ?string $default = 'assets/images/iscp_logo.png'): string {
    if (empty($org_name)) {
        return $default;
    }

    static $logo_cache = [];
    if (isset($logo_cache[$org_name])) {
        return $logo_cache[$org_name];
    }

    $pdo = get_db();
    if ($pdo) {
        try {
            // Check offices
            $stmt = $pdo->prepare("SELECT logo_path FROM offices WHERE name = ? OR code = ? LIMIT 1");
            $stmt->execute([$org_name, $org_name]);
            $logo = $stmt->fetchColumn();
            if ($logo && file_exists(dirname(__DIR__) . '/' . $logo)) {
                return $logo_cache[$org_name] = $logo;
            }

            // Check colleges
            $stmt = $pdo->prepare("SELECT logo_path FROM colleges WHERE name = ? OR code = ? LIMIT 1");
            $stmt->execute([$org_name, $org_name]);
            $logo = $stmt->fetchColumn();
            if ($logo && file_exists(dirname(__DIR__) . '/' . $logo)) {
                return $logo_cache[$org_name] = $logo;
            }

            // Check departments
            $stmt = $pdo->prepare("SELECT logo_path FROM departments WHERE name = ? OR code = ? LIMIT 1");
            $stmt->execute([$org_name, $org_name]);
            $logo = $stmt->fetchColumn();
            if ($logo && file_exists(dirname(__DIR__) . '/' . $logo)) {
                return $logo_cache[$org_name] = $logo;
            }
        } catch (Exception $e) {
            // Fallback
        }
    }

    return $logo_cache[$org_name] = $default;
}

