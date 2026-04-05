export type LocaleLike = string | { value?: string } | null | undefined

const ISO_COUNTRY_CODES = [
  'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
  'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS',
  'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
  'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
  'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF',
  'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM',
  'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM',
  'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC',
  'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK',
  'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
  'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG',
  'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW',
  'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
  'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO',
  'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
  'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
] as const

const COUNTRY_OPTION_CACHE = new Map<string, Array<{ code: string; label: string }>>()

function normalizeLocale(locale: LocaleLike) {
  if (typeof locale === 'string' && locale.trim() !== '') {
    return locale.trim()
  }

  if (locale && typeof locale === 'object' && 'value' in locale && typeof locale.value === 'string' && locale.value.trim() !== '') {
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

function normalizeCountryCode(code?: string | null) {
  const normalized = String(code || '').trim().toUpperCase()
  return /^[A-Z]{2}$/.test(normalized) ? normalized : ''
}

function displayNamesForLocale(locale: string) {
  try {
    return new Intl.DisplayNames([locale], { type: 'region' })
  } catch {
    try {
      return new Intl.DisplayNames(['en'], { type: 'region' })
    } catch {
      return null
    }
  }
}

export function countryNameFromCode(code?: string | null, locale: LocaleLike = 'en'): string {
  const normalizedCode = normalizeCountryCode(code)
  if (!normalizedCode) return '—'

  const localeCode = normalizeLocale(locale)
  const displayNames = displayNamesForLocale(localeCode)

  return displayNames?.of(normalizedCode) || normalizedCode
}

export function allCountryOptions(locale: LocaleLike = 'en') {
  const localeCode = normalizeLocale(locale).toLowerCase()
  const cached = COUNTRY_OPTION_CACHE.get(localeCode)
  if (cached) return cached

  const displayNames = displayNamesForLocale(localeCode)
  const options = ISO_COUNTRY_CODES
    .map((code) => ({
      code,
      label: displayNames?.of(code) || code,
    }))
    .sort((a, b) => a.label.localeCompare(b.label, localeCode, { sensitivity: 'base' }))

  COUNTRY_OPTION_CACHE.set(localeCode, options)
  return options
}
