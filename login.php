<?php
// ============================================================
//  login.php — Halaman Login Admin
// ============================================================
require_once 'koneksi.php';

if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $koneksi->prepare(
            'SELECT id, username, password, role FROM admin_klinik WHERE username = ? LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role']     = $admin['role'] ?? 'superadmin';
            header('Location: index.php');
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Ozthetique Jakarta</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --gold:      #c9a96e;
            --gold-light:#e8c98a;
            --gold-dark: #a07840;
            --ink:       #0a0a0f;
            --ink-2:     #12121a;
            --ink-3:     #1c1c28;
            --ink-4:     #252535;
            --white:     #ffffff;
            --muted:     rgba(255,255,255,.45);
            --border:    rgba(255,255,255,.08);
        }

        html, body {
            height: 100%;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--ink);
            color: var(--white);
            overflow: hidden;
        }

        /* ── Animated background ── */
        .bg-canvas {
            position: fixed;
            inset: 0;
            z-index: 0;
            overflow: hidden;
        }

        .bg-canvas::before {
            content: '';
            position: absolute;
            width: 700px; height: 700px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,169,110,.12) 0%, transparent 70%);
            top: -200px; left: -150px;
            animation: drift1 18s ease-in-out infinite alternate;
        }

        .bg-canvas::after {
            content: '';
            position: absolute;
            width: 500px; height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(100,80,200,.1) 0%, transparent 70%);
            bottom: -100px; right: -100px;
            animation: drift2 22s ease-in-out infinite alternate;
        }

        .bg-dot {
            position: absolute;
            width: 400px; height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(201,169,110,.07) 0%, transparent 70%);
            bottom: 30%; right: 20%;
            animation: drift3 15s ease-in-out infinite alternate;
        }

        @keyframes drift1 { from { transform: translate(0,0) scale(1); } to { transform: translate(60px,40px) scale(1.15); } }
        @keyframes drift2 { from { transform: translate(0,0) scale(1); } to { transform: translate(-40px,60px) scale(1.1); } }
        @keyframes drift3 { from { transform: translate(0,0); } to { transform: translate(30px,-50px); } }

        /* ── Layout ── */
        .page {
            position: relative;
            z-index: 1;
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 56px;
            border-right: 1px solid var(--border);
            position: relative;
        }

        .left-inner {
            text-align: center;
            max-width: 340px;
        }

        /* Logo */
        .logo-ring {
            position: relative;
            width: 150px; height: 150px;
            margin: 0 auto 36px;
        }

        .logo-ring::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: conic-gradient(from 0deg, var(--gold), var(--gold-light), transparent, var(--gold-dark), var(--gold));
            animation: spin-ring 6s linear infinite;
        }

        @keyframes spin-ring { to { transform: rotate(360deg); } }

        .logo-ring-inner {
            position: absolute;
            inset: 4px;
            border-radius: 50%;
            background: var(--ink-2);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden;
        }

        .logo-ring-inner img {
            width: 100%; height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        /* Fallback monogram jika logo belum ada */
        .logo-monogram {
            font-size: 2.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--gold), var(--gold-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -.05em;
        }

        .brand-name {
            font-size: 1.65rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--gold-light), var(--gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.2;
        }

        .brand-city {
            font-size: .72rem;
            font-weight: 600;
            letter-spacing: .45em;
            text-transform: uppercase;
            color: var(--muted);
            margin-top: 6px;
        }

        .divider-gold {
            width: 60px; height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 28px auto;
        }

        .tagline {
            font-size: .85rem;
            color: rgba(255,255,255,.38);
            line-height: 1.7;
            letter-spacing: .02em;
        }

        .feature-pills {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 32px;
            text-align: left;
        }

        .pill {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,.04);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: .78rem;
            color: rgba(255,255,255,.55);
            transition: all .3s;
        }

        .pill:hover { background: rgba(201,169,110,.08); border-color: rgba(201,169,110,.2); color: rgba(255,255,255,.75); }

        .pill-icon {
            width: 28px; height: 28px;
            border-radius: 8px;
            background: linear-gradient(135deg, rgba(201,169,110,.2), rgba(201,169,110,.05));
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }

        .pill-icon svg { width: 14px; height: 14px; fill: var(--gold); }

        /* ── RIGHT PANEL ── */
        .right-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 56px;
        }

        .form-box {
            width: 100%;
            max-width: 400px;
        }

        .form-eyebrow {
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .35em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 10px;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--white);
            letter-spacing: -.04em;
            line-height: 1.15;
            margin-bottom: 8px;
        }

        .form-sub {
            font-size: .85rem;
            color: var(--muted);
            margin-bottom: 36px;
            line-height: 1.6;
        }

        /* Error alert */
        .alert-error {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.3);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: .84rem;
            color: #fca5a5;
            margin-bottom: 24px;
            animation: shake .4s ease;
        }

        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%      { transform: translateX(-6px); }
            60%      { transform: translateX(6px); }
        }

        .alert-error svg { width: 16px; height: 16px; fill: #f87171; flex-shrink: 0; }

        /* Field */
        .field { margin-bottom: 20px; }

        .field-label {
            display: block;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            color: rgba(255,255,255,.4);
            margin-bottom: 8px;
        }

        .field-wrap { position: relative; }

        .field-wrap svg.field-icon {
            position: absolute;
            left: 16px; top: 50%;
            transform: translateY(-50%);
            width: 16px; height: 16px;
            fill: rgba(255,255,255,.2);
            pointer-events: none;
            transition: fill .2s;
        }

        .field-wrap:focus-within svg.field-icon { fill: var(--gold); }

        .field-input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            background: var(--ink-3);
            border: 1.5px solid var(--border);
            border-radius: 12px;
            font-size: .95rem;
            color: var(--white);
            font-family: inherit;
            outline: none;
            transition: border-color .25s, box-shadow .25s, background .25s;
        }

        .field-input::placeholder { color: rgba(255,255,255,.18); }

        .field-input:focus {
            background: var(--ink-4);
            border-color: rgba(201,169,110,.6);
            box-shadow: 0 0 0 4px rgba(201,169,110,.1);
        }

        /* Password toggle */
        .toggle-pw {
            position: absolute;
            right: 14px; top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            display: flex; align-items: center;
            color: rgba(255,255,255,.25);
            transition: color .2s;
        }

        .toggle-pw:hover { color: rgba(255,255,255,.6); }
        .toggle-pw svg { width: 16px; height: 16px; fill: currentColor; }

        /* Submit */
        .btn-submit {
            width: 100%;
            margin-top: 8px;
            padding: 15px;
            background: linear-gradient(135deg, var(--gold), var(--gold-dark));
            color: var(--ink);
            font-size: .95rem;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all .25s;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(201,169,110,.25);
        }

        .btn-submit::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.15), transparent);
            opacity: 0;
            transition: opacity .2s;
        }

        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 12px 40px rgba(201,169,110,.4); }
        .btn-submit:hover::before { opacity: 1; }
        .btn-submit:active { transform: translateY(0); }

        /* Footer */
        .form-footer {
            text-align: center;
            margin-top: 28px;
            font-size: .72rem;
            color: rgba(255,255,255,.18);
            letter-spacing: .06em;
        }

        /* ── Responsive ── */
        @media (max-width: 720px) {
            html, body { overflow: auto; }
            .page { grid-template-columns: 1fr; }
            .left-panel { display: none; }
            .right-panel { padding: 48px 28px; min-height: 100vh; }
        }
    </style>
