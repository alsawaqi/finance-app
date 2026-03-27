import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import HomePage from '@/pages/public/HomePage.vue'
import AboutPage from '@/pages/public/AboutPage.vue'

import LoginPage from '@/pages/auth/LoginPage.vue'
import RegisterPage from '@/pages/auth/RegisterPage.vue'
import ForgotPasswordPage from '@/pages/auth/ForgotPasswordPage.vue'
import ResetPasswordPage from '@/pages/auth/ResetPasswordPage.vue'
import VerifyEmailPage from '@/pages/auth/VerifyEmailPage.vue'

import ClientLayoutPage from '@/pages/client/ClientLayoutPage.vue'
import ClientDashboardOverviewPage from '@/pages/client/ClientDashboardOverviewPage.vue'
import ClientNewRequestPage from '@/pages/client/ClientNewRequestPage.vue'
import ClientRequestsPage from '@/pages/client/ClientRequestsPage.vue'
import ClientRequestDetailsPage from '@/pages/client/ClientRequestDetailsPage.vue'
import ClientRequestSignPage from '@/pages/client/ClientRequestSignPage.vue'
import ClientRequestDocumentsPage from '@/pages/client/ClientRequestDocumentsPage.vue'
import AdminLayoutPage from '@/pages/admin/AdminLayoutPage.vue'
import AdminDashboardPage from '@/pages/admin/AdminDashboardPage.vue'
import AdminRequestQuestionsPage from '@/pages/admin/AdminRequestQuestionsPage.vue'

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
      meta: { guestOnly: true, layout: 'auth' },
    },
    {
      path: '/verify-email',
      name: 'verify-email',
      component: VerifyEmailPage,
      meta: { requiresAuth: true, layout: 'auth' },
    },
    {
      path: '/dashboard',
      component: ClientLayoutPage,
      meta: { requiresAuth: true, allowedRoles: ['client'], layout: 'client' },
      children: [
        {
          path: '',
          name: 'client-dashboard',
          component: ClientDashboardOverviewPage,
        },
        {
          path: 'new-request',
          name: 'client-new-request',
          component: ClientNewRequestPage,
        },
        {
          path: 'requests',
          name: 'client-requests',
          component: ClientRequestsPage,
        },
        {
          path: 'requests/:id',
          name: 'client-request-details',
          component: ClientRequestDetailsPage,
        },
        {
          path: 'requests/:id/sign',
          name: 'client-request-sign',
          component: ClientRequestSignPage,
        },
        {
          path: 'requests/:id/documents',
          name: 'client-request-documents',
          component: ClientRequestDocumentsPage,
        },
      ],
    },
    {
      path: '/admin',
      component: AdminLayoutPage,
      meta: { requiresAuth: true, allowedRoles: ['admin', 'staff'], layout: 'admin' },
      children: [
        {
          path: '',
          name: 'admin-dashboard',
          component: AdminDashboardPage,
        },
        {
          path: 'request-questions',
          name: 'admin-request-questions',
          component: AdminRequestQuestionsPage,
        },
      ],
    },
    {
      path: '/staff',
      redirect: { name: 'admin-dashboard' },
      meta: { requiresAuth: true, allowedRoles: ['admin', 'staff'] },
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
  const hasRoleRules = Array.isArray(to.meta.allowedRoles)
    ? to.meta.allowedRoles.length > 0
    : typeof to.meta.role === 'string'

  // Revalidate auth state from server for guarded/guest routes so browser
  // history restores (back-forward cache) cannot show stale authenticated UI.
  if (!auth.initialized || to.meta.requiresAuth || to.meta.guestOnly || hasRoleRules) {
    await auth.fetchUser()
  } else {
    await auth.init()
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: auth.dashboardRouteName }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return {
      name: 'login',
      query: { redirect: to.fullPath },
    }
  }

  const allowedRoles = Array.isArray(to.meta.allowedRoles)
    ? to.meta.allowedRoles.filter((role) => typeof role === 'string')
    : typeof to.meta.role === 'string'
      ? [to.meta.role]
      : []

  if (allowedRoles.length > 0) {
    const canAccessRoute = allowedRoles.some((role) => auth.roleNames.includes(role))

    if (!canAccessRoute) {
      if (auth.isAuthenticated) {
        return { name: auth.dashboardRouteName }
      }

      return {
        name: 'login',
        query: { redirect: to.fullPath },
      }
    }
  }

  return true
})

export default router
