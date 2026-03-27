import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import HomePage from '@/pages/public/HomePage.vue'
import AboutPage from '@/pages/public/AboutPage.vue'
import LoginPage from '@/pages/auth/LoginPage.vue'
import ClientDashboardPage from '@/pages/client/ClientDashboardPage.vue'
import AdminDashboardPage from '@/pages/admin/AdminDashboardPage.vue'
import StaffDashboardPage from '@/pages/staff/StaffDashboard.vue'
import NotFoundPage from '@/pages/NotFoundPage.vue'


const router = createRouter({
  history: createWebHistory(),
  routes: [
      {
          path: '/',
          name: 'home',
          component: HomePage,
      },
      {
          path: '/about',
          name: 'about',
          component: AboutPage,
      },
      {
          path: '/login',
          name: 'login',
          component: LoginPage,
      },
      {
          path: '/dashboard',
          name: 'client-dashboard',
          component: ClientDashboardPage,
      },
      {
          path: '/admin',
          name: 'admin-dashboard',
          component: AdminDashboardPage,
      },
      {
          path: '/staff',
          name: 'staff-dashboard',
          component: StaffDashboardPage,
      },
      {
          path: '/:pathMatch(.*)*',
          name: 'not-found',
          component: NotFoundPage,
      },
  ],
})


router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (!auth.initialized) {
    await auth.fetchUser()
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    if (auth.isAdmin) return { name: 'admin-dashboard' }
    if (auth.isStaff) return { name: 'staff-dashboard' }
    return { name: 'client-dashboard' }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login' }
  }

  if (to.meta.role) {
    const role = to.meta.role as string
    if (!auth.roleNames.includes(role)) {
      if (auth.isAdmin) return { name: 'admin-dashboard' }
      if (auth.isStaff) return { name: 'staff-dashboard' }
      if (auth.isClient) return { name: 'client-dashboard' }
      return { name: 'login' }
    }
  }

  return true
})

export default router