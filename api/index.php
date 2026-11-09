<?php
/**
 * Master Minds — website & admin API (PHP + MySQL, built for Hostinger shared hosting).
 * Every request is index.php?a=<action>. Public actions: public, inquiry, status, login, setup.
 * Everything else requires an admin session.
 */
declare(strict_types=1);

/* ---------------------------------------------------------------- config */
$CONFIG = null;
foreach ([dirname(__DIR__, 2) . '/mm-config.php', __DIR__ . '/config.php'] as $f) {
    if (is_file($f)) { $CONFIG = require $f; break; }
}
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

function out($data, int $code = 200): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }
function fail(string $msg, int $code = 400): void { out(['error' => $msg], $code); }

if (!is_array($CONFIG)) fail('The server is not configured yet: create mm-config.php (see SETUP.md).', 503);
$cfg = $CONFIG + ['allowed_origins' => [], 'notify_email' => '', 'secret' => '', 'site_url' => ''];

/* ---------------------------------------------------------------- CORS (only needed if the site and API are on different domains) */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$crossSite = $origin !== '' && in_array($origin, (array)$cfg['allowed_origins'], true);
if ($crossSite) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Headers: Content-Type, X-MM');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Vary: Origin');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') { http_response_code(204); exit; }

/* ---------------------------------------------------------------- database */
try {
    $db = new PDO(
        'mysql:host=' . $cfg['db_host'] . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4',
        $cfg['db_user'], $cfg['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    error_log('Master Minds DB connection failed: ' . $e->getMessage());
    fail('Could not connect to the database. Check the details in mm-config.php.', 500);
}
set_exception_handler(function (Throwable $e) {
    error_log('Master Minds API error: ' . $e->getMessage());
    fail('Server error. Please try again.', 500);
});

/* ---------------------------------------------------------------- helpers */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = (string)($_GET['a'] ?? '');

function body(): array {
    static $b = null;
    if ($b === null) { $b = json_decode(file_get_contents('php://input') ?: '[]', true); if (!is_array($b)) $b = []; }
    return $b;
}
function str($v, int $max, bool $required = false, string $label = 'Field'): ?string {
    $v = is_scalar($v) ? trim((string)$v) : '';
    if ($v === '') { if ($required) fail("$label is required."); return null; }
    if (mb_strlen($v) > $max) fail("$label is too long (max $max characters).");
    return $v;
}
function ipHash(): string { global $cfg; return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $cfg['secret']); }
function rateLimited(string $bucket, int $max, int $minutes): bool {
    global $db;
    if (random_int(1, 50) === 1) $db->exec('DELETE FROM rate_limits WHERE created_at < NOW() - INTERVAL 1 DAY');
    $q = $db->prepare('SELECT COUNT(*) FROM rate_limits WHERE bucket=? AND ip_hash=? AND created_at > NOW() - INTERVAL ' . (int)$minutes . ' MINUTE');
    $q->execute([$bucket, ipHash()]);
    return (int)$q->fetchColumn() >= $max;
}
function hit(string $bucket): void { global $db; $db->prepare('INSERT INTO rate_limits (bucket, ip_hash) VALUES (?, ?)')->execute([$bucket, ipHash()]); }
function siteBase(): string {
    global $cfg, $https;
    if ($cfg['site_url']) return rtrim($cfg['site_url'], '/');
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/index.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}
function uploadsDir(): string { return dirname(__DIR__) . '/uploads'; }
/** Delete a file we uploaded earlier, if $url points into our own uploads folder. */
function removeUpload(?string $url): void {
    if (!$url) return;
    $prefix = siteBase() . '/uploads/';
    if (strpos($url, $prefix) !== 0) return;
    $rel = substr($url, strlen($prefix));
    if (!preg_match('~^[a-z]+/\d{4}/[a-f0-9]{24}\.(jpg|png|webp|gif)$~', $rel)) return;
    $path = uploadsDir() . '/' . $rel;
    if (is_file($path)) @unlink($path);
}
function decodeContent(array $rows): array {
    $c = [];
    foreach ($rows as $r) $c[$r['key']] = json_decode($r['value'], true);
    return $c;
}
function castRow(array $r): array {
    foreach (['id', 'sort'] as $k) if (isset($r[$k])) $r[$k] = (int)$r[$k];
    if (isset($r['published'])) $r['published'] = (bool)$r['published'];
    return $r;
}

/* ---------------------------------------------------------------- session */
session_name('mm_admin');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true,
    'secure' => $https, 'samesite' => ($crossSite && $https) ? 'None' : 'Lax',
]);
session_start();
function admin(): ?array { return $_SESSION['admin'] ?? null; }

// Writes must carry a custom header. Browsers cannot add it to cross-site requests without CORS approval,
// which blocks cross-site request forgery.
if ($method === 'POST' && ($_SERVER['HTTP_X_MM'] ?? '') !== '1') fail('Bad request.', 400);

