<?php
/**
 * 前台：裁判资格公开查询
 * 输入：裁判编号 + 姓名（二者必填，精确匹配）
 * 输出：级别 / 项目 / 有效期 / 发证单位 / 最近培训记录（结果卡片）
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';

$refereeNo = trim((string)($_GET['referee_no'] ?? ''));
$name      = trim((string)($_GET['name'] ?? ''));
$searched  = ($refereeNo !== '' || $name !== '');
$error     = '';
$referee   = null;
$status    = null;
$trainings = [];

if ($searched) {
    if ($refereeNo === '' || $name === '') {
        $error = '请同时输入“裁判编号”和“姓名”后再查询。';
    } else {
        // PHP 负责数据查询：预处理 + 精确匹配，避免泄露编号清单
        $referee = find_referee_public($refereeNo, $name);
        if ($referee) {
            $status    = referee_status($referee);
            $trainings = training_records((int)$referee['id'], 5);
        }
    }
}

page_header('裁判资格查询', 'public');
?>

<section class="hero">
    <h1>赛事裁判资格查询</h1>
    <p>输入裁判编号与姓名，核验裁判员的执业级别、执裁项目、证书有效期、发证单位及最近培训记录。</p>
    <form class="query-form" method="get" action="index.php" autocomplete="off">
        <div class="field">
            <label for="referee_no">裁判编号</label>
            <input type="text" id="referee_no" name="referee_no"
                   value="<?= e($refereeNo) ?>" placeholder="例如：RF20230001" required>
        </div>
        <div class="field">
            <label for="name">姓名</label>
            <input type="text" id="name" name="name"
                   value="<?= e($name) ?>" placeholder="例如：张伟" required>
        </div>
        <div class="field" style="flex:0 0 auto;">
            <button type="submit" class="btn btn-primary">查询资格</button>
        </div>
    </form>
</section>

<?php if ($error !== ''): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($searched && $error === '' && !$referee): ?>
    <!-- 查无此人 / 编号姓名不匹配：给出统一的模糊提示，防止编号被枚举 -->
    <div class="empty">
        <div class="icon">🔍</div>
        <h3>未查询到匹配的裁判资格信息</h3>
        <p>请核对裁判编号与姓名是否一致；如有疑问，请联系发证单位核实。</p>
    </div>
<?php endif; ?>

<?php if ($referee && $status): ?>
    <?php
    // 有效期提示样式：有效(>180天绿 / 180天内黄) / 过期红
    $validityClass = 'validity-ok';
    if ($status === 'expired' || $status === 'revoked') {
        $validityClass = 'validity-bad';
    } elseif (!empty($referee['valid_until'])) {
        $d = (int)dateDiff($referee['valid_until']);
        if ($d <= 180) {
            $validityClass = 'validity-warn';
        }
    }
    ?>
    <section class="card" aria-label="资格查询结果">
        <div class="card-head">
            <h2>
                <?= e($referee['name']) ?>
                <span class="level-tag"><?= e($referee['level']) ?>裁判</span>
                <span class="sport-tag"><?= e($referee['sport']) ?></span>
            </h2>
            <span class="badge <?= e(status_class($status)) ?>"><?= e(status_text($status)) ?></span>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">裁判编号</div>
                    <div class="value"><?= e($referee['referee_no']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">裁判级别</div>
                    <div class="value"><?= e($referee['level']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">执裁项目</div>
                    <div class="value"><?= e($referee['sport']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">发证单位</div>
                    <div class="value"><?= e($referee['issuer']) ?></div>
                </div>
                <div class="info-item">
                    <div class="label">发证日期</div>
                    <div class="value"><?= e($referee['valid_from'] ?: '—') ?></div>
                </div>
                <div class="info-item">
                    <div class="label">证书有效期</div>
                    <div class="value"><?= e($referee['valid_until'] ?: '长期有效') ?></div>
                </div>
            </div>

            <div class="validity-box <?= e($validityClass) ?>">
                <?php if ($status === 'revoked'): ?>
                    ⚠️ 该裁判资格已被发证单位停用，当前不得承担赛事执裁工作。
                <?php elseif ($status === 'expired'): ?>
                    ⚠️ 该裁判资格已过有效期，需完成续期后方可执裁。
                <?php else: ?>
                    ✅ <?= e(days_left_text($referee['valid_until'])) ?>
                <?php endif; ?>
            </div>

            <?php if ($status === 'revoked' && $referee['remark'] !== ''): ?>
                <div class="notice notice-warn">管理备注：<?= e($referee['remark']) ?></div>
            <?php endif; ?>

            <h3 class="section-title">📚 最近培训记录</h3>
            <?php if (!$trainings): ?>
                <div class="no-record">暂无培训记录。</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead>
                        <tr>
                            <th style="width:120px;">培训日期</th>
                            <th>培训课程</th>
                            <th>主办单位</th>
                            <th class="num" style="width:80px;">学时</th>
                            <th style="width:90px;">考核结果</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($trainings as $t): ?>
                            <tr>
                                <td><?= e($t['train_date']) ?></td>
                                <td><?= e($t['course_name']) ?></td>
                                <td><?= e($t['organizer'] ?: '—') ?></td>
                                <td class="num"><?= e((string)(float)$t['hours']) ?></td>
                                <td><?= e($t['result']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <div class="notice notice-info">
        信息来源：<?= e($referee['issuer']) ?> 登记数据。若对查询结果有异议，请联系发证单位复核。
    </div>
<?php endif; ?>

<?php if (!$searched): ?>
    <div class="card">
        <div class="card-body">
            <h3 class="section-title" style="margin-top:0;">查询说明</h3>
            <ul style="margin:0; padding-left:20px; color:#334155; font-size:14px;">
                <li>裁判编号与姓名<strong>需同时填写且完全一致</strong>，方可查询。</li>
                <li>查询结果展示：级别、执裁项目、证书有效期、发证单位与最近 5 条培训记录。</li>
                <li>状态为「已过期」或「已停用」的资格，不得用于赛事执裁。</li>
                <li>演示数据可试：<code>RF20230001 / 张伟</code>、<code>RF20220108 / 李娜</code>。</li>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php page_footer(); ?>
