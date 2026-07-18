<?php
// ============================================================
//  sidebar.php — Komponen shared: Topbar + Sidebar navigasi
//  Include file ini di setiap halaman setelah <body>
//
//  Variabel yang harus di-set sebelum include:
//    $page_title  — judul tab browser  (string)
//    $active_menu — menu yang aktif: 'dashboard' | 'pasien' | 'treatment'
// ============================================================
$active_menu = $active_menu ?? 'dashboard';
$admin_name  = htmlspecialchars($_SESSION['admin_username'] ?? 'Admin');
$admin_init  = strtoupper(mb_substr($_SESSION['admin_username'] ?? 'A', 0, 1));
?>
<!-- ===================== TOPBAR ===================== -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<header class="topbar">
    <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle menu">
        <span></span><span></span><span></span>
    </button>
    <div class="topbar-title" id="topbarTitle"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></div>
    <div class="topbar-right">
        <div class="admin-badge">
            <div class="admin-avatar"><?= $admin_init ?></div>
            <span class="admin-name"><?= $admin_name ?></span>
        </div>
    </div>
</header>

<!-- ===================== SIDEBAR ===================== -->
<aside class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <?php if (file_exists(__DIR__ . '/assets/images/logo.png')): ?>
                <img src="assets/images/logo.png" alt="Ozthetique">
            <?php else: ?>
                <span class="brand-icon-fallback">OZ</span>
            <?php endif; ?>
        </div>
        <div class="brand-text">
            <span class="brand-name">Ozthetique</span>
            <span class="brand-sub">Jakarta · Rekam Medis</span>
        </div>
    </div>

    <!-- Divider label -->
    <div class="sidebar-label">MENU UTAMA</div>

    <!-- Nav items -->
    <nav class="sidebar-nav">
        <a href="index.php"
           class="nav-item <?= $active_menu === 'dashboard' ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10
                     0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            </span>
            <span class="nav-label">Dashboard</span>
        </a>

        <a href="pasien.php"
           class="nav-item <?= $active_menu === 'pasien' ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34
                     2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8
                     0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5
                     8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8
                     0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </span>
            <span class="nav-label">Daftar Pasien</span>
        </a>

        <a href="tambah_treatment.php"
           class="nav-item <?= $active_menu === 'treatment' ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9
                     2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7
                     14l-5-5 1.4-1.4L12 14.2l7.6-7.6L21 8l-9 9z"/></svg>
            </span>
            <span class="nav-label">Catat Treatment</span>
        </a>

        <a href="master_perawatan.php"
           class="nav-item <?= $active_menu === 'master' ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M14 6l-1-2H5v17h2v-7h5l1 2h7V6h-6zm4
                     8h-4l-1-2H7V6h5l1 2h5v6z"/></svg>
            </span>
            <span class="nav-label">Master Perawatan</span>
        </a>

        <a href="crm_bulk.php"
           class="nav-item <?= $active_menu === 'crm' ? 'active' : '' ?>">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
            </span>
            <span class="nav-label">Import CRM</span>
        </a>
    </nav>

    <!-- Spacer -->
    <div class="sidebar-spacer"></div>

    <!-- Divider label -->
    <div class="sidebar-label">AKUN</div>

    <nav class="sidebar-nav">
        <a href="logout.php" class="nav-item nav-logout">
            <span class="nav-icon">
                <svg viewBox="0 0 24 24"><path d="M17 7l-1.4 1.4L18.2 11H9v2h9.2l-2.6
                     2.6L17 17l5-5-5-5zM5 5h7V3H5c-1.1 0-2 .9-2
                     2v14c0 1.1.9 2 2 2h7v-2H5V5z"/></svg>
            </span>
            <span class="nav-label">Keluar</span>
        </a>
    </nav>
</aside>

<!-- Overlay untuk mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
