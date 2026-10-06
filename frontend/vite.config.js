import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// 开发态:/api 代理到本地 PHP 开发服务器(php -S 127.0.0.1:8080)
// host: true = 局域网可访问(队友手机连同一 WiFi 打开 http://你的IP:5173 即可)
export default defineConfig({
  plugins: [vue()],
  server: {
    host: true,
    allowedHosts: true, // 允许经 cpolar 隧道等任意域名访问(隧道域名每次重启会变)
    proxy: {
      '/api': { target: 'http://127.0.0.1:8080', changeOrigin: true },
    },
  },
})
