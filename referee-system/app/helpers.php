<?php
/**
 * 通用辅助函数
 */

/** JSON 响应并结束 */
function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function json_ok(array $data = []): void
{
    json_response(array_merge(['ok' => true], $data), 200);
}

function json_fail(string $message, int $status = 400, array $extra = []): void
{
    json_response(array_merge(['ok' => false, 'error' => $message], $extra), $status);
}

/** 读取 JSON 请求体 */
function request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) {
        return $_POST ?: [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/** 标准化裁判编号（去空格、转大写） */
function normalize_code(string $code): string
{
    return strtoupper(trim($code));
}

/** 校验日期 YYYY-MM-DD */
function valid_date(?string $date): bool
{
    if (!is_string($date) || $date === '') {
        return false;
    }
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * 根据有效期与状态计算展示状态
 * valid 有效 / expiring 即将到期 / expired 已过期 / disabled 已停用
 */
function qualification_status(string $validUntil, int $isActive): array
{
    if (!$isActive) {
        return ['code' => 'disabled', 'text' => '已停用'];
    }
    $today = new DateTime('today');
    $end   = DateTime::createFromFormat('Y-m-d', $validUntil);
    if (!$end) {
        return ['code' => 'valid', 'text' => '有效'];
    }
    $diff = (int) $today->diff($end)->format('%r%a');
    if ($diff < 0) {
        return ['code' => 'expired', 'text' => '已过期'];
    }
    if ($diff <= EXPIRY_WARN_DAYS) {
        return ['code' => 'expiring', 'text' => '即将到期'];
    }
    return ['code' => 'valid', 'text' => '有效'];
}

/** 组装裁判详情（含状态与最近培训） */
function format_referee(array $row): array
{
    $status = qualification_status($row['valid_until'], (int) $row['is_active']);
    return [
        'id'            => (int) $row['id'],
        'referee_code'  => $row['referee_code'],
        'name'          => $row['name'],
        'level'         => $row['level'],
        'sport'         => $row['sport'],
        'valid_until'   => $row['valid_until'],
        'issuer'        => $row['issuer'],
        'is_active'     => (int) $row['is_active'],
        'created_at'    => $row['created_at'],
        'status_code'   => $status['code'],
        'status_text'   => $status['text'],
        'last_training' => !empty($row['training_date']) ? [
            'training_date' => $row['training_date'],
            'course_name'   => $row['course_name'] ?? '',
            'organizer'     => $row['organizer'] ?? '',
            'result'        => $row['result'] ?? '',
        ] : null,
    ];
}
