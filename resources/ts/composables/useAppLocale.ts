import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { getLocaleDirection, setAppLocale, type AppLocale, useLocaleOptions } from '@/i18n'

export function useAppLocale() {
  const { locale } = useI18n()
  const localeOptions = useLocaleOptions()

  const currentLocale = computed<AppLocale>({
    get: () => locale.value as AppLocale,
    set: (value) => setAppLocale(value),
  })

  const isRtl = computed(() => getLocaleDirection(currentLocale.value) === 'rtl')

  function changeLocale(value: AppLocale) {
    currentLocale.value = value
  }

  return {
    localeOptions,
    currentLocale,
    isRtl,
    changeLocale,
  }
}
