<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/config/base.inc.phtml';

try {
    app_bootstrap();
    start_minify();
    $route = app_route();
    $name = $route['name'];
    $routeId = $route['id'];
    $allowed = ['index', 'books', 'book', 'progress', 'login', 'logout', 'register', 'forgot', 'user', 'admin'];
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
        default => $name,
    } . '.phtml';
    if (!is_file($file)) {
        throw new RuntimeException('ページファイルが見つかりません。');
    }
    $pageDescription = setting('description_default', app_config('description_default'));
    $pageTitle = page_title(match($name) {
        'books' => '図書リスト',
        'book' => '図書紹介',
        'progress' => '読書進展',
        'login' => 'ログイン',
        'register' => '新規登録',
        'forgot' => 'パスワード忘れ',
        'user' => 'ユーザーセンター',
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
?><!doctype html><html lang="ja"><head><?php require APP_ROOT . '/page/common/meta.phtml'; ?><?php require APP_ROOT . '/page/common/link.phtml'; ?><title><?php echo e($pageTitle); ?></title></head><body><?php require APP_ROOT . '/page/parts/noscript.phtml'; ?><div class="rpm-shell"><?php require APP_ROOT . '/page/parts/nav.phtml'; ?><main class="rpm-main uk-container"><?php render_flash(); require $file; ?></main><?php require APP_ROOT . '/page/parts/footer.phtml'; ?></div><?php require APP_ROOT . '/page/common/script.phtml'; ?></body></html><?php
} catch (Throwable $e) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 500);
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    app_bootstrap();
    start_minify();
    $pageTitle = page_title('エラー');
    $pageDescription = 'エラーが発生しました。';
?><!doctype html><html lang="ja"><head><?php require APP_ROOT . '/page/common/meta.phtml'; ?><?php require APP_ROOT . '/page/common/link.phtml'; ?><title><?php echo e($pageTitle); ?></title></head><body><?php require APP_ROOT . '/page/parts/noscript.phtml'; ?><div class="rpm-shell"><?php require APP_ROOT . '/page/parts/nav.phtml'; ?><main class="rpm-main uk-container"><div class="rpm-error-card uk-card uk-card-default uk-card-body"><h1>処理できませんでした</h1><p><?php echo e($e->getMessage()); ?></p><p class="uk-text-meta">データベース未作成の場合は、database-sample/schema.sql と database-sample/seed.sample.sql を参考に、実運用用の database/ を準備してください。</p><a class="uk-button uk-button-default" href="<?php echo e(url_for('index')); ?>">トップへ戻る</a></div></main><?php require APP_ROOT . '/page/parts/footer.phtml'; ?></div><?php require APP_ROOT . '/page/common/script.phtml'; ?></body></html><?php
}
?>
