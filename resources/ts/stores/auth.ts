import { defineStore } from 'pinia'
import * as authApi from '@/services/auth'

type AuthUser = {
  id: number
  name: string
  email: string
  email_verified_at: string | null
  roles?: Array<{ name: string }>
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    initialized: false,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    roleNames: (state) => state.user?.roles?.map(r => r.name) ?? [],
    isAdmin(): boolean {
      return this.roleNames.includes('admin')
    },
    isStaff(): boolean {
      return this.roleNames.includes('staff')
    },
    isClient(): boolean {
      return this.roleNames.includes('client')
    },
    isVerified: (state) => !!state.user?.email_verified_at,
  },

  actions: {
    async fetchUser() {
      try {
        const { data } = await authApi.me()
        this.user = data.user
      } catch {
        this.user = null
      } finally {
        this.initialized = true
      }
    },

    async login(payload: { email: string; password: string; remember?: boolean }) {
      this.loading = true
      try {
        await authApi.login(payload)
        await this.fetchUser()
      } finally {
        this.loading = false
      }
    },

    async register(payload: {
      name: string
      email: string
      phone?: string
      password: string
      password_confirmation: string
    }) {
      this.loading = true
      try {
        await authApi.register(payload)
        await this.fetchUser()
      } finally {
        this.loading = false
      }
    },

    async logout() {
      await authApi.logout()
      this.user = null
      this.initialized = true
    },
  },
})