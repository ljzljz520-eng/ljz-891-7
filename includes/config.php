<?php
/**
 * 全局配置与初始化
 * - 会话（HttpOnly + SameSite）
 * - 安全响应头
 * - 公共函数（转义 / 级别白名单 / 状态计算）
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Shanghai');

define('APP_NAME', '赛事裁判资格查询系统');
define('DB_PATH', __DIR__ . '/../data/app.db');
define('CSRF_KEY', 'csrf_token');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_name('refsid');
    session_start();
}

// 基础安全响应头（X-Frame-Options / MIME 嗅探）
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

/** HTML 转义输出（标量统一按字符串处理，兼容表单回填中的数字） */
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** 裁判等级（级别）白名单，顺序即展示顺序 */
function referee_levels(): array
{
    return ['国家级', '一级', '二级', '三级'];
}

/** 常见运动项目（可选择，也允许手动输入自定义项目） */
function referee_sports(): array
{
    return [
        '田径', '游泳', '篮球', '足球', '排球', '乒乓球', '羽毛球',
        '网球', '体操', '举重', '射击', '射箭', '跆拳道', '武术', '其他',
    ];
}

/** 资格状态：active 有效 / expired 已过期 / revoked 已停用 */
function referee_status(array $r): string
{
    if ((int)$r['is_active'] === 0) {
        return 'revoked';
    }
    if (!empty($r['valid_until']) && strcmp($r['valid_until'], date('Y-m-d')) < 0) {
        return 'expired';
    }
    return 'active';
}

function status_text(string $s): string
{
    return match ($s) {
        'active'  => '有效',
        'expired' => '已过期',
        'revoked' => '已停用',
        default   => '未知',
    };
}

function status_class(string $s): string
{
    return match ($s) {
        'active'  => 'badge-valid',
        'expired' => 'badge-expired',
        'revoked' => 'badge-revoked',
        default   => 'badge-expired',
    };
}

/** 距离到期天数描述 */
function days_left_text(?string $validUntil): string
{
    if (empty($validUntil)) {
        return '长期有效';
    }
    $diff = (int)dateDiff($validUntil);
    if ($diff < 0) {
        return '已于 ' . $validUntil . ' 到期';
    }
    if ($diff === 0) {
        return '今日到期（' . $validUntil . '）';
    }
    return '有效期至 ' . $validUntil . '（剩余 ' . $diff . ' 天）';
}

function dateDiff(string $date): string
{
    $d1 = new DateTime($date);
    $d2 = new DateTime(date('Y-m-d'));
    return (string)$d2->diff($d1)->format('%r%a');
}

/** 闪存消息 */
function set_flash(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** CSRF token */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_KEY])) {
        $_SESSION[CSRF_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/** 校验 POST 来源的 CSRF token，失败直接 419 */
function csrf_verify(): void
{
    $t = $_POST['_token'] ?? '';
    if (!is_string($t) || empty($_SESSION[CSRF_KEY]) || !hash_equals($_SESSION[CSRF_KEY], $t)) {
        http_response_code(419);
        exit('会话已过期，请返回重试。');
    }
}

/** 重定向 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
