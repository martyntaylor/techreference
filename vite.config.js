import { defineConfig } from "vite";
import laravel from "laravel-vite-plugin";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig({
    plugins: [
        laravel({
            input: ["resources/css/app.css", "resources/js/app.js"],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        // Enable code splitting for production builds
        rollupOptions: {
            output: {
                // Split vendor code into separate chunk
                manualChunks(id) {
                    // Separate vendor dependencies (node_modules)
                    if (id.includes("node_modules")) {
                        // AlpineJS in its own chunk
                        if (id.includes("alpinejs")) {
                            return "alpine";
                        }
                        // Axios in its own chunk
                        if (id.includes("axios")) {
                            return "axios";
                        }
                        // Other vendor code
                        return "vendor";
                    }
                },
            },
        },
        // Chunk size warning limit (500kb)
        chunkSizeWarningLimit: 500,
        // Minification options
        minify: "terser",
        terserOptions: {
            compress: {
                drop_console: true, // Remove console.log in production
                drop_debugger: true,
            },
        },
        // CSS code splitting
        cssCodeSplit: true,
    },
    // Optimize dependencies
    optimizeDeps: {
        include: ["alpinejs", "axios"],
    },
});
