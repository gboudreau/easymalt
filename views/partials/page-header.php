<?php
namespace EasyMalt;

if (!isset($view)) { http_response_code(404); return; }
?>
<header class="em-dashboard-header em-dashboard-frame">
    <div class="em-dashboard-masthead">
        <div>
            <p class="em-dashboard-eyebrow">EasyMalt</p>
            <h1><?php phe($view['title']) ?></h1>
        </div>
        <div class="em-dashboard-actions">
            <a class="em-dashboard-secondary" href="<?php phe($view['theme_url']) ?>"><?php echo $view['is_dark'] ? 'Light mode' : 'Dark mode' ?></a>
            <a class="em-dashboard-primary" href="/txn/?id=new">New transaction</a>
        </div>
    </div>
    <nav class="em-dashboard-nav" aria-label="Main navigation">
        <?php if ($view['show_dashboard_link']) : ?>
            <a href="/">Dashboard</a>
        <?php endif; ?>
        <a href="/accounts/">Accounts</a>
        <?php foreach ($view['extra_pages'] as $url => $page_name) : ?>
            <?php if (!str_starts_with($url, '/') && parse_url($url, PHP_URL_SCHEME) === null) { $url = '/' . $url; } ?>
            <a href="<?php phe($url) ?>"><?php phe($page_name) ?></a>
        <?php endforeach; ?>
        <?php if (!empty($view['back_link']) && $view['back_link'] !== '/') : ?>
            <a href="<?php phe($view['back_link']) ?>">&larr; Back</a>
        <?php endif; ?>
    </nav>
</header>
