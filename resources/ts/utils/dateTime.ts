import type { LocaleLike } from './countries'

function normalizeLocale(locale?: LocaleLike) {
  if (typeof locale === 'string' && locale.trim() !== '') return locale.trim()

  if (locale && typeof locale === 'object' && 'value' in locale && typeof locale.value === 'string' && locale.value.trim() !== '') {
    return locale.value.trim()
  }

  if (typeof document !== 'undefined') {
    const htmlLang = String(document.documentElement.lang || '').trim()
    if (htmlLang) return htmlLang
  }

  return 'en'
}

function toDate(value: unknown) {
  if (!value) return null
  const parsed = new Date(String(value))
  return Number.isNaN(parsed.getTime()) ? null : parsed
}

export function formatDateTime(value: unknown, locale?: LocaleLike, fallback = '-') {
  const parsed = toDate(value)
  if (!parsed) return fallback

  const localeCode = normalizeLocale(locale)

  try {
    return new Intl.DateTimeFormat(localeCode, {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(parsed)
  } catch {
    return parsed.toLocaleString()
  }
}

export function formatDateOnly(value: unknown, locale?: LocaleLike, fallback = '-') {
  const parsed = toDate(value)
  if (!parsed) return fallback

  const localeCode = normalizeLocale(locale)

  try {
    return new Intl.DateTimeFormat(localeCode, {
      dateStyle: 'medium',
    }).format(parsed)
  } catch {
    return parsed.toLocaleDateString()
  }
}
