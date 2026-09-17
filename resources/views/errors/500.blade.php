<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terjadi Gangguan · {{ config('app.name', 'SPJ BOSP Web') }}</title>
    <style>
        :root { color-scheme: dark; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; color: #f5f5f7; background: #161617; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 32px 20px; }
        .panel { width: min(100%, 560px); text-align: center; }
        .mark { width: 52px; height: 52px; margin: 0 auto 28px; display: grid; place-items: center; border: 1px solid #48484a; border-radius: 16px; color: #d2d2d7; font-size: 16px; font-weight: 700; letter-spacing: .08em; }
        .eyebrow { margin: 0 0 12px; color: #98989d; font-size: 13px; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
        h1 { margin: 0; font-size: clamp(32px, 6vw, 48px); line-height: 1.08; letter-spacing: -.04em; font-weight: 700; }
        p { margin: 18px auto 0; max-width: 430px; color: #a1a1a6; font-size: 16px; line-height: 1.65; }
        .actions { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; margin-top: 30px; }
        a, button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 18px; border-radius: 999px; border: 1px solid #48484a; color: #f5f5f7; background: transparent; font: inherit; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; transition: background .2s ease, border-color .2s ease; }
        a:hover, button:hover { background: #2c2c2e; border-color: #68686b; }
        .primary { color: #161617; background: #f5f5f7; border-color: #f5f5f7; }
        .primary:hover { background: #d2d2d7; border-color: #d2d2d7; }
        .hint { margin-top: 48px; color: #6e6e73; font-size: 12px; }
    </style>
</head>

<body>
    <main>
        <section class="panel" role="alert" aria-labelledby="error-title">
            <div class="mark" aria-hidden="true">SPJ</div>
            <p class="eyebrow">Gangguan sementara</p>
            <h1 id="error-title">Halaman belum siap.</h1>
            <p>Terjadi kesalahan saat memproses permintaan. Silakan coba lagi; data ARKAS lama tetap aman dan tidak diubah.</p>
            <div class="actions">
                <button type="button" class="primary" onclick="window.location.reload()">Coba lagi</button>
                <a href="{{ url('/') }}">Kembali ke beranda</a>
            </div>
            <p class="hint">Kode kesalahan 500</p>
        </section>
    </main>
</body>

</html>
