<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import { forgotPassword } from '@/services/authApi'

const { t } = useI18n()

const form = ref({
  email: '',
})

const isSubmitting = ref(false)
const formError = ref('')
const formSuccess = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const submitLabel = computed(() => (
  isSubmitting.value ? t('authForgot.form.sending') : t('authForgot.form.sendResetLink')
))

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

function resetMessages() {
  formError.value = ''
  formSuccess.value = ''
  fieldErrors.value = {}
}

async function submitForgotPassword() {
  resetMessages()
  isSubmitting.value = true

  try {
    const response = await forgotPassword({
      email: form.value.email,
    })

    formSuccess.value = response.data?.message || t('authForgot.success.linkSent')
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? t('authForgot.errors.unableToSend')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = t('authForgot.errors.unableToSend')
    }
  } finally {
    isSubmitting.value = false
  }
}
</script>

<template>
  <AuthPageShell>
    <section class="auth-hero-section">
      <div class="container">
        <div class="auth-page-head auth-reveal-up">
          <span class="auth-kicker">{{ t('authForgot.hero.kicker') }}</span>
          <h2>{{ t('authForgot.hero.title') }}</h2>
          <p>{{ t('authForgot.hero.subtitle') }}</p>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div class="auth-form-top">
                  <span class="auth-mini-badge">
                    <i class="fas fa-key"></i>
                    {{ t('authForgot.card.badge') }}
                  </span>
                  <h3>{{ t('authForgot.card.title') }}</h3>
                  <p>{{ t('authForgot.card.subtitle') }}</p>
                </div>

                <div v-if="formError" class="auth-alert auth-alert--error">
                  {{ formError }}
                </div>

                <div v-if="formSuccess" class="auth-alert auth-alert--success">
                  {{ formSuccess }}
                </div>

                <form class="auth-grid" @submit.prevent="submitForgotPassword">
                  <div class="auth-field">
                    <label for="forgot-email">{{ t('authForgot.form.emailLabel') }}</label>
                    <div class="auth-input-wrap">
                      <i class="far fa-envelope"></i>
                      <input
                        id="forgot-email"
                        v-model="form.email"
                        type="email"
                        class="auth-input"
                        :class="{ 'auth-input--error': firstFieldError('email') }"
                        :placeholder="t('authForgot.form.emailPlaceholder')"
                        autocomplete="email"
                      />
                    </div>
                    <small v-if="firstFieldError('email')" class="auth-field-error">{{ firstFieldError('email') }}</small>
                  </div>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="isSubmitting">
                    <span>{{ submitLabel }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  <RouterLink to="/login" class="auth-text-link">{{ t('authForgot.footer.backToLogin') }}</RouterLink>
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </AuthPageShell>
</template>

<style scoped>
.auth-alert {
  padding: 12px 14px;
  margin-bottom: 16px;
  border-radius: 14px;
  font-size: 14px;
  line-height: 1.5;
}

.auth-alert--error {
  border: 1px solid rgba(220, 38, 38, 0.25);
  background: rgba(254, 242, 242, 0.9);
  color: #b91c1c;
}

.auth-alert--success {
  border: 1px solid rgba(5, 150, 105, 0.24);
  background: rgba(236, 253, 245, 0.9);
  color: #047857;
}

.auth-field-error {
  color: #b91c1c;
  font-size: 12px;
  margin-top: -2px;
}

.auth-input--error {
  border-color: rgba(220, 38, 38, 0.45);
}
</style>

