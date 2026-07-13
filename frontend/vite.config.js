import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    port: 3000,
    proxy: {
      '/admin': 'http://localhost:8084',   // admin 后端
      '/api': 'https://sofun.online',      // think1 公开 API
    }
  },
  build: {
    // 直接打到后端 public，上传宝塔时网站根目录指向 backend/public
    outDir: '../backend/public',
    emptyOutDir: false, // 保留 index.php / nginx.htaccess 等 PHP 入口文件
  }
})
