<script setup lang="ts">
import { computed, ref } from 'vue'
import axios from 'axios'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import * as authApi from '@/services/authApi'

const route = useRoute()
const router = useRouter()

const form = ref({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
})

const token = computed(() => String(route.params.token ?? ''))

const loading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const showPassword = ref(false)
const showConfirmPassword = ref(false)

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

async function submitResetPassword() {
  loading.value = true
  successMessage.value = ''
  errorMessage.value = ''
  fieldErrors.value = {}

  try {
    const { data } = await authApi.resetPassword({
      token: token.value,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    })

    successMessage.value = data.message ?? 'Password has been reset successfully.'

    setTimeout(() => {
      router.push({ name: 'login' })
    }, 1200)
  } catch (error) {
    if (axios.isAxiosError(error)) {
      errorMessage.value = error.response?.data?.message ?? 'Unable to reset password.'
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      errorMessage.value = 'Unable to reset password.'
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
          <span class="auth-kicker">Password reset</span>
          <h2>Create a new password</h2>
          <p>Enter your email and choose a new secure password.</p>
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

                <form class="auth-grid" @submit.prevent="submitResetPassword">
                  <div class="auth-field">
                    <label for="reset-email">Email address</label>
                    <div class="auth-input-wrap">
                      <i class="far fa-envelope"></i>
                      <input
                        id="reset-email"
                        v-model="form.email"
                        type="email"
                        class="auth-input"
                        :class="{ 'auth-input--error': firstFieldError('email') }"
                        autocomplete="email"
                      />
                    </div>
                    <small v-if="firstFieldError('email')" class="auth-field-error">{{ firstFieldError('email') }}</small>
                  </div>

                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="reset-password">New password</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="reset-password"
                          v-model="form.password"
                          :type="showPassword ? 'text' : 'password'"
                          class="auth-input"
                          :class="{ 'auth-input--error': firstFieldError('password') }"
                          autocomplete="new-password"
                        />
                        <button type="button" class="auth-password-toggle" @click="showPassword = !showPassword">
                          {{ showPassword ? 'Hide' : 'Show' }}
                        </button>
                      </div>
                      <small v-if="firstFieldError('password')" class="auth-field-error">{{ firstFieldError('password') }}</small>
                    </div>

                    <div class="auth-field">
                      <label for="reset-password-confirmation">Confirm password</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="reset-password-confirmation"
                          v-model="form.password_confirmation"
                          :type="showConfirmPassword ? 'text' : 'password'"
                          class="auth-input"
                          autocomplete="new-password"
                        />
                        <button type="button" class="auth-password-toggle" @click="showConfirmPassword = !showConfirmPassword">
                          {{ showConfirmPassword ? 'Hide' : 'Show' }}
                        </button>
                      </div>
                    </div>
                  </div>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="loading">
                    <span>{{ loading ? 'Updating...' : 'Reset password' }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  <RouterLink to="/login" class="auth-text-link">Back to sign in</RouterLink>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthPageShell>
</template>