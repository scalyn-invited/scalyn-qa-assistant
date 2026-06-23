/** @type {import('tailwindcss').Config} */
module.exports = {
  prefix: 'sqt-',
  content: [
    './templates/**/*.php',
    './assets/js/**/*.js',
    './includes/admin/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        primary: '#4F46E5',
        success: '#10B981',
        warning: '#F59E0B',
        danger: '#EF4444',
        info: '#3B82F6',
      },
    },
  },
  plugins: [],
};
