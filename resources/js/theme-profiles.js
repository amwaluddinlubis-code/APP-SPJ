import '../css/theme-profiles.css';
import '../css/theme-profile-components.css';
import '../css/comfortable-text.css';
import '../css/theme-soft-surfaces.css';
import '../css/token-native-components.css';

const profile = (config) => Object.freeze(config);

export const themeProfiles = Object.freeze({
    light: profile({ label: 'Light Modern', appearance: 'light', personality: 'modern', density: 'comfortable', radius: 'rounded', shadow: 'soft', header: 'gradient', sidebar: 'gradient', table: 'soft', controls: 'rounded' }),
    dark: profile({ label: 'Dark Professional', appearance: 'dark', personality: 'professional', density: 'compact', radius: 'medium', shadow: 'subtle', header: 'deep', sidebar: 'deep', table: 'quiet', controls: 'medium' }),
    slate: profile({ label: 'Slate Minimal', appearance: 'light', personality: 'minimal', density: 'compact', radius: 'medium', shadow: 'flat', header: 'flat', sidebar: 'solid', table: 'minimal', controls: 'medium' }),
    gray: profile({ label: 'Gray Office', appearance: 'light', personality: 'office', density: 'compact', radius: 'small', shadow: 'subtle', header: 'solid', sidebar: 'solid', table: 'dense', controls: 'small' }),
    zinc: profile({ label: 'Zinc Studio', appearance: 'light', personality: 'studio', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'split', sidebar: 'solid', table: 'soft', controls: 'rounded' }),
    neutral: profile({ label: 'Neutral Classic', appearance: 'light', personality: 'classic', density: 'compact', radius: 'small', shadow: 'flat', header: 'classic', sidebar: 'solid', table: 'lined', controls: 'small' }),
    stone: profile({ label: 'Stone Warm', appearance: 'light', personality: 'warm', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'soft', sidebar: 'soft', table: 'soft', controls: 'rounded' }),
    red: profile({ label: 'Red Command', appearance: 'light', personality: 'command', density: 'compact', radius: 'medium', shadow: 'strong', header: 'bold', sidebar: 'deep', table: 'lined', controls: 'medium' }),
    orange: profile({ label: 'Orange Energy', appearance: 'light', personality: 'energetic', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'bold', sidebar: 'gradient', table: 'soft', controls: 'rounded' }),
    yellow: profile({ label: 'Yellow Bright', appearance: 'light', personality: 'bright', density: 'spacious', radius: 'large', shadow: 'soft', header: 'soft', sidebar: 'soft', table: 'airy', controls: 'rounded' }),
    lime: profile({ label: 'Lime Fresh', appearance: 'light', personality: 'fresh', density: 'spacious', radius: 'large', shadow: 'soft', header: 'split', sidebar: 'gradient', table: 'airy', controls: 'pill' }),
    green: profile({ label: 'Green School', appearance: 'light', personality: 'school', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'gradient', sidebar: 'gradient', table: 'soft', controls: 'rounded' }),
    teal: profile({ label: 'Teal Operational', appearance: 'light', personality: 'operational', density: 'comfortable', radius: 'medium', shadow: 'subtle', header: 'gradient', sidebar: 'gradient', table: 'lined', controls: 'medium' }),
    sky: profile({ label: 'Sky Airy', appearance: 'light', personality: 'airy', density: 'spacious', radius: 'large', shadow: 'soft', header: 'glass', sidebar: 'soft', table: 'airy', controls: 'rounded' }),
    blue: profile({ label: 'Blue Modern', appearance: 'light', personality: 'modern', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'glass', sidebar: 'gradient', table: 'soft', controls: 'rounded' }),
    indigo: profile({ label: 'Indigo Executive', appearance: 'light', personality: 'executive', density: 'compact', radius: 'medium', shadow: 'strong', header: 'bold', sidebar: 'deep', table: 'lined', controls: 'medium' }),
    violet: profile({ label: 'Violet Premium', appearance: 'light', personality: 'premium', density: 'comfortable', radius: 'xl', shadow: 'floating', header: 'bold', sidebar: 'gradient', table: 'soft', controls: 'rounded' }),
    purple: profile({ label: 'Purple Creative', appearance: 'light', personality: 'creative', density: 'spacious', radius: 'xl', shadow: 'floating', header: 'split', sidebar: 'gradient', table: 'airy', controls: 'pill' }),
    fuchsia: profile({ label: 'Fuchsia Expressive', appearance: 'light', personality: 'expressive', density: 'comfortable', radius: 'xl', shadow: 'floating', header: 'bold', sidebar: 'gradient', table: 'soft', controls: 'pill' }),
    pink: profile({ label: 'Pink Soft', appearance: 'light', personality: 'soft', density: 'spacious', radius: 'xl', shadow: 'soft', header: 'soft', sidebar: 'soft', table: 'airy', controls: 'rounded' }),
    rose: profile({ label: 'Rose Soft', appearance: 'light', personality: 'soft', density: 'comfortable', radius: 'xl', shadow: 'soft', header: 'soft', sidebar: 'gradient', table: 'soft', controls: 'rounded' }),
    cyan: profile({ label: 'Cyan Tech', appearance: 'light', personality: 'tech', density: 'compact', radius: 'small', shadow: 'subtle', header: 'split', sidebar: 'deep', table: 'lined', controls: 'small' }),
    emerald: profile({ label: 'Emerald School', appearance: 'light', personality: 'school', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'gradient', sidebar: 'gradient', table: 'soft', controls: 'rounded' }),
    amber: profile({ label: 'Amber Warm', appearance: 'light', personality: 'warm', density: 'comfortable', radius: 'large', shadow: 'soft', header: 'soft', sidebar: 'solid', table: 'soft', controls: 'rounded' }),
});

export const resolveThemeProfile = (theme) => themeProfiles[theme] ?? themeProfiles.light;

export const applyThemeProfile = (theme, root = document.documentElement) => {
    const selected = resolveThemeProfile(theme);

    root.dataset.themeProfile = themeProfiles[theme] ? theme : 'light';
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

    window.dispatchEvent(new CustomEvent('app-theme-profile-changed', {
        detail: { theme, profile: selected },
    }));

    return selected;
};

export const initializeThemeProfiles = () => {
    const root = document.documentElement;
    const select = document.getElementById('theme-select');
    const storedTheme = localStorage.getItem('spj-theme') || root.dataset.theme || 'light';

    applyThemeProfile(storedTheme, root);

    if (!select || select.dataset.profileInitialized === 'true') return;

    Object.entries(themeProfiles).forEach(([value, item]) => {
        const option = select.querySelector(`option[value="${value}"]`);
        if (option) option.textContent = item.label;
    });

    select.value = themeProfiles[storedTheme] ? storedTheme : 'light';
    select.dataset.profileInitialized = 'true';
    select.addEventListener('change', () => applyThemeProfile(select.value, root));
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
