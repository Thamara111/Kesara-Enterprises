/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./*.php", "./layouts/**/*.php", "./admin/**/*.php", "./src/**/*.{html,js,php}"],
  safelist: [
    { pattern: /^md:(hidden|flex)$/ },
    { pattern: /^lg:(hidden|flex|pl-72)$/ },
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ["Geograph", "sans-serif"],
        serif: ["Self Modern", "serif"],
      },
      colors: {
        brand: {
          DEFAULT: "#0F6E56",
          light: "#E1F5EE",
          dark: "#0c5a46",
        },
        gray: {
          950: "#030712",
        },
      },
      maxWidth: {
        '8xl': '88rem',
      },
    },
  },
  plugins: [],
}

