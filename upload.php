<?php
// ===================================================================
// ShareX 多功能接收服务端 (v2.1 - 二级域名优化版)
// 功能: 接收图片/文件/文本，并实现真正的URL缩短
// 特性: 文件分类存储、图片按月归档、SQLite短链
// 部署: 适用于二级域名根目录 (如 x.z.ai)
// ===================================================================

// --- 1. 配置区域 (请根据您的环境修改) ---

// 安全：请设置一个复杂的随机字符串作为 API Key
define('API_KEY', 'YourSecretApiKeyHere123!@#'); // <--- 修改这里

// Web 根目录 URL (必须以 / 结尾)
define('WEB_ROOT_URL', 'https://x.z.ai/'); // <--- 修改这里

// 短链接访问 URL 前缀 (必须以 / 结尾)
define('SHORT_URL_PREFIX', 'https://x.z.ai/s/'); // <--- 修改这里

// 存储目录 (相对于当前脚本的路径)
define('IMG_DIR', 'img');
define('FILE_DIR', 'file');
define('TXT_DIR', 'txt');

// SQLite 数据库文件路径
define('DB_PATH', __DIR__ . '/urls.db');

// 允许上传的文件扩展名 (小写)
 $allowed_extensions = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg', // 图片
    'mp4', 'webm', 'mov', 'avi', 'mkv',                       // 视频
    'mp3', 'wav', 'ogg', 'flac',                               // 音频
    'txt', 'md', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', // 文档
    'zip', 'rar', '7z', 'tar', 'gz',                           // 压缩包
    'html', 'css', 'js', 'json', 'xml', 'py', 'sh'             // 代码/其他
];
define('ALLOWED_EXTENSIONS', $allowed_extensions);

// 最大上传文件大小 (字节) - 10MB
define('MAX_FILE_SIZE', 10 * 1024 * 1024);

// --- 2. 核心逻辑 (通常无需修改) ---

header('Content-Type: application/json; charset=utf-8');

function send_error($message, $http_code = 400) {
    http_response_code($http_code);
    echo json_encode(['error' => $message]);
    exit();
}

function send_success($url) {
    echo json_encode(['url' => $url]);
    exit();
}

// 获取数据库连接
function get_db() {
    try {
        $db = new SQLite3(DB_PATH);
        $db->busyTimeout(5000);
        $db->exec('CREATE TABLE IF NOT EXISTS urls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            short_code TEXT UNIQUE NOT NULL,
            original_url TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )');
        return $db;
    } catch (Exception $e) {
        send_error('Database connection failed: ' . $e->getMessage(), 500);
    }
}

// 生成短码
function generate_short_code($length = 6) {
    return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
}

// --- 3. 请求处理 ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Invalid request method. Only POST is allowed.', 405);
}

 $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['key'] ?? null;
if (!hash_equals(API_KEY, $apiKey)) {
    send_error('Unauthorized: Invalid API Key.', 401);
}

// --- 4. 内容类型判断与处理 ---

// 情况一: 文件上传 (图片或普通文件)
if (isset($_FILES['sharex'])) {
    $file = $_FILES['sharex'];
    if ($file['error'] !== UPLOAD_ERR_OK) send_error('File upload error code: ' . $file['error']);
    if ($file['size'] > MAX_FILE_SIZE) send_error('File is too large.');

    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    if (!in_array($extension, ALLOWED_EXTENSIONS)) send_error('File type not allowed.');

    $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico', 'svg']);
    
    if ($isImage) {
        $storageDir = IMG_DIR . '/' . date('Y-m');
        $urlDir = IMG_DIR . '/' . date('Y-m');
    } else {
        $storageDir = FILE_DIR;
        $urlDir = FILE_DIR;
    }

    $fullStoragePath = __DIR__ . '/' . $storageDir;
    if (!is_dir($fullStoragePath)) {
        mkdir($fullStoragePath, 0755, true);
    }

    $randomBytes = bin2hex(random_bytes(8));
    $newFileName = sprintf('%s_%s.%s', date('YmdHis'), $randomBytes, $extension);
    $destinationPath = $fullStoragePath . '/' . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $destinationPath)) {
        send_error('Failed to move uploaded file.', 500);
    }

    send_success(WEB_ROOT_URL . $urlDir . '/' . $newFileName);
}

// 情况二: 文本或URL上传
elseif (isset($_POST['content'])) {
    $content = trim($_POST['content']);
    if (empty($content)) send_error('Content cannot be empty.');

    if (filter_var($content, FILTER_VALIDATE_URL)) {
        // URL 缩短逻辑
        $db = get_db();
        $stmt = $db->prepare('SELECT short_code FROM urls WHERE original_url = :url');
        $stmt->bindValue(':url', $content, SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray();

        if ($row) {
            $shortCode = $row['short_code'];
        } else {
            do {
                $shortCode = generate_short_code();
                $checkStmt = $db->prepare('SELECT id FROM urls WHERE short_code = :code');
                $checkStmt->bindValue(':code', $shortCode, SQLITE3_TEXT);
                $checkResult = $checkStmt->execute();
            } while ($checkResult->fetchArray());

            $insertStmt = $db->prepare('INSERT INTO urls (short_code, original_url) VALUES (:code, :url)');
            $insertStmt->bindValue(':code', $shortCode, SQLITE3_TEXT);
            $insertStmt->bindValue(':url', $content, SQLITE3_TEXT);
            $insertStmt->execute();
        }
        $db->close();
        send_success(SHORT_URL_PREFIX . $shortCode);
    } else {
        // 纯文本保存逻辑
        $storageDir = TXT_DIR;
        $fullStoragePath = __DIR__ . '/' . $storageDir;
        if (!is_dir($fullStoragePath)) {
            mkdir($fullStoragePath, 0755, true);
        }
        
        $randomBytes = bin2hex(random_bytes(8));
        $newFileName = sprintf('%s_%s.txt', date('YmdHis'), $randomBytes);
        $destinationPath = $fullStoragePath . '/' . $newFileName;

        if (file_put_contents($destinationPath, $content) === false) {
            send_error('Failed to save content to file.', 500);
        }
        send_success(WEB_ROOT_URL . $storageDir . '/' . $newFileName);
    }
}

else {
    send_error('Invalid request. No file or content found.');
}