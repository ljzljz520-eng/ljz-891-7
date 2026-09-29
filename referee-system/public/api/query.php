<?php
/**
 * 公开查询接口：裁判编号 + 姓名双重校验
 * GET/POST  api/query.php?referee_code=&name=
 */

require_once __DIR__ . '/../../app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = request_body();
    $code = (string) ($body['referee_code'] ?? '');
    $name = trim((string) ($body['name'] ?? ''));
} else {
    $code = (string) ($_GET['referee_code'] ?? '');
    $name = trim((string) ($_GET['name'] ?? ''));
}

$code = normalize_code($code);
$name = trim($name);

if ($code === '' || $name === '') {
    json_fail('请同时输入裁判编号和姓名');
}

// 编号与姓名必须同时匹配，防止仅凭编号枚举信息
$sql = <<<SQL
SELECT r.*,
       t.training_date, t.course_name, t.organizer, t.result
FROM referees r
LEFT JOIN (
    SELECT t1.*
    FROM trainings t1
    JOIN (
        SELECT referee_id, MAX(training_date) AS max_date
        FROM trainings GROUP BY referee_id
    ) t2 ON t1.referee_id = t2.referee_id AND t1.training_date = t2.max_date
) t ON t.referee_id = r.id
WHERE r.referee_code = :code AND r.name = :name
LIMIT 1
SQL;

$stmt = db()->prepare($sql);
$stmt->execute([':code' => $code, ':name' => $name]);
$row = $stmt->fetch();

if (!$row) {
    json_fail('未查询到匹配的裁判资格信息，请核对编号与姓名', 404);
}

json_ok(['referee' => format_referee($row)]);
