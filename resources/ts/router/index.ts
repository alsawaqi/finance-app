import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useAppProgress } from '@/composables/useAppProgress'

const HomePage = () => import('@/pages/public/HomePage.vue')
const AboutPage = () => import('@/pages/public/AboutPage.vue')

const LoginPage = () => import('@/pages/auth/LoginPage.vue')
const RegisterPage = () => import('@/pages/auth/RegisterPage.vue')
const ForgotPasswordPage = () => import('@/pages/auth/ForgotPasswordPage.vue')
const ResetPasswordPage = () => import('@/pages/auth/ResetPasswordPage.vue')
const VerifyEmailPage = () => import('@/pages/auth/VerifyEmailPage.vue')

const ClientLayoutPage = () => import('@/pages/client/ClientLayoutPage.vue')
const ClientDashboardOverviewPage = () => import('@/pages/client/ClientDashboardOverviewPage.vue')
const ClientNewRequestPage = () => import('@/pages/client/ClientNewRequestPage.vue')
const ClientRequestWizardPage = () => import('@/pages/client/ClientRequestWizardPage.vue')
const ClientRequestsPage = () => import('@/pages/client/ClientRequestsPage.vue')
const ClientRequestDetailsPage = () => import('@/pages/client/ClientRequestDetailsPage.vue')
const ClientRequestSignPage = () => import('@/pages/client/ClientRequestSignPage.vue')
const ClientRequestDocumentsPage = () => import('@/pages/client/ClientRequestDocumentsPage.vue')
const ClientChangePasswordPage = () => import('@/pages/client/ClientChangePasswordPage.vue')

const AdminLayoutPage = () => import('@/pages/admin/AdminLayoutPage.vue')
const AdminDashboardPage = () => import('@/pages/admin/AdminDashboardPage.vue')
const AdminRequestQuestionsPage = () => import('@/pages/admin/AdminRequestQuestionsPage.vue')
const AdminDocumentUploadStepsPage = () => import('@/pages/admin/AdminDocumentUploadStepsPage.vue')
const AdminStaffPage = () => import('@/pages/admin/AdminStaffPage.vue')
const AdminBanksPage = () => import('@/pages/admin/AdminBanksPage.vue')
const AdminCategorizationPage = () => import('@/pages/admin/AdminCategorizationPage.vue')
const AdminRequestFilteringPage = () => import('@/pages/admin/AdminRequestFilteringPage.vue')
const AdminClientsOverviewPage = () => import('@/pages/admin/AdminClientsOverviewPage.vue')
const AdminAgentsPage = () => import('@/pages/admin/AdminAgentsPage.vue')
const AdminNewRequestsPage = () => import('@/pages/admin/AdminNewRequestsPage.vue')
const AdminRequestDetailsPage = () => import('@/pages/admin/AdminRequestDetailsPage.vue')
const AdminRequestEmailsPage = () => import('@/pages/admin/AdminRequestEmailsPage.vue')
const AdminContractBuilderPage = () => import('@/pages/admin/AdminContractBuilderPage.vue')
const AdminAssignmentsPage = () => import('@/pages/admin/AdminAssignmentsPage.vue')
const AdminAssignmentDetailsPage = () => import('@/pages/admin/AdminAssignmentDetailsPage.vue')
const StaffRequestsPage = () => import('@/pages/admin/StaffRequestsPage.vue')
const StaffRequestDetailsPage = () => import('@/pages/admin/StaffRequestDetailsPage.vue')
const StaffRequestSendEmailPage = () => import('@/pages/admin/StaffRequestSendEmailPage.vue')
const StaffRequestEmailsPage = () => import('@/pages/admin/StaffRequestEmailsPage.vue')
const AdminStaffQuestionTemplatesPage = () => import('@/pages/admin/AdminStaffQuestionTemplatesPage.vue')
const AdminMailSettingsPage = () => import('@/pages/admin/AdminMailSettingsPage.vue')
const AdminInboxPage = () => import('@/pages/admin/AdminInboxPage.vue')

