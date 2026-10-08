import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Light, dark, or follow the device (spec v4 section 11.5).
 *
 * Tailwind runs in class mode, so the whole system is dark when the `dark`
 * class is on <html> and light when it is not; nothing else decides the
 * theme. The first paint is set by the four-line script in app.blade.php,
 * which reads `maiic.theme` from the browser before the page renders; this
 * composable mirrors that state in one module-level ref, so the top-bar
 * switch, the profile page and any other consumer stay in step. The choice
 * is kept in the browser (so the first paint is right) and on the user's
 * record (so it follows the user to another machine).
 */
const STORAGE_KEY = 'maiic.theme';
export const THEMES = ['light', 'dark', 'system'];

function stored() {
    try {
        const v = localStorage.getItem(STORAGE_KEY);
        return THEMES.includes(v) ? v : 'system';
    } catch (e) {
        return 'system';
    }
}

function deviceDark() {
    return typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
}

const preference = ref(stored());
const isDark = ref(typeof document !== 'undefined' && document.documentElement.classList.contains('dark'));

function apply(pref, tellServer) {
    preference.value = pref;
    const dark = pref === 'dark' || (pref === 'system' && deviceDark());
    isDark.value = dark;
    if (typeof document !== 'undefined') document.documentElement.classList.toggle('dark', dark);
    try {
        localStorage.setItem(STORAGE_KEY, pref);
    } catch (e) {
        /* storage unavailable: the theme still applies for the session */
    }
    if (tellServer) {
        try {
            router.post(route('profile.theme'), { theme: pref }, { preserveScroll: true, preserveState: true, only: [] });
        } catch (e) {
            /* no route on this page (login): the browser value stands */
        }
    }
}

if (typeof window !== 'undefined' && window.matchMedia) {
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (preference.value === 'system') apply('system', false);
    });
}

export function useTheme() {
    return {
        preference,
        isDark,
        /** Cycle light -> dark -> system -> light. */
        cycle: () => apply(THEMES[(THEMES.indexOf(preference.value) + 1) % THEMES.length], true),
        set: (pref) => apply(THEMES.includes(pref) ? pref : 'system', true),
        /** On login the server's value is written to the browser. */
        adoptFromServer: (pref) => { if (THEMES.includes(pref) && pref !== stored()) apply(pref, false); },
        label: () => ({ light: 'Light', dark: 'Dark', system: 'Follow the device' })[preference.value],
    };
}
