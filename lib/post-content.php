<?php

/**
 * Постобработка HTML статьи (см. TASK.md, раздел 6).
 *
 * Таблицы в статьях должны прокручиваться горизонтально на мобильных,
 * поэтому каждая <table> оборачивается в .post-table-wrap (стили — style.css).
 *
 * Контент — доверенный JSON автора. Вложенных <table> нет (проверено 34/34),
 * поэтому регулярного выражения достаточно; при появлении вложенных таблиц
 * логику нужно перевести на DOMDocument.
 */

if (!function_exists('wrapPostTables')) {
    /**
     * Оборачивает каждую таблицу в скроллируемый контейнер.
     *
     * Идемпотентна: если в HTML уже есть post-table-wrap, строка возвращается
     * без изменений. При ошибке PCRE возвращается исходный HTML.
     */
    function wrapPostTables(string $html): string
    {
        if ($html === '') {
            return '';
        }

        if (strpos($html, 'post-table-wrap') !== false) {
            return $html;
        }

        $wrapped = preg_replace_callback(
            '/<table\b[^>]*>.*?<\/table>/is',
            static function (array $match): string {
                return '<div class="post-table-wrap" role="region" aria-label="Таблица" tabindex="0">'
                    . $match[0]
                    . '</div>';
            },
            $html
        );

        return $wrapped === null ? $html : $wrapped;
    }
}
