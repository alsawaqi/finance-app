<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { RouterLink, useRoute } from 'vue-router'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import { useAuthStore } from '@/stores/auth'
import * as authApi from '@/services/authApi'

const route = useRoute()
const auth = useAuthStore()

const loading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const hasVerificationQuery = computed(() => {
  return typeof route.query.id === 'string'
    && typeof route.query.hash === 'string'
    && typeof route.query.expires === 'string'
    && typeof route.query.signature === 'string'
})

onMounted(async () => {
  if (hasVerificationQuery.value) {
    await verifyNow()
  }
})

async function verifyNow() {
  loading.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const { data } = await authApi.verifyEmail({
      id: String(route.query.id),
      hash: String(route.query.hash),
      expires: String(route.query.expires),
      signature: String(route.query.signature),
    })

    successMessage.value = data.message ?? 'Email verified successfully.'
    await auth.fetchUser()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      errorMessage.value = error.response?.data?.message ?? 'Unable to verify email.'
    } else {
      errorMessage.value = 'Unable to verify email.'
    }
  } finally {
    loading.value = false
  }
}

async function resendVerification() {
  loading.value = true
  successMessage.value = ''
  errorMessage.value = ''

  try {
    const { data } = await auth.resendVerification()
    successMessage.value = data.message ?? 'Verification link sent.'
  } catch (error) {
    if (axios.isAxiosError(error)) {
      errorMessage.value = error.response?.data?.message ?? 'Unable to resend verification email.'
    } else {
      errorMessage.value = 'Unable to resend verification email.'
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthPageShell>
    <section class="auth-hero-section">
      <div class="container">
        <div class="auth-page-head auth-reveal-up">
          <span class="auth-kicker">Email verification</span>
          <h2>Verify your email address</h2>
          <p>Use the verification link sent to your inbox, or resend a new one below.</p>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div v-if="successMessage" class="auth-alert auth-alert--success">
                  {{ successMessage }}
                </div>

                <div v-if="errorMessage" class="auth-alert auth-alert--error">
                  {{ errorMessage }}
                </div>

                <div class="auth-grid">
                  <button
                    v-if="auth.isAuthenticated && !auth.isVerified"
                    type="button"
                    class="btn_style_one auth-submit"
                    :disabled="loading"
                    @click="resendVerification"
                  >
                    <span>{{ loading ? 'Sending...' : 'Resend verification email' }}</span>
                  </button>

                  <button
                    v-if="hasVerificationQuery"
                    type="button"
                    class="admin-secondary-btn"
                    :disabled="loading"
                    @click="verifyNow"
                  >
                    {{ loading ? 'Verifying...' : 'Verify now' }}
                  </button>
                </div>

                <p class="auth-foot-note">
                  <RouterLink to="/login" class="auth-text-link">Go to sign in</RouterLink>
                  <template v-if="auth.isAuthenticated">
                    ·
                    <RouterLink :to="{ name: auth.dashboardRouteName }" class="auth-text-link">Go to dashboard</RouterLink>
                  </template>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthPageShell>
</template>