/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './storage/framework/views/*.php',
    ],
    safelist: [
        'publish-modal-open',
        'is-preview-active',
        'blade-nav-active',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', 'ui-sans-serif', 'system-ui', 'sans-serif'],
            },
            colors: {
                brand: 'var(--menu-color, #d83a7c)',
            },
            boxShadow: {
                soft: '0 4px 24px -4px rgb(15 23 42 / 0.07)',
                lift: '0 12px 40px -12px rgb(15 23 42 / 0.12)',
            },
            animation: {
                'fade-in': 'fadeIn 0.2s ease-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
            },
        },
    },
    plugins: [require('@tailwindcss/forms')],
};
