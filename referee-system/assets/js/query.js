/**
 * 前台查询页交互
 */
(function () {
    'use strict';

    const form   = document.getElementById('queryForm');
    const codeEl = document.getElementById('refereeCode');
    const nameEl = document.getElementById('refereeName');
    const result = document.getElementById('resultArea');
    const submitBtn = form.querySelector('button[type="submit"]');

    const STATUS_LABEL = {
        valid:    '有效',
        expiring: '即将到期',
        expired:  '已过期',
        disabled: '已停用'
    };

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    function showLoading() {
        result.innerHTML = `
            <div class="alert alert-loading">
                <div class="spinner"></div>正在查询资格信息…
            </div>`;
    }

    function showError(msg) {
        result.innerHTML = `<div class="alert alert-error">${esc(msg)}</div>`;
    }

    function dateClass(d, status) {
        return status === 'expired' ? 'value date-expired' : 'value';
    }

    function renderTraining(t) {
        if (!t) {
            return '<p class="no-training">暂无培训记录</p>';
        }
        return `
            <div class="training-box">
                <div class="t-name">${esc(t.course_name)}</div>
                <div class="t-meta">
                    <span>📅 培训日期：${esc(t.training_date)}</span>
                    <span>🏛 主办单位：${esc(t.organizer) || '—'}</span>
                    <span>✅ 考核结果：${esc(t.result) || '—'}</span>
                </div>
            </div>`;
    }

    function renderCard(r) {
        const disabledNotice = r.status_code === 'disabled'
            ? `<div class="stopped-notice">⚠️ 该裁判资格当前已被停用，信息仅供核验参考。</div>` : '';
        return `
        <div class="result-card">
            <div class="card-head">
                <div class="who">
                    <div class="avatar">${esc(r.name.charAt(0))}</div>
                    <div>
                        <h2>${esc(r.name)}</h2>
                        <div class="code">裁判编号：${esc(r.referee_code)}</div>
                    </div>
                </div>
                <span class="status-badge status-${esc(r.status_code)}">${esc(r.status_text)}</span>
            </div>
            ${disabledNotice}
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="label">🏅 裁判级别</div>
                        <div class="value">${esc(r.level)}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">🏆 执裁项目</div>
                        <div class="value">${esc(r.sport)}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">📆 资格有效期至</div>
                        <div class="${dateClass(r.valid_until, r.status_code)}">${esc(r.valid_until)}</div>
                    </div>
                    <div class="info-item">
                        <div class="label">🏛 发证单位</div>
                        <div class="value" style="font-size:14px">${esc(r.issuer)}</div>
                    </div>
                </div>
                <div class="subsection-title">最近培训记录</div>
                ${renderTraining(r.last_training)}
            </div>
        </div>`;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const code = codeEl.value.trim();
        const name = nameEl.value.trim();

        if (!code || !name) {
            showError('请同时输入裁判编号和姓名后再查询。');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = '查询中…';
        showLoading();

        try {
            const resp = await fetch('/api/query.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ referee_code: code, name: name })
            });
            const data = await resp.json();
            if (!resp.ok || !data.ok) {
                showError(data.error || '查询失败，请稍后重试');
            } else {
                result.innerHTML = renderCard(data.referee);
            }
        } catch (err) {
            showError('网络异常，请检查连接后重试');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = '查询资格';
        }
    });
})();
