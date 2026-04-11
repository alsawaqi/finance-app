import { parsePhoneNumberFromString, type CountryCode } from 'libphonenumber-js'

export function validateNationalPhoneForCountry(nationalNumber: string, countryIso: string): boolean {
  const normalizedCountry = String(countryIso || '').trim().toUpperCase()
  const normalizedNumber = String(nationalNumber || '').replace(/\D/g, '')

  if (!normalizedCountry || !normalizedNumber) return false

  try {
    const parsed = parsePhoneNumberFromString(normalizedNumber, normalizedCountry as CountryCode)
    return Boolean(parsed?.isValid())
  } catch {
    return false
  }
}

