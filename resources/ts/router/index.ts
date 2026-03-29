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
import ClientRequestWizardPage from '@/pages/client/ClientRequestWizardPage.vue'
import ClientRequestsPage from '@/pages/client/ClientRequestsPage.vue'
import ClientRequestDetailsPage from '@/pages/client/ClientRequestDetailsPage.vue'
import ClientRequestSignPage from '@/pages/client/ClientRequestSignPage.vue'
import ClientRequestDocumentsPage from '@/pages/client/ClientRequestDocumentsPage.vue'

import AdminLayoutPage from '@/pages/admin/AdminLayoutPage.vue'
import AdminDashboardPage from '@/pages/admin/AdminDashboardPage.vue'
import AdminRequestQuestionsPage from '@/pages/admin/AdminRequestQuestionsPage.vue'
import AdminDocumentUploadStepsPage from '@/pages/admin/AdminDocumentUploadStepsPage.vue'
import AdminStaffPage from '@/pages/admin/AdminStaffPage.vue'
import AdminAgentsPage from '@/pages/admin/AdminAgentsPage.vue'
import AdminNewRequestsPage from '@/pages/admin/AdminNewRequestsPage.vue'
import AdminRequestDetailsPage from '@/pages/admin/AdminRequestDetailsPage.vue'
import AdminContractBuilderPage from '@/pages/admin/AdminContractBuilderPage.vue'
import AdminAssignmentsPage from '@/pages/admin/AdminAssignmentsPage.vue'
import StaffRequestsPage from '@/pages/admin/StaffRequestsPage.vue'
import StaffRequestDetailsPage from '@/pages/admin/StaffRequestDetailsPage.vue'

import NotFoundPage from '@/pages/NotFoundPage.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: HomePage, meta: { layout: 'public' } },
    { path: '/about', name: 'about', component: AboutPage, meta: { layout: 'public' } },
    { path: '/login', name: 'login', component: LoginPage, meta: { guestOnly: true, layout: 'auth' } },
    { path: '/register', name: 'register', component: RegisterPage, meta: { guestOnly: true, layout: 'auth' } },
    { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordPage, meta: { guestOnly: true, layout: 'auth' } },
    { path: '/reset-password/:token', name: 'reset-password', component: ResetPasswordPage, meta: { guestOnly: true, layout: 'auth' } },
    { path: '/verify-email', name: 'verify-email', component: VerifyEmailPage, meta: { requiresAuth: true, layout: 'auth' } },

    {
      path: '/dashboard',
      component: ClientLayoutPage,
      meta: { requiresAuth: true, allowedRoles: ['client'], layout: 'client' },
      children: [
        { path: '', name: 'client-dashboard', component: ClientDashboardOverviewPage },
        { path: 'new-request', name: 'client-new-request', component: ClientNewRequestPage },
        { path: 'new-request/start', name: 'client-request-wizard', component: ClientRequestWizardPage },
        { path: 'requests', name: 'client-requests', component: ClientRequestsPage },
        { path: 'requests/:id', name: 'client-request-details', component: ClientRequestDetailsPage },
        { path: 'requests/:id/sign', name: 'client-request-sign', component: ClientRequestSignPage },
        { path: 'requests/:id/documents', name: 'client-request-documents', component: ClientRequestDocumentsPage },
      ],
    },

    {
      path: '/admin',
      component: AdminLayoutPage,
      meta: { requiresAuth: true, allowedRoles: ['admin', 'staff'], layout: 'admin' },
      children: [
        { path: '', name: 'admin-dashboard', component: AdminDashboardPage },
        { path: 'request-questions', name: 'admin-request-questions', component: AdminRequestQuestionsPage },
        { path: 'document-upload-steps', name: 'admin-document-upload-steps', component: AdminDocumentUploadStepsPage },
        { path: 'staff', name: 'admin-staff', component: AdminStaffPage },
        { path: 'agents', name: 'admin-agents', component: AdminAgentsPage },
        { path: 'assignments', name: 'admin-assignments', component: AdminAssignmentsPage, meta: { allowedRoles: ['admin'] } },
        { path: 'assigned-requests', name: 'staff-requests', component: StaffRequestsPage },
        { path: 'assigned-requests/:id', name: 'staff-request-details', component: StaffRequestDetailsPage },
        { path: 'requests/new', name: 'admin-new-requests', component: AdminNewRequestsPage },
        { path: 'requests/:id', name: 'admin-request-details', component: AdminRequestDetailsPage },
        { path: 'requests/:id/contract', name: 'admin-request-contract', component: AdminContractBuilderPage },
      ],
    },

    { path: '/staff', redirect: { name: 'admin-dashboard' }, meta: { requiresAuth: true, allowedRoles: ['admin', 'staff'] } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundPage, meta: { layout: 'public' } },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const hasRoleRules = Array.isArray(to.meta.allowedRoles)
    ? to.meta.allowedRoles.length > 0
    : typeof to.meta.role === 'string'

  if (!auth.initialized || to.meta.requiresAuth || to.meta.guestOnly || hasRoleRules) {
    await auth.fetchUser()
  } else {
    await auth.init()
  }

  if (to.meta.guestOnly && auth.isAuthenticated) {
    return { name: auth.dashboardRouteName }
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
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

      return { name: 'login', query: { redirect: to.fullPath } }
    }
  }

  return true
})

export default router
