import axios from 'axios'

const baseURL = (import.meta.env.VITE_API_BASE_URL || window.location.origin).replace(/\/$/, '')

const api = axios.create({
  baseURL,
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
})

export default api