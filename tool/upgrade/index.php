<?php
declare(strict_types=1);

/**
 * ShioriNote database upgrade tool.
 * CLI only: php tool/upgrade/index.php
 */

const SHIORINOTE_TARGET_VERSION = '2.5.0';
const SHIORINOTE_ENGLISH_NAME = 'ShioriNote';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This upgrade tool is CLI only.\n";
    exit(1);
}

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}

$configFile = APP_ROOT . '/config/base.inc.phtml';
$lockFile = APP_ROOT . '/update.lock';
$migrationDir = APP_ROOT . '/database-sample';
$backupDir = APP_ROOT . '/backup/database';

function up_line(string $message = ''): void
{
    echo $message . PHP_EOL;
}

function up_fail(string $message, ?Throwable $e = null): never
{
    fwrite(STDERR, '[ERROR] ' . $message . PHP_EOL);
    if ($e !== null) {
        fwrite(STDERR, '        ' . $e->getMessage() . PHP_EOL);
    }
    exit(1);
}

function up_parse_lock(string $file): array
{
    if (!is_file($file)) {
        return [];
    }
    $data = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $data[trim($key)] = trim($value);
    }
    return $data;
}

function up_validate_version(string $version): string
{
    $version = ltrim(trim($version), 'vV');
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        return '0.0.0';
    }
    return $version;
}

function up_sql_value(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }
    return (string)$pdo->quote((string)$value);
}

function up_backup_database(PDO $pdo, string $backupDir): string
{
    if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
        throw new RuntimeException('バックアップフォルダを作成できません: ' . $backupDir);
    }
    if (!is_writable($backupDir)) {
        throw new RuntimeException('バックアップフォルダに書き込めません: ' . $backupDir);
    }

    $file = rtrim($backupDir, '/\\') . '/' . date('Ymd_His') . '_' . SHIORINOTE_ENGLISH_NAME . '.sql';
    $fh = fopen($file, 'wb');
    if ($fh === false) {
        throw new RuntimeException('バックアップファイルを作成できません: ' . $file);
    }

    try {
        fwrite($fh, "-- ShioriNote database backup\n");
        fwrite($fh, "-- Created at: " . gmdate('c') . "\n");
        fwrite($fh, "-- Database: " . (string)$pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = [];
        $stmt = $pdo->query('SHOW FULL TABLES');
        while (($row = $stmt->fetch(PDO::FETCH_NUM)) !== false) {
            if (($row[1] ?? '') === 'BASE TABLE') {
                $tables[] = (string)$row[0];
            }
        }

        foreach ($tables as $table) {
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            fwrite($fh, "-- --------------------------------------------------------\n");
            fwrite($fh, "-- Table structure for $quotedTable\n\n");
            $createRow = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
            $createSql = (string)($createRow[1] ?? '');
            fwrite($fh, 'DROP TABLE IF EXISTS ' . $quotedTable . ";\n");
            fwrite($fh, $createSql . ";\n\n");
            fwrite($fh, "-- Data for $quotedTable\n\n");
            $rows = $pdo->query('SELECT * FROM ' . $quotedTable);
            $count = 0;
            while (($row = $rows->fetch(PDO::FETCH_ASSOC)) !== false) {
                $columns = array_map(static fn($col) => '`' . str_replace('`', '``', (string)$col) . '`', array_keys($row));
                $values = array_map(static fn($value) => up_sql_value($pdo, $value), array_values($row));
                fwrite($fh, 'INSERT INTO ' . $quotedTable . '(' . implode(',', $columns) . ') VALUES(' . implode(',', $values) . ");\n");
                $count++;
            }
            fwrite($fh, "\n-- $count rows exported from $quotedTable\n\n");
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
    } finally {
        fclose($fh);
    }
    @chmod($file, 0600);
    return $file;
}

function up_split_sql(string $sql): array
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
            if ($ch === '\\') {
                if ($i + 1 < $len) {
                    $buf .= $sql[++$i];
                }
                continue;
            }
            if ($ch === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
            continue;
        }
        if ($ch === ';') {
            $stmt = trim(substr($buf, 0, -1));
            if ($stmt !== '') {
                $parts[] = $stmt;
            }
            $buf = '';
        }
    }
    $last = trim($buf);
    if ($last !== '') {
        $parts[] = $last;
    }
    return $parts;
}

