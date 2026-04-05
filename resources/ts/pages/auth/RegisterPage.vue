<script setup lang="ts">
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import axios from 'axios'
import AuthPageShell from '../public/inc/AuthPageShell.vue'
import { useAuthStore } from '@/stores/auth'
import { useI18n } from 'vue-i18n'

const router = useRouter()
const auth = useAuthStore()
const { t } = useI18n()

const form = ref({
  firstName: '',
  lastName: '',
  email: '',
  phoneCountryCode: '+966',
  phoneNumber: '',
  password: '',
  confirmPassword: '',
  agree: true,
})

const countryCodeOptions = ['+966', '+971', '+965', '+973', '+974', '+968', '+20', '+962', '+1', '+44']

const showPassword = ref(false)
const showConfirmPassword = ref(false)
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

function digitsOnlyPhone(value: string) {
  return value.replace(/\D/g, '')
}

async function submitRegister() {
  resetErrors()

  const fullName = [form.value.firstName.trim(), form.value.lastName.trim()].filter(Boolean).join(' ')

  if (!fullName) {
    formError.value = t('authRegister.errors.fullNameRequired')
    return
  }

  if (!form.value.agree) {
    formError.value = t('authRegister.errors.acceptTermsRequired')
    return
  }

  try {
    await auth.register({
      name: fullName,
      email: form.value.email,
      phone_country_code: form.value.phoneNumber ? form.value.phoneCountryCode : undefined,
      phone: form.value.phoneNumber || undefined,
      password: form.value.password,
      password_confirmation: form.value.confirmPassword,
    })

    await router.push({ name: auth.dashboardRouteName })
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? t('authRegister.errors.unableToCreate')
      fieldErrors.value = error.response?.data?.errors ?? {}
      return
    }

    formError.value = t('authRegister.errors.unableToCreate')
  }
}
</script>

