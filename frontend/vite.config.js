import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [react()],
  server: {
    // Alle verzoeken naar /api worden doorgestuurd naar de PHP-server.
    // Voor de browser is het dan hetzelfde adres, dus de sessiecookie werkt en er is geen CORS nodig.
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
})
