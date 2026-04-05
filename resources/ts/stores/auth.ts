import { defineStore } from 'pinia'
import * as authApi from '../services/authApi'

type AuthRole = {
  id?: number
  name: string
}

type AuthUser = {
  id: number
  name: string
  email: string
  phone?: string | null
  email_verified_at: string | null
  roles?: AuthRole[]
  permission_names?: string[]
  mailbox_settings?: {
    sender_email?: string | null
    sender_name?: string | null
    smtp_username?: string | null
    smtp_enabled?: boolean
    smtp_verified_at?: string | null
    has_smtp_password?: boolean
    smtp_last_error?: string | null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as AuthUser | null,
    initialized: false,
    loading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
    roleNames: (state) => state.user?.roles?.map((role) => role.name) ?? [],
    permissionNames: (state) => state.user?.permission_names ?? [],
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
    hasVerifiedMailbox: (state) => !!(state.user?.mailbox_settings?.smtp_enabled && state.user?.mailbox_settings?.smtp_verified_at && state.user?.mailbox_settings?.has_smtp_password),
    dashboardRouteName(): string {
      if (this.isAdmin) return 'admin-dashboard'
      if (this.isStaff) return 'staff-requests'
      return 'client-dashboard'
    },
  },

  actions: {
    can(permission: string) {
      return this.isAdmin || this.permissionNames.includes(permission)
    },

    async init() {
      if (this.initialized) return
      await this.fetchUser()
    },

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

    async register(payload: { name: string; email: string; phone_country_code?: string; phone?: string; password: string; password_confirmation: string }) {
      this.loading = true
      try {
        await authApi.register(payload)
        await this.fetchUser()
      } finally {
        this.loading = false
      }
    },

    async resendVerification() {
      return authApi.resendVerification()
    },

    async logout() {
      try {
        await authApi.logout()
      } finally {
        this.user = null
        this.initialized = true
      }
    },
  },
})
