import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig(({ mode }) => {
  const isAdminBuild = mode === 'admin';

  return {
    define: {
      'process.env.NODE_ENV': JSON.stringify('production'),
      'process.env': '{}',
    },
    plugins: [react()],
    build: {
      outDir: 'dist',
      emptyOutDir: !isAdminBuild,
      sourcemap: true,
      lib: {
        entry: resolve(__dirname, isAdminBuild ? 'src/admin.tsx' : 'src/main.tsx'),
        name: isAdminBuild ? 'PostCalendarAdmin' : 'PostCalendarApp',
        formats: ['iife'],
        fileName: () => (isAdminBuild ? 'post-calendar-admin.js' : 'post-calendar.js'),
        cssFileName: isAdminBuild ? 'post-calendar-admin' : 'post-calendar',
      },
      rollupOptions: {
        output: {
          intro: "var process = globalThis.process || { env: { NODE_ENV: 'production' } };",
        },
      },
    },
  };
});