</head>
<body>
<div class="bg-canvas"><div class="bg-dot"></div></div>

<div class="page">
    <!-- ── LEFT ── -->
    <div class="left-panel">
        <div class="left-inner">
            <div class="logo-ring">
                <div class="logo-ring-inner">
                    <?php if (file_exists(__DIR__ . '/assets/images/logo.png')): ?>
                        <img src="assets/images/logo.png" alt="Ozthetique Jakarta">
                    <?php else: ?>
                        <span class="logo-monogram">OZ</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="brand-name">Ozthetique</div>
            <div class="brand-city">Jakarta</div>

            <div class="divider-gold"></div>

            <p class="tagline">Sistem Rekam Medis Digital<br>untuk klinik kecantikan modern</p>

            <div class="feature-pills">
                <div class="pill">
                    <div class="pill-icon">
                        <svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    </div>
                    Pencarian pasien real-time
                </div>
                <div class="pill">
                    <div class="pill-icon">
                        <svg viewBox="0 0 24 24"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
                    </div>
                    Riwayat treatment kronologis
                </div>
                <div class="pill">
                    <div class="pill-icon">
                        <svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                    </div>
                    Galeri foto before &amp; after
                </div>
                <div class="pill">
                    <div class="pill-icon">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-2 .9-2 2v16c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>
                    </div>
                    Catatan klinis terstruktur
                </div>
            </div>
        </div>
    </div>

    <!-- ── RIGHT ── -->
    <div class="right-panel">
        <div class="form-box">
            <p class="form-eyebrow">Sistem Manajemen Klinik</p>
            <h1 class="form-title">Selamat<br>Datang Kembali</h1>
            <p class="form-sub">Masuk ke panel administrasi untuk mengelola rekam medis pasien.</p>

            <?php if ($error !== ''): ?>
            <div class="alert-error" role="alert">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="login.php" novalidate>
                <div class="field">
                    <label class="field-label" for="username">Username</label>
                    <div class="field-wrap">
                        <svg class="field-icon" viewBox="0 0 24 24"><path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/></svg>
                        <input class="field-input" type="text" id="username" name="username"
                               placeholder="Masukkan username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="password">Password</label>
                    <div class="field-wrap">
                        <svg class="field-icon" viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                        <input class="field-input" type="password" id="password" name="password"
                               placeholder="Masukkan password"
                               autocomplete="current-password" required>
                        <button type="button" class="toggle-pw" id="togglePw" aria-label="Tampilkan password">
                            <svg id="eye-icon" viewBox="0 0 24 24">
                                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Masuk ke Dashboard</button>
            </form>

            <div class="form-footer">&copy; <?= date('Y') ?> Ozthetique Jakarta &mdash; Rekam Medis</div>
        </div>
    </div>
</div>

<script>
document.getElementById('togglePw').addEventListener('click', function () {
    var pw   = document.getElementById('password');
    var icon = document.getElementById('eye-icon');
    if (pw.type === 'password') {
        pw.type = 'text';
        icon.innerHTML = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
    } else {
        pw.type = 'password';
        icon.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
    }
});
</script>
</body>
</html>
