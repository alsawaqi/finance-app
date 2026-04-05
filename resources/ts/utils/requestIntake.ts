import type { LocaleLike } from './countries'

export type IntakePayload = Record<string, unknown> | null | undefined

const SAUDI_RIYAL_SYMBOL_GLYPH = '\u00EA'

function readIntakeValue(details: IntakePayload, key: string) {
  if (!details || typeof details !== 'object') return null
  return (details as Record<string, unknown>)[key]
}

function asText(value: unknown, fallback = '-') {
  return value === null || value === undefined || value === '' ? fallback : String(value)
}

function normalizeLocale(locale?: LocaleLike) {
  if (typeof locale === 'string' && locale.trim() !== '') {
    return locale.trim()
  }

  if (
    locale
    && typeof locale === 'object'
    && 'value' in locale
    && typeof locale.value === 'string'
    && locale.value.trim() !== ''
  ) {
    return locale.value.trim()
  }

  if (typeof document !== 'undefined') {
    const htmlLang = String(document.documentElement.lang || '').trim()
    if (htmlLang) return htmlLang

    const htmlDir = String(document.documentElement.dir || '').trim().toLowerCase()
    if (htmlDir === 'rtl') return 'ar'
  }

  return 'en'
}

function isArabicLocale(locale?: LocaleLike) {
  return normalizeLocale(locale).toLowerCase().startsWith('ar')
}

function parseAmountValue(value: unknown): number | null {
  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : null
  }

  if (typeof value !== 'string') {
    return null
  }

  const normalized = value.replace(/,/g, '').replace(/[^0-9.-]/g, '').trim()
  if (!normalized) return null

  const parsed = Number(normalized)
  return Number.isFinite(parsed) ? parsed : null
}

function formatAmountValue(value: number) {
  const hasFraction = Math.abs(value % 1) > 0
  return new Intl.NumberFormat('en-US', {
    minimumFractionDigits: hasFraction ? 2 : 0,
    maximumFractionDigits: 2,
  }).format(value)
}

export function intakeFullName(details: IntakePayload, fallback = '-') {
  return asText(readIntakeValue(details, 'full_name') ?? readIntakeValue(details, 'name'), fallback)
}

export function intakeCountryCode(details: IntakePayload) {
  return asText(readIntakeValue(details, 'country_code') ?? readIntakeValue(details, 'country'), '').trim().toUpperCase()
}

export function intakeRequestedAmount(details: IntakePayload, fallback = '-', withCurrencySymbol = false) {
  const rawValue = readIntakeValue(details, 'requested_amount')
  const parsed = parseAmountValue(rawValue)
  if (parsed === null) return asText(rawValue, fallback)

  const formatted = formatAmountValue(parsed)
  return withCurrencySymbol ? `${formatted} ${SAUDI_RIYAL_SYMBOL_GLYPH}` : formatted
}

export function applicantTypeLabel(value: unknown, locale?: LocaleLike, fallback = '-') {
  const normalized = String(value ?? '').trim().toLowerCase()
  if (!normalized) return fallback

  if (normalized === 'company') {
    return isArabicLocale(locale) ? '\u0634\u0631\u0643\u0629' : 'Company'
  }

  if (normalized === 'individual') {
    return isArabicLocale(locale) ? '\u0641\u0631\u062f' : 'Individual'
  }

  return String(value)
}

export function intakeFinanceType(details: IntakePayload, fallback = '-', locale?: LocaleLike) {
  const rawValue = readIntakeValue(details, 'finance_type')
  return applicantTypeLabel(rawValue, locale, fallback)
}

export function intakeCompanyName(details: IntakePayload, fallback = '-') {
  return asText(
    readIntakeValue(details, 'company_name') ?? readIntakeValue(details, 'company'),
    fallback,
  )
}

export function intakeNotes(details: IntakePayload, fallback = 'No additional notes were submitted.') {
  return asText(readIntakeValue(details, 'notes'), fallback)
}

export function intakeEmail(details: IntakePayload, fallback = '-') {
  return asText(readIntakeValue(details, 'email'), fallback)
}

export function intakePhoneCountryCode(details: IntakePayload) {
  return asText(readIntakeValue(details, 'phone_country_code'), '').trim()
}

export function intakePhoneNumber(details: IntakePayload) {
  return asText(readIntakeValue(details, 'phone_number'), '').trim()
}

export function intakePhoneDisplay(details: IntakePayload, fallback = '-') {
  const code = intakePhoneCountryCode(details)
  const phone = intakePhoneNumber(details)
  const parts = [code, phone].filter(Boolean)

  return parts.length ? parts.join(' ') : fallback
}

export function intakeUnifiedNumber(details: IntakePayload, fallback = '-') {
  return asText(readIntakeValue(details, 'unified_number'), fallback)
}

export function intakeNationalAddressNumber(details: IntakePayload, fallback = '-') {
  return asText(readIntakeValue(details, 'national_address_number'), fallback)
}

export function intakeAddress(details: IntakePayload, fallback = '-') {
  return asText(readIntakeValue(details, 'address'), fallback)
}

export function intakeCompanyCrNumber(details: IntakePayload, fallback = '-') {
  return asText(readIntakeValue(details, 'company_cr_number'), fallback)
}
