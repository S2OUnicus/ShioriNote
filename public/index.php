<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

function sn_request_path(): string
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
    if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
        $path = substr($path, strlen($scriptDir));
    }
    return '/' . ltrim($path, '/');
}

$path = sn_request_path();
if (preg_match('#^/install(?:/s2odev)?/?$#', $path)) {
    require APP_ROOT . '/public/install/index.php';
    exit;
}

$configFile = APP_ROOT . '/config/base.inc.phtml';
$lockFile = APP_ROOT . '/install.lock';
if (!is_file($configFile) && is_file($lockFile)) {
    http_response_code(500);
    ?><!doctype html><html lang="ja"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>設定ファイルが見つかりません｜しおりノート</title></head><body><main style="max-width:760px;margin:48px auto;font-family:sans-serif;line-height:1.8"><h1>設定ファイルが見つかりません</h1><p><code>install.lock</code> は存在しますが、<code>config/base.inc.phtml</code> が見つかりません。バックアップから復元するか、再インストールする場合はサーバー上で <code>install.lock</code> を削除してください。</p></main></body></html><?php
    exit;
}
if (!is_file($configFile) || !is_file($lockFile)) {
    header('Location: /install/');
    exit;
}

require_once $configFile;
require_once APP_ROOT . '/config/app.functions.phtml';

try {
    app_bootstrap();
    start_minify();
    if (users_count() <= 0) {
        $pageTitle = page_title('初期設定が必要です');
        $pageDescription = '初期管理ユーザーが見つかりません。';
?><!doctype html><html lang="ja"><head><?php require APP_ROOT . '/page/common/meta.phtml'; ?><?php require APP_ROOT . '/page/common/link.phtml'; ?><title><?php echo e($pageTitle); ?></title></head><body><?php require APP_ROOT . '/page/parts/noscript.phtml'; ?><div class="rpm-shell"><?php require APP_ROOT . '/page/parts/nav.phtml'; ?><main class="rpm-main uk-container"><div class="uk-card uk-card-default uk-card-body rpm-card uk-margin-large"><h1>初期管理ユーザーがありません</h1><p>データベースにユーザーが一人も登録されていません。初期インストールを行うか、既存データベースを確認してください。</p><?php if (!is_file($lockFile)): ?><a class="uk-button uk-button-primary" href="/install/">初期インストールへ進む</a><?php else: ?><div class="uk-alert-warning" uk-alert><p><code>install.lock</code> が存在するため、インストーラーは起動できません。再インストールする場合は、サーバー上で <code>install.lock</code> を削除してからアクセスしてください。</p></div><?php endif; ?></div></main><?php require APP_ROOT . '/page/parts/footer.phtml'; ?></div><a href="#" class="uk-totop rpm-totop" uk-totop uk-scroll aria-label="Top">Top</a><?php require APP_ROOT . '/page/common/script.phtml'; ?></body></html><?php
        exit;
    }

    $route = app_route();
    $name = $route['name'];
    $routeId = $route['id'];
    $allowed = ['index', 'books', 'book', 'progress', 'login', 'logout', 'register', 'forgot', 'user', 'my-books', 'admin'];
    if (!in_array($name, $allowed, true)) {
        http_response_code(404);
        $name = 'index';
        app_flash('warning', 'ページが見つからなかったため、トップページを表示しました。');
    }

    $guestAllowed = ['login', 'forgot'];
    if (registration_enabled()) {
        $guestAllowed[] = 'register';
    }
    if ($name === 'register' && !registration_enabled()) {
        app_flash('warning', '新規登録は現在停止されています。管理者にお問い合わせください。');
        redirect_to('login');
    }
    if (!is_logged_in() && !in_array($name, $guestAllowed, true)) {
        app_flash('warning', 'ログインが必要です。');
        redirect_to('login');
    }

    if ($name === 'logout') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            throw new RuntimeException('ログアウトはPOSTで実行してください。');
        }
        verify_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start();
        app_flash('success', 'ログアウトしました。');
        redirect_to('login');
    }

    $file = APP_ROOT . '/page/item/' . match ($name) {
        'books' => 'books_grid',
        'book' => 'book_detail',
        'user' => 'user_center',
        'my-books' => 'my_books',
        default => $name,
    } . '.phtml';
    if (!is_file($file)) {
        throw new RuntimeException('ページファイルが見つかりません。');
    }
    $pageDescription = setting('description_default', app_config('description_default'));
    $pageTitle = page_title(match($name) {
        'books' => '図書リスト',
        'book' => '図書紹介',
        'progress' => '読書進展管理',
        'login' => 'ログイン',
        'register' => '新規登録',
        'forgot' => 'パスワード忘れ',
        'user' => 'ユーザーセンター',
        'my-books' => '図書管理',
        'admin' => '管理システム',
        default => 'インデックス',
    });
    $isHtmx = strtolower($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
    if ($isHtmx) {
        echo '<main class="rpm-main uk-container">';
        render_flash();
        require $file;
        echo '</main>';
        exit;
    }
?><!doctype html><html lang="ja"><head><?php require APP_ROOT . '/page/common/meta.phtml'; ?><?php require APP_ROOT . '/page/common/link.phtml'; ?><title><?php echo e($pageTitle); ?></title></head><body><?php require APP_ROOT . '/page/parts/noscript.phtml'; ?><div class="rpm-shell"><?php require APP_ROOT . '/page/parts/nav.phtml'; ?><main class="rpm-main uk-container"><?php render_flash(); require $file; ?></main><?php require APP_ROOT . '/page/parts/footer.phtml'; ?></div><a href="#" class="uk-totop rpm-totop" uk-totop uk-scroll aria-label="Top">Top</a><?php require APP_ROOT . '/page/common/script.phtml'; ?></body></html><?php
} catch (Throwable $e) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    app_bootstrap();
    app_log_error($e);
    start_minify();
    $pageTitle = page_title('エラー');
    $pageDescription = 'エラーが発生しました。';
    $publicMessage = app_debug() ? $e->getMessage() : '処理中にエラーが発生しました。時間を置いて再度お試しください。';
?><!doctype html><html lang="ja"><head><?php require APP_ROOT . '/page/common/meta.phtml'; ?><?php require APP_ROOT . '/page/common/link.phtml'; ?><title><?php echo e($pageTitle); ?></title></head><body><?php require APP_ROOT . '/page/parts/noscript.phtml'; ?><div class="rpm-shell"><?php require APP_ROOT . '/page/parts/nav.phtml'; ?><main class="rpm-main uk-container"><div class="rpm-error-card uk-card uk-card-default uk-card-body"><h1>処理できませんでした</h1><p><?php echo e($publicMessage); ?></p><p class="uk-text-meta">初期設定がまだの場合は、<code>/install/</code> にアクセスしてインストールを行ってください。</p><a class="uk-button uk-button-default" href="<?php echo e(url_for('index')); ?>">トップへ戻る</a></div></main><?php require APP_ROOT . '/page/parts/footer.phtml'; ?></div><a href="#" class="uk-totop rpm-totop" uk-totop uk-scroll aria-label="Top">Top</a><?php require APP_ROOT . '/page/common/script.phtml'; ?></body></html><?php
}
?>
