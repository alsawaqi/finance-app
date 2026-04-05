<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import { resetPassword } from '@/services/authApi'

const route = useRoute()
const router = useRouter()
const { t } = useI18n()

const form = ref({
  token: String(route.params.token ?? ''),
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  passwordConfirmation: '',
})

const showPassword = ref(false)
const showConfirmPassword = ref(false)
const isSubmitting = ref(false)
const formError = ref('')
const formSuccess = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const submitLabel = computed(() => (
  isSubmitting.value ? t('authReset.form.resetting') : t('authReset.form.resetPassword')
))

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

function resetMessages() {
  formError.value = ''
  formSuccess.value = ''
  fieldErrors.value = {}
}

async function submitResetPassword() {
  resetMessages()

  if (!form.value.token) {
    formError.value = t('authReset.errors.missingToken')
    return
  }

  isSubmitting.value = true

  try {
    const response = await resetPassword({
      token: form.value.token,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.passwordConfirmation,
    })

    formSuccess.value = response.data?.message || t('authReset.success.passwordUpdated')
    form.value.password = ''
    form.value.passwordConfirmation = ''

    window.setTimeout(() => {
      void router.push({ name: 'login' })
    }, 1200)
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? t('authReset.errors.unableToReset')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = t('authReset.errors.unableToReset')
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
          <span class="auth-kicker">{{ t('authReset.hero.kicker') }}</span>
          <h2>{{ t('authReset.hero.title') }}</h2>
          <p>{{ t('authReset.hero.subtitle') }}</p>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div class="auth-form-top">
                  <span class="auth-mini-badge">
                    <i class="fas fa-unlock-alt"></i>
                    {{ t('authReset.card.badge') }}
                  </span>
                  <h3>{{ t('authReset.card.title') }}</h3>
                  <p>{{ t('authReset.card.subtitle') }}</p>
                </div>

                <div v-if="formError" class="auth-alert auth-alert--error">
                  {{ formError }}
                </div>

                <div v-if="formSuccess" class="auth-alert auth-alert--success">
                  {{ formSuccess }}
                </div>

                <form class="auth-grid" @submit.prevent="submitResetPassword">
                  <div class="auth-field">
                    <label for="reset-email">{{ t('authReset.form.emailLabel') }}</label>
                    <div class="auth-input-wrap">
                      <i class="far fa-envelope"></i>
                      <input
                        id="reset-email"
                        v-model="form.email"
                        type="email"
                        class="auth-input"
                        :class="{ 'auth-input--error': firstFieldError('email') }"
                        :placeholder="t('authReset.form.emailPlaceholder')"
                        autocomplete="email"
                      />
                    </div>
                    <small v-if="firstFieldError('email')" class="auth-field-error">{{ firstFieldError('email') }}</small>
                  </div>

                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="reset-password">{{ t('authReset.form.passwordLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="reset-password"
                          v-model="form.password"
                          :type="showPassword ? 'text' : 'password'"
                          class="auth-input"
                          :class="{ 'auth-input--error': firstFieldError('password') }"
                          :placeholder="t('authReset.form.passwordPlaceholder')"
                          autocomplete="new-password"
                        />
                        <button type="button" class="auth-password-toggle" @click="showPassword = !showPassword">
                          {{ showPassword ? t('authReset.form.hide') : t('authReset.form.show') }}
                        </button>
                      </div>
                      <small v-if="firstFieldError('password')" class="auth-field-error">{{ firstFieldError('password') }}</small>
                    </div>

                    <div class="auth-field">
                      <label for="reset-password-confirmation">{{ t('authReset.form.confirmPasswordLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="reset-password-confirmation"
                          v-model="form.passwordConfirmation"
                          :type="showConfirmPassword ? 'text' : 'password'"
                          class="auth-input"
                          :class="{ 'auth-input--error': firstFieldError('password_confirmation') }"
                          :placeholder="t('authReset.form.confirmPasswordPlaceholder')"
                          autocomplete="new-password"
                        />
                        <button type="button" class="auth-password-toggle" @click="showConfirmPassword = !showConfirmPassword">
                          {{ showConfirmPassword ? t('authReset.form.hide') : t('authReset.form.show') }}
                        </button>
                      </div>
                      <small v-if="firstFieldError('password_confirmation')" class="auth-field-error">
                        {{ firstFieldError('password_confirmation') }}
                      </small>
                    </div>
                  </div>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="isSubmitting">
                    <span>{{ submitLabel }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  <RouterLink to="/login" class="auth-text-link">{{ t('authReset.footer.backToLogin') }}</RouterLink>
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