const AdminFinanceRequestTypesPage = () => import('@/pages/admin/AdminFinanceRequestTypesPage.vue')

const NotFoundPage = () => import('@/pages/NotFoundPage.vue')

const router = createRouter({
  history: createWebHistory(),
  scrollBehavior(_to, _from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    }

    return { top: 0, left: 0 }
  },
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
        { path: 'change-password', name: 'client-change-password', component: ClientChangePasswordPage },
      ],
    },

    {
      path: '/admin',
      component: AdminLayoutPage,
      meta: { requiresAuth: true, allowedRoles: ['admin', 'staff'], layout: 'admin' },
      children: [
        { path: '', name: 'admin-dashboard', component: AdminDashboardPage },
        { path: 'request-questions', name: 'admin-request-questions', component: AdminRequestQuestionsPage },
        { path: 'finance-request-types', name: 'admin-finance-request-types', component: AdminFinanceRequestTypesPage, meta: { allowedRoles: ['admin'] } },

        { path: 'document-upload-steps', name: 'admin-document-upload-steps', component: AdminDocumentUploadStepsPage },
        { path: 'staff', name: 'admin-staff', component: AdminStaffPage },
        { path: 'banks', name: 'admin-banks', component: AdminBanksPage, meta: { allowedRoles: ['admin'] } },
        { path: 'categorization', name: 'admin-categorization', component: AdminCategorizationPage, meta: { allowedRoles: ['admin'] } },
        { path: 'request-filtration', name: 'admin-request-filtration', component: AdminRequestFilteringPage, meta: { allowedRoles: ['admin'] } },
        { path: 'clients-overview', name: 'admin-clients-overview', component: AdminClientsOverviewPage, meta: { allowedRoles: ['admin'] } },
        { path: 'clients-overview/deactivated', name: 'admin-clients-overview-deactivated', component: AdminClientsOverviewPage, meta: { allowedRoles: ['admin'] } },
        { path: 'agents', name: 'admin-agents', component: AdminAgentsPage },
        { path: 'assignments', name: 'admin-assignments', component: AdminAssignmentsPage, meta: { allowedRoles: ['admin'] } },
        { path: 'assignments/:id', name: 'admin-assignment-details', component: AdminAssignmentDetailsPage, meta: { allowedRoles: ['admin'] } },
        { path: 'assigned-requests', name: 'staff-requests', component: StaffRequestsPage },
        { path: 'assigned-requests/:id', name: 'staff-request-details', component: StaffRequestDetailsPage },
        { path: 'assigned-requests/:id/send-email', name: 'staff-request-send-email', component: StaffRequestSendEmailPage },
        { path: 'assigned-requests/:id/emails', name: 'staff-request-emails', component: StaffRequestEmailsPage },
        { path: 'requests/new', name: 'admin-new-requests', component: AdminNewRequestsPage },
        { path: 'requests/:id', name: 'admin-request-details', component: AdminRequestDetailsPage },
        { path: 'requests/:id/emails', name: 'admin-request-emails', component: AdminRequestEmailsPage },
        { path: 'requests/:id/contract', name: 'admin-request-contract', component: AdminContractBuilderPage },
        { path: 'staff-question-templates', name: 'admin-staff-question-templates', component: AdminStaffQuestionTemplatesPage, meta: { allowedRoles: ['admin'] } },
        { path: 'mail-settings', name: 'admin-mail-settings', component: AdminMailSettingsPage, meta: { allowedRoles: ['admin'] } },
        { path: 'inbox', name: 'admin-inbox', component: AdminInboxPage },
      ],
    },

    { path: '/staff', redirect: { name: 'admin-dashboard' }, meta: { requiresAuth: true, allowedRoles: ['admin', 'staff'] } },
    { path: '/:pathMatch(.*)*', name: 'not-found', component: NotFoundPage, meta: { layout: 'public' } },
  ],
})

const appProgress = useAppProgress()

router.beforeEach(async (to) => {
  appProgress.startNavigation()
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

router.afterEach(() => {
  appProgress.finishNavigation()
})

router.onError(() => {
  appProgress.finishNavigation()
})

export default router
