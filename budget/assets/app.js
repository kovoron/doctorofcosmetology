// Регистрация Service Worker
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/budget/assets/sw.js')
        .then(reg => console.log('SW registered:', reg.scope))
        .catch(err => console.log('SW error:', err));
}

// Установка PWA на iOS
function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || 
           window.navigator.standalone;
}

// Подсказка для установки на iOS
if (/iPhone|iPad/.test(navigator.userAgent) && !isStandalone()) {
    const tip = document.createElement('div');
    tip.innerHTML = '💡 Нажмите <strong>⎋</strong> → «На экран «Домой»"';
    tip.style.cssText = 'position:fixed;bottom:90px;left:0;right:0;background:#8B7355;color:#fff;padding:12px 20px;text-align:center;font-size:14px;z-index:999;border-radius:12px 12px 0 0;margin:0 20px;';
    document.body.appendChild(tip);
    setTimeout(() => tip.remove(), 8000);
}

// Выбор категории внутри своей группы (форма расхода, дохода, правки)
document.querySelectorAll('.categories').forEach(group => {
    group.addEventListener('change', event => {
        if (event.target.name !== 'category_id') {
            return;
        }
        group.querySelectorAll('.category-btn').forEach(button => {
            button.classList.toggle('selected', button.contains(event.target));
        });
    });
});

// Автофокус на поле суммы в формах добавления (не в модальном окне правки)
document.querySelector('.form input[name="amount"]')?.focus();

/* ==========================================================
 * Анализ: график динамики по статьям
 * ========================================================== */
(function initTrend() {
    const chartEl = document.getElementById('trend-chart');
    if (!chartEl || !window.BudgetChart) {
        return;
    }

    const base = chartEl.dataset.base || 'filter=current_month';
    const categoriesEl = document.getElementById('trend-categories');
    const legendEl = document.getElementById('trend-legend');
    const noteEl = document.getElementById('trend-note');
    const typeTabs = document.querySelector('[data-role="type"]');
    const granTabs = document.querySelector('[data-role="granularity"]');

    const state = { type: 'expense', granularity: 'auto', selected: [] };
    let loading = false;

    function buildUrl() {
        const params = new URLSearchParams(base);
        params.set('type', state.type);
        params.set('granularity', state.granularity);
        if (state.selected.length) {
            params.set('category_ids', state.selected.join(','));
        }
        return 'api/get-trend.php?' + params.toString();
    }

    function renderChips(data) {
        if (!categoriesEl) {
            return;
        }
        categoriesEl.innerHTML = '';

        const colors = window.BudgetChart.palette();
        const seriesIndexById = {};
        data.series.forEach((item, index) => {
            if (item.category_id) {
                seriesIndexById[item.category_id] = index;
            }
        });

        data.categories.forEach(category => {
            const chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'chip' + (seriesIndexById[category.id] !== undefined ? ' chip--active' : '');
            chip.dataset.categoryId = category.id;

            const dot = document.createElement('span');
            dot.className = 'chip__dot';
            if (seriesIndexById[category.id] !== undefined) {
                dot.style.background = colors[seriesIndexById[category.id] % colors.length];
            }
            chip.appendChild(dot);
            chip.appendChild(document.createTextNode(category.icon + ' ' + category.name));
            categoriesEl.appendChild(chip);
        });

        if (state.selected.length) {
            const reset = document.createElement('button');
            reset.type = 'button';
            reset.className = 'chip';
            reset.dataset.reset = '1';
            reset.textContent = '× сбросить';
            categoriesEl.appendChild(reset);
        }
    }

    function renderLegend(data) {
        if (!legendEl) {
            return;
        }
        legendEl.innerHTML = '';
        const colors = window.BudgetChart.palette();

        data.series.forEach((item, index) => {
            const row = document.createElement('span');
            row.className = 'legend-item';

            const dot = document.createElement('span');
            dot.className = 'legend-item__dot';
            dot.style.background = colors[index % colors.length];

            const sum = document.createElement('span');
            sum.className = 'legend-item__sum';
            sum.textContent = window.BudgetChart.formatMoney(item.total);

            row.appendChild(dot);
            row.appendChild(document.createTextNode(item.name + ' — '));
            row.appendChild(sum);
            legendEl.appendChild(row);
        });
    }

    function renderNote(data) {
        if (!noteEl) {
            return;
        }
        const parts = [data.period.label];
        if (data.granularity.downgraded) {
            const names = { day: 'по дням', week: 'по неделям', month: 'по месяцам' };
            parts.push('шаг изменён автоматически на «' + (names[data.granularity.effective] || data.granularity.effective) + '» — слишком много точек');
        }
        noteEl.textContent = parts.join(' · ');
    }

    async function load() {
        if (loading) {
            return;
        }
        loading = true;
        try {
            const response = await fetch(buildUrl(), { credentials: 'same-origin' });
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            const data = await response.json();
            window.BudgetChart.render(chartEl, data);
            renderChips(data);
            renderLegend(data);
            renderNote(data);
        } catch (error) {
            chartEl.innerHTML = '<p class="empty-state">Не удалось загрузить график</p>';
            if (noteEl) {
                noteEl.textContent = '';
            }
            console.log('trend error:', error);
        } finally {
            loading = false;
        }
    }

    if (typeTabs) {
        typeTabs.addEventListener('click', event => {
            const button = event.target.closest('[data-type]');
            if (!button || button.dataset.type === state.type) {
                return;
            }
            state.type = button.dataset.type;
            state.selected = [];
            typeTabs.querySelectorAll('[data-type]').forEach(item => item.classList.toggle('active', item === button));
            load();
        });
    }

    if (granTabs) {
        granTabs.addEventListener('click', event => {
            const button = event.target.closest('[data-granularity]');
            if (!button || button.dataset.granularity === state.granularity) {
                return;
            }
            state.granularity = button.dataset.granularity;
            granTabs.querySelectorAll('[data-granularity]').forEach(item => item.classList.toggle('active', item === button));
            load();
        });
    }

    if (categoriesEl) {
        categoriesEl.addEventListener('click', event => {
            if (event.target.closest('[data-reset]')) {
                state.selected = [];
                load();
                return;
            }
            const chip = event.target.closest('[data-category-id]');
            if (!chip) {
                return;
            }
            const id = parseInt(chip.dataset.categoryId, 10);
            if (!id) {
                return;
            }
            const position = state.selected.indexOf(id);
            if (position === -1) {
                state.selected.push(id);
            } else {
                state.selected.splice(position, 1);
            }
            load();
        });
    }

    load();
})();

