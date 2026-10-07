import { ref } from 'vue';

/**
 * Colour-scheme (light / dark) toggle.
 *
 * Tailwind is in `class` mode (see tailwind.config.js), so the active theme is
 * driven purely by the presence of the `dark` class on <html>. The INITIAL class
 * is set by an inline script in resources/views/app.blade.php (runs before paint,
 * so there is no flash of the wrong theme). This module simply mirrors that state
 * and flips it, persisting the user's explicit choice to localStorage.
 *
 * `isDark` is a module-level singleton ref, so the topbar toggle, the (optional)
 * profile setting and any other consumer all stay in lock-step automatically.
 */
const STORAGE_KEY = 'fdh.theme';

function currentlyDark() {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('dark');
}

const isDark = ref(currentlyDark());

function apply(dark) {
    isDark.value = dark;
    if (typeof document !== 'undefined') {
        document.documentElement.classList.toggle('dark', dark);
    }
    try {
        localStorage.setItem(STORAGE_KEY, dark ? 'dark' : 'light');
    } catch (e) {
        /* localStorage unavailable (private mode) - theme still applies for this session. */
    }
}

export function useTheme() {
    return {
        isDark,
        toggle: () => apply(!isDark.value),
        setDark: (v) => apply(!!v),
    };
}
