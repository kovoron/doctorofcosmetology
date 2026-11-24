<?php
$canonical = '/';
$pageDescription = 'Врач врач-косметолог с красным дипломом и опытом с 2011 года. Обучалась в Австрии, Испании, Турции, Кипре и России. Ксения Юрьевна Якшигулова.';

include $_SERVER['DOCUMENT_ROOT'] . '/header.php';
?>

<section class="hero">
    <!-- Фоновое изображение (на весь экран, без скруглений) -->
    <div class="hero__bg">
        <img src="/images/hero-doctor.jpg" alt="Ксения Якшигулова — врач-косметолог" class="hero__bg-image">
        <!-- Затемнение для читаемости текста (тоны палитры) -->
        <div class="hero__overlay"></div>
    </div>

    <!-- Основной контент -->
    <div class="hero__container">
        <div class="hero__content">
            <h1 class="hero__title">
                ЕСТЕСТВЕННАЯ<br>
                КРАСОТА —<br>
                ЭТО НАУКА
            </h1>
            
<p class="hero__subtitle">
    Врач-косметолог Ксения Якшигулова:<br>
    индивидуальный подход,<br>
    современные методики —<br>
    ваша уверенность в себе
</p>
            
            <a href="https://max.ru/u/f9LHodD0cOLGlvkkywieaju4QXn5XPH-B_dFHU-2UB0kocaZvHQneo71bXc" class="hero__btn" target="_blank">
                ЗАДАТЬ ЛЮБОЙ ВОПРОС (отвечаю в рабочее время)
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </a>
        </div>

        <!-- Информационная карточка врача (как на макете) -->
        <div class="hero__doctor-info">
            <span class="hero__doctor-name">КСЕНИЯ ЯКШИГУЛОВА</span>
            <span class="hero__doctor-position">врач-косметолог<br>дерматовенеролог</span>
        </div>
    </div>

    <!-- Нижняя панель с преимуществами (4 колонки) -->
    <div class="hero__features">
        <div class="features__container">
            <!-- 1. Естественный результат -->
            <div class="feature">
                <div class="feature__icon">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 4C20 4 8 16 8 28C8 34.6274 13.3726 40 20 40C26.6274 40 32 34.6274 32 28C32 16 20 4 20 4Z" stroke="#8B7355" stroke-width="2"/>
                        <path d="M20 40V20" stroke="#8B7355" stroke-width="2"/>
                        <path d="M14 24C14 24 16 28 20 28C24 28 26 24 26 24" stroke="#8B7355" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <h3 class="feature__title">ЕСТЕСТВЕННЫЙ<br>РЕЗУЛЬТАТ</h3>
                <p class="feature__desc">подчеркиваем вашу<br>индивидуальность</p>
            </div>

            <!-- 2. Безопасность -->
            <div class="feature">
                <div class="feature__icon">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 4L4 12V20C4 30 12 36 20 38C28 36 36 30 36 20V12L20 4Z" stroke="#8B7355" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M16 20L19 23L24 17" stroke="#8B7355" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="feature__title">БЕЗОПАСНОСТЬ<br>И КАЧЕСТВО</h3>
                <p class="feature__desc">сертифицированные<br>препараты и оборудование</p>
            </div>

            <!-- 3. Комплексный подход -->
            <div class="feature">
                <div class="feature__icon">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M20 4L24 14L34 16L26 24L28 34L20 30L12 34L14 24L6 16L16 14L20 4Z" stroke="#8B7355" stroke-width="2" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h3 class="feature__title">КОМПЛЕКСНЫЙ<br>ПОДХОД</h3>
                <p class="feature__desc">решение не только<br>эстетических, но и<br>физиологических задач</p>
            </div>

            <!-- 4. Персональное сопровождение -->
            <div class="feature">
                <div class="feature__icon">
                    <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="20" cy="20" r="16" stroke="#8B7355" stroke-width="2"/>
                        <path d="M20 12V20L26 24" stroke="#8B7355" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <h3 class="feature__title">ПЕРСОНАЛЬНОЕ<br>СОПРОВОЖДЕНИЕ</h3>
                <p class="feature__desc">поддержка на всех<br>этапах лечения</p>
            </div>
        </div>
    </div>
