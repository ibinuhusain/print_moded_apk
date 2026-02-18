import { defineConfig } from 'vite';

export default defineConfig({
  root: 'src',
  build: {
    outDir: '../dist',
    minify: 'esbuild',
    emptyOutDir: true,
  },
  server: {
    port: 3000,
    strictPort: true,
    open: true,
  },
  define: {
    'process.env': process.env
  }
});