function up_find_migrations(string $dir, string $current, string $target): array
{
    if (!is_dir($dir)) {
        throw new RuntimeException('migrationフォルダが見つかりません: ' . $dir);
    }
    $items = [];
    foreach (glob($dir . '/migration_v*.sql') ?: [] as $file) {
        if (preg_match('/migration_v(\d+\.\d+\.\d+)\.sql$/', basename($file), $m)) {
            $version = $m[1];
            if (version_compare($version, $current, '>') && version_compare($version, $target, '<=')) {
                $items[] = ['version' => $version, 'file' => $file];
            }
        }
    }
    usort($items, static fn($a, $b) => version_compare($a['version'], $b['version']));
    return $items;
}

function up_apply_migration(PDO $pdo, string $file): int
{
    if (!is_file($file)) {
        throw new RuntimeException('migrationファイルが見つかりません: ' . $file);
    }
    $sql = (string)file_get_contents($file);
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;\s*/ims', '', $sql) ?? $sql;
    $sql = preg_replace('/^\s*USE\s+[`\w]+\s*;\s*/ims', '', $sql) ?? $sql;
    $count = 0;
    foreach (up_split_sql($sql) as $stmt) {
        $pdo->exec($stmt);
        $count++;
    }
    return $count;
}

function up_write_lock(string $file, string $version): void
{
    $text = "version={$version}\nupdated_at=" . gmdate('c') . "\nsource=tool/upgrade\n";
    if (file_put_contents($file, $text, LOCK_EX) === false) {
        throw new RuntimeException('update.lockを書き込めません: ' . $file);
    }
    @chmod($file, 0600);
}

try {
    if (!is_file($configFile)) {
        up_fail('config/base.inc.phtml が見つかりません。先に /install/ で初期インストールしてください。');
    }
    require_once $configFile;
    if (!function_exists('app_config') || !function_exists('db')) {
        up_fail('config/base.inc.phtml を読み込めましたが、必要な関数が見つかりません。');
    }

    $lock = up_parse_lock($lockFile);
    $current = up_validate_version((string)($lock['version'] ?? app_config('version', '0.0.0')));
    $target = SHIORINOTE_TARGET_VERSION;

    up_line('ShioriNote upgrade tool');
    up_line('Current DB version: v' . $current);
    up_line('Target version    : v' . $target);

    if (version_compare($current, $target, '>=')) {
        up_line('Already up to date.');
        if (!is_file($lockFile)) {
            up_write_lock($lockFile, $target);
            up_line('update.lock を作成しました。');
        }
        exit(0);
    }

    $pdo = db();
    $pdo->query('SELECT 1')->fetchColumn();
    up_line('Database connection: OK');

    $backup = up_backup_database($pdo, $backupDir);
    up_line('Backup created: ' . $backup);

    $migrations = up_find_migrations($migrationDir, $current, $target);
    if (!$migrations) {
        up_line('No SQL migration files to apply for this version range.');
    }

    foreach ($migrations as $migration) {
        up_line('Applying migration v' . $migration['version'] . ': ' . basename($migration['file']));
        $count = up_apply_migration($pdo, $migration['file']);
        up_line('  Done. Statements: ' . $count);
    }

    up_write_lock($lockFile, $target);
    up_line('update.lock updated to v' . $target . '.');
    up_line('Upgrade completed successfully.');
    exit(0);
} catch (Throwable $e) {
    up_fail('アップグレードに失敗しました。バックアップが作成済みの場合は backup/database を確認してください。', $e);
}
