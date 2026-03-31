import { computed, watch } from 'vue'
import { createI18n } from 'vue-i18n'
import en from './locales/en'
import ar from './locales/ar'

export const SUPPORTED_LOCALES = ['en', 'ar'] as const
export type AppLocale = (typeof SUPPORTED_LOCALES)[number]

export const LOCALE_STORAGE_KEY = 'finance-app-locale'

const DEFAULT_LOCALE: AppLocale = 'ar'

function isSupportedLocale(value: string | null | undefined): value is AppLocale {
  return value === 'en' || value === 'ar'
}

export function getStoredLocale(): AppLocale {
  if (typeof window === 'undefined') return DEFAULT_LOCALE

  const stored = window.localStorage.getItem(LOCALE_STORAGE_KEY)
  if (isSupportedLocale(stored)) return stored

  return DEFAULT_LOCALE
}

export function getLocaleDirection(locale: AppLocale) {
  return locale === 'ar' ? 'rtl' : 'ltr'
}

export const messages = {
  en,
  ar,
}

export const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: getStoredLocale(),
  fallbackLocale: 'en',
  messages,
})

export function applyDocumentLanguage(locale: AppLocale) {
  if (typeof document === 'undefined') return

  const dir = getLocaleDirection(locale)
  document.documentElement.lang = locale
  document.documentElement.dir = dir
  document.body.setAttribute('dir', dir)
  document.body.classList.toggle('app-rtl', dir === 'rtl')
}

export function setAppLocale(locale: AppLocale) {
  i18n.global.locale.value = locale

  if (typeof window !== 'undefined') {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, locale)
  }

  applyDocumentLanguage(locale)
}

export function initI18n() {
  applyDocumentLanguage(i18n.global.locale.value as AppLocale)

  watch(
    () => i18n.global.locale.value,
    (locale) => applyDocumentLanguage(locale as AppLocale),
    { immediate: false },
  )
}

export function useLocaleOptions() {
  return computed(() => [
    { value: 'en' as AppLocale, label: messages.en.common.languages.en },
    { value: 'ar' as AppLocale, label: messages.ar.common.languages.ar },
  ])
}