$PUBLIC = ['public', 'inquiry', 'status', 'login', 'setup', 'logout'];
if (!in_array($action, $PUBLIC, true) && !admin()) fail('Please sign in again.', 401);
$needPost = fn() => $method === 'POST' || fail('Use POST.', 405);

/* ---------------------------------------------------------------- table definitions */
$TABLES = [
    'results' => [
        'student_name' => ['str', 120, false, 'Student name'], 'score' => ['str', 20, true, 'Score'],
        'metric' => ['str', 60, true, 'What the score is'], 'school' => ['str', 120, true, 'College / University'],
        'region' => ['enum', ['India', 'Abroad']], 'program' => ['str', 40, false, 'Program'],
        'photo_url' => ['url', false, 'Photo'], 'sort' => ['int'], 'published' => ['bool'],
    ],
    'testimonials' => [
        'name' => ['str', 120, true, 'Student name'], 'result' => ['str', 120, false, 'Result'],
        'quote' => ['str', 600, true, 'Review'], 'program' => ['str', 40, false, 'Program'],
        'photo_url' => ['url', false, 'Photo'], 'sort' => ['int'], 'published' => ['bool'],
    ],
    'gallery' => [
        'url' => ['url', true, 'Photo'], 'caption' => ['str', 120, false, 'Caption'],
        'sort' => ['int'], 'published' => ['bool'],
    ],
];
function clean(array $spec, array $in, bool $partial): array {
    $out = [];
    foreach ($spec as $f => $t) {
        if (!array_key_exists($f, $in)) { if ($partial) continue; $in[$f] = null; }
        $v = $in[$f];
        switch ($t[0]) {
            case 'str':  $out[$f] = str($v, $t[1], $t[2], $t[3]); break;
            case 'int':  $out[$f] = (int)($v ?? 0); break;
            case 'bool': $out[$f] = $v === null ? 1 : ($v ? 1 : 0); break;
            case 'enum': $out[$f] = in_array($v, $t[1], true) ? $v : $t[1][0]; break;
            case 'url':
                $u = str($v, 500, $t[1], $t[2]);
                if ($u !== null && !preg_match('~^https?://~i', $u)) fail($t[2] . ' must be a web address starting with http:// or https://');
                $out[$f] = $u; break;
        }
    }
    return $out;
}
$CONTENT_KEYS = ['site', 'branches', 'loans', 'program_desc'];
$tableParam = function () use ($TABLES) { $t = (string)($_GET['t'] ?? ''); if (!isset($TABLES[$t])) fail('Unknown list.'); return $t; };

