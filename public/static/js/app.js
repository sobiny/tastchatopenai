(function () {
    const form = document.getElementById('task-form');
    if (!form) {
        return;
    }

    const refreshBtn = document.getElementById('refresh-btn');
    const actionSelect = document.getElementById('action');
    const taskTableElement = document.getElementById('task-table');
    const taskTableBody = taskTableElement ? taskTableElement.querySelector('tbody') : null;
    const advancedToggle = document.getElementById('advanced-toggle');
    const optionInputs = document.querySelectorAll('[data-option-key]');
    const customOptionsField = document.getElementById('custom-options');
    const imageUrlInput = document.getElementById('image-url-input');
    const imageUrlAddBtn = document.getElementById('image-url-add');
    const imageUrlList = document.getElementById('image-url-list');
    const imageUploadInput = document.getElementById('image-file');
    const imageUploadStatus = document.getElementById('image-upload-status');
    const imageClearBtn = document.getElementById('image-clear');
    const MAX_IMAGE_URLS = 6;
    const imageUrls = [];

    const escapeHtml = (value = '') => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const setUploadStatus = (message, isError = false) => {
        if (!imageUploadStatus) return;
        imageUploadStatus.textContent = message || '';
        imageUploadStatus.style.color = isError ? '#b91c1c' : '#6b7280';
    };

    const renderImageUrls = () => {
        if (!imageUrlList) return;
        if (!imageUrls.length) {
            imageUrlList.innerHTML = '<li class="image-url-empty">暂无图片，请上传或添加 URL</li>';
            return;
        }
        imageUrlList.innerHTML = imageUrls.map((url, index) => `
            <li>
                <span>${escapeHtml(url)}</span>
                <button type="button" class="image-url-remove" data-index="${index}">移除</button>
            </li>
        `).join('');
    };

    const addImageUrl = (url, source = 'manual') => {
        if (!url) return;
        const normalized = url.trim();
        if (!normalized) return;
        if (!/^https?:\/\//i.test(normalized)) {
            setUploadStatus('请输入以 http/https 开头的图片地址', true);
            return;
        }
        if (imageUrls.includes(normalized)) {
            setUploadStatus('该图片已在列表中，无需重复添加', true);
            return;
        }
        if (imageUrls.length >= MAX_IMAGE_URLS) {
            setUploadStatus(`最多支持添加 ${MAX_IMAGE_URLS} 张图片`, true);
            return;
        }
        imageUrls.push(normalized);
        renderImageUrls();
        setUploadStatus(source === 'upload' ? '图片上传成功，已加入垫图列表' : '已添加图片 URL');
    };

    const uploadImageFile = (file) => {
        if (!file) return;
        const formData = new FormData();
        formData.append('image', file);
        setUploadStatus('上传中，请稍候...');
        axios.post('/api/task/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        })
            .then((response) => {
                const url = response?.data?.data?.url;
                if (url) {
                    addImageUrl(url, 'upload');
                } else {
                    setUploadStatus('上传成功，但未获取到图片地址', true);
                }
            })
            .catch((error) => {
                console.error(error);
                setUploadStatus('上传失败：' + (error.response?.data?.message || error.message), true);
            })
            .finally(() => {
                imageUploadInput.value = '';
            });
    };

    const resetAdvancedOptions = () => {
        optionInputs.forEach((input) => {
            if (input.type === 'checkbox') {
                input.checked = false;
            } else {
                input.value = '';
            }
        });
        if (customOptionsField) {
            customOptionsField.value = '';
        }
        imageUrls.length = 0;
        renderImageUrls();
        setUploadStatus('');
    };

    const collectOptions = () => {
        const result = {};
        optionInputs.forEach((input) => {
            const key = input.dataset.optionKey;
            if (!key) {
                return;
            }
            const alias = (input.dataset.optionAlias || '')
                .split(',')
                .map((item) => item.trim())
                .filter(Boolean);
            const type = input.dataset.optionType || 'string';
            let value;

            if (input.type === 'checkbox') {
                if (!input.checked) {
                    return;
                }
                if (input.dataset.optionValue) {
                    value = input.dataset.optionValue;
                } else if (type === 'boolean') {
                    value = true;
                } else {
                    value = true;
                }
            } else {
                const raw = input.value.trim();
                if (!raw) {
                    return;
                }
                if (type === 'int') {
                    const parsed = parseInt(raw, 10);
                    if (Number.isNaN(parsed)) {
                        return;
                    }
                    value = parsed;
                } else if (type === 'float' || type === 'number') {
                    const parsed = parseFloat(raw);
                    if (Number.isNaN(parsed)) {
                        return;
                    }
                    value = parsed;
                } else {
                    value = raw;
                }
            }

            result[key] = value;
            alias.forEach((aliasKey) => {
                result[aliasKey] = value;
            });
        });

        if (customOptionsField && customOptionsField.value.trim()) {
            let extra;
            try {
                extra = JSON.parse(customOptionsField.value.trim());
            } catch (error) {
                throw new Error('自定义参数必须是合法 JSON');
            }
            if (!extra || typeof extra !== 'object' || Array.isArray(extra)) {
                throw new Error('自定义参数必须是对象结构');
            }
            Object.assign(result, extra);
        }

        return result;
    };

    const toggleAdvanced = () => {
        if (!actionSelect) return;
        const advanced = document.getElementById('advanced-options');
        if (!advanced) return;
        const action = actionSelect.value;
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
            if (!advanced || advanced.dataset.locked === 'true') {
                return;
            }
            const isOpen = advanced.dataset.open === 'true';
            advanced.dataset.open = (!isOpen).toString();
            advanced.style.display = isOpen ? 'none' : 'block';
            advancedToggle.textContent = isOpen ? '显示高级选项' : '隐藏高级选项';
        });
    }

    const renderStatus = (status) => {
        if (!status) return '<span class="badge">排队中</span>';
        const normalized = String(status).toLowerCase();
        if (normalized.includes('success') || normalized.includes('finished')) {
            return '<span class="badge status-done">已完成</span>';
        }
        if (normalized.includes('fail')) {
            return '<span class="badge status-failed">失败</span>';
        }
        return '<span class="badge status-progress">进行中</span>';
    };

    const renderRow = (task) => {
        const prompt = escapeHtml(task.prompt || '');
        const imageUrl = task.image_url ? escapeHtml(task.image_url) : '';
        const imageCell = imageUrl
            ? `<a href="${imageUrl}" target="_blank">查看</a>`
            : '--';
        const failReason = task.fail_reason
            ? `<div class="prompt">${escapeHtml(task.fail_reason)}</div>`
            : '--';
        const progress = task.progress ? escapeHtml(task.progress) : '--';
        return `
            <tr data-id="${escapeHtml(String(task.id || ''))}">
                <td data-label="ID">${escapeHtml(task.task_id || '--')}</td>
                <td data-label="操作">${escapeHtml(task.action || '')}</td>
                <td data-label="提示词"><div class="prompt">${prompt}</div></td>
                <td data-label="状态">${renderStatus(task.status)}</td>
                <td data-label="进度">${progress}</td>
                <td data-label="图片">${imageCell}</td>
                <td data-label="失败原因">${failReason}</td>
                <td data-label="更新时间">${escapeHtml(task.updated_at || '')}</td>
            </tr>
        `;
    };

    const loadTasks = () => {
        if (!taskTableBody) return;
        axios.get('/api/task/list')
            .then((response) => {
                const tasks = response.data?.data || [];
                taskTableBody.innerHTML = tasks.map(renderRow).join('');
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

        delete payload.options;

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

        let options = {};
        try {
            options = collectOptions();
        } catch (error) {
            alert(error.message || error);
            return;
        }
        if (Object.keys(options).length > 0) {
            payload.options = options;
        }

        if (imageUrls.length > 0) {
            payload.image_urls = imageUrls.slice();
            payload.image_url = imageUrls[0];
        }

        let url = '/api/task/create';
        if (payload.action === 'DESCRIBE') {
            url = '/api/task/describe';
        } else if (payload.action === 'UPSCALE') {
            url = '/api/task/upscale';
        } else if (payload.action === 'VARIATION') {
            url = '/api/task/variation';
        }

        axios.post(url, payload)
            .then((response) => {
                const taskId = response.data?.data?.task_id || response.data?.data?.taskId;
                alert('任务提交成功，任务ID：' + (taskId || '未知'));
                form.reset();
                resetAdvancedOptions();
                toggleAdvanced();
                loadTasks();
            })
            .catch((error) => {
                console.error(error);
                alert('提交失败：' + (error.response?.data?.message || error.message));
            });
    });

    if (refreshBtn) {
        refreshBtn.addEventListener('click', loadTasks);
    }

    if (actionSelect) {
        actionSelect.addEventListener('change', toggleAdvanced);
    }

    if (imageUrlAddBtn && imageUrlInput) {
        imageUrlAddBtn.addEventListener('click', () => {
            addImageUrl(imageUrlInput.value);
            imageUrlInput.value = '';
        });
        imageUrlInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                addImageUrl(imageUrlInput.value);
                imageUrlInput.value = '';
            }
        });
    }

    if (imageUploadInput) {
        imageUploadInput.addEventListener('change', () => {
            const [file] = imageUploadInput.files;
            uploadImageFile(file);
        });
    }

    if (imageClearBtn) {
        imageClearBtn.addEventListener('click', () => {
            if (!imageUrls.length) {
                setUploadStatus('列表为空，无需清理');
                return;
            }
            imageUrls.length = 0;
            renderImageUrls();
            setUploadStatus('垫图列表已清空');
        });
    }

    if (imageUrlList) {
        imageUrlList.addEventListener('click', (event) => {
            const target = event.target;
            if (target && target.classList.contains('image-url-remove')) {
                const index = Number(target.dataset.index);
                if (!Number.isNaN(index)) {
                    imageUrls.splice(index, 1);
                    renderImageUrls();
                    setUploadStatus('已移除图片 URL');
                }
            }
        });
    }

    toggleAdvanced();
    renderImageUrls();
    loadTasks();
})();
