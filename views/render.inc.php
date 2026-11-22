<?php

namespace EasyMalt;

function renderView(string $viewName, array $view): void {
    require __DIR__ . '/' . $viewName . '.php';
}

function renderPageHeader(string $title, bool $is_dark, array $query_keys = [], bool $show_dashboard_link = true, ?string $back_link = null): void {
    renderView('partials/page-header', [
        'title' => $title,
        'is_dark' => $is_dark,
        'theme_url' => '?' . http_build_query(['dark' => $is_dark ? 0 : 1]
            + array_intersect_key($_GET, array_flip($query_keys))),
        'extra_pages' => Config::get('EXTRA_PAGES') ?: [],
        'show_dashboard_link' => $show_dashboard_link,
        'back_link' => $back_link,
    ]);
}
