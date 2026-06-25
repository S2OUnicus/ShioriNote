<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

$lockFile = APP_ROOT . '/install.lock';
$updateLockFile = APP_ROOT . '/update.lock';
$configFile = APP_ROOT . '/config/base.inc.phtml';
$sampleConfigFile = APP_ROOT . '/config/base.sample.phtml';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/install/', PHP_URL_PATH) ?: '/install/';
$devMode = str_ends_with(rtrim($path, '/'), '/s2odev');
$stepParam = (string)($_GET['step'] ?? 'env');

session_name('shiorinote_install');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

if (is_file($lockFile) && !($stepParam === 'done' && !empty($_SESSION['install_done']))) {
    header('Location: /');
    exit;
}

function inst_e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function inst_csrf(): string { $_SESSION['install_csrf'] ??= bin2hex(random_bytes(32)); return (string)$_SESSION['install_csrf']; }
function inst_check_csrf(): void { if (!hash_equals((string)($_SESSION['install_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) { throw new RuntimeException('CSRF検証に失敗しました。'); } }
function inst_url(int|string $step, bool $devMode = false): string { return ($devMode ? '/install/s2odev' : '/install/') . '?step=' . rawurlencode((string)$step); }
function inst_random_path(int $length = 16): string { $chars = 'abcdefghijklmnopqrstuvwxyz0123456789'; $out = ''; for ($i=0; $i<$length; $i++) { $out .= $chars[random_int(0, strlen($chars)-1)]; } return $out; }
function inst_post_value(string $key, mixed $default = ''): mixed { return $_POST[$key] ?? ($_SESSION['install'][$key] ?? $default); }
function inst_save_values(array $keys): void { foreach ($keys as $key) { if (isset($_POST[$key])) { $_SESSION['install'][$key] = is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key]; } } }
function inst_php_sq(string $v): string { return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $v) . "'"; }
function inst_public_url(string $path): string { return '/' . ltrim($path, '/'); }
function inst_clean_subdir(string $subdir): string { $subdir = '/' . trim($subdir, '/'); return $subdir === '/' ? '' : $subdir; }

function inst_env_checks(): array
{
    $server = strtolower((string)($_SERVER['SERVER_SOFTWARE'] ?? 'unknown'));
    $checks = [];
    $checks[] = ['label' => 'PHP 8.1以上（PHP 8.5以上推奨）', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>='), 'note' => '現在: ' . PHP_VERSION, 'fatal' => true];
    $checks[] = ['label' => 'Webサーバー', 'ok' => str_contains($server, 'nginx') || str_contains($server, 'apache'), 'note' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown', 'fatal' => false];
    $checks[] = ['label' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql'), 'note' => extension_loaded('pdo_mysql') ? '利用可能' : 'php-pdo_mysql が必要です', 'fatal' => true];
    $checks[] = ['label' => 'mbstring', 'ok' => extension_loaded('mbstring'), 'note' => '文字数制限・日本語処理に必要です', 'fatal' => true];
    $checks[] = ['label' => 'fileinfo', 'ok' => extension_loaded('fileinfo'), 'note' => '画像アップロード検証に必要です', 'fatal' => true];
    $checks[] = ['label' => 'openssl / random_bytes', 'ok' => extension_loaded('openssl') && function_exists('random_bytes'), 'note' => '安全なトークン生成に必要です', 'fatal' => true];
    $checks[] = ['label' => 'password_hash', 'ok' => function_exists('password_hash') && defined('PASSWORD_ARGON2ID'), 'note' => 'Argon2id パスワード保存に必要です', 'fatal' => true];
    $checks[] = ['label' => 'config フォルダ書き込み', 'ok' => is_writable(APP_ROOT . '/config'), 'note' => APP_ROOT . '/config', 'fatal' => true];
    $checks[] = ['label' => 'install.lock 作成先書き込み', 'ok' => is_writable(APP_ROOT), 'note' => APP_ROOT, 'fatal' => true];
    foreach ([APP_ROOT . '/public/upload/avatar', APP_ROOT . '/public/upload/book'] as $dir) {
        if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
        $checks[] = ['label' => basename(dirname($dir)) . '/' . basename($dir) . ' 書き込み', 'ok' => is_dir($dir) && is_writable($dir), 'note' => $dir, 'fatal' => true];
    }
    return $checks;
}

function inst_env_ok(array $checks): bool
{
    foreach ($checks as $c) { if (($c['fatal'] ?? false) && !($c['ok'] ?? false)) { return false; } }
    return true;
}

function inst_pdo_from(array $db, bool $withDb = true): PDO
{
    $charset = 'utf8mb4';
    $dsn = 'mysql:host=' . $db['host'] . ';port=' . (int)$db['port'] . ($withDb ? ';dbname=' . $db['name'] : '') . ';charset=' . $charset;
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    if (!empty($db['ssl_ca']) && is_file($db['ssl_ca']) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $db['ssl_ca'];
    }
    return new PDO($dsn, $db['user'], $db['pass'], $options);
}

function inst_table_names(): array
{
    return ['password_reset_tokens','user_book_progress','user_toc_progress','site_settings','progress_logs','book_toc','books','users'];
}

function inst_prefix_sql(string $sql, string $prefix): string
{
    $prefix = preg_match('/^[A-Za-z0-9_]*$/', $prefix) ? $prefix : '';
    if ($prefix === '') { return $sql; }
    foreach (inst_table_names() as $table) {
        $sql = preg_replace('/(?<![A-Za-z0-9_])' . preg_quote($table, '/') . '(?![A-Za-z0-9_])/u', $prefix . $table, $sql) ?? $sql;
    }
    $sql = preg_replace('/\bCONSTRAINT\s+(fk_[A-Za-z0-9_]+)/u', 'CONSTRAINT ' . $prefix . '$1', $sql) ?? $sql;
    return $sql;
}

function inst_split_sql(string $sql): array
{
    $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;
    $parts = [];
    $buf = '';
    $quote = null;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        $buf .= $ch;
        if ($quote !== null) {
            if ($ch === '\\') { if ($i + 1 < $len) { $buf .= $sql[++$i]; } continue; }
            if ($ch === $quote) { $quote = null; }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; continue; }
        if ($ch === ';') { $stmt = trim(substr($buf, 0, -1)); if ($stmt !== '') { $parts[] = $stmt; } $buf = ''; }
    }
    $last = trim($buf); if ($last !== '') { $parts[] = $last; }
    return $parts;
}

function inst_run_sql_file(PDO $pdo, string $file, string $prefix, array &$log): void
{
    if (!is_file($file)) { throw new RuntimeException('SQLファイルが見つかりません: ' . $file); }
    $sql = (string)file_get_contents($file);
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;\s*/ims', '', $sql) ?? $sql;
    $sql = preg_replace('/^\s*USE\s+[`\w]+\s*;\s*/ims', '', $sql) ?? $sql;
    $sql = inst_prefix_sql($sql, $prefix);
    $count = 0;
    foreach (inst_split_sql($sql) as $stmt) { $pdo->exec($stmt); $count++; }
    $log[] = basename($file) . ' を導入しました（' . $count . ' statements）。';
}

function inst_create_password_bundle(string $password): array
{
    $salt = random_bytes(32);
    $hash = password_hash($salt . $password, PASSWORD_ARGON2ID, ['memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST, 'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST, 'threads' => PASSWORD_ARGON2_DEFAULT_THREADS]);
    if ($hash === false) { throw new RuntimeException('パスワードハッシュを作成できませんでした。'); }
    return [$salt, $hash];
}

function inst_save_avatar(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return '/upload/avatar/default-avatar.svg'; }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) { throw new RuntimeException('アバターのアップロードに失敗しました。'); }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) { throw new RuntimeException('アバターは2MB以内にしてください。'); }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $ext = match ($mime) { 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', default => throw new RuntimeException('アバターは PNG/JPEG/WebP のみ利用できます。') };
    $dir = APP_ROOT . '/public/upload/avatar';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) { throw new RuntimeException('アバター保存先を作成できません。'); }
    $name = 'admin_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . '/' . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) { throw new RuntimeException('アバターを保存できませんでした。'); }
    @chmod($dest, 0644);
    return '/upload/avatar/' . $name;
}

function inst_make_config(array $cfg): string
{
    global $sampleConfigFile;
    $sample = (string)file_get_contents($sampleConfigFile);
    $tailPos = strpos($sample, 'function app_config');
    if ($tailPos === false) { throw new RuntimeException('base.sample.phtml の構造を読み取れません。'); }
    $tail = substr($sample, $tailPos);
    $db = $cfg['db'];
    return "<?php\ndeclare(strict_types=1);\n\n/**\n * ShioriNote v2.4.0 generated config.\n * Created by installer at " . date('Y-m-d H:i:s T') . ".\n * Never commit this file to GitHub.\n */\n\nif (!defined('APP_ROOT')) {\n    define('APP_ROOT', dirname(__DIR__));\n}\n\n\$BASE_CONFIG = [\n" .
        "    'version' => '2.4.0',\n" .
        "    'site_name' => " . inst_php_sq($cfg['site_name']) . ",\n" .
        "    'author' => " . inst_php_sq($cfg['author']) . ",\n" .
        "    'theme_color' => " . inst_php_sq($cfg['theme_color']) . ",\n" .
        "    'description_default' => " . inst_php_sq($cfg['description_default']) . ",\n" .
        "    'default_timezone' => " . inst_php_sq($cfg['default_timezone']) . ",\n" .
        "    'admin_panel_path' => getenv('SN_ADMIN_PANEL_PATH') ?: " . inst_php_sq($cfg['admin_panel_path']) . ",\n" .
        "    'registration_enabled' => false,\n" .
        "    'rewrite_enabled' => true,\n" .
        "    'progress_time_bucket' => 'daily',\n" .
        "    'site_base_url' => " . inst_php_sq($cfg['site_base_url']) . ",\n" .
        "    'site_subdir' => " . inst_php_sq($cfg['site_subdir']) . ",\n" .
        "    'debug' => false,\n" .
        "    'uploads' => [\n        'avatar_dir' => APP_ROOT . '/public/upload/avatar',\n        'book_dir' => APP_ROOT . '/public/upload/book',\n        'max_image_bytes' => 2 * 1024 * 1024,\n        'max_toc_bytes' => 1024 * 1024,\n    ],\n" .
        "    'db' => [\n" .
        "        'host' => getenv('SN_DB_HOST') ?: " . inst_php_sq($db['host']) . ",\n" .
        "        'port' => (int)(getenv('SN_DB_PORT') ?: " . (int)$db['port'] . "),\n" .
        "        'name' => getenv('SN_DB_NAME') ?: " . inst_php_sq($db['name']) . ",\n" .
        "        'user' => getenv('SN_DB_USER') ?: " . inst_php_sq($db['user']) . ",\n" .
        "        'pass' => getenv('SN_DB_PASS') ?: " . inst_php_sq($db['pass']) . ",\n" .
        "        'charset' => 'utf8mb4',\n" .
        "        'prefix' => getenv('SN_DB_PREFIX') ?: " . inst_php_sq($db['prefix']) . ",\n" .
        "        'ssl_ca' => getenv('SN_DB_SSL_CA') ?: " . inst_php_sq($db['ssl_ca']) . ",\n" .
        "    ],\n];\n\n" . $tail;
}

function inst_install(bool $devMode): array
{
    global $configFile, $lockFile, $updateLockFile;
    $log = [];
    $db = [
        'host' => (string)($_SESSION['install']['db_host'] ?? '127.0.0.1'),
        'port' => (int)($_SESSION['install']['db_port'] ?? 3306),
        'name' => (string)($_SESSION['install']['db_name'] ?? 'shiorinote'),
        'user' => (string)($_SESSION['install']['db_user'] ?? 'shiorinote'),
        'pass' => (string)($_SESSION['install']['db_pass'] ?? ''),
        'prefix' => (string)($_SESSION['install']['db_prefix'] ?? 'sn1_'),
        'ssl_ca' => (string)($_SESSION['install']['db_ssl_ca'] ?? ''),
    ];
    if (!preg_match('/^[A-Za-z0-9_]*$/', $db['prefix'])) { throw new RuntimeException('テーブルプレフィックスは半角英数字とアンダースコアだけ使えます。'); }
    $pdo = inst_pdo_from($db, true);
    $log[] = 'データベースへ接続しました。';
    inst_run_sql_file($pdo, APP_ROOT . '/database-sample/01_schema.sql', $db['prefix'], $log);
    inst_run_sql_file($pdo, APP_ROOT . '/database-sample/02_seed.sample.sql', $db['prefix'], $log);

    $siteName = 'しおりノート';
    $theme = preg_match('/^#[0-9A-Fa-f]{6}$/', (string)($_SESSION['install']['theme_color'] ?? '')) ? (string)$_SESSION['install']['theme_color'] : '#6E1E51';
    $timezone = in_array((string)($_SESSION['install']['timezone'] ?? ''), timezone_identifiers_list(), true) ? (string)$_SESSION['install']['timezone'] : 'Asia/Tokyo';
    $description = trim((string)($_SESSION['install']['description_default'] ?? '')) ?: '図書ごと・ユーザーごとの読書進展を、目次・メモ・グラフでやさしく管理するサイトです。';
    $settings = [
        'site_name' => $siteName,
        'author' => 'ShioriNote Project',
        'theme_color' => $theme,
        'description_default' => $description,
        'default_timezone' => $timezone,
        'rewrite_enabled' => 'true',
        'registration_enabled' => 'false',
        'progress_time_bucket' => 'daily',
    ];
    $settingsTable = $db['prefix'] . 'site_settings';
    $stmt = $pdo->prepare("INSERT INTO `$settingsTable`(setting_key, setting_value, updated_at_utc) VALUES(?, ?, UTC_TIMESTAMP(6)) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at_utc=UTC_TIMESTAMP(6)");
    foreach ($settings as $k => $v) { $stmt->execute([$k, $v]); }
    $log[] = 'サイト基本設定を保存しました。';

    $loginId = trim((string)($_SESSION['install']['login_id'] ?? ''));
    $email = trim((string)($_SESSION['install']['email'] ?? ''));
    $password = (string)($_SESSION['install']['password'] ?? '');
    $nickname = trim((string)($_SESSION['install']['nickname'] ?? '')) ?: $loginId;
    if ($loginId === '' || !preg_match('/^[A-Za-z0-9_\-]{3,64}$/', $loginId)) { throw new RuntimeException('管理者IDの形式が正しくありません。'); }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('メールアドレスの形式が正しくありません。'); }
    if (strlen($password) < 8) { throw new RuntimeException('パスワードは8文字以上にしてください。'); }
    [$salt, $hash] = inst_create_password_bundle($password);
    $avatar = (string)($_SESSION['install']['avatar_path'] ?? '/upload/avatar/default-avatar.svg');
    $bio = trim((string)($_SESSION['install']['bio'] ?? '')) ?: '初期管理ユーザー';
    $link = trim((string)($_SESSION['install']['link_url'] ?? ''));
    if ($link !== '' && !filter_var($link, FILTER_VALIDATE_URL)) { throw new RuntimeException('リンクURLの形式が正しくありません。'); }
    $role = $devMode ? 'developer' : 'super_admin';
    $usersTable = $db['prefix'] . 'users';
    $stmt = $pdo->prepare("INSERT INTO `$usersTable`(login_id,email,password_salt,password_hash,nickname,avatar_path,bio,link_url,timezone,role,status,created_at_utc,updated_at_utc) VALUES(?,?,?,?,?,?,?,?,?,?, 'active', UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))");
    $stmt->execute([$loginId, $email, $salt, $hash, $nickname, $avatar, $bio, $link, $timezone, $role]);
    $log[] = '初期管理ユーザーを登録しました（権限: ' . ($devMode ? '開発者' : '総管理') . '）。';

    $adminPath = trim((string)($_SESSION['install']['admin_path'] ?? ''));
    if (!preg_match('/^[a-z0-9]{8,64}$/', $adminPath)) { throw new RuntimeException('管理パスは小文字英数字8〜64文字で指定してください。'); }
    $baseUrl = rtrim(trim((string)($_SESSION['install']['site_base_url'] ?? '')), '/');
    if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) { throw new RuntimeException('インストールしようドメインの形式が正しくありません。'); }
    $scheme = strtolower((string)parse_url($baseUrl, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http','https'], true)) { throw new RuntimeException('インストールしようドメインは http または https で指定してください。'); }
    $subdir = inst_clean_subdir((string)($_SESSION['install']['site_subdir'] ?? ''));
    if ($subdir !== '' && !preg_match('#^/[A-Za-z0-9_./-]+$#', $subdir)) { throw new RuntimeException('サブフォルダの形式が正しくありません。'); }
    $cfg = [
        'site_name' => $siteName,
        'author' => 'ShioriNote Project',
        'theme_color' => $theme,
        'description_default' => $description,
        'default_timezone' => $timezone,
        'admin_panel_path' => $adminPath,
        'site_base_url' => $baseUrl,
        'site_subdir' => $subdir,
        'db' => $db,
    ];
    if (is_file($configFile)) {
        $bak = $configFile . '-' . date('ymdHis') . '.bak';
        if (!rename($configFile, $bak)) { throw new RuntimeException('既存設定ファイルのバックアップを作成できません。'); }
        $log[] = '既存 config/base.inc.phtml をバックアップしました。';
    }
    $configText = inst_make_config($cfg);
    if (file_put_contents($configFile, $configText, LOCK_EX) === false) { throw new RuntimeException('config/base.inc.phtml を作成できません。'); }
    @chmod($configFile, 0600);
    $log[] = 'config/base.inc.phtml を作成しました。';
    if (file_put_contents($lockFile, 'installed_at=' . gmdate('c') . "\n", LOCK_EX) === false) { throw new RuntimeException('install.lock を作成できません。'); }
    @chmod($lockFile, 0600);
    $log[] = 'install.lock を作成しました。';
    $updateLock = "version=2.4.0\nupdated_at=" . gmdate('c') . "\nsource=installer\n";
    if (file_put_contents($updateLockFile, $updateLock, LOCK_EX) === false) { throw new RuntimeException('update.lock を作成できません。'); }
    @chmod($updateLockFile, 0600);
    $log[] = 'update.lock を作成しました。';

    $_SESSION['install_done'] = [
        'admin_url' => rtrim($baseUrl, '/') . $subdir . '/' . $adminPath,
        'index_url' => rtrim($baseUrl, '/') . $subdir . '/',
        'login_id' => $loginId,
        'password' => $password,
        'role' => $role,
        'log' => $log,
    ];
    return $log;
}

$step = $stepParam;
$error = '';
$notice = '';
$testOk = false;
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        inst_check_csrf();
        $action = (string)($_POST['action'] ?? '');
        if ($action === 'env_next') { header('Location: ' . inst_url('db', $devMode)); exit; }
        if ($action === 'db_save' || $action === 'db_test') {
            inst_save_values(['db_name','db_user','db_pass','db_host','db_port','db_prefix','db_ssl_ca']);
            $db = ['name'=>(string)inst_post_value('db_name','shiorinote'),'user'=>(string)inst_post_value('db_user','shiorinote'),'pass'=>(string)inst_post_value('db_pass',''),'host'=>(string)inst_post_value('db_host','127.0.0.1'),'port'=>(int)inst_post_value('db_port',3306),'prefix'=>(string)inst_post_value('db_prefix','sn1_'),'ssl_ca'=>(string)inst_post_value('db_ssl_ca','')];
            if (!preg_match('/^[A-Za-z0-9_]*$/', $db['prefix'])) { throw new RuntimeException('テーブルプレフィックスは半角英数字とアンダースコアだけ使えます。'); }
            inst_pdo_from($db, true);
            $_SESSION['install']['db_test_ok'] = true;
            $testOk = true;
            $notice = 'データベース接続テストに成功しました。';
            if ($action === 'db_save') { header('Location: ' . inst_url('site', $devMode)); exit; }
        }
        if ($action === 'site_save') {
            inst_save_values(['site_base_url','site_subdir','theme_color','timezone','description_default']);
            header('Location: ' . inst_url('account', $devMode)); exit;
        }
        if ($action === 'account_save') {
            inst_save_values(['login_id','email','password','nickname','bio','link_url','admin_path']);
            if (!preg_match('/^[A-Za-z0-9_\-]{3,64}$/', (string)inst_post_value('login_id',''))) { throw new RuntimeException('IDは半角英数字・アンダースコア・ハイフン3〜64文字で指定してください。'); }
            if (!filter_var((string)inst_post_value('email',''), FILTER_VALIDATE_EMAIL)) { throw new RuntimeException('メールアドレスの形式が正しくありません。'); }
            if (strlen((string)inst_post_value('password','')) < 8) { throw new RuntimeException('パスワードは8文字以上にしてください。'); }
            $linkValue = trim((string)inst_post_value('link_url',''));
            if ($linkValue !== '' && !filter_var($linkValue, FILTER_VALIDATE_URL)) { throw new RuntimeException('リンクURLの形式が正しくありません。'); }
            if (!preg_match('/^[a-z0-9]{8,64}$/', (string)inst_post_value('admin_path',''))) { throw new RuntimeException('管理パスは小文字英数字8〜64文字で指定してください。'); }
            if (($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $_SESSION['install']['avatar_path'] = inst_save_avatar($_FILES['avatar']);
            }
            header('Location: ' . inst_url('run', $devMode)); exit;
        }
    }
    if ($step === 'run') {
        $log = inst_install($devMode);
        $_SESSION['install_log'] = $log;
        header('Refresh: 2; url=' . inst_url('done', $devMode));
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function inst_head(string $title): void { ?><!doctype html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?php echo inst_e($title); ?>｜しおりノート Install</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Klee+One:wght@400;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.23.13/css/uikit.min.css" integrity="sha512-giAxX2Dm0fHfTxCGThgfHXfyqC+NAsPAMI39ZDfs70vsKGALMfsNEbxlq6rZxPWWjH685ehdfvTQJkAWEgxOPw==" crossorigin="anonymous" referrerpolicy="no-referrer"><link rel="stylesheet" href="<?php echo inst_e(inst_public_url('/style/base.css')); ?>"><style>.sn-install{max-width:960px;margin:32px auto}.sn-step{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}.sn-step span{border-radius:999px;padding:4px 12px;background:#f4edf2;color:#6E1E51}.sn-step .on{background:#6E1E51;color:white}.sn-card{border-radius:22px}.sn-ok{color:#138a52}.sn-ng{color:#b91c1c}.sn-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}@media screen and (orientation: portrait){.sn-grid{grid-template-columns:1fr}}</style></head><body><main class="uk-container sn-install"><div class="uk-text-center uk-margin"><img src="<?php echo inst_e(inst_public_url('/brand.png')); ?>" alt="しおりノート" style="max-width:360px;width:80%;height:auto"></div><?php }
function inst_foot(): void { ?><script defer src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.23.13/js/uikit.min.js" integrity="sha512-g9wkFlti+bZT3YNTbVcMumimOS+hJSfbBEnKKP+e307qqQ3Ye4Bx7p/xUJ8yNRMotwudcofKL60ck1BGxk1t6Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><script defer src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.23.13/js/uikit-icons.min.js" integrity="sha512-fyzBJExpV4/Aprql1Gm4X0g3Qtmyev/D8KFVkuYYLD4ixhkVwTrSm/3rvYWWKTFtxN0H5/xTBQYxqOgL8CL5Rw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script></main></body></html><?php }
function inst_steps(string $current): void { $steps=['env'=>'環境確認','db'=>'DB設定','site'=>'サイト設定','account'=>'初期アカウント','run'=>'導入','done'=>'完了']; ?><div class="sn-step"><?php foreach($steps as $k=>$v): ?><span class="<?php echo $k===$current?'on':''; ?>"><?php echo inst_e($v); ?></span><?php endforeach; ?></div><?php }

inst_head('インストール');
inst_steps($step);
if ($error): ?><div class="uk-alert-danger" uk-alert><p><?php echo inst_e($error); ?></p></div><?php endif; ?><?php if ($notice): ?><div class="uk-alert-success" uk-alert><p><?php echo inst_e($notice); ?></p></div><?php endif; ?>
<?php if ($step === 'env'): $checks = inst_env_checks(); ?><section class="uk-card uk-card-default uk-card-body sn-card"><h1>セットアップ環境チェック</h1><p>しおりノートを動かすために必要なPHP機能、Webサーバー、書き込み権限を確認します。</p><table class="uk-table uk-table-divider uk-table-small"><thead><tr><th>項目</th><th>状態</th><th>詳細</th></tr></thead><tbody><?php foreach($checks as $c): ?><tr><td><?php echo inst_e($c['label']); ?></td><td class="<?php echo $c['ok']?'sn-ok':'sn-ng'; ?>"><?php echo $c['ok']?'OK':'NG'; ?></td><td><?php echo inst_e($c['note']); ?><?php echo !$c['fatal'] && !$c['ok'] ? '（警告）' : ''; ?></td></tr><?php endforeach; ?></tbody></table><form method="post"><input type="hidden" name="csrf_token" value="<?php echo inst_e(inst_csrf()); ?>"><input type="hidden" name="action" value="env_next"><button class="uk-button uk-button-primary" type="submit" <?php echo inst_env_ok($checks)?'':'disabled'; ?>>次へ</button></form></section>
<?php elseif ($step === 'db'): ?><section class="uk-card uk-card-default uk-card-body sn-card"><h1>データベース設定</h1><p>使用するデータベースは事前に作成してください。SQLファイル側ではデータベース名を固定しません。</p><form method="post" class="sn-grid"><input type="hidden" name="csrf_token" value="<?php echo inst_e(inst_csrf()); ?>"><label>データベース名<input class="uk-input" name="db_name" value="<?php echo inst_e(inst_post_value('db_name','shiorinote')); ?>" required></label><label>データユーザー名<input class="uk-input" name="db_user" value="<?php echo inst_e(inst_post_value('db_user','shiorinote')); ?>" required></label><label>パスワード<input class="uk-input" type="password" name="db_pass" value="<?php echo inst_e(inst_post_value('db_pass','')); ?>"></label><label>ホスト<input class="uk-input" name="db_host" value="<?php echo inst_e(inst_post_value('db_host','127.0.0.1')); ?>" required></label><label>ポート<input class="uk-input" type="number" name="db_port" value="<?php echo inst_e(inst_post_value('db_port','3306')); ?>" required></label><label>テーブルプレフィックス<input class="uk-input" name="db_prefix" value="<?php echo inst_e(inst_post_value('db_prefix','sn1_')); ?>" pattern="[A-Za-z0-9_]*"></label><label class="uk-width-1-1">SQL証明書 / SSL CA（任意）<input class="uk-input" name="db_ssl_ca" value="<?php echo inst_e(inst_post_value('db_ssl_ca','')); ?>" placeholder="/path/to/ca.pem"></label><div><button class="uk-button uk-button-default" name="action" value="db_test" type="submit">接続テスト</button><button class="uk-button uk-button-primary uk-margin-small-left" name="action" value="db_save" type="submit">次へ</button></div></form></section>
<?php elseif ($step === 'site'): ?><section class="uk-card uk-card-default uk-card-body sn-card"><h1>サイト基本設定</h1><form method="post" class="sn-grid"><input type="hidden" name="csrf_token" value="<?php echo inst_e(inst_csrf()); ?>"><input type="hidden" name="action" value="site_save"><label>インストールしようドメイン<input class="uk-input" name="site_base_url" value="<?php echo inst_e(inst_post_value('site_base_url', ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com'))); ?>" placeholder="https://yourdomain.com"></label><label>サブフォルダ<input class="uk-input" name="site_subdir" value="<?php echo inst_e(inst_post_value('site_subdir','')); ?>" placeholder="例: /books"></label><label>テーマ色<input class="uk-input" type="color" name="theme_color" value="<?php echo inst_e(inst_post_value('theme_color','#6E1E51')); ?>"></label><label>Timezone<select class="uk-select" name="timezone"><?php $tzv=(string)inst_post_value('timezone','Asia/Tokyo'); foreach(timezone_identifiers_list() as $tz): ?><option value="<?php echo inst_e($tz); ?>" <?php echo $tz===$tzv?'selected':''; ?>><?php echo inst_e($tz); ?></option><?php endforeach; ?></select></label><label class="uk-width-1-1">サイトのdescription<textarea class="uk-textarea" name="description_default" rows="3"><?php echo inst_e(inst_post_value('description_default','図書ごと・ユーザーごとの読書進展を、目次・メモ・グラフでやさしく管理するサイトです。')); ?></textarea></label><div><button class="uk-button uk-button-primary" type="submit">次へ</button></div></form></section>
<?php elseif ($step === 'account'): $defaultPath = (string)inst_post_value('admin_path', inst_random_path(16)); if (empty($_SESSION['install']['admin_path'])) { $_SESSION['install']['admin_path'] = $defaultPath; } ?><section class="uk-card uk-card-default uk-card-body sn-card"><h1>初期アカウントと管理パス</h1><p>この画面で作成する初期ユーザーの権限は <strong><?php echo $devMode ? '開発者' : '総管理'; ?></strong> です。UIDとユーザーグループは自動設定されます。</p><form method="post" enctype="multipart/form-data" class="sn-grid"><input type="hidden" name="csrf_token" value="<?php echo inst_e(inst_csrf()); ?>"><input type="hidden" name="action" value="account_save"><label>ID<input class="uk-input" name="login_id" value="<?php echo inst_e(inst_post_value('login_id','admin')); ?>" required pattern="[A-Za-z0-9_\-]{3,64}"></label><label>メール<input class="uk-input" type="email" name="email" value="<?php echo inst_e(inst_post_value('email','admin@example.com')); ?>" required></label><label>パスワード<input class="uk-input" type="password" name="password" value="<?php echo inst_e(inst_post_value('password','')); ?>" required minlength="8"></label><label>あだ名<input class="uk-input" name="nickname" value="<?php echo inst_e(inst_post_value('nickname','管理者')); ?>"></label><label>アバター（任意）<input class="uk-input" type="file" name="avatar" accept="image/png,image/jpeg,image/webp"></label><label>リンク<input class="uk-input" name="link_url" value="<?php echo inst_e(inst_post_value('link_url','')); ?>" placeholder="https://example.com"></label><label class="uk-width-1-1">個人紹介<textarea class="uk-textarea" name="bio" rows="2"><?php echo inst_e(inst_post_value('bio','サイト管理者')); ?></textarea></label><label class="uk-width-1-1">管理パス<div class="uk-flex"><input id="sn-admin-path" class="uk-input" name="admin_path" value="<?php echo inst_e($defaultPath); ?>" required pattern="[a-z0-9]{8,64}"><button class="uk-button uk-button-default uk-margin-small-left" type="button" onclick="snGenPath()">自動生成</button></div></label><div><button class="uk-button uk-button-primary" type="submit">次へ</button></div></form><script>function snGenPath(){const c='abcdefghijklmnopqrstuvwxyz0123456789';let s='';for(let i=0;i<16;i++)s+=c[Math.floor(Math.random()*c.length)];document.getElementById('sn-admin-path').value=s;}</script></section>
<?php elseif ($step === 'run'): $log = $_SESSION['install_log'] ?? []; ?><section class="uk-card uk-card-default uk-card-body sn-card"><h1>データベースインストール</h1><p>初期データベース、設定ファイル、初期管理ユーザーを作成しています。完了後、自動で完了画面へ移動します。</p><ul class="uk-list uk-list-divider"><?php foreach((array)$log as $line): ?><li><?php echo inst_e($line); ?></li><?php endforeach; ?><?php if (!$log): ?><li>処理中です...</li><?php endif; ?></ul><progress class="uk-progress" value="100" max="100"></progress></section>
<?php elseif ($step === 'done'): $done = $_SESSION['install_done'] ?? null; ?><section class="uk-card uk-card-default uk-card-body sn-card"><h1>セットアップ完了</h1><?php if (!$done): ?><p>完了情報が見つかりません。トップページへ移動してください。</p><a class="uk-button uk-button-primary" href="/">サイトインデックスへ</a><?php else: ?><div class="uk-alert-warning" uk-alert><p>管理パスは大切に保存してください。管理パスを忘れた場合、config/base.inc.phtml をサーバー上で確認する必要があります。</p></div><dl class="uk-description-list"><dt>管理パス</dt><dd><a id="sn-admin-url" href="<?php echo inst_e($done['admin_url']); ?>"><?php echo inst_e($done['admin_url']); ?></a> <button class="uk-button uk-button-default uk-button-small" onclick="snSaveUrl('admin')">デスクトップリンクを保存</button></dd><dt>サイトインデックス</dt><dd><a id="sn-index-url" href="<?php echo inst_e($done['index_url']); ?>"><?php echo inst_e($done['index_url']); ?></a> <button class="uk-button uk-button-default uk-button-small" onclick="snSaveUrl('index')">デスクトップリンクを保存</button></dd><dt>管理者ユーザー名</dt><dd><?php echo inst_e($done['login_id']); ?></dd><dt>パスワード</dt><dd><code><?php echo inst_e($done['password']); ?></code></dd><dt>権限</dt><dd><?php echo $done['role']==='developer'?'開発者':'総管理'; ?></dd></dl><p><a class="uk-button uk-button-primary" href="<?php echo inst_e($done['admin_url']); ?>">管理パネルへ</a><a class="uk-button uk-button-default uk-margin-small-left" href="<?php echo inst_e($done['index_url']); ?>">サイトインデックスへ</a></p><script>function snSaveUrl(type){const id=type==='admin'?'sn-admin-url':'sn-index-url';const url=document.getElementById(id).href;const name=type==='admin'?'ShioriNote-Admin.url':'ShioriNote.url';const body='[InternetShortcut]\r\nURL='+url+'\r\n';const blob=new Blob([body],{type:'application/octet-stream'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(a.href),1000);}</script><?php endif; ?></section>
<?php else: ?><section class="uk-card uk-card-default uk-card-body sn-card"><h1>不明なステップ</h1><a class="uk-button uk-button-primary" href="<?php echo inst_e(inst_url('env', $devMode)); ?>">最初へ戻る</a></section><?php endif; ?>
<?php inst_foot(); ?>
