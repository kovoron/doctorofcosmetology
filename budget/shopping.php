<?php
require_once 'config.php';
requireAuth(); // Можно убрать, если хотите, чтобы список был доступен без авторизации

$pdo = getDB();
$activeTab = $_GET['tab'] ?? 'tobuy'; // 'purchases' или 'tobuy'
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>Покупки — Бюджет</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="manifest" href="assets/manifest.json">
</head>
<body>
    <header class="header">
        <h1 class="header__title">🛒 Покупки</h1>
        <a href="index.php" class="header__logout">← Назад</a>
    </header>

    <!-- Вкладки -->
    <div class="tabs">
        <a href="?tab=tobuy" class="tab <?= $activeTab === 'tobuy' ? 'active' : '' ?>">Надо купить</a>
        <a href="?tab=purchases" class="tab <?= $activeTab === 'purchases' ? 'active' : '' ?>">Покупки</a>
    </div>

    <main class="shopping-page">
        <?php if ($activeTab === 'tobuy'): ?>
            <!-- Вкладка "Надо купить" -->
            <div class="shopping-search">
                <input type="text" id="searchInput" class="search-input" placeholder="Поиск товара (2-3 буквы)..." autocomplete="off">
                <div id="searchResults" class="search-results hidden"></div>
            </div>

            <div class="shopping-list" id="tobuyList">
                <?php
                $stmt = $pdo->query('SELECT product_name FROM shopping_list ORDER BY added_at DESC');
                $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if ($items):
                    foreach ($items as $item):
                ?>
                <div class="shopping-item" data-name="<?= htmlspecialchars($item) ?>">
                    <span class="shopping-item__name"><?= htmlspecialchars($item) ?></span>
                    <button class="shopping-item__remove" aria-label="Удалить">×</button>
                </div>
                <?php 
                    endforeach;
                else:
                ?>
                <p class="empty-state">Список пуст. Начните вводить название товара выше 👆</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Вкладка "Покупки" (история) -->
            <div class="shopping-history">
                <p class="empty-state">История покупок пока пуста.<br>Купленные товары появятся здесь.</p>
            </div>
        <?php endif; ?>
    </main>

    <nav class="bottom-nav">
        <a href="index.php" class="nav-item">
            <span class="nav-item__icon">🏠</span>
            <span>Главная</span>
        </a>
        <a href="add-expense.php" class="nav-item">
            <span class="nav-item__icon">➖</span>
            <span>Расход</span>
        </a>
        <a href="add-income.php" class="nav-item">
            <span class="nav-item__icon">➕</span>
            <span>Доход</span>
        </a>
        <a href="analytics.php" class="nav-item">
            <span class="nav-item__icon">📈</span>
            <span>Анализ</span>
        </a>
        <a href="shopping.php" class="nav-item active">
            <span class="nav-item__icon">🛒</span>
            <span>Покупки</span>
        </a>
    </nav>

    <script src="assets/app.js"></script>
    <script>
    // === ЛОГИКА ПОИСКА И СПИСКА ПОКУПОК ===
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const resultsBox = document.getElementById('searchResults');
        const tobuyList = document.getElementById('tobuyList');
        let debounceTimer;

        if (!searchInput) return;

        // Поиск с задержкой (debounce)
        searchInput.addEventListener('input', function() {
            const query = this.value.trim();
            clearTimeout(debounceTimer);
            
            if (query.length < 2) {
                resultsBox.classList.add('hidden');
                return;
            }
            
            debounceTimer = setTimeout(() => {
                fetch(`api/shopping-search.php?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.length === 0) {
                            resultsBox.classList.add('hidden');
                            return;
                        }
                        
                        resultsBox.innerHTML = data.map(item => 
                            `<div class="search-result" data-name="${item.name}">${item.name}</div>`
                        ).join('');
                        resultsBox.classList.remove('hidden');
                    });
            }, 200);
        });

        // Клик по результату поиска
        resultsBox.addEventListener('click', function(e) {
            if (e.target.classList.contains('search-result')) {
                const name = e.target.dataset.name;
                
                fetch('api/shopping-add.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        // Добавляем в список без перезагрузки
                        addItemToList(name);
                        searchInput.value = '';
                        resultsBox.classList.add('hidden');
                        searchInput.blur();
                    }
                });
            }
        });

        // Удаление товара из списка (крестик)
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('shopping-item__remove')) {
                const item = e.target.closest('.shopping-item');
                const name = item.dataset.name;
                
                fetch('api/shopping-remove.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        item.remove();
                        // Если список пуст — показываем подсказку
                        if (tobuyList.querySelectorAll('.shopping-item').length === 0) {
                            tobuyList.innerHTML = '<p class="empty-state">Список пуст. Начните вводить название товара выше 👆</p>';
                        }
                    }
                });
            }
        });

        // Закрытие результатов поиска при клике вне
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !resultsBox.contains(e.target)) {
                resultsBox.classList.add('hidden');
            }
        });

        // Функция добавления элемента в DOM
        function addItemToList(name) {
            // Проверяем, нет ли уже такого товара
            if (document.querySelector(`.shopping-item[data-name="${CSS.escape(name)}"]`)) {
                return;
            }
            
            // Убираем подсказку "Список пуст", если есть
            const emptyState = tobuyList.querySelector('.empty-state');
            if (emptyState) emptyState.remove();
            
            const item = document.createElement('div');
            item.className = 'shopping-item';
            item.dataset.name = name;
            item.innerHTML = `
                <span class="shopping-item__name">${name}</span>
                <button class="shopping-item__remove" aria-label="Удалить">×</button>
            `;
            
            // Добавляем в начало списка
            tobuyList.insertBefore(item, tobuyList.firstChild);
        }
    });
    </script>
</body>
</html>