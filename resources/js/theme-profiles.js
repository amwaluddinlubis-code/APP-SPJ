import '../css/theme-system.css';

const profile = (config) => Object.freeze({
    ...config,
    palette: Object.freeze(config.palette),
});

export const themeProfiles = Object.freeze({
    light: profile({ label: 'Light Modern', appearance: 'light', personality: 'modern', density: 'comfortable', radius: 'rounded', shadow: 'soft', header: 'gradient', sidebar: 'gradient', table: 'soft', controls: 'rounded', palette: { accent: '#14b8a6', accentStrong: '#0f766e', accentSoft: '#ccfbf1', sidebar: '#134e4a', sidebarDeep: '#042f2e' } }),
    dark: profile({ label: 'Dark Professional', appearance: 'dark', personality: 'professional', density: 'compact', radius: 'medium', shadow: 'subtle', header: 'deep', sidebar: 'deep', table: 'quiet', controls: 'medium', palette: { accent: '#64748b', accentStrong: '#94a3b8', accentSoft: '#1f2937', sidebar: '#111827', sidebarDeep: '#030712' } }),
    slate: profile({ label: 'Slate Minimal', appearance: 'light', personality: 'minimal', density: 'compact', radius: 'medium', shadow: 'flat', header: 'flat', sidebar: 'solid', table: 'minimal', controls: 'medium', palette: { accent: '#64748b', accentStrong: '#475569', accentSoft: '#e2e8f0', sidebar: '#334155', sidebarDeep: '#0f172a' } }),
    gray: profile({ label: 'Gray Office', appearance: 'light', personality: 'office', density: 'compact', radius: 'small', shadow: 'subtle', header: 'solid', sidebar: 'solid', table: 'dense', controls: 'small', palette: { accent: '#6b7280', accentStrong: '#4b5563', accentSoft: '#e5e7eb', sidebar: '#374151', sidebarDeep: '#111827' } }),
    zinc: profile({ label: 'Zinc Studio', appearance: 'light', personality: 'studio', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'split', sidebar: 'solid', table: 'soft', controls: 'rounded', palette: { accent: '#71717a', accentStrong: '#52525b', accentSoft: '#e4e4e7', sidebar: '#3f3f46', sidebarDeep: '#18181b' } }),
    neutral: profile({ label: 'Neutral Classic', appearance: 'light', personality: 'classic', density: 'compact', radius: 'small', shadow: 'flat', header: 'classic', sidebar: 'solid', table: 'lined', controls: 'small', palette: { accent: '#737373', accentStrong: '#525252', accentSoft: '#e5e5e5', sidebar: '#404040', sidebarDeep: '#171717' } }),
    stone: profile({ label: 'Stone Warm', appearance: 'light', personality: 'warm', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'soft', sidebar: 'soft', table: 'soft', controls: 'rounded', palette: { accent: '#78716c', accentStrong: '#57534e', accentSoft: '#e7e5e4', sidebar: '#44403c', sidebarDeep: '#1c1917' } }),
    red: profile({ label: 'Red Command', appearance: 'light', personality: 'command', density: 'compact', radius: 'medium', shadow: 'strong', header: 'bold', sidebar: 'deep', table: 'lined', controls: 'medium', palette: { accent: '#ef4444', accentStrong: '#dc2626', accentSoft: '#fee2e2', sidebar: '#7f1d1d', sidebarDeep: '#450a0a' } }),
    orange: profile({ label: 'Orange Energy', appearance: 'light', personality: 'energetic', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'bold', sidebar: 'gradient', table: 'soft', controls: 'rounded', palette: { accent: '#f97316', accentStrong: '#ea580c', accentSoft: '#ffedd5', sidebar: '#7c2d12', sidebarDeep: '#431407' } }),
    yellow: profile({ label: 'Yellow Bright', appearance: 'light', personality: 'bright', density: 'spacious', radius: 'large', shadow: 'soft', header: 'soft', sidebar: 'soft', table: 'airy', controls: 'rounded', palette: { accent: '#eab308', accentStrong: '#ca8a04', accentSoft: '#fef9c3', sidebar: '#713f12', sidebarDeep: '#422006' } }),
    lime: profile({ label: 'Lime Fresh', appearance: 'light', personality: 'fresh', density: 'spacious', radius: 'large', shadow: 'soft', header: 'split', sidebar: 'gradient', table: 'airy', controls: 'pill', palette: { accent: '#84cc16', accentStrong: '#65a30d', accentSoft: '#ecfccb', sidebar: '#365314', sidebarDeep: '#1a2e05' } }),
    green: profile({ label: 'Green School', appearance: 'light', personality: 'school', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'gradient', sidebar: 'gradient', table: 'soft', controls: 'rounded', palette: { accent: '#22c55e', accentStrong: '#16a34a', accentSoft: '#dcfce7', sidebar: '#166534', sidebarDeep: '#052e16' } }),
    teal: profile({ label: 'Teal Operational', appearance: 'light', personality: 'operational', density: 'comfortable', radius: 'medium', shadow: 'subtle', header: 'gradient', sidebar: 'gradient', table: 'lined', controls: 'medium', palette: { accent: '#14b8a6', accentStrong: '#0f766e', accentSoft: '#ccfbf1', sidebar: '#134e4a', sidebarDeep: '#042f2e' } }),
    sky: profile({ label: 'Sky Airy', appearance: 'light', personality: 'airy', density: 'spacious', radius: 'large', shadow: 'soft', header: 'glass', sidebar: 'soft', table: 'airy', controls: 'rounded', palette: { accent: '#0ea5e9', accentStrong: '#0284c7', accentSoft: '#e0f2fe', sidebar: '#0c4a6e', sidebarDeep: '#082f49' } }),
    blue: profile({ label: 'Blue Modern', appearance: 'light', personality: 'modern', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'glass', sidebar: 'gradient', table: 'soft', controls: 'rounded', palette: { accent: '#3b82f6', accentStrong: '#2563eb', accentSoft: '#dbeafe', sidebar: '#1e3a8a', sidebarDeep: '#172554' } }),
    indigo: profile({ label: 'Indigo Executive', appearance: 'light', personality: 'executive', density: 'compact', radius: 'medium', shadow: 'strong', header: 'bold', sidebar: 'deep', table: 'lined', controls: 'medium', palette: { accent: '#6366f1', accentStrong: '#4f46e5', accentSoft: '#e0e7ff', sidebar: '#312e81', sidebarDeep: '#1e1b4b' } }),
    violet: profile({ label: 'Violet Premium', appearance: 'light', personality: 'premium', density: 'comfortable', radius: 'xl', shadow: 'floating', header: 'bold', sidebar: 'gradient', table: 'soft', controls: 'rounded', palette: { accent: '#8b5cf6', accentStrong: '#7c3aed', accentSoft: '#ede9fe', sidebar: '#4c1d95', sidebarDeep: '#2e1065' } }),
    purple: profile({ label: 'Purple Creative', appearance: 'light', personality: 'creative', density: 'spacious', radius: 'xl', shadow: 'floating', header: 'split', sidebar: 'gradient', table: 'airy', controls: 'pill', palette: { accent: '#a855f7', accentStrong: '#9333ea', accentSoft: '#f3e8ff', sidebar: '#581c87', sidebarDeep: '#3b0764' } }),
    fuchsia: profile({ label: 'Fuchsia Expressive', appearance: 'light', personality: 'expressive', density: 'comfortable', radius: 'xl', shadow: 'floating', header: 'bold', sidebar: 'gradient', table: 'soft', controls: 'pill', palette: { accent: '#d946ef', accentStrong: '#c026d3', accentSoft: '#fae8ff', sidebar: '#701a75', sidebarDeep: '#4a044e' } }),
    pink: profile({ label: 'Pink Soft', appearance: 'light', personality: 'soft', density: 'spacious', radius: 'xl', shadow: 'soft', header: 'soft', sidebar: 'soft', table: 'airy', controls: 'rounded', palette: { accent: '#ec4899', accentStrong: '#db2777', accentSoft: '#fce7f3', sidebar: '#831843', sidebarDeep: '#500724' } }),
    rose: profile({ label: 'Rose Soft', appearance: 'light', personality: 'soft', density: 'comfortable', radius: 'xl', shadow: 'soft', header: 'soft', sidebar: 'gradient', table: 'soft', controls: 'rounded', palette: { accent: '#f43f5e', accentStrong: '#e11d48', accentSoft: '#ffe4e6', sidebar: '#881337', sidebarDeep: '#4c0519' } }),
    cyan: profile({ label: 'Cyan Tech', appearance: 'light', personality: 'tech', density: 'compact', radius: 'small', shadow: 'subtle', header: 'split', sidebar: 'deep', table: 'lined', controls: 'small', palette: { accent: '#06b6d4', accentStrong: '#0891b2', accentSoft: '#cffafe', sidebar: '#155e75', sidebarDeep: '#083344' } }),
    emerald: profile({ label: 'Emerald School', appearance: 'light', personality: 'school', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'gradient', sidebar: 'gradient', table: 'soft', controls: 'rounded', palette: { accent: '#10b981', accentStrong: '#059669', accentSoft: '#d1fae5', sidebar: '#065f46', sidebarDeep: '#022c22' } }),
    amber: profile({ label: 'Amber Warm', appearance: 'light', personality: 'warm', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'soft', sidebar: 'solid', table: 'soft', controls: 'rounded', palette: { accent: '#f59e0b', accentStrong: '#d97706', accentSoft: '#fef3c7', sidebar: '#78350f', sidebarDeep: '#451a03' } }),
});

