(function () {
    const form = document.getElementById('task-form');
    const refreshBtn = document.getElementById('refresh-btn');
    const actionSelect = document.getElementById('action');
    const taskTable = document.getElementById('task-table').querySelector('tbody');
    const advancedToggle = document.getElementById('advanced-toggle');
    
    const toggleAdvanced = () => {
        const action = actionSelect.value;
        const advanced = document.getElementById('advanced-options');
        if (['UPSCALE', 'VARIATION'].includes(action)) {
            advanced.style.display = 'block';
            advanced.dataset.locked = 'true';
            if (advancedToggle) {
                advancedToggle.style.display = 'none';
            }
        } else {
            const isOpen = advanced.dataset.open === 'true';
            advanced.style.display = isOpen ? 'block' : 'none';
            advanced.dataset.locked = 'false';
            if (advancedToggle) {
                advancedToggle.style.display = 'inline-flex';
                advancedToggle.textContent = isOpen ? '隐藏高级选项' : '显示高级选项';
            }
        }
    };

    if (advancedToggle) {
        advancedToggle.addEventListener('click', () => {
            const advanced = document.getElementById('advanced-options');
            const isOpen = advanced.dataset.open === 'true';
            advanced.dataset.open = (!isOpen).toString();
            advanced.style.display = isOpen ? 'none' : 'block';
            advancedToggle.textContent = isOpen ? '显示高级选项' : '隐藏高级选项';
        });
    }

    const renderStatus = (status) => {
        if (!status) return '<span class="badge">排队中</span>';
        const normalized = status.toLowerCase();
        if (normalized.includes('success') || normalized.includes('finished')) {
            return '<span class="badge status-done">已完成</span>';
        }
        if (normalized.includes('fail')) {
            return '<span class="badge status-failed">失败</span>';
        }
        return '<span class="badge status-progress">进行中</span>';
    };

    const renderRow = (task) => {
        const imageCell = task.image_url
            ? `<a href="${task.image_url}" target="_blank">查看</a>`
            : '--';
        const failReason = task.fail_reason
            ? `<div class="prompt">${task.fail_reason}</div>`
            : '--';
        const progress = task.progress || '--';
        return `
            <tr data-id="${task.id}">
                <td data-label="ID">${task.task_id || '--'}</td>
                <td data-label="操作">${task.action}</td>
                <td data-label="提示词"><div class="prompt">${task.prompt || ''}</div></td>
                <td data-label="状态">${renderStatus(task.status)}</td>
                <td data-label="进度">${progress}</td>
                <td data-label="图片">${imageCell}</td>
                <td data-label="失败原因">${failReason}</td>
                <td data-label="更新时间">${task.updated_at || ''}</td>
            </tr>
        `;
    };

    const loadTasks = () => {
        axios.get('/api/task/list')
            .then((response) => {
                const tasks = response.data.data || [];
                taskTable.innerHTML = tasks.map(renderRow).join('');
            })
            .catch((error) => {
                console.error(error);
                alert('加载任务失败：' + (error.response?.data?.message || error.message));
            });
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(form);
        const payload = {};
        formData.forEach((value, key) => {
            if (value) {
                payload[key] = value;
            }
        });
        if (payload.options) {
            try {
                payload.options = JSON.parse(payload.options);
            } catch (e) {
                alert('自定义参数必须是合法 JSON');
                return;
            }
        }
        if (['UPSCALE', 'VARIATION'].includes(payload.action)) {
            if (!payload.task_id) {
                alert('放大或变换操作需要提供原任务 ID');
                return;
            }
            if (!payload.index) {
                alert('放大或变换操作需要提供图片序号');
                return;
            }
        }
        const action = payload.action;
        let url = '/api/task/create';
        if (action === 'DESCRIBE') {
            url = '/api/task/describe';
        } else if (action === 'UPSCALE') {
            url = '/api/task/upscale';
        } else if (action === 'VARIATION') {
            url = '/api/task/variation';
        }

        axios.post(url, payload)
            .then((response) => {
                alert('任务提交成功，任务ID：' + response.data.data.task_id);
                form.reset();
                toggleAdvanced();
                loadTasks();
            })
            .catch((error) => {
                console.error(error);
                alert('提交失败：' + (error.response?.data?.message || error.message));
            });
    });

    refreshBtn.addEventListener('click', loadTasks);
    actionSelect.addEventListener('change', toggleAdvanced);

    toggleAdvanced();
    loadTasks();
})();
