/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./*.html', 'tailwind-input.css'],
  theme: {
    extend: {
      colors: {
        cream: '#F5F1EA',
        parchment: '#EBE5D8',
        stone: '#DCD5C7',
        ink: '#0F0F0F',
        graphite: '#3A3A38',
        sage: '#5C6F66',
        forest: '#1F3D36',
        forest_deep: '#13261F',
        champagne: '#C4A572',
        champagne_d: '#9C8252',
        rose: '#A85A4A',
        leaf: '#6B8E5A',
      },
      fontFamily: {
        display: ['Fraunces', 'serif'],
        body: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
      fontSize: {
        'display-2xl': ['10rem', { lineHeight: '0.85', letterSpacing: '-0.03em' }],
        'display-xl': ['7.5rem', { lineHeight: '0.86', letterSpacing: '-0.025em' }],
        'display-lg': ['5rem', { lineHeight: '0.92', letterSpacing: '-0.02em' }],
        'display-md': ['3.5rem', { lineHeight: '0.98', letterSpacing: '-0.015em' }],
        'display-sm': ['2.25rem', { lineHeight: '1.05', letterSpacing: '-0.01em' }],
        'body-lg': ['1.125rem', { lineHeight: '1.55' }],
        'body': ['0.9375rem', { lineHeight: '1.5' }],
        'body-sm': ['0.8125rem', { lineHeight: '1.4' }],
        'caption': ['0.6875rem', { lineHeight: '1.3', letterSpacing: '0.06em' }],
      },
      transitionTimingFunction: { editorial: 'cubic-bezier(0.2, 0.8, 0.2, 1)' },
      transitionDuration: { '480': '480ms', '720': '720ms' },
    },
  },
  plugins: [],
};
