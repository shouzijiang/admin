import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 3000,
    proxy: {
      '/admin': 'http://10.3.123.48:8084',   // admin 后端
      '/api': 'https://sofun.online',      // think1 公开 API
    }
  },
  build: {
    outDir: 'dist',
  }
})
