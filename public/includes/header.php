<?php
$current_page = basename($_SERVER['PHP_SELF'], '.php');
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="lt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MT Modulis - Gamybos valdymo sistema</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="stylesheet" href="/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">MT Modulis</div>
                <button class="sidebar-close" id="sidebarClose" data-testid="button-sidebar-close">&times;</button>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-section-label">Gamyba</div>
                <a href="/index.php" class="nav-item <?= $current_page === 'index' ? 'active' : '' ?>" data-testid="link-dashboard">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    <span>Kokybiniai rodikliai</span>
                </a>
                <a href="/uzsakymai.php" class="nav-item <?= $current_page === 'uzsakymai' ? 'active' : '' ?>" data-testid="link-orders">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Užsakymai</span>
                </a>
                <a href="/pretenzijos.php" class="nav-item <?= $current_page === 'pretenzijos' ? 'active' : '' ?>" data-testid="link-claims">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Pretenzijos</span>
                </a>
                <div class="nav-section-label">Administravimas</div>
                <a href="/prietaisai.php" class="nav-item <?= $current_page === 'prietaisai' ? 'active' : '' ?>" data-testid="link-devices">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                    <span>Prietaisų patikra</span>
                </a>
                <?php if (($user['role'] ?? '') === 'admin'): ?>
                <a href="/vartotojai.php" class="nav-item <?= $current_page === 'vartotojai' ? 'active' : '' ?>" data-testid="link-users">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Vartotojų valdymas</span>
                </a>
                <?php endif; ?>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?= h(mb_substr($user['vardas'] ?? 'V', 0, 1)) ?></div>
                    <div class="user-details">
                        <div class="user-name"><?= h(($user['vardas'] ?? '') . ' ' . ($user['pavarde'] ?? '')) ?></div>
                        <div class="user-role"><?= h($user['role'] ?? 'user') ?></div>
                    </div>
                </div>
                <a href="/profilis.php" class="nav-item <?= $current_page === 'profilis' ? 'active' : '' ?>" data-testid="link-profile">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span>Profilis</span>
                </a>
                <a href="/logout.php" class="nav-item logout-link" data-testid="link-logout">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span>Atsijungti</span>
                </a>
            </div>
        </aside>
        <div class="main-content">
            <header class="top-header">
                <button class="menu-toggle" id="menuToggle" data-testid="button-menu-toggle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                </button>
                <h1 class="page-title" data-testid="text-page-title"><?= h($page_title ?? 'MT Modulis') ?></h1>
            </header>
            <main class="content-area">
