export function countryNameFromCode(code?: string | null, locale = 'en'): string {
  if (!code) return '—'

  try {
    const normalized = code.toUpperCase()
    const display = new Intl.DisplayNames([locale], { type: 'region' })
    return display.of(normalized) || normalized
  } catch {
    return code
  }
}
