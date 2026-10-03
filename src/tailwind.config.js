import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/spa/**/*.{vue,ts,js}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                background: 'hsl(var(--background))',
                foreground: 'hsl(var(--foreground))',
                card: {
                    DEFAULT: 'hsl(var(--card))',
                    foreground: 'hsl(var(--card-foreground))',
                },
                popover: {
                    DEFAULT: 'hsl(var(--popover))',
                    foreground: 'hsl(var(--popover-foreground))',
                },
                secondary: {
                    DEFAULT: 'hsl(var(--secondary))',
                    foreground: 'hsl(var(--secondary-foreground))',
                },
                muted: {
                    DEFAULT: 'hsl(var(--muted))',
                    foreground: 'hsl(var(--muted-foreground))',
                    light: 'var(--spa-muted-light)',
                },
                accent: {
                    DEFAULT: 'hsl(var(--accent))',
                    foreground: 'hsl(var(--accent-foreground))',
                },
                destructive: {
                    DEFAULT: 'hsl(var(--destructive))',
                    foreground: 'hsl(var(--destructive-foreground))',
                },
                input: 'hsl(var(--input))',
                ring: 'hsl(var(--ring))',
                primary: {
                    DEFAULT: 'hsl(var(--primary))',
                    foreground: 'hsl(var(--primary-foreground))',
                    hover: 'var(--spa-primary-hover)',
                },
                danger: {
                    DEFAULT: 'var(--spa-danger)',
                    bg: 'var(--spa-danger-bg)',
                },
                success: {
                    DEFAULT: 'var(--spa-success)',
                    bg: 'var(--spa-success-bg)',
                    fg: 'var(--spa-success-fg)',
                },
                warning: {
                    DEFAULT: 'var(--spa-warning)',
                    bg: 'var(--spa-warning-bg)',
                    border: 'var(--spa-warning-border)',
                    fg: 'var(--spa-warning-fg)',
                },
                surface: {
                    DEFAULT: 'var(--spa-surface)',
                    alt: 'var(--spa-surface-alt)',
                },
                border: {
                    DEFAULT: 'hsl(var(--border))',
                    strong: 'var(--spa-border-strong)',
                },
                fg: {
                    DEFAULT: 'var(--spa-fg)',
                    secondary: 'var(--spa-fg-secondary)',
                },
            },
            borderRadius: {
                spa: 'var(--spa-radius)',
                'spa-lg': 'var(--spa-radius-lg)',
            },
        },
    },

    plugins: [forms],
};
