import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    laravel({
      input: [
        'resources/css/app.css',
        'resources/js/app.js',
      ],
      refresh: true,
    }),
    vue(),
    tailwindcss(),
  ],

  server: {
  host: true,
  port: 5173,
  strictPort: true,
  cors: true, 

  // ✅ IMPORTANT: URL publique Codespaces (sinon public/hot devient localhost / 0.0.0.0)
  origin: 'https://bug-free-memory-r4vgp7gw9vgqfp6v6-5173.app.github.dev',

  // ✅ HMR via HTTPS (Codespaces)
  hmr: {
    protocol: 'wss',
    host: 'bug-free-memory-r4vgp7gw9vgqfp6v6-5173.app.github.dev',
    clientPort: 443,
  },

  watch: {
    usePolling: true,
  },
},

})
