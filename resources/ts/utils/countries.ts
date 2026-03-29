export function countryNameFromCode(code?: string | null): string {
  if (!code) return '—'

  try {
    const normalized = code.toUpperCase()
    const display = new Intl.DisplayNames(['en'], { type: 'region' })
    return display.of(normalized) || normalized
  } catch {
    return code
  }
}
