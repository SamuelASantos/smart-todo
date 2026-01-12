/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./public/**/*.php", "./src/**/*.php", "./*.php"],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', "sans-serif"],
      },
    },
  },
  plugins: [],
};
