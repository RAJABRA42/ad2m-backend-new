import axios from 'axios'

window.axios = axios

// ✅ Si tu ouvres via http://localhost (Laravel), pas besoin de baseURL.
// ✅ Si tu ouvres via Vite (5174) un jour, ça reste OK si VITE_BACKEND_URL est défini.
const BACKEND_URL = import.meta.env.VITE_BACKEND_URL || ''

if (BACKEND_URL) {
  window.axios.defaults.baseURL = BACKEND_URL
}

// ✅ IMPORTANT : permet d’envoyer/recevoir cookies (session + XSRF) même si jamais tu passes en cross-origin
window.axios.defaults.withCredentials = true

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
window.axios.defaults.headers.common['Accept'] = 'application/json'

// ✅ Logs utiles (tu verras 419/401/500 clairement)
window.axios.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error?.response?.status
    console.error('❌ API error:', status, error?.response?.data || error)
    return Promise.reject(error)
  }
)
