{{-- Terapkan profil tema tersimpan secara sinkron sebelum first paint agar
     tidak terjadi flash tema lain saat pindah halaman. Peta profil disalin
     dari resources/js/theme-profiles.js (sumber kebenaran tunggal); test
     ThemeEarlyInitTest mengunci parity keduanya. Urutan nilai per tema:
     appearance, personality, density, radius, shadow, header, sidebar, table,
     controls, accent, accentStrong, accentSoft, sidebarColor, sidebarDeepColor. --}}
<script>
    (() => {
        const profiles = {"light":["light","modern","comfortable","rounded","soft","gradient","gradient","soft","rounded","#14b8a6","#0f766e","#ccfbf1","#134e4a","#042f2e"],"dark":["dark","professional","compact","medium","subtle","deep","deep","quiet","medium","#64748b","#94a3b8","#1f2937","#111827","#030712"],"slate":["light","minimal","compact","medium","flat","flat","solid","minimal","medium","#64748b","#475569","#e2e8f0","#334155","#0f172a"],"gray":["light","office","compact","small","subtle","solid","solid","dense","small","#6b7280","#4b5563","#e5e7eb","#374151","#111827"],"zinc":["light","studio","comfortable","large","soft","split","solid","soft","rounded","#71717a","#52525b","#e4e4e7","#3f3f46","#18181b"],"neutral":["light","classic","compact","small","flat","classic","solid","lined","small","#737373","#525252","#e5e5e5","#404040","#171717"],"stone":["light","warm","comfortable","large","soft","soft","soft","soft","rounded","#78716c","#57534e","#e7e5e4","#44403c","#1c1917"],"red":["light","command","compact","medium","strong","bold","deep","lined","medium","#ef4444","#dc2626","#fee2e2","#7f1d1d","#450a0a"],"orange":["light","energetic","comfortable","large","soft","bold","gradient","soft","rounded","#f97316","#ea580c","#ffedd5","#7c2d12","#431407"],"yellow":["light","bright","spacious","large","soft","soft","soft","airy","rounded","#eab308","#ca8a04","#fef9c3","#713f12","#422006"],"lime":["light","fresh","spacious","large","soft","split","gradient","airy","pill","#84cc16","#65a30d","#ecfccb","#365314","#1a2e05"],"green":["light","school","comfortable","large","soft","gradient","gradient","soft","rounded","#22c55e","#16a34a","#dcfce7","#166534","#052e16"],"teal":["light","operational","comfortable","medium","subtle","gradient","gradient","lined","medium","#14b8a6","#0f766e","#ccfbf1","#134e4a","#042f2e"],"sky":["light","airy","spacious","large","soft","glass","soft","airy","rounded","#0ea5e9","#0284c7","#e0f2fe","#0c4a6e","#082f49"],"blue":["light","modern","comfortable","large","soft","glass","gradient","soft","rounded","#3b82f6","#2563eb","#dbeafe","#1e3a8a","#172554"],"indigo":["light","executive","compact","medium","strong","bold","deep","lined","medium","#6366f1","#4f46e5","#e0e7ff","#312e81","#1e1b4b"],"violet":["light","premium","comfortable","xl","floating","bold","gradient","soft","rounded","#8b5cf6","#7c3aed","#ede9fe","#4c1d95","#2e1065"],"purple":["light","creative","spacious","xl","floating","split","gradient","airy","pill","#a855f7","#9333ea","#f3e8ff","#581c87","#3b0764"],"fuchsia":["light","expressive","comfortable","xl","floating","bold","gradient","soft","pill","#d946ef","#c026d3","#fae8ff","#701a75","#4a044e"],"pink":["light","soft","spacious","xl","soft","soft","soft","airy","rounded","#ec4899","#db2777","#fce7f3","#831843","#500724"],"rose":["light","soft","comfortable","xl","soft","soft","gradient","soft","rounded","#f43f5e","#e11d48","#ffe4e6","#881337","#4c0519"],"cyan":["light","tech","compact","small","subtle","split","deep","lined","small","#06b6d4","#0891b2","#cffafe","#155e75","#083344"],"emerald":["light","school","comfortable","large","soft","gradient","gradient","soft","rounded","#10b981","#059669","#d1fae5","#065f46","#022c22"],"amber":["light","warm","comfortable","large","soft","soft","solid","soft","rounded","#f59e0b","#d97706","#fef3c7","#78350f","#451a03"],"arkas_light":["light","operational","comfortable","large","soft","gradient","gradient","soft","rounded","#1f63e9","#284393","#eaf2ff","#284393","#1e3578"],"arkas_dark":["dark","professional","comfortable","large","subtle","deep","gradient","quiet","rounded","#22c7d6","#67d6e5","#123542","#0b3a55","#06243a"],"arkas_dark_v2":["dark","professional","comfortable","large","subtle","deep","gradient","quiet","rounded","#1769e8","#1f63e9","#132340","#152b63","#091b46"]};
        let saved = null;
        try {
            saved = localStorage.getItem('spj-theme');
        } catch (error) {
            saved = null;
        }
        const fallback = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        const name = saved && profiles[saved] ? saved : fallback;
        const profile = profiles[name] || profiles.light;
        const root = document.documentElement;
        root.dataset.theme = name;
        root.dataset.themeProfile = name;
        root.dataset.uiAppearance = profile[0];
        root.dataset.uiPersonality = profile[1];
        root.dataset.uiDensity = profile[2];
        root.dataset.uiRadius = profile[3];
        root.dataset.uiShadow = profile[4];
        root.dataset.uiHeader = profile[5];
        root.dataset.uiSidebar = profile[6];
        root.dataset.uiTable = profile[7];
        root.dataset.uiControls = profile[8];
        root.classList.toggle('dark', profile[0] === 'dark');
        root.style.colorScheme = profile[0];
        root.style.setProperty('--theme-accent', profile[9]);
        root.style.setProperty('--theme-accent-strong', profile[10]);
        root.style.setProperty('--theme-accent-soft', profile[11]);
        root.style.setProperty('--theme-sidebar', profile[12]);
        root.style.setProperty('--theme-sidebar-deep', profile[13]);
    })();
</script>
