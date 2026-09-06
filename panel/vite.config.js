import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/js/main.tsx'],
      refresh: true,
    }),
    react(),
  ],
  build: {
    target: 'es2020',
    sourcemap: false,
    cssCodeSplit: true,
    modulePreload: { polyfill: false },
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (!id.includes('node_modules')) return undefined;
          if (id.includes('/react/') || id.includes('/react-dom/') || id.includes('/scheduler/')) return 'react-core';
          if (id.includes('/axios/')) return 'http-client';
          return 'vendor';
        },
      },
    },
  },
});
