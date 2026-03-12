import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
    'process.env': '{}',
  },
  plugins: [react()],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    sourcemap: true,
    lib: {
      entry: resolve(__dirname, 'src/main.tsx'),
      name: 'PostCalendarApp',
      formats: ['iife'],
      fileName: () => 'post-calendar.js',
      cssFileName: 'post-calendar',
    },
    rollupOptions: {
      output: {
        intro: "var process = globalThis.process || { env: { NODE_ENV: 'production' } };",
      },
    },
  },
});
