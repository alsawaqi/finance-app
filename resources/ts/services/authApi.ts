import api from './api'

export function me() {
  return api.get<{
    user: {
      id: number
      name: string
      email: string
      phone?: string | null
      email_verified_at: string | null
      roles?: { id?: number; name: string }[]
      permission_names?: string[]
    }
  }>('/api/auth/user')
}

export function login(payload: { email: string; password: string; remember?: boolean }) {
  return api.post('/api/auth/login', payload)
}

export function register(payload: {
  name: string
  email: string
  phone?: string
  password: string
  password_confirmation: string
}) {
  return api.post('/api/auth/register', payload)
}

export function logout() {
  return api.post('/api/auth/logout')
}

export function resendVerification() {
  return api.post('/api/auth/email/verification-notification')
}

export function forgotPassword(payload: { email: string }) {
  return api.post('/api/auth/forgot-password', payload)
}

export function resetPassword(payload: {
  token: string
  email: string
  password: string
  password_confirmation: string
}) {
  return api.post('/api/auth/reset-password', payload)
}

export function verifyEmail(payload: {
  id: string | number
  hash: string
  expires: string
  signature: string
}) {
  return api.get(`/api/auth/email/verify/${payload.id}/${payload.hash}`, {
    params: {
      expires: payload.expires,
      signature: payload.signature,
    },
  })
}