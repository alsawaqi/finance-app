<script setup lang="ts">
import { ref } from 'vue'
import axios from 'axios'
import { RouterLink } from 'vue-router'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import * as authApi from '@/services/authApi'

const email = ref('')
const loading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

async function submitForgotPassword() {
  loading.value = true
  successMessage.value = ''
  errorMessage.value = ''
  fieldErrors.value = {}

  try {
    const { data } = await authApi.forgotPassword({
      email: email.value,
    })

    successMessage.value = data.message ?? 'Password reset email sent.'
  } catch (error) {
    if (axios.isAxiosError(error)) {
      errorMessage.value = error.response?.data?.message ?? 'Unable to send reset email.'
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      errorMessage.value = 'Unable to send reset email.'
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
          <span class="auth-kicker">Password recovery</span>
          <h2>Forgot your password?</h2>
          <p>Enter your email address and we will send you a reset link.</p>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div class="auth-form-top">
                  <span class="auth-mini-badge">
                    <i class="fas fa-key"></i>
                    Secure account recovery
                  </span>
                  <h3>Reset your password</h3>
                  <p>Use the email address linked to your account.</p>
                </div>

                <div v-if="successMessage" class="auth-alert auth-alert--success">
                  {{ successMessage }}
                </div>

                <div v-if="errorMessage" class="auth-alert auth-alert--error">
                  {{ errorMessage }}
                </div>

                <form class="auth-grid" @submit.prevent="submitForgotPassword">
                  <div class="auth-field">
                    <label for="forgot-email">Email address</label>
                    <div class="auth-input-wrap">
                      <i class="far fa-envelope"></i>
                      <input
                        id="forgot-email"
                        v-model="email"
                        type="email"
                        class="auth-input"
                        :class="{ 'auth-input--error': firstFieldError('email') }"
                        placeholder="name@example.com"
                        autocomplete="email"
                      />
                    </div>
                    <small v-if="firstFieldError('email')" class="auth-field-error">{{ firstFieldError('email') }}</small>
                  </div>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="loading">
                    <span>{{ loading ? 'Sending...' : 'Send reset link' }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  Remembered your password?
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