/* ---------------------------------------------------------------- actions */
switch ($action) {

case 'public': {
    $pub = fn($t) => array_map('castRow', $db->query("SELECT * FROM `$t` WHERE published=1 ORDER BY sort, id")->fetchAll());
    $rows = $db->query('SELECT `key`, value FROM content')->fetchAll();
    out(['results' => $pub('results'), 'testimonials' => $pub('testimonials'), 'gallery' => $pub('gallery'), 'content' => decodeContent($rows)]);
}

case 'inquiry': {
    $needPost();
    $b = body();
    if (!empty($b['website'])) out(['ok' => true]);              // honeypot: a bot filled the hidden field
    if (rateLimited('inquiry', 5, 10)) fail('Too many enquiries from your connection. Please call us instead.', 429);
    $row = [
        'name' => str($b['name'] ?? '', 120, true, 'Name'),
        'phone' => str($b['phone'] ?? '', 20, true, 'Contact number'),
        'email' => str($b['email'] ?? '', 200, false, 'Email'),
        'city' => str($b['city'] ?? '', 100, false, 'City'),
        'course' => str($b['course'] ?? '', 100, false, 'Course'),
    ];
    if (!preg_match('/^[0-9+\-\s()]{6,20}$/', $row['phone'])) fail('Please enter a valid contact number.');
    if ($row['email'] !== null && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
    hit('inquiry');
    $db->prepare('INSERT INTO inquiries (name, phone, email, city, course, ip_hash) VALUES (?,?,?,?,?,?)')
       ->execute([$row['name'], $row['phone'], $row['email'], $row['city'], $row['course'], ipHash()]);
    if ($cfg['notify_email'] && filter_var($cfg['notify_email'], FILTER_VALIDATE_EMAIL)) {
        $clean = fn($s) => str_replace(["\r", "\n"], ' ', (string)$s);
        $msg = "New enquiry from the Master Minds website\n\n"
             . "Name:   {$clean($row['name'])}\nPhone:  {$clean($row['phone'])}\nEmail:  {$clean($row['email'])}\n"
             . "City:   {$clean($row['city'])}\nCourse: {$clean($row['course'])}\n\nSee all enquiries: " . siteBase() . "/admin.html\n";
        $host = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        @mail($cfg['notify_email'], 'New enquiry: ' . $clean($row['name']) . ' (' . $clean($row['course']) . ')', $msg,
              "From: Master Minds Website <no-reply@$host>\r\nContent-Type: text/plain; charset=utf-8");
    }
    out(['ok' => true]);
}

case 'status': {
    $needsSetup = (int)$db->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0;
    out(['admin' => admin(), 'needsSetup' => $needsSetup]);
}

case 'setup': {          // creates the very first admin; disabled once any admin exists
    $needPost();
    $b = body();
    $db->beginTransaction();
    if ((int)$db->query('SELECT COUNT(*) FROM admins FOR UPDATE')->fetchColumn() > 0) { $db->rollBack(); fail('An admin already exists. Please sign in.', 403); }
    $email = strtolower(str($b['email'] ?? '', 190, true, 'Email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $db->rollBack(); fail('Please enter a valid email address.'); }
    $pass = (string)($b['password'] ?? '');
    if (strlen($pass) < 8) { $db->rollBack(); fail('Password must be at least 8 characters.'); }
    $name = str($b['name'] ?? '', 120, false, 'Name');
    $db->prepare('INSERT INTO admins (email, name, password_hash, last_login) VALUES (?,?,?,NOW())')->execute([$email, $name, password_hash($pass, PASSWORD_DEFAULT)]);
    $id = (int)$db->lastInsertId();
    $db->commit();
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id' => $id, 'email' => $email, 'name' => $name];
    out(['admin' => $_SESSION['admin']]);
}

case 'login': {
    $needPost();
    if (rateLimited('login', 10, 15)) fail('Too many sign-in attempts. Please wait 15 minutes and try again.', 429);
    $b = body();
    $q = $db->prepare('SELECT * FROM admins WHERE email = ?');
    $q->execute([strtolower(trim((string)($b['email'] ?? '')))]);
    $a = $q->fetch();
    if (!$a || !password_verify((string)($b['password'] ?? ''), $a['password_hash'])) { hit('login'); fail('Wrong email or password.', 401); }
    if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT))
        $db->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash((string)$b['password'], PASSWORD_DEFAULT), $a['id']]);
    $db->prepare('UPDATE admins SET last_login=NOW() WHERE id=?')->execute([$a['id']]);
    session_regenerate_id(true);
    $_SESSION['admin'] = ['id' => (int)$a['id'], 'email' => $a['email'], 'name' => $a['name']];
    out(['admin' => $_SESSION['admin']]);
}

case 'logout': {
    $needPost();
    $_SESSION = [];
    session_destroy();
    out(['ok' => true]);
}

/* ----- admin only from here ----- */

case 'list': {
    $t = $tableParam();
    out(['rows' => array_map('castRow', $db->query("SELECT * FROM `$t` ORDER BY sort, id")->fetchAll())]);
}

case 'save': {
    $needPost();
    $t = $tableParam(); $b = body();
    $id = isset($b['id']) ? (int)$b['id'] : 0;
    $row = clean($TABLES[$t], $b, $id > 0);
    if (!$row) fail('Nothing to save.');
    if ($id > 0) {
        $old = $db->prepare("SELECT * FROM `$t` WHERE id=?"); $old->execute([$id]); $old = $old->fetch();
        if (!$old) fail('This item no longer exists.', 404);
        $set = implode(', ', array_map(fn($k) => "`$k`=?", array_keys($row)));
        $db->prepare("UPDATE `$t` SET $set WHERE id=?")->execute([...array_values($row), $id]);
        foreach (['photo_url', 'url'] as $f) if (array_key_exists($f, $row) && $old[$f] !== $row[$f]) removeUpload($old[$f]);
    } else {
        $cols = implode(', ', array_map(fn($k) => "`$k`", array_keys($row)));
        $db->prepare("INSERT INTO `$t` ($cols) VALUES (" . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
        $id = (int)$db->lastInsertId();
    }
    $q = $db->prepare("SELECT * FROM `$t` WHERE id=?"); $q->execute([$id]);
    out(['row' => castRow($q->fetch())]);
}

case 'delete': {
    $needPost();
    $t = $tableParam(); $id = (int)(body()['id'] ?? 0);
    $q = $db->prepare("SELECT * FROM `$t` WHERE id=?"); $q->execute([$id]); $old = $q->fetch();
    if ($old) {
        $db->prepare("DELETE FROM `$t` WHERE id=?")->execute([$id]);
        removeUpload($old['photo_url'] ?? $old['url'] ?? null);
    }
    out(['ok' => true]);
}

case 'inquiries': {
    $rows = $db->query('SELECT id, created_at, name, phone, email, city, course, status, notes FROM inquiries ORDER BY id DESC LIMIT 5000')->fetchAll();
    foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['created_at'] = date(DATE_ATOM, strtotime($r['created_at'])); }
    out(['rows' => $rows]);
}

