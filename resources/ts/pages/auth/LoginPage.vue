<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const form = ref({
  email: '',
  password: '',
  remember: true,
})

const showPassword = ref(false)
const formError = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const isSubmitting = computed(() => auth.loading)

function resetErrors() {
  formError.value = ''
  fieldErrors.value = {}
}

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

async function submitLogin() {
  resetErrors()

  try {
    await auth.login({
      email: form.value.email,
      password: form.value.password,
      remember: form.value.remember,
    })

    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : null

    if (redirect) {
      await router.push(redirect)
      return
    }

    await router.push({ name: auth.dashboardRouteName })
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? 'Unable to sign in right now.'
      fieldErrors.value = error.response?.data?.errors ?? {}
      return
    }

    formError.value = 'Unable to sign in right now.'
  }
}
</script>

<template>
  <AuthPageShell>
    <section class="auth-hero-section">
      <div class="container">
        <div class="auth-page-head auth-reveal-up">
          <span class="auth-kicker">Access Portal</span>
          <h2>Sign in</h2>
          <p>
            Your login screen should feel like part of the same premium finance platform —
            secure, modern, and visually consistent from the first click.
          </p>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div class="auth-form-top">
                  <span class="auth-mini-badge">
                    <i class="fas fa-shield-alt"></i>
                    Secure Login
                  </span>
                  <h3>Login to your account</h3>
                  <p>
                    Enter your account details to continue. Once authenticated, you will be redirected
                    to the correct dashboard for your account type.
                  </p>
                </div>

                <div v-if="formError" class="auth-alert auth-alert--error">
                  {{ formError }}
                </div>

                <form class="auth-grid" @submit.prevent="submitLogin">
                  <div class="auth-field">
                    <label for="login-email">Email address</label>
                    <div class="auth-input-wrap">
                      <i class="far fa-envelope"></i>
                      <input
                        id="login-email"
                        v-model="form.email"
                        type="email"
                        class="auth-input"
                        :class="{ 'auth-input--error': firstFieldError('email') }"
                        placeholder="Enter your email"
                        autocomplete="email"
                      />
                    </div>
                    <small v-if="firstFieldError('email')" class="auth-field-error">{{ firstFieldError('email') }}</small>
                  </div>

                  <div class="auth-field">
                    <label for="login-password">Password</label>
                    <div class="auth-input-wrap">
                      <i class="fas fa-lock"></i>
                      <input
                        id="login-password"
                        v-model="form.password"
                        :type="showPassword ? 'text' : 'password'"
                        class="auth-input"
                        :class="{ 'auth-input--error': firstFieldError('password') }"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                      />
                      <button
                        type="button"
                        class="auth-password-toggle"
                        @click="showPassword = !showPassword"
                      >
                        {{ showPassword ? 'Hide' : 'Show' }}
                      </button>
                    </div>
                    <small v-if="firstFieldError('password')" class="auth-field-error">{{ firstFieldError('password') }}</small>
                  </div>

                  <div class="auth-row-between">
                    <label class="auth-check">
                      <input v-model="form.remember" type="checkbox" />
                      <span>Keep me signed in</span>
                    </label>

                    <RouterLink to="/forgot-password" class="auth-link">
                      Forgot password?
                    </RouterLink>
                  </div>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="isSubmitting">
                    <span>{{ isSubmitting ? 'Signing In...' : 'Sign In' }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  Don’t have an account?
                  <RouterLink to="/register" class="auth-text-link">Create one now</RouterLink>
                </p>

                <p class="auth-policy">
                  By signing in, you continue into a secure finance experience built to feel smooth, trustworthy,
                  and aligned with your public-facing brand.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthPageShell>
</template>
