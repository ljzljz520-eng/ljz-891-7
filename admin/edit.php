<?php
/**
 * 后台：新增 / 编辑裁判资格（含培训记录维护）
 * 本页只负责展示表单；提交由 save.php 处理
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

$id       = (int)($_GET['id'] ?? 0);
$isEdit   = $id > 0;
$referee  = null;
$training = [];

if ($isEdit) {
    $referee = find_referee_by_id($id);
    if (!$referee) {
        set_flash('error', '未找到该裁判资格记录，可能已被删除。');
        redirect('index.php');
    }
    $training = training_records($id);
}

// 保存失败后回填（闪存旧输入）
$old = $_SESSION['old_input'] ?? null;
unset($_SESSION['old_input']);
if ($old) {
    $referee  = $old['referee'] ?? $referee;
    $training = $old['training'] ?? $training;
}
$training = $training ?: [
    ['train_date' => '', 'course_name' => '', 'organizer' => '', 'hours' => '', 'result' => '合格'],
];

$formAction = $isEdit ? 'save.php?id=' . $id : 'save.php';
page_header($isEdit ? '编辑资格' : '新增资格', 'admin');
?>

<div class="page-head">
    <h1><?= $isEdit ? '编辑裁判资格' : '新增裁判资格' ?></h1>
    <a href="index.php" class="btn btn-outline btn-sm">← 返回列表</a>
</div>

<form method="post" action="<?= e($formAction) ?>" class="card form-card">
    <?= csrf_field() ?>
    <div class="card-body">
        <h3 class="section-title" style="margin-top:0;">📋 基本信息</h3>
        <div class="form-grid">
            <div class="form-row">
                <label for="referee_no">裁判编号 <span class="hint">（唯一，建议字母+数字）</span></label>
                <input type="text" id="referee_no" name="referee_no" required maxlength="40"
                       pattern="[A-Za-z0-9\-_]+"
                       value="<?= e($referee['referee_no'] ?? '') ?>" placeholder="如 RF20260001">
            </div>
            <div class="form-row">
                <label for="name">姓名</label>
                <input type="text" id="name" name="name" required maxlength="30"
                       value="<?= e($referee['name'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="level">裁判级别</label>
                <select id="level" name="level" required>
                    <?php foreach (referee_levels() as $lv): ?>
                        <option value="<?= e($lv) ?>" <?= ($referee['level'] ?? '') === $lv ? 'selected' : '' ?>><?= e($lv) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <label for="sport">执裁项目 <span class="hint">（可选列表或自行输入）</span></label>
                <input type="text" id="sport" name="sport" list="sport-list" required maxlength="30"
                       value="<?= e($referee['sport'] ?? '') ?>" placeholder="如 田径">
                <datalist id="sport-list">
                    <?php foreach (referee_sports() as $sp): ?>
                        <option value="<?= e($sp) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-row" style="grid-column:1 / -1;">
                <label for="issuer">发证单位</label>
                <input type="text" id="issuer" name="issuer" required maxlength="100"
                       value="<?= e($referee['issuer'] ?? '') ?>" placeholder="如 国家体育总局田径运动管理中心">
            </div>
            <div class="form-row">
                <label for="valid_from">发证日期</label>
                <input type="date" id="valid_from" name="valid_from"
                       value="<?= e($referee['valid_from'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="valid_until">有效期截止 <span class="hint">（留空=长期有效）</span></label>
                <input type="date" id="valid_until" name="valid_until"
                       value="<?= e($referee['valid_until'] ?? '') ?>">
            </div>
            <div class="form-row">
                <label for="is_active">资格状态</label>
                <select id="is_active" name="is_active">
                    <option value="1" <?= (int)($referee['is_active'] ?? 1) === 1 ? 'selected' : '' ?>>正常（启用）</option>
                    <option value="0" <?= (int)($referee['is_active'] ?? 1) === 0 ? 'selected' : '' ?>>停用</option>
                </select>
            </div>
            <div class="form-row" style="grid-column:1 / -1;">
                <label for="remark">管理备注 <span class="hint">（仅后台可见，停用原因等可记录于此）</span></label>
                <textarea id="remark" name="remark" rows="2" maxlength="255"><?= e($referee['remark'] ?? '') ?></textarea>
            </div>
        </div>

        <h3 class="section-title">📚 培训记录
            <button type="button" class="btn btn-outline btn-sm" id="addTrain">＋ 添加一行</button>
        </h3>
        <div class="training-editor" id="trainEditor">
            <div class="train-row head">
                <div>培训日期</div><div>培训课程</div><div>主办单位</div><div>学时</div><div>考核结果</div><div></div>
            </div>
            <?php foreach ($training as $i => $t): ?>
                <div class="train-row">
                    <input type="date"  name="train_date[]"    value="<?= e($t['train_date'] ?? '') ?>">
                    <input type="text"  name="course_name[]"  maxlength="100" value="<?= e($t['course_name'] ?? '') ?>" placeholder="规则培训 / 年度复训">
                    <input type="text"  name="organizer[]"    maxlength="100" value="<?= e($t['organizer'] ?? '') ?>" placeholder="主办单位">
                    <input type="number" name="hours[]"       min="0" max="999" step="0.5" value="<?= e($t['hours'] ?? '') ?>" placeholder="0">
                    <input type="text"  name="result[]"       maxlength="20" value="<?= e($t['result'] ?? '合格') ?>" placeholder="合格">
                    <button type="button" class="del" title="删除该行">✕</button>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $isEdit ? '保存修改' : '创建资格' ?></button>
            <a href="index.php" class="btn btn-outline">取消</a>
        </div>
    </div>
</form>

<template id="trainTpl">
    <div class="train-row">
        <input type="date"  name="train_date[]">
        <input type="text"  name="course_name[]" maxlength="100" placeholder="规则培训 / 年度复训">
        <input type="text"  name="organizer[]" maxlength="100" placeholder="主办单位">
        <input type="number" name="hours[]" min="0" max="999" step="0.5" placeholder="0">
        <input type="text"  name="result[]" maxlength="20" value="合格" placeholder="合格">
        <button type="button" class="del" title="删除该行">✕</button>
    </div>
</template>
<script>
(function () {
    var box = document.getElementById('trainEditor');
    var tpl = document.getElementById('trainTpl');
    document.getElementById('addTrain').addEventListener('click', function () {
        box.appendChild(tpl.content.firstElementChild.cloneNode(true));
    });
    box.addEventListener('click', function (ev) {
        if (ev.target.classList.contains('del')) {
            var rows = box.querySelectorAll('.train-row:not(.head)');
            if (rows.length <= 1) { ev.target.closest('.train-row').querySelectorAll('input').forEach(function(i){ i.value=''; }); return; }
            ev.target.closest('.train-row').remove();
        }
    });
})();
</script>

<?php page_footer(); ?>