</section>


<section class="sections">
    <div class="container">
        <div class="sections__grid">
            <!-- Портфолио -->
            <a href="/portfolio/" class="section-card">
                <div class="section-card__image">
                    <img src="/images/section-portfolio.jpg" alt="Портфолио" loading="lazy">
                    <div class="section-card__overlay"></div>
                </div>
                <div class="section-card__content">
                    <h3 class="section-card__title">ПОРТФОЛИО</h3>
                    <p class="section-card__desc">Реальные результаты<br>пациентов до и после<br>процедур</p>
                    <div class="section-card__arrow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </div>
                </div>
            </a>

            <!-- Блог -->
            <a href="/blog/" class="section-card">
                <div class="section-card__image">
                    <img src="/images/section-blog.jpg" alt="Блог" loading="lazy">
                    <div class="section-card__overlay"></div>
                </div>
                <div class="section-card__content">
                    <h3 class="section-card__title">БЛОГ</h3>
                    <p class="section-card__desc">Полезные статьи<br>о красоте, уходе<br>и современных<br>методиках</p>
                    <div class="section-card__arrow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </div>
                </div>
            </a>

            <!-- О враче -->
            <a href="/about/" class="section-card">
                <div class="section-card__image">
                    <img src="/images/section-about.jpg" alt="О враче" loading="lazy">
                    <div class="section-card__overlay"></div>
                </div>
                <div class="section-card__content">
                    <h3 class="section-card__title">О ВРАЧЕ</h3>
                    <p class="section-card__desc">Опыт, образование<br>и принципы работы<br>врача-косметолога</p>
                    <div class="section-card__arrow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </div>
                </div>
            </a>

            <!-- Задать вопрос -->
            <a href="/q/" class="section-card">
                <div class="section-card__image">
                    <img src="/images/section-question.jpg" alt="Задать вопрос" loading="lazy">
                    <div class="section-card__overlay"></div>
                </div>
                <div class="section-card__content">
                    <h3 class="section-card__title">ЗАДАТЬ ВОПРОС</h3>
                    <p class="section-card__desc">Получите ответ<br>на ваш следующий<br>вопрос</p>
                    <div class="section-card__arrow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </div>
                </div>
            </a>
        </div>
    </div>
</section>

<section class="signature-section">
    <div class="container">
        <div class="signature-grid">
            <!-- Левый блок: Цитата -->
            <div class="signature-quote">
                <div class="signature-quote__icon">“</div>
                <p class="signature-quote__text">
                    Моя главная цель —<br>
                    ваша естественная красота<br>
                    и уверенность в себе
                </p>
                <div class="signature-quote__sign">
                    <!-- Замените src на путь к вашему файлу с рукописной подписью -->
                    <img src="/images/signature.png" alt="Подпись Ксении Якшигуловой">
                </div>
            </div>

            <!-- Правый блок: Запись на прием -->
            <div class="signature-cta">
                <div class="signature-cta__content">
                    <h3 class="signature-cta__title">ЗАПИСАТЬСЯ<br>НА ПРИЕМ</h3>
                    <p class="signature-cta__desc">Индивидуальная консультация<br>и подбор процедур</p>
                    <a href="https://max.ru/u/f9LHodD0cOLGlvkkywieaju4QXn5XPH-B_dFHU-2UB0kocaZvHQneo71bXc" class="signature-cta__btn" target="_blank" aria-label="Записаться на прием">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                            <polyline points="12 5 19 12 12 19"></polyline>
                        </svg>
                    </a>
                </div>
                
                <div class="signature-cta__image">
                    <img src="/images/cta-decor.jpg" alt="Интерьер кабинета">
                </div>
            </div>
        </div>
    </div>
</section>


<?php include $_SERVER['DOCUMENT_ROOT'] . '/footer.php'; ?>