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

// Выбор категорий (если есть на странице)
document.querySelectorAll('.category-btn')?.forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('selected'));
        this.classList.add('selected');
        const input = this.closest('form')?.querySelector('[name="category_id"]');
        if (input) input.value = this.dataset.id;
    });
});

// Автофокус на поле суммы
document.querySelector('input[name="amount"]')?.focus();