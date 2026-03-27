import api from './api'

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterPayload {
  name: string
  email: string
  phone?: string
  password: string
  password_confirmation: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export interface VerifyEmailPayload {
  id: string
  hash: string
  expires: string
  signature: string
}

export async function csrf() {
  await api.get('/sanctum/csrf-cookie')
}

export async function login(payload: LoginPayload) {
  await csrf()
  return api.post('/api/auth/login', payload)
}

export async function register(payload: RegisterPayload) {
  await csrf()
  return api.post('/api/auth/register', payload)
}

export async function logout() {
  return api.post('/api/auth/logout')
}

export async function me() {
  return api.get('/api/auth/user')
}

export async function forgotPassword(email: string) {
  return api.post('/api/auth/forgot-password', { email })
}

export async function resetPassword(payload: ResetPasswordPayload) {
  return api.post('/api/auth/reset-password', payload)
}

export async function resendVerification() {
  return api.post('/api/auth/email/verification-notification')
}

export async function verifyEmail(payload: VerifyEmailPayload) {
  return api.get(`/api/auth/email/verify/${payload.id}/${payload.hash}`, {
    params: {
      expires: payload.expires,
      signature: payload.signature,
    },
  })
}