import 'vue-router'

type AuthRoleName = 'admin' | 'staff' | 'client'

declare module 'vue-router' {
  interface RouteMeta {
    requiresAuth?: boolean
    guestOnly?: boolean
    role?: AuthRoleName
    allowedRoles?: AuthRoleName[]
    layout?: 'public' | 'auth' | 'client' | 'admin' | 'staff'
  }
}

export {}