<template>
  <AuthPageShell>
    <section class="auth-hero-section">
      <div class="container">
        <div class="auth-page-head auth-reveal-up">
          <span class="auth-kicker">{{ t('authRegister.hero.kicker') }}</span>
          <h2>{{ t('authRegister.hero.title') }}</h2>
        </div>

        <div class="row g-4 align-items-stretch">
          <div class="col-lg-12 order-lg-2">
            <div class="auth-form-card auth-reveal-up">
              <div class="auth-form-card__shine"></div>

              <div class="auth-form-card__content">
                <div class="auth-form-top">
                  <span class="auth-mini-badge">
                    <i class="fas fa-user-plus"></i>
                    {{ t('authRegister.card.badge') }}
                  </span>
                  <h3>{{ t('authRegister.card.title') }}</h3>
                  <p>
                    {{ t('authRegister.card.subtitle') }}
                  </p>
                </div>

                <div v-if="formError" class="auth-alert auth-alert--error">
                  {{ formError }}
                </div>

                <form class="auth-grid" @submit.prevent="submitRegister">
                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-first-name">{{ t('authRegister.form.firstNameLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-user"></i>
                        <input
                          id="register-first-name"
                          v-model="form.firstName"
                          type="text"
                          class="auth-input"
                          :placeholder="t('authRegister.form.firstNamePlaceholder')"
                          autocomplete="given-name"
                        />
                      </div>
                    </div>

                    <div class="auth-field">
                      <label for="register-last-name">{{ t('authRegister.form.lastNameLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-user"></i>
                        <input
                          id="register-last-name"
                          v-model="form.lastName"
                          type="text"
                          class="auth-input"
                          :placeholder="t('authRegister.form.lastNamePlaceholder')"
                          autocomplete="family-name"
                        />
                      </div>
                    </div>
                  </div>

                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-email">{{ t('authRegister.form.emailLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="far fa-envelope"></i>
                        <input
                          id="register-email"
                          v-model="form.email"
                          type="email"
                          class="auth-input"
                          :class="{ 'auth-input--error': firstFieldError('email') }"
                          :placeholder="t('authRegister.form.emailPlaceholder')"
                          autocomplete="email"
                        />
                      </div>
                      <small v-if="firstFieldError('email')" class="auth-field-error">{{ firstFieldError('email') }}</small>
                    </div>

                    <div class="auth-field auth-field--phone">
                      <label for="register-phone-number">{{ t('authRegister.form.phoneNumberLabel') }}</label>
                      <div class="auth-grid auth-grid--phone">
                        <div class="auth-input-wrap">
                          <i class="fas fa-globe"></i>
                          <select
                            id="register-phone-country"
                            v-model="form.phoneCountryCode"
                            class="auth-select"
                            :class="{ 'auth-input--error': firstFieldError('phone_country_code') }"
                          >
                            <option v-for="code in countryCodeOptions" :key="code" :value="code">
                              {{ code }}
                            </option>
                          </select>
                        </div>

                        <div class="auth-input-wrap">
                          <i class="fas fa-phone-alt"></i>
                          <input
                            id="register-phone-number"
                            :value="form.phoneNumber"
                            type="tel"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            pattern="[0-9]*"
                            class="auth-input"
                            :class="{ 'auth-input--error': firstFieldError('phone') }"
                            :placeholder="t('authRegister.form.phoneNumberPlaceholder')"
                            @input="form.phoneNumber = digitsOnlyPhone(($event.target as HTMLInputElement).value)"
                          />
                        </div>
                      </div>
                      <small v-if="firstFieldError('phone_country_code')" class="auth-field-error">
                        {{ firstFieldError('phone_country_code') }}
                      </small>
                      <small v-if="firstFieldError('phone')" class="auth-field-error">{{ firstFieldError('phone') }}</small>
                    </div>
                  </div>

                  <div class="auth-grid auth-grid--2">
                    <div class="auth-field">
                      <label for="register-password">{{ t('authRegister.form.passwordLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="register-password"
                          v-model="form.password"
                          :type="showPassword ? 'text' : 'password'"
                          class="auth-input"
                          :class="{ 'auth-input--error': firstFieldError('password') }"
                          :placeholder="t('authRegister.form.passwordPlaceholder')"
                          autocomplete="new-password"
                        />
                        <button
                          type="button"
                          class="auth-password-toggle"
                          @click="showPassword = !showPassword"
                        >
                          {{ showPassword ? t('authRegister.form.hide') : t('authRegister.form.show') }}
                        </button>
                      </div>
                      <small v-if="firstFieldError('password')" class="auth-field-error">{{ firstFieldError('password') }}</small>
                    </div>

                    <div class="auth-field">
                      <label for="register-confirm-password">{{ t('authRegister.form.confirmPasswordLabel') }}</label>
                      <div class="auth-input-wrap">
                        <i class="fas fa-lock"></i>
                        <input
                          id="register-confirm-password"
                          v-model="form.confirmPassword"
                          :type="showConfirmPassword ? 'text' : 'password'"
                          class="auth-input"
                          :placeholder="t('authRegister.form.confirmPasswordPlaceholder')"
                          autocomplete="new-password"
                        />
                        <button
                          type="button"
                          class="auth-password-toggle"
                          @click="showConfirmPassword = !showConfirmPassword"
                        >
                          {{ showConfirmPassword ? t('authRegister.form.hide') : t('authRegister.form.show') }}
                        </button>
                      </div>
                    </div>
                  </div>

                  <label class="auth-check">
                    <input v-model="form.agree" type="checkbox" />
                    <span>
                      {{ t('authRegister.form.agreePrefix') }}
                      <a href="#" class="auth-link" @click.prevent>{{ t('authRegister.form.termsOfService') }}</a>
                      {{ t('authRegister.form.agreeAnd') }}
                      <a href="#" class="auth-link" @click.prevent>{{ t('authRegister.form.privacyPolicy') }}</a>
                    </span>
                  </label>

                  <button type="submit" class="btn_style_one auth-submit" :disabled="isSubmitting">
                    <span>{{ isSubmitting ? t('authRegister.form.creatingAccount') : t('authRegister.form.createAccount') }}</span>
                  </button>
                </form>

                <p class="auth-foot-note">
                  {{ t('authRegister.footer.haveAccount') }}
                  <RouterLink to="/login" class="auth-text-link">{{ t('authRegister.footer.signInHere') }}</RouterLink>
                </p>

                <p class="auth-policy">
                  {{ t('authRegister.footer.policy') }}
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
.auth-grid--phone {
  grid-template-columns: minmax(112px, 0.85fr) minmax(0, 1.15fr);
}

@media (max-width: 767px) {
  .auth-grid--phone {
    grid-template-columns: 1fr;
  }
}
</style>