/* ==========================================================
 * Главная: правка операции, созданной сегодня (до конца дня)
 * ========================================================== */
(function initEditSheet() {
    const sheet = document.getElementById('edit-sheet');
    const form = document.getElementById('edit-form');
    if (!sheet || !form) {
        return;
    }

    const idEl = document.getElementById('edit-id');
    const csrfEl = document.getElementById('edit-csrf');
    const amountEl = document.getElementById('edit-amount');
    const descriptionEl = document.getElementById('edit-description');
    const dateEl = document.getElementById('edit-date');
    const errorEl = document.getElementById('edit-error');
    const submitEl = document.getElementById('edit-submit');

    const expenseGroup = sheet.querySelector('.categories[data-type="expense"]');
    const incomeGroup = sheet.querySelector('.categories[data-type="income"]');

    function resetRadios() {
        sheet.querySelectorAll('input[name="category_id"]').forEach(radio => {
            radio.checked = false;
            const label = radio.closest('.category-btn');
            if (label) {
                label.classList.remove('selected');
            }
        });
    }

    function open(item) {
        const type = item.dataset.type === 'income' ? 'income' : 'expense';
        const group = type === 'income' ? incomeGroup : expenseGroup;

        idEl.value = item.dataset.id || '';
        amountEl.value = item.dataset.amount || '';
        descriptionEl.value = item.dataset.description || '';
        dateEl.value = item.dataset.date || '';
        errorEl.textContent = '';

        if (expenseGroup && incomeGroup) {
            expenseGroup.classList.toggle('hidden', type !== 'expense');
            incomeGroup.classList.toggle('hidden', type !== 'income');
        }

        resetRadios();
        const radio = group ? group.querySelector('input[value="' + item.dataset.categoryId + '"]') : null;
        if (radio) {
            radio.checked = true;
            const label = radio.closest('.category-btn');
            if (label) {
                label.classList.add('selected');
            }
        }

        sheet.classList.add('sheet--open');
        sheet.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        sheet.classList.remove('sheet--open');
        sheet.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        errorEl.textContent = '';
    }

    document.addEventListener('click', event => {
        const item = event.target.closest('.transaction-item[data-editable="1"]');
        if (item) {
            open(item);
            return;
        }
        if (event.target.closest('[data-close="1"]')) {
            close();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && sheet.classList.contains('sheet--open')) {
            close();
            return;
        }
        const active = document.activeElement;
        if ((event.key === 'Enter' || event.key === ' ') && active && active.matches('.transaction-item[data-editable="1"]')) {
            event.preventDefault();
            open(active);
        }
    });

    form.addEventListener('submit', async event => {
        event.preventDefault();

        const checked = sheet.querySelector('input[name="category_id"]:checked');
        if (!checked) {
            errorEl.textContent = 'Выберите категорию';
            return;
        }

        submitEl.disabled = true;
        errorEl.textContent = '';

        try {
            const response = await fetch('api/update-transaction.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfEl.value
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    id: idEl.value,
                    amount: amountEl.value,
                    category_id: checked.value,
                    description: descriptionEl.value,
                    date: dateEl.value,
                    csrf_token: csrfEl.value
                })
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok) {
                let message = data.error || ('Ошибка ' + response.status);
                if (data.fields) {
                    message += ': ' + Object.keys(data.fields).map(key => data.fields[key]).join('; ');
                }
                errorEl.textContent = message;
                return;
            }

            close();
            window.location.reload();
        } catch (error) {
            errorEl.textContent = 'Нет связи с сервером';
        } finally {
            submitEl.disabled = false;
        }
    });
})();
