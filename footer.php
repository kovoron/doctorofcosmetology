<?php require_once __DIR__ . '/config/site.php'; ?>
<footer class="footer">
    <div class="container">
        <div class="footer__main">
            <!-- Информация о враче -->
            <div class="footer__doctor">
                <span class="footer__label">Врач-косметолог</span>
                <h3 class="footer__name">Якшигулова Ксения Юрьевна</h3>
            </div>

            <!-- Места приёма -->
            <div class="footer__locations">
                <h4 class="footer__section-title">Места приёма пациентов</h4>
                
                <div class="footer__location-item">
                    <span class="footer__clinic-name"><a href="https://majesty-cosmetology.ru">Центр косметологии Majesty</a></span>
                    <span class="footer__address">ул. Оптиков, 51</span>
                    <a href="tel:+79219040040" class="footer__phone">+7 (921) 904-00-40</a>
                </div>

                <div class="footer__location-item">
                    <span class="footer__clinic-name">Клиника Ю-MED</span>
                    <span class="footer__address">6-я линия В.О., 17</span>
                    <a href="tel:+78127048320" class="footer__phone">+7 (812) 704-83-20</a>
                </div>
            </div>
        </div>

        <!-- Копирайт -->
        <div class="footer__bottom">
            <p class="footer__copyright">© <?php echo date('Y'); ?> Ксения Якшигулова. Все права защищены.</p>
        </div>
    </div>
</footer>


<script>
    // Фиксация хедера при скролле
    const header = document.getElementById('header');
    let lastScroll = 0;

    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 100) {
            header.classList.add('header--scrolled');
        } else {
            header.classList.remove('header--scrolled');
        }
        
        lastScroll = currentScroll;
    });


 

</script>

<script>
    // Бургер меню
    const burgerBtn = document.querySelector('.header__burger');
    const mobileMenu = document.querySelector('.mobile-menu');
    const closeMenu = document.querySelector('.mobile-menu__close');
    const mobileLinks = document.querySelectorAll('.mobile-menu__link');

    // Открытие меню
    if (burgerBtn) {
        burgerBtn.addEventListener('click', () => {
            mobileMenu.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }

    // Закрытие меню
    if (closeMenu) {
        closeMenu.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            document.body.style.overflow = '';
        });
    }

    // Закрытие при клике на ссылку
    mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
            mobileMenu.classList.remove('active');
            document.body.style.overflow = '';
        });
    });

    // Закрытие при клике вне меню
    if (mobileMenu) {
        mobileMenu.addEventListener('click', (e) => {
            if (e.target === mobileMenu) {
                mobileMenu.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
</script>

<!-- Yandex.Metrika counter -->
<?php if (METRIKA_ENABLED): ?>
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=<?= (int) METRIKA_ID ?>', 'ym');

    ym(<?= (int) METRIKA_ID ?>, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/<?= (int) METRIKA_ID ?>" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<?php endif; ?>
<!-- /Yandex.Metrika counter -->
<!-- Плавающая кнопка Онлайн-записи -->
<style>
    .floating-booking-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background-color: #C5A065; /* Ваш фирменный золотой цвет */
        color: #ffffff;
        padding: 14px 24px;
        border-radius: 50px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-family: 'Montserrat', sans-serif;
        font-size: 1rem;
        font-weight: 600;
        text-decoration: none;
        box-shadow: 0 8px 25px rgba(197, 160, 101, 0.4);
        z-index: 99999; /* Чтобы кнопка была поверх всех элементов */
        transition: all 0.3s ease;
        animation: floatPulse 2s infinite; /* Легкая пульсация для привлечения внимания */
    }

    .floating-booking-btn:hover {
        background-color: #a8864f;
        transform: translateY(-4px);
        box-shadow: 0 12px 30px rgba(197, 160, 101, 0.6);
        animation: none;
    }

    .floating-booking-btn svg {
        flex-shrink: 0;
    }

    /* Анимация пульсации */
    @keyframes floatPulse {
        0% { box-shadow: 0 0 0 0 rgba(197, 160, 101, 0.5); }
        70% { box-shadow: 0 0 0 15px rgba(197, 160, 101, 0); }
        100% { box-shadow: 0 0 0 0 rgba(197, 160, 101, 0); }
    }

    /* Мобильная версия: превращаем в круглую иконку */
    @media (max-width: 768px) {
        .floating-booking-btn {
            bottom: 20px;
            right: 20px;
            padding: 16px;
            border-radius: 50%;
        }
        
        /* Скрываем текст на мобильных, оставляем только иконку */
        .floating-booking-btn span {
            display: none; 
        }
    }
</style>

<a href="https://n323536.yclients.com/company/311208/personal/select-services?o=m4280871&utm_source=site&utm_medium=doctorofcosmetology" 
   class="floating-booking-btn" 
   target="_blank" 
   rel="noopener noreferrer"
   aria-label="Онлайн-запись">
    
    <!-- Иконка календаря/записи -->
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <line x1="16" y1="2" x2="16" y2="6"></line>
        <line x1="8" y1="2" x2="8" y2="6"></line>
        <line x1="3" y1="10" x2="21" y2="10"></line>
        <path d="M8 14h.01"></path>
        <path d="M12 14h.01"></path>
        <path d="M16 14h.01"></path>
        <path d="M8 18h.01"></path>
        <path d="M12 18h.01"></path>
    </svg>
    
    <span>Онлайн-запись</span>
</a>
</main>
</body>