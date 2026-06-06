import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./index.html", "./src/**/*.{vue,ts}"],
  theme: {
    extend: {
      colors: {
        cream: "#F5F1EA",
        parchment: "#EBE5D8",
        stone: "#DCD5C7",
        ink: "#0F0F0F",
        graphite: "#3A3A38",
        sage: "#5C6F66",
        forest: "#1F3D36",
        "forest-deep": "#13261F",
        champagne: "#C4A572",
        "champagne-d": "#9C8252",
        rose: "#A85A4A",
        leaf: "#6B8E5A",
        porcelain: "#FAFAF7",
        clinical: "#1F5F50",
        amber: "#B98948",
      },
      fontFamily: {
        display: ['"Fraunces"', '"GT Sectra"', '"Tiempos Headline"', '"Playfair Display"', "serif"],
        body: ['"Inter"', '"Söhne"', "system-ui", "sans-serif"],
        mono: ['"JetBrains Mono"', '"IBM Plex Mono"', "monospace"],
      },
      fontSize: {
        "display-2xl": ["10rem", { lineHeight: "0.85", letterSpacing: "-0.03em" }],
        "display-xl": ["7.5rem", { lineHeight: "0.86", letterSpacing: "-0.025em" }],
        "display-lg": ["5rem", { lineHeight: "0.92", letterSpacing: "-0.02em" }],
        "display-md": ["3.5rem", { lineHeight: "0.98", letterSpacing: "-0.015em" }],
        "display-sm": ["2.25rem", { lineHeight: "1.05", letterSpacing: "-0.01em" }],
        "body-lg": ["1.125rem", { lineHeight: "1.55", letterSpacing: "0" }],
        body: ["0.9375rem", { lineHeight: "1.5", letterSpacing: "0" }],
        "body-sm": ["0.8125rem", { lineHeight: "1.4", letterSpacing: "0" }],
        caption: ["0.6875rem", { lineHeight: "1.3", letterSpacing: "0.06em" }],
      },
      spacing: {
        canvas: "96px",
        "canvas-lg": "160px",
        card: "32px",
        "card-lg": "48px",
      },
      transitionTimingFunction: {
        editorial: "cubic-bezier(0.2, 0.8, 0.2, 1)",
      },
      transitionDuration: {
        480: "480ms",
        720: "720ms",
      },
      boxShadow: {
        paper: "0 1px 0 rgba(15,15,15,0.04), 0 18px 48px -24px rgba(15,15,15,0.18)",
        lift: "0 32px 80px -28px rgba(15,15,15,0.32)",
        inset: "inset 0 0 0 1px rgba(15,15,15,0.06)",
      },
    },
  },
};

export default config;
