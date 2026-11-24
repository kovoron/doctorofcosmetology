<?php

/**
 * Служебный слой URL (см. TASK.md, раздел 1.3).
 *
 * Чистые функции: без вывода и побочных эффектов. Единственная зависимость
 * от окружения — requestPath() читает $_SERVER['REQUEST_URI'].
 * Ни одна функция не бросает исключений.
 *
 * Правила:
 *  - id статьи всегда первые 5 цифр имени файла ^[0-9]{5}$;
 *  - поддерживаются оба имени файла: {id}.json и {id}-{slug}.json;
 *  - slug берётся из JSON, а если его там нет — из имени файла;
 *  - невалидный slug → '' + error_log (не 500).
 */

require_once __DIR__ . '/site.php';

if (!function_exists('normalizePostSlug')) {
    /**
     * Приводит slug из JSON к безопасному виду: trim, нижний регистр,
     * пробелы и «_» → «-», схлопывание «--», только \p{L}, \p{N} и «-».
     * Невалидный (пустой после нормализации или длиннее 80 символов) → '' + error_log.
     */
    function normalizePostSlug($slug): string
    {
        if (!is_string($slug)) {
            return '';
        }

        $original = trim($slug);
        if ($original === '') {
            return '';
        }

        $normalized = mb_strtolower($original, 'UTF-8');
        $normalized = (string) preg_replace('/[\s_]+/u', '-', $normalized);
        $normalized = (string) preg_replace('/[^\p{L}\p{N}-]+/u', '', $normalized);
        $normalized = (string) preg_replace('/-{2,}/', '-', $normalized);
        $normalized = trim($normalized, '-');

        if ($normalized === '' || mb_strlen($normalized, 'UTF-8') > 80) {
            error_log('config/urls.php: invalid post slug rejected: ' . $original);

            return '';
        }

        return $normalized;
    }
}

if (!function_exists('postSlug')) {
    /**
     * Slug поста из массива JSON (или строки), прошедший normalizePostSlug().
     * Если slug нет или он невалиден — ''.
     */
    function postSlug($post): string
    {
        if (is_array($post)) {
            return normalizePostSlug($post['slug'] ?? '');
        }

        if (is_string($post)) {
            return normalizePostSlug($post);
        }

        return '';
    }
}

if (!function_exists('postIdFromFilename')) {
    /**
     * ID статьи из имени файла: «00058-fotona-…json» и «00058.json» → «00058».
     * Невалидное имя (нет 5 цифр в начале или лишние символы) → '' + error_log.
     */
    function postIdFromFilename($filename): string
    {
        $name = is_string($filename) ? basename($filename) : '';
        $name = (string) preg_replace('/\.json$/i', '', $name);

        if (preg_match('/^([0-9]{5})(?:-.*)?$/', $name, $m) !== 1) {
            error_log('config/urls.php: invalid post filename: ' . $name);

            return '';
        }

        return $m[1];
    }
}

if (!function_exists('postSlugFromFilename')) {
    /**
     * Slug из имени файла «00058-fotona-…json» → «fotona-…».
     * У имени «00058.json» slug отсутствует → ''.
     */
    function postSlugFromFilename($filename): string
    {
        $name = is_string($filename) ? basename($filename) : '';
        $name = (string) preg_replace('/\.json$/i', '', $name);

        if (preg_match('/^[0-9]{5}-(.+)$/', $name, $m) !== 1) {
            return '';
        }

        return normalizePostSlug($m[1]);
    }
}

if (!function_exists('postIdentityFromFilename')) {
    /**
     * id и slug статьи по имени файла и данным JSON.
     * Приоритет slug: поле JSON, затем суффикс имени файла.
     * Возвращает ['id' => '00058', 'slug' => 'fotona-…'] либо ['id' => '', 'slug' => ''].
     */
    function postIdentityFromFilename($filename, array $data = []): array
    {
        $id = postIdFromFilename($filename);
        if ($id === '') {
            return ['id' => '', 'slug' => ''];
        }

        $slug = postSlug($data);
        if ($slug === '') {
            $slug = postSlugFromFilename($filename);
        }

        return ['id' => $id, 'slug' => $slug];
    }
}

if (!function_exists('blogPostPath')) {
    /**
     * Путь статьи: «/blog/{id}/» или «/blog/{id}-{slug}/» (slug — rawurlencode).
     * Принимает массив с ключами id/slug либо строку id.
     * Невалидный id → '' + error_log.
     */
    function blogPostPath($post): string
    {
        $id = is_array($post) ? ($post['id'] ?? '') : $post;
        $id = is_scalar($id) ? trim((string) $id) : '';

        if (!preg_match('/^[0-9]{5}$/', $id)) {
            error_log('config/urls.php: invalid post id for blogPostPath: ' . (string) $id);

            return '';
        }

        $slug = postSlug($post);
        if ($slug === '') {
            return '/blog/' . $id . '/';
        }

        return '/blog/' . $id . '-' . rawurlencode($slug) . '/';
    }
}

if (!function_exists('absoluteUrl')) {
    /**
     * Абсолютный URL из пути: rtrim(SITE_URL, '/') . $path.
     */
    function absoluteUrl(string $path = '/'): string
    {
        $base = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';

        $path = trim($path);
        if ($path === '') {
            $path = '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return $base . $path;
    }
}

if (!function_exists('requestPath')) {
    /**
     * Путь из REQUEST_URI: без query, после rawurldecode, с ведущим «/».
     */
    function requestPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (!is_string($uri) || $uri === '') {
            return '/';
        }

        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = '/';
        }

        $path = rawurldecode($path);
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        return $path;
    }
}

if (!function_exists('blogPostUrl')) {
    /**
     * Абсолютный URL статьи (canonical).
     */
    function blogPostUrl($post): string
    {
        $path = blogPostPath($post);

        return $path === '' ? '' : absoluteUrl($path);
    }
}
