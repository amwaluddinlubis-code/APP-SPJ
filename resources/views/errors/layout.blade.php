@php
    $code = $code ?? '500';
    $eyebrow = $eyebrow ?? 'ARKAS · DARK V2';
    $title = $title ?? 'Halaman belum siap.';
    $message = $message ?? 'Terjadi gangguan saat memproses permintaan. Silakan coba lagi beberapa saat lagi.';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#08152f">
    <title>{{ $code }} · {{ config('app.name', 'SPJ BOSP Web') }}</title>
    <style>
        :root { color-scheme: dark; --arkas-night: #061126; --arkas-panel: rgba(14, 35, 72, .72); --arkas-line: rgba(134, 177, 238, .22); --arkas-text: #f4f7ff; --arkas-muted: #a9b9d5; --arkas-accent: #5bd7ff; font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-width: 320px; min-height: 100vh; color: var(--arkas-text); background: var(--arkas-night); }
        body::before { position: fixed; inset: 0; z-index: -2; content: ""; background: radial-gradient(circle at 15% 10%, rgba(45, 108, 223, .28), transparent 34%), radial-gradient(circle at 90% 85%, rgba(91, 215, 255, .15), transparent 32%), linear-gradient(145deg, #07132c 0%, #0a1b39 52%, #061024 100%); }
        body::after { position: fixed; inset: 0; z-index: -1; content: ""; opacity: .22; background-image: linear-gradient(rgba(174, 215, 255, .08) 1px, transparent 1px), linear-gradient(90deg, rgba(174, 215, 255, .08) 1px, transparent 1px); background-size: 48px 48px; mask-image: linear-gradient(to bottom, black, transparent 75%); }
        main { display: grid; min-height: 100vh; place-items: center; padding: 28px 20px; }
        .shell { width: min(100%, 720px); }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 18px; color: var(--arkas-muted); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; }
        .brand { display: inline-flex; align-items: center; gap: 10px; color: var(--arkas-text); font-weight: 700; letter-spacing: -.01em; text-transform: none; }
        .mark { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid rgba(255,255,255,.18); border-radius: 11px; background: linear-gradient(145deg, #1764cf, #123b9a); box-shadow: 0 8px 24px rgba(12, 84, 190, .35); color: white; font-size: 11px; letter-spacing: .04em; }
        .status { border: 1px solid var(--arkas-line); border-radius: 999px; padding: 8px 12px; background: rgba(7, 20, 43, .42); }
        .panel { position: relative; overflow: hidden; border: 1px solid var(--arkas-line); border-radius: 28px; padding: clamp(34px, 7vw, 72px) clamp(24px, 8vw, 80px); background: linear-gradient(145deg, rgba(18, 47, 96, .82), var(--arkas-panel)); box-shadow: 0 30px 80px rgba(0, 0, 0, .28), inset 0 1px 0 rgba(255,255,255,.08); text-align: center; backdrop-filter: blur(18px); }
        .panel::before { position: absolute; top: -130px; right: -90px; width: 260px; height: 260px; border: 1px solid rgba(91, 215, 255, .18); border-radius: 50%; content: ""; box-shadow: 0 0 0 24px rgba(91, 215, 255, .03), 0 0 0 48px rgba(91, 215, 255, .02); }
        .code { margin: 0; color: var(--arkas-accent); font-size: clamp(64px, 14vw, 122px); font-weight: 700; letter-spacing: -.09em; line-height: .86; text-shadow: 0 0 38px rgba(91, 215, 255, .18); }
        .eyebrow { margin: 28px 0 12px; color: #c9d8f3; font-size: 12px; font-weight: 700; letter-spacing: .15em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(28px, 5vw, 46px); font-weight: 700; letter-spacing: -.045em; line-height: 1.06; }
        .message { max-width: 480px; margin: 18px auto 0; color: var(--arkas-muted); font-size: 15px; line-height: 1.7; }
        .actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; margin-top: 30px; }
        a, button { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; border: 1px solid var(--arkas-line); border-radius: 999px; padding: 0 20px; color: var(--arkas-text); background: rgba(5, 17, 38, .38); font: inherit; font-size: 14px; font-weight: 650; text-decoration: none; cursor: pointer; transition: transform .2s ease, border-color .2s ease, background .2s ease; }
        a:hover, button:hover { transform: translateY(-1px); border-color: rgba(139, 222, 255, .6); background: rgba(36, 84, 151, .52); }
        .primary { border-color: transparent; color: #061126; background: linear-gradient(135deg, #9beaff, var(--arkas-accent)); box-shadow: 0 10px 24px rgba(49, 188, 235, .2); }
        .primary:hover { border-color: transparent; background: linear-gradient(135deg, #c8f4ff, #70e0ff); }
        .hint { margin: 32px 0 0; color: #7891b8; font-size: 12px; }
        @media (max-width: 520px) { .topbar { align-items: flex-start; } .status { padding: 7px 9px; font-size: 10px; } .panel { border-radius: 22px; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; } }
    </style>
</head>
<body>
    <main>
        <div class="shell">
            <div class="topbar"><div class="brand"><span class="mark" aria-hidden="true">SPJ</span><span>{{ config('app.name', 'SPJ BOSP Web') }}</span></div><span class="status">{{ $eyebrow }}</span></div>
            <section class="panel" role="alert" aria-labelledby="error-title">
                <p class="code" aria-hidden="true">{{ $code }}</p>
                <p class="eyebrow">{{ $eyebrow }}</p>
                <h1 id="error-title">{{ $title }}</h1>
                <p class="message">{{ $message }}</p>
                <div class="actions"><button type="button" class="primary" onclick="window.location.reload()">Coba lagi</button><a href="{{ url('/') }}">Kembali ke beranda</a></div>
                <p class="hint">Data ARKAS/BKU tidak diubah oleh halaman ini.</p>
            </section>
        </div>
    </main>
</body>
</html>
