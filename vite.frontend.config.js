import { v4wp } from "@kucrut/vite-for-wp";
import path from "path";

// Public chat widget: Preact (~5 KB) instead of React to keep visitor pages fast.
export default {
  plugins: [
    v4wp({
      input: "src/frontend/main.jsx",
      outDir: "assets/frontend/dist",
    }),
  ],
  esbuild: {
    jsx: "automatic",
    jsxImportSource: "preact",
  },
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
  css: {
    // Widget CSS is injected into a Shadow DOM, not processed by Tailwind.
    postcss: { plugins: [] },
  },
};
