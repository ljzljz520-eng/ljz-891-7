<?php
require_once __DIR__ . '/../app/bootstrap.php';
$admin = require_admin_page();
$csrf  = csrf_token();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>资格管理后台 - 赛事裁判资格查询系统</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<header class="admin-header">
    <div class="brand">🛡️ 裁判资格管理后台</div>
    <div class="user-box">
        <a href="/" target="_blank">查询前台 ↗</a>
        <span>👤 <?= htmlspecialchars($admin['username'], ENT_QUOTES) ?></span>
        <button class="btn btn-ghost btn-sm" id="logoutBtn">退出登录</button>
    </div>
</header>

<main class="admin-main">
    <!-- 统计 -->
    <div class="stat-grid" id="statGrid">
        <div class="stat-card"><div class="num" id="stTotal">-</div><div class="txt">资格总数</div></div>
        <div class="stat-card s-active"><div class="num" id="stActive">-</div><div class="txt">有效</div></div>
        <div class="stat-card s-expiring"><div class="num" id="stExpiring">-</div><div class="txt">90天内到期</div></div>
        <div class="stat-card s-expired"><div class="num" id="stExpired">-</div><div class="txt">已过期</div></div>
        <div class="stat-card s-disabled"><div class="num" id="stDisabled">-</div><div class="txt">已停用</div></div>
    </div>

    <section class="panel">
        <div class="panel-toolbar">
            <div class="filter-tabs" id="filterTabs">
                <button data-status="" class="active">全部</button>
                <button data-status="active">有效</button>
                <button data-status="expiring">即将到期</button>
                <button data-status="expired">已过期</button>
                <button data-status="disabled">已停用</button>
            </div>
            <div class="grow"></div>
            <div class="form-group" style="min-width:220px">
                <input type="text" id="keyword" placeholder="搜索编号 / 姓名 / 项目">
            </div>
            <button class="btn btn-primary" id="addBtn">＋ 新增资格</button>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                <tr>
                    <th>编号</th>
                    <th>姓名</th>
                    <th>级别</th>
                    <th>项目</th>
                    <th>有效期至</th>
                    <th>发证单位</th>
                    <th>状态</th>
                    <th style="text-align:right">操作</th>
                </tr>
                </thead>
                <tbody id="tableBody">
                <tr><td colspan="8" class="empty-row">加载中…</td></tr>
                </tbody>
            </table>
        </div>

        <div class="pagination">
            <button class="btn btn-ghost btn-sm" id="prevPage">上一页</button>
            <span id="pageInfo">-</span>
            <button class="btn btn-ghost btn-sm" id="nextPage">下一页</button>
        </div>
    </section>
</main>

<!-- 新增 / 编辑弹窗 -->
<div class="modal-mask" id="editModal">
    <div class="modal">
        <div class="modal-head">
            <h3 id="editTitle">新增裁判资格</h3>
            <button class="modal-close" data-close="editModal">&times;</button>
        </div>
        <form id="editForm">
            <div class="modal-body">
                <input type="hidden" id="fId">
                <div class="form-row">
                    <div class="form-group">
                        <label>裁判编号 *</label>
                        <input type="text" id="fCode" maxlength="30" placeholder="如：RJ2025001">
                    </div>
                    <div class="form-group">
                        <label>姓名 *</label>
                        <input type="text" id="fName" maxlength="30">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>裁判级别 *</label>
                        <select id="fLevel">
                            <option value="国家级">国家级</option>
                            <option value="一级">一级</option>
                            <option value="二级">二级</option>
                            <option value="三级">三级</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>执裁项目 *</label>
                        <input type="text" id="fSport" maxlength="50" placeholder="如：田径、篮球">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>有效期至 *</label>
                        <input type="date" id="fValidUntil">
                    </div>
                    <div class="form-group">
                        <label>资格状态</label>
                        <select id="fActive">
                            <option value="1">正常</option>
                            <option value="0">停用</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>发证单位 *</label>
                    <input type="text" id="fIssuer" maxlength="100">
                </div>

                <div id="trainingFields" style="border-top:1px dashed var(--border);padding-top:14px;margin-top:4px">
                    <div class="subsection-title" style="margin:0 0 12px">培训记录（可选，新增时录入一条）</div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>培训日期</label>
                            <input type="date" id="fTrainDate">
                        </div>
                        <div class="form-group">
                            <label>考核结果</label>
                            <input type="text" id="fTrainResult" maxlength="30" value="合格">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>培训名称</label>
                        <input type="text" id="fTrainCourse" maxlength="100" placeholder="如：2026 年度裁判复训班">
                    </div>
                    <div class="form-group">
                        <label>主办单位</label>
                        <input type="text" id="fTrainOrg" maxlength="100">
                    </div>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn btn-ghost" data-close="editModal">取消</button>
                <button type="submit" class="btn btn-primary" id="saveBtn">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 详情 / 培训弹窗 -->
<div class="modal-mask" id="detailModal">
    <div class="modal">
        <div class="modal-head">
            <h3>资格详情与培训记录</h3>
            <button class="modal-close" data-close="detailModal">&times;</button>
        </div>
        <div class="modal-body" id="detailBody"></div>
        <div class="modal-foot">
            <button type="button" class="btn btn-ghost" data-close="detailModal">关闭</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>window.CSRF_TOKEN = <?= json_encode($csrf, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="/assets/js/admin.js"></script>
</body>
</html>
