import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const source = fs.readFileSync(new URL('../resources/js/theme-profiles.js', import.meta.url), 'utf8');
const representativeThemes = ['dark', 'slate', 'yellow', 'indigo', 'violet'];
const minimumContrast = 4.5;
const projectRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const toRgb = (hex) => {
    const value = hex.replace('#', '');
    return [0, 2, 4].map((offset) => Number.parseInt(value.slice(offset, offset + 2), 16) / 255);
};

const luminance = (hex) => {
    const [red, green, blue] = toRgb(hex).map((channel) => (
        channel <= 0.04045
            ? channel / 12.92
            : ((channel + 0.055) / 1.055) ** 2.4
    ));

    return (0.2126 * red) + (0.7152 * green) + (0.0722 * blue);
};

const contrast = (first, second) => {
    const brighter = Math.max(luminance(first), luminance(second));
    const darker = Math.min(luminance(first), luminance(second));

    return (brighter + 0.05) / (darker + 0.05);
};

const extractTheme = (theme) => {
    const expression = new RegExp(
        `${theme}:\\s*profile\\(\\{[\\s\\S]*?appearance:\\s*'([^']+)'[\\s\\S]*?header:\\s*'([^']+)'[\\s\\S]*?palette:\\s*\\{\\s*accent:\\s*'(#[0-9a-fA-F]{6})',\\s*accentStrong:\\s*'(#[0-9a-fA-F]{6})',\\s*accentSoft:\\s*'(#[0-9a-fA-F]{6})',\\s*sidebar:\\s*'(#[0-9a-fA-F]{6})',\\s*sidebarDeep:\\s*'(#[0-9a-fA-F]{6})'\\s*\\}\\s*\\}\\)`,
    );
    const match = source.match(expression);

    if (!match) {
        throw new Error(`Theme registry entry not found: ${theme}`);
    }

    return {
        appearance: match[1],
        header: match[2],
        accent: match[3],
        accentStrong: match[4],
        accentSoft: match[5],
        sidebar: match[6],
        sidebarDeep: match[7],
    };
};

const semanticsFor = (theme, tokens) => {
    const neutralLightForeground = ['dark', 'slate', 'gray', 'zinc', 'neutral', 'stone'].includes(theme);
    const strongerAction = ['indigo', 'violet'].includes(theme);

    return {
        actionBackground: strongerAction ? tokens.accentStrong : tokens.accent,
        actionForeground: neutralLightForeground || strongerAction ? '#f8fafc' : '#0f172a',
        contentAccent: theme === 'dark' ? '#cbd5e1' : tokens.sidebar,
        contentSurface: theme === 'dark' ? '#111827' : '#ffffff',
        sidebarText: theme === 'dark' ? '#c3cedb' : '#cbd5e1',
        topbarText: theme === 'dark' ? '#c4cfdd' : '#334155',
        topbarSurface: theme === 'dark' ? '#111827' : '#ffffff',
    };
};

const walkFiles = (directory) => fs.readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const fullPath = path.join(directory, entry.name);

    if (entry.isDirectory()) {
        return walkFiles(fullPath);
    }

    return entry.isFile() ? [fullPath] : [];
});

const findLightSurfaceWhiteTextConflicts = () => {
    const viewsDirectory = path.join(projectRoot, 'resources', 'views');
    const lightSurfaces = new Set(['bg-white', 'bg-white/80', 'bg-white/90', 'bg-white/95', 'bg-slate-50', 'bg-gray-50', 'bg-zinc-50']);
    const conflicts = [];

    for (const file of walkFiles(viewsDirectory).filter((item) => item.endsWith('.blade.php'))) {
        const contents = fs.readFileSync(file, 'utf8');
        const tagPattern = /<(a|button)\b[^>]*\bclass\s*=\s*["']([^"']*)["'][^>]*>/gi;

        for (const match of contents.matchAll(tagPattern)) {
            const classes = match[2].split(/\s+/).filter(Boolean);
            const hasLightSurface = classes.some((className) => lightSurfaces.has(className));
            const hasWhiteText = classes.includes('text-white');

            if (!hasLightSurface || !hasWhiteText) {
                continue;
            }

            const line = contents.slice(0, match.index).split('\n').length;
            conflicts.push(`${path.relative(projectRoot, file)}:${line} <${match[1]}> ${match[2]}`);
        }
    }

    return conflicts;
};

let failed = false;

console.log('Representative theme contrast QA (WCAG AA normal text >= 4.5:1)');
console.log('');

for (const theme of representativeThemes) {
    const tokens = extractTheme(theme);
    const semantics = semanticsFor(theme, tokens);
    const checks = [
        ['primary action', semantics.actionForeground, semantics.actionBackground],
        ['content accent', semantics.contentAccent, semantics.contentSurface],
        ['sidebar navigation', semantics.sidebarText, tokens.sidebar],
        ['topbar text', semantics.topbarText, semantics.topbarSurface],
    ];

    if (tokens.header === 'soft') {
        checks.push(['soft page header', semantics.contentAccent, tokens.accentSoft]);
    }

    console.log(theme.toUpperCase());

    for (const [label, foreground, background] of checks) {
        const ratio = contrast(foreground, background);
        const passes = ratio >= minimumContrast;
        failed ||= !passes;
        console.log(`  ${passes ? 'PASS' : 'FAIL'} ${label.padEnd(20)} ${ratio.toFixed(2)}:1  ${foreground} on ${background}`);
    }

    console.log('');
}

const classConflicts = findLightSurfaceWhiteTextConflicts();
console.log('Interactive class conflict QA');

if (classConflicts.length > 0) {
    failed = true;
    console.log('  FAIL light surface + text-white combinations found:');
    classConflicts.forEach((conflict) => console.log(`    ${conflict}`));
} else {
    console.log('  PASS no bg-white/light-surface + text-white combinations on <a>/<button>.');
}

console.log('');

if (failed) {
    console.error('Theme QA failed.');
    process.exitCode = 1;
} else {
    console.log('All representative theme checks passed.');
}
