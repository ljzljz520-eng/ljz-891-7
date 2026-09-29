/**
 * 后台管理交互：列表 / 搜索 / 新增 / 编辑 / 停用 / 培训记录
 */
(function () {
    'use strict';

    const state = {
        page: 1,
        pageSize: 10,
        keyword: '',
        status: '',
        totalPages: 1
    };

    const $ = sel => document.querySelector(sel);
    const tbody = $('#tableBody');
    const toastEl = $('#toast');

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    let toastTimer = null;
    function toast(msg, type) {
        toastEl.textContent = msg;
        toastEl.className = 'toast show' + (type ? ' toast-' + type : '');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { toastEl.className = 'toast'; }, 2600);
    }

    async function api(url, opts) {
        opts = opts || {};
        const options = {
            method: opts.method || 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        if (opts.body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(opts.body);
            if (opts.csrf !== false) {
                options.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
            }
        } else if (opts.csrf) {
            options.headers['X-CSRF-Token'] = window.CSRF_TOKEN;
        }
        const resp = await fetch(url, options);
        let data = {};
        try { data = await resp.json(); } catch (e) { /* ignore */ }

        if (resp.status === 401) {
            window.location.href = '/login.php';
            throw new Error('未登录');
        }
        if (resp.status === 419) {
            toast('登录已过期，请重新登录', 'error');
            setTimeout(() => window.location.href = '/login.php', 1200);
            throw new Error('csrf');
        }
        if (!resp.ok || !data.ok) {
            throw new Error(data.error || '请求失败');
        }
        return data;
    }

    /* ---------------- 列表 ---------------- */
    async function loadList() {
        tbody.innerHTML = '<tr><td colspan="8" class="empty-row">加载中…</td></tr>';
        const qs = new URLSearchParams({
            action: 'list',
            page: state.page,
            page_size: state.pageSize,
            keyword: state.keyword,
            status: state.status
        });
        try {
            const data = await api('/api/referees.php?' + qs.toString());
            renderStats(data.stats);
            renderRows(data.list);
            state.totalPages = Math.max(1, Math.ceil(data.total / data.page_size));
            $('#pageInfo').textContent =
                `第 ${data.page} / ${state.totalPages} 页（共 ${data.total} 条）`;
            $('#prevPage').disabled = data.page <= 1;
            $('#nextPage').disabled = data.page >= state.totalPages;
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="8" class="empty-row">${esc(err.message)}</td></tr>`;
        }
    }

    function renderStats(s) {
        $('#stTotal').textContent = s.total;
        $('#stActive').textContent = s.active;
        $('#stExpiring').textContent = s.expiring;
        $('#stExpired').textContent = s.expired;
        $('#stDisabled').textContent = s.disabled;
    }

    function renderRows(rows) {
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="empty-row">暂无符合条件的记录</td></tr>';
            return;
        }
        tbody.innerHTML = rows.map(r => `
            <tr>
                <td><strong>${esc(r.referee_code)}</strong></td>
                <td>${esc(r.name)}</td>
                <td>${esc(r.level)}</td>
                <td>${esc(r.sport)}</td>
                <td style="${r.status_code === 'expired' ? 'color:var(--red)' : ''}">${esc(r.valid_until)}</td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis"
                    title="${esc(r.issuer)}">${esc(r.issuer)}</td>
                <td><span class="status-badge status-${esc(r.status_code)}">${esc(r.status_text)}</span></td>
                <td>
                    <div class="actions" style="justify-content:flex-end">
                        <button class="btn btn-ghost btn-sm" data-act="detail" data-id="${r.id}">详情</button>
                        <button class="btn btn-ghost btn-sm" data-act="edit" data-id="${r.id}">编辑</button>
                        ${r.is_active
                            ? `<button class="btn btn-danger btn-sm" data-act="toggle" data-id="${r.id}">停用</button>`
                            : `<button class="btn btn-success btn-sm" data-act="toggle" data-id="${r.id}">启用</button>`}
                    </div>
                </td>
            </tr>
        `).join('');
    }

    /* ---------------- 新增 / 编辑 ---------------- */
    const fields = ['Id', 'Code', 'Name', 'Level', 'Sport', 'ValidUntil',
                    'Issuer', 'Active', 'TrainDate', 'TrainResult', 'TrainCourse', 'TrainOrg'];

    function fval(key) { return $('#f' + key).value.trim(); }
    function fset(key, val) { $('#f' + key).value = val ?? ''; }

    function resetForm() {
        $('#editForm').reset();
        fset('Id', '');
        fset('Level', '国家级');
        fset('Active', '1');
        fset('TrainResult', '合格');
    }

    function openCreate() {
        resetForm();
        $('#editTitle').textContent = '新增裁判资格';
        $('#saveBtn').textContent = '确认新增';
        $('#trainingFields').style.display = '';
        openModal('editModal');
        $('#fCode').focus();
    }

    async function openEdit(id) {
        try {
            const data = await api(`/api/referees.php?action=detail&id=${id}`);
            const r = data.referee;
            resetForm();
            fset('Id', r.id);
            fset('Code', r.referee_code);
            fset('Name', r.name);
            fset('Level', r.level);
            fset('Sport', r.sport);
            fset('ValidUntil', r.valid_until);
            fset('Issuer', r.issuer);
            fset('Active', r.is_active ? '1' : '0');
            $('#editTitle').textContent = '编辑裁判资格';
            $('#saveBtn').textContent = '保存修改';
            $('#trainingFields').style.display = 'none';
            openModal('editModal');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    async function submitForm(e) {
        e.preventDefault();
        const id = fval('Id');
        const payload = {
            referee_code: fval('Code'),
            name: fval('Name'),
            level: fval('Level'),
            sport: fval('Sport'),
            valid_until: fval('ValidUntil'),
            issuer: fval('Issuer'),
            is_active: fval('Active') === '1'
        };

        // 简单前端校验
        if (!payload.referee_code || !payload.name || !payload.sport
            || !payload.valid_until || !payload.issuer) {
            toast('请填写所有必填项', 'error');
            return;
        }

        const btn = $('#saveBtn');
        btn.disabled = true;
        try {
            if (id) {
                payload.id = Number(id);
                await api('/api/referees.php?action=update', { body: payload });
                toast('修改已保存', 'success');
            } else {
                if (fval('TrainDate') && fval('TrainCourse')) {
                    payload.training_date = fval('TrainDate');
                    payload.course_name = fval('TrainCourse');
                    payload.organizer = fval('TrainOrg');
                    payload.result = fval('TrainResult') || '合格';
                }
                await api('/api/referees.php?action=create', { body: payload });
                toast('资格已新增', 'success');
            }
            closeModal('editModal');
            loadList();
        } catch (err) {
            toast(err.message, 'error');
        } finally {
            btn.disabled = false;
        }
    }

    /* ---------------- 停用 / 启用 ---------------- */
    async function toggleRow(id, isActive) {
        const action = isActive ? '停用' : '重新启用';
        if (!confirm(`确定要${action}该裁判资格吗？`)) return;
        try {
            const data = await api('/api/referees.php?action=toggle',
                { body: { id: Number(id), is_active: !isActive } });
            toast(data.message, 'success');
            loadList();
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    /* ---------------- 详情 + 培训 ---------------- */
    async function openDetail(id) {
        try {
            const data = await api(`/api/referees.php?action=detail&id=${id}`);
            renderDetail(data);
            openModal('detailModal');
        } catch (err) {
            toast(err.message, 'error');
        }
    }

    function renderDetail(data) {
        const r = data.referee;
        const ts = data.trainings || [];
        $('#detailBody').innerHTML = `
            <div class="info-grid" style="margin-bottom:8px">
                <div class="info-item"><div class="label">裁判编号</div><div class="value">${esc(r.referee_code)}</div></div>
                <div class="info-item"><div class="label">姓名</div><div class="value">${esc(r.name)}</div></div>
                <div class="info-item"><div class="label">级别</div><div class="value">${esc(r.level)}</div></div>
                <div class="info-item"><div class="label">项目</div><div class="value">${esc(r.sport)}</div></div>
                <div class="info-item"><div class="label">有效期至</div><div class="value">${esc(r.valid_until)}</div></div>
                <div class="info-item"><div class="label">状态</div>
                    <div class="value"><span class="status-badge status-${esc(r.status_code)}">${esc(r.status_text)}</span></div></div>
                <div class="info-item" style="grid-column:1/-1"><div class="label">发证单位</div>
                    <div class="value" style="font-size:14px">${esc(r.issuer)}</div></div>
            </div>

            <div class="subsection-title">培训记录（${ts.length}）</div>
            <div class="training-list">
                ${ts.length ? ts.map(t => `
                    <div class="t-item">
                        <div class="t-item-head">
                            <span class="t-item-name">${esc(t.course_name)}</span>
                            <span class="status-badge status-valid">${esc(t.result)}</span>
                        </div>
                        <div class="t-meta" style="margin-top:4px;color:var(--text-muted);font-size:12px">
                            <span>📅 ${esc(t.training_date)}</span>
                            <span>🏛 ${esc(t.organizer) || '—'}</span>
                        </div>
                    </div>`).join('') : '<p class="no-training">暂无培训记录</p>'}
            </div>

            <div class="subsection-title">添加培训记录</div>
            <form id="addTrainingForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>培训日期 *</label>
                        <input type="date" id="ntDate" required>
                    </div>
                    <div class="form-group">
                        <label>考核结果</label>
                        <input type="text" id="ntResult" value="合格" maxlength="30">
                    </div>
                </div>
                <div class="form-group">
                    <label>培训名称 *</label>
                    <input type="text" id="ntCourse" maxlength="100" required>
                </div>
                <div class="form-group">
                    <label>主办单位</label>
                    <input type="text" id="ntOrg" maxlength="100">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"
                        style="margin-top:4px">＋ 添加记录</button>
            </form>`;

        $('#addTrainingForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            try {
                await api('/api/referees.php?action=add_training', {
                    body: {
                        referee_id: r.id,
                        training_date: $('#ntDate').value,
                        course_name: $('#ntCourse').value.trim(),
                        organizer: $('#ntOrg').value.trim(),
                        result: $('#ntResult').value.trim() || '合格'
                    }
                });
                toast('培训记录已添加', 'success');
                openDetail(r.id);
                loadList();
            } catch (err) {
                toast(err.message, 'error');
            }
        });
    }

    /* ---------------- 弹窗 ---------------- */
    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    document.querySelectorAll('[data-close]').forEach(btn => {
        btn.addEventListener('click', () => closeModal(btn.dataset.close));
    });
    document.querySelectorAll('.modal-mask').forEach(mask => {
        mask.addEventListener('click', e => {
            if (e.target === mask) mask.classList.remove('open');
        });
    });

    /* ---------------- 事件绑定 ---------------- */
    $('#addBtn').addEventListener('click', openCreate);
    $('#editForm').addEventListener('submit', submitForm);

    tbody.addEventListener('click', e => {
        const btn = e.target.closest('button[data-act]');
        if (!btn) return;
        const id = Number(btn.dataset.id);
        const act = btn.dataset.act;
        if (act === 'edit') openEdit(id);
        else if (act === 'detail') openDetail(id);
        else if (act === 'toggle') {
            const row = btn.closest('tr');
            const isActive = btn.textContent.trim() === '停用';
            toggleRow(id, isActive);
        }
    });

    // 筛选 tabs
    $('#filterTabs').addEventListener('click', e => {
        const btn = e.target.closest('button[data-status]');
        if (!btn) return;
        document.querySelectorAll('#filterTabs button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        state.status = btn.dataset.status;
        state.page = 1;
        loadList();
    });

    // 搜索（防抖）
    let kwTimer = null;
    $('#keyword').addEventListener('input', e => {
        clearTimeout(kwTimer);
        kwTimer = setTimeout(() => {
            state.keyword = e.target.value.trim();
            state.page = 1;
            loadList();
        }, 300);
    });

    $('#prevPage').addEventListener('click', () => {
        if (state.page > 1) { state.page--; loadList(); }
    });
    $('#nextPage').addEventListener('click', () => {
        if (state.page < state.totalPages) { state.page++; loadList(); }
    });

    $('#logoutBtn').addEventListener('click', async () => {
        try {
            await api('/api/auth.php?action=logout', { method: 'POST', csrf: true, body: {} });
        } catch (e) { /* 忽略，强制跳转 */ }
        window.location.href = '/login.php';
    });

    loadList();
})();