case 'inquiry_update': {
    $needPost();
    $b = body(); $id = (int)($b['id'] ?? 0);
    if (array_key_exists('status', $b)) {
        if (!in_array($b['status'], ['new', 'contacted', 'enrolled', 'closed'], true)) fail('Unknown status.');
        $db->prepare('UPDATE inquiries SET status=? WHERE id=?')->execute([$b['status'], $id]);
    }
    if (array_key_exists('notes', $b))
        $db->prepare('UPDATE inquiries SET notes=? WHERE id=?')->execute([str($b['notes'], 2000, false, 'Note'), $id]);
    out(['ok' => true]);
}

case 'inquiry_delete': {
    $needPost();
    $db->prepare('DELETE FROM inquiries WHERE id=?')->execute([(int)(body()['id'] ?? 0)]);
    out(['ok' => true]);
}

case 'new_count': {
    out(['count' => (int)$db->query("SELECT COUNT(*) FROM inquiries WHERE status='new'")->fetchColumn()]);
}

case 'content': {
    out(['content' => decodeContent($db->query('SELECT `key`, value FROM content')->fetchAll())]);
}

case 'content_save': {
    $needPost();
    $items = body()['items'] ?? null;
    if (!is_array($items)) fail('Nothing to save.');
    $q = $db->prepare('INSERT INTO content (`key`, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)');
    foreach ($items as $k => $v) {
        if (!in_array($k, $CONTENT_KEYS, true)) fail('Unknown content section.');
        $json = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > 100000) fail('Content is too long.');
        $q->execute([$k, $json]);
    }
    out(['ok' => true]);
}

case 'upload': {
    $needPost();
    $f = $_FILES['file'] ?? null;
    if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) fail(($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? 'The photo is too large for the server.' : 'Upload failed. Please try again.');
    if ($f['size'] > 5 * 1024 * 1024) fail('Photos must be 5 MB or smaller.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext || !@getimagesize($f['tmp_name'])) fail('Please upload a JPG, PNG, WebP or GIF image.');
    $folder = preg_replace('/[^a-z]/', '', (string)($_POST['folder'] ?? 'misc')) ?: 'misc';
    $rel = $folder . '/' . date('Y') . '/' . bin2hex(random_bytes(12)) . '.' . $ext;
    $dest = uploadsDir() . '/' . $rel;
    if (!is_dir(dirname($dest)) && !@mkdir(dirname($dest), 0755, true)) fail('The uploads folder is not writable on the server.', 500);
    if (!move_uploaded_file($f['tmp_name'], $dest)) fail('Could not save the photo on the server.', 500);
    @chmod($dest, 0644);
    out(['url' => siteBase() . '/uploads/' . $rel]);
}

case 'admins': {
    out(['rows' => array_map(fn($r) => ['id' => (int)$r['id']] + $r, $db->query('SELECT id, email, name, created_at, last_login FROM admins ORDER BY id')->fetchAll()), 'me' => admin()['id']]);
}

case 'admin_add': {
    $needPost();
    $b = body();
    $email = strtolower(str($b['email'] ?? '', 190, true, 'Email'));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
    if (strlen((string)($b['password'] ?? '')) < 8) fail('Password must be at least 8 characters.');
    $q = $db->prepare('SELECT 1 FROM admins WHERE email=?'); $q->execute([$email]);
    if ($q->fetch()) fail('That email is already an admin.');
    $db->prepare('INSERT INTO admins (email, name, password_hash) VALUES (?,?,?)')
       ->execute([$email, str($b['name'] ?? '', 120, false, 'Name'), password_hash((string)$b['password'], PASSWORD_DEFAULT)]);
    out(['ok' => true]);
}

case 'admin_delete': {
    $needPost();
    $id = (int)(body()['id'] ?? 0);
    if ($id === admin()['id']) fail('You cannot remove your own account.');
    $db->prepare('DELETE FROM admins WHERE id=?')->execute([$id]);
    out(['ok' => true]);
}

case 'password': {
    $needPost();
    $b = body();
    $q = $db->prepare('SELECT password_hash FROM admins WHERE id=?'); $q->execute([admin()['id']]);
    if (!password_verify((string)($b['current'] ?? ''), (string)$q->fetchColumn())) fail('Your current password is wrong.');
    if (strlen((string)($b['new'] ?? '')) < 8) fail('New password must be at least 8 characters.');
    $db->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash((string)$b['new'], PASSWORD_DEFAULT), admin()['id']]);
    out(['ok' => true]);
}

default:
    fail('Unknown action.', 404);
}
