import { v4wp } from "@kucrut/vite-for-wp";
import react from "@vitejs/plugin-react";
import path from "path";
import externalGlobals from "rollup-plugin-external-globals";

// React is not bundled: the admin uses the copy WordPress loads (script handles react, react-dom and
// react-jsx-runtime, see includes/Assets/Admin.php). WordPress.org requires this (Plugin Check, TX-523).
// The import "react" in our code and in the libraries becomes the global that WordPress defines.
const wpGlobals = {
  react: "React",
  "react-dom": "ReactDOM",
  "react-dom/client": "ReactDOM",
  "react/jsx-runtime": "ReactJSXRuntime",
};

export default {
  plugins: [
    v4wp({
      input: "src/admin/main.jsx",
      outDir: "assets/admin/dist",
    }),
    // Production build only: the dev server (npm run dev) still serves its own React.
    {
      name: "terax:wp-react",
      apply: "build",
      config: () => ({
        build: { rollupOptions: { external: Object.keys(wpGlobals), output: { globals: wpGlobals } } },
      }),
    },
    // .mjs is included on purpose: Radix UI and react-router ship ES module files (.mjs) that import "react";
    // without it those imports stay as bare module names, which a browser cannot resolve.
    { ...externalGlobals(wpGlobals, { include: ["**/*.js", "**/*.jsx", "**/*.mjs", "**/*.ts", "**/*.tsx"] }), apply: "build" },
    react(),
  ],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src"),
    },
  },
};
