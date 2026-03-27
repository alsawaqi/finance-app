import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import HomePage from '@/pages/public/HomePage.vue'
import AboutPage from '@/pages/public/AboutPage.vue'

import LoginPage from '@/pages/auth/LoginPage.vue'
import RegisterPage from '@/pages/auth/RegisterPage.vue'
import ForgotPasswordPage from '@/pages/auth/ForgotPasswordPage.vue'
import ResetPasswordPage from '@/pages/auth/ResetPasswordPage.vue'
import VerifyEmailPage from '@/pages/auth/VerifyEmailPage.vue'

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
      meta: { layout: 'public' },
    },
    {
      path: '/about',
      name: 'about',
      component: AboutPage,
      meta: { layout: 'public' },
    },
    {
      path: '/login',
      name: 'login',
      component: LoginPage,
      meta: { guestOnly: true, layout: 'auth' },
    },
    {
      path: '/register',
      name: 'register',
      component: RegisterPage,
      meta: { guestOnly: true, layout: 'auth' },
    },
    {
      path: '/forgot-password',
      name: 'forgot-password',
      component: ForgotPasswordPage,
      meta: { guestOnly: true, layout: 'auth' },
    },
    {
      path: '/reset-password/:token',
      name: 'reset-password',
      component: ResetPasswordPage,
      meta: { layout: 'auth' },
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: VerifyEmailPage,
      meta: { layout: 'auth' },
    },
    {
      path: '/dashboard',
      name: 'client-dashboard',
      component: ClientDashboardPage,
      meta: { requiresAuth: true, role: 'client', layout: 'client' },
    },
    {
      path: '/admin',
      name: 'admin-dashboard',
      component: AdminDashboardPage,
      meta: { requiresAuth: true, role: 'admin', layout: 'admin' },
    },
    {
      path: '/staff',
      name: 'staff-dashboard',
      component: StaffDashboardPage,
      meta: { requiresAuth: true, role: 'staff', layout: 'staff' },
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'not-found',
      component: NotFoundPage,
      meta: { layout: 'public' },
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  await auth.init()

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: auth.dashboardRouteName }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  if (to.meta.role && !auth.roleNames.includes(to.meta.role)) {
    if (auth.isAuthenticated) {
      return { name: auth.dashboardRouteName }
    }

    return { name: 'login' }
  }

  return true
})

export default router