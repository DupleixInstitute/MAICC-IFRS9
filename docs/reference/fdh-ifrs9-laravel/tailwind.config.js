import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Toggle-driven dark mode: the theme follows the `dark` class on <html>
    // (set pre-paint in app.blade.php, flipped by resources/js/composables/useTheme.js).
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        // Also scan .js: nav.js holds the sidebar accent class tokens
        // (text-teal-300, bg-*-400/10, ...). Without this they are purged and the
        // active menu item/group falls back to inherited text - invisible on the
        // dark sidebar in light mode.
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
