import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    role?: 'admin' | 'staff' | 'client'
    layout?: 'public' | 'auth' | 'client' | 'admin' | 'staff'
  }
}

export {}