export const resolveThemeProfile = (theme) => themeProfiles[theme] ?? themeProfiles.light;

const applyPalette = (palette, root) => {
    root.style.setProperty('--theme-accent', palette.accent);
    root.style.setProperty('--theme-accent-strong', palette.accentStrong);
    root.style.setProperty('--theme-accent-soft', palette.accentSoft);
    root.style.setProperty('--theme-sidebar', palette.sidebar);
    root.style.setProperty('--theme-sidebar-deep', palette.sidebarDeep);
};

export const applyThemeProfile = (theme, root = document.documentElement) => {
    const selectedTheme = themeProfiles[theme] ? theme : 'light';
    const selected = resolveThemeProfile(selectedTheme);

    root.dataset.theme = selectedTheme;
    root.dataset.themeProfile = selectedTheme;
    root.dataset.uiAppearance = selected.appearance;
    root.dataset.uiPersonality = selected.personality;
    root.dataset.uiDensity = selected.density;
    root.dataset.uiRadius = selected.radius;
    root.dataset.uiShadow = selected.shadow;
    root.dataset.uiHeader = selected.header;
    root.dataset.uiSidebar = selected.sidebar;
    root.dataset.uiTable = selected.table;
    root.dataset.uiControls = selected.controls;
    root.classList.toggle('dark', selected.appearance === 'dark');
    root.style.colorScheme = selected.appearance;
    applyPalette(selected.palette, root);
    localStorage.setItem('spj-theme', selectedTheme);

    window.dispatchEvent(new CustomEvent('app-theme-profile-changed', {
        detail: { theme: selectedTheme, profile: selected },
    }));

    return selected;
};

export const renderThemeSelector = (select, selectedTheme) => {
    const options = Object.entries(themeProfiles).map(([value, item]) => {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = item.label;
        option.selected = value === selectedTheme;
        return option;
    });

    select.replaceChildren(...options);
};

export const initializeThemeProfiles = () => {
    const root = document.documentElement;
    const storedTheme = localStorage.getItem('spj-theme') || root.dataset.theme || 'light';
    const selectedTheme = themeProfiles[storedTheme] ? storedTheme : 'light';

    applyThemeProfile(selectedTheme, root);

    document.querySelectorAll('[data-theme-selector]').forEach((select) => {
        if (!(select instanceof HTMLSelectElement)) return;

        renderThemeSelector(select, selectedTheme);

        if (select.dataset.profileInitialized === 'true') return;

        select.dataset.profileInitialized = 'true';
        select.addEventListener('change', () => applyThemeProfile(select.value, root));
    });
};

const bootThemeProfiles = () => {
    initializeThemeProfiles();
    document.addEventListener('livewire:navigated', initializeThemeProfiles);
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootThemeProfiles, { once: true });
} else {
    bootThemeProfiles();
}
