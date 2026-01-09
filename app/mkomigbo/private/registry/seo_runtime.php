<?php
declare(strict_types=1);

/**
 * project-root/private/registry/seo_runtime.php
 *
 * SEO runtime helpers. Loads templates from /private/registry/seo_templates.php.
 * If the newer seo_build()/seo_html() helpers exist (from seo_functions.php),
 * this file will delegate to them for consistency.
 */

function seo_templates(): array {
    $path = dirname(__FILE__) . '/seo_templates.php';
    if (is_file($path)) {
        $tpl = include $path;
        return is_array($tpl) ? $tpl : [];
    }
    // Safe defaults
    return [
        'site' => [
            'title'       => 'Mkomigbo • Igbo Heritage Resource Center',
            'description' => 'Explore 19 subjects covering Igbo history, culture, language, people, and more.',
            'keywords'    => 'igbo, culture, history, language, people, biafra, africa',
            'site_name'   => 'Mkomigbo',
            'separator'   => ' • ',
        ],
    ];
}

function seo_base_vars(): array {
    $site = seo_templates()['site'] ?? [];
    return [
        'site_name'         => $site['site_name'] ?? 'Mkomigbo',
        'site_title'        => $site['title'] ?? 'Mkomigbo',
        'site_description'  => $site['description'] ?? '',
        'site_keywords'     => $site['keywords'] ?? '',
        'separator'         => $site['separator'] ?? ' • ',
    ];
}

/** Replace tokens like {key} or {a|b|c} using first non-empty from $vars */
function seo_apply_template(string $tpl, array $vars): string {
    return preg_replace_callback('/\{([^}]+)\}/', function ($m) use ($vars) {
        $keys = explode('|', $m[1]);
        foreach ($keys as $k) {
            $k = trim($k);
            if ($k === 'separator' && isset($vars['separator'])) return (string)$vars['separator'];
            if (array_key_exists($k, $vars) && $vars[$k] !== '' && $vars[$k] !== null) {
                return (string)$vars[$k];
            }
        }
        return '';
    }, $tpl);
}

function seo_render(string $type, array $vars): array {
    // Prefer newer engine if present
    if (function_exists('seo_build')) {
        return seo_build($type, $vars + seo_base_vars());
    }

    $tpls = seo_templates();
    $site = seo_base_vars();
    $t = $tpls[$type] ?? $tpls['site'];

    if (!isset($vars['separator'])) $vars['separator'] = $site['separator'];

    return [
        'title'       => seo_apply_template($t['title']       ?? $site['site_title'],      $vars + $site),
        'description' => seo_apply_template($t['description'] ?? $site['site_description'], $vars + $site),
        'keywords'    => seo_apply_template($t['keywords']    ?? $site['site_keywords'],    $vars + $site),
    ];
}

/** Convenience wrappers */
function seo_for_subject(array $subject): array {
    $vars = [
        'subject_name'             => $subject['name'] ?? '',
        'subject_meta_description' => $subject['meta_description'] ?? '',
        'subject_meta_keywords'    => $subject['meta_keywords'] ?? '',
    ];
    return seo_render('subject', $vars);
}

function seo_for_page(array $subject, string $pageTitle, ?string $desc = null, ?string $keys = null): array {
    $vars = [
        'page_title'               => $pageTitle,
        'page_meta_description'    => $desc ?? '',
        'page_meta_keywords'       => $keys ?? '',
        'subject_meta_description' => $subject['meta_description'] ?? '',
        'subject_meta_keywords'    => $subject['meta_keywords'] ?? '',
    ];
    return seo_render('page', $vars);
}
