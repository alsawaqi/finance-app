export type IntakePayload = Record<string, unknown> | null | undefined

function readIntakeValue(details: IntakePayload, key: string) {
  if (!details || typeof details !== 'object') return null
  return (details as Record<string, unknown>)[key]
}

function asText(value: unknown, fallback = '—') {
  return value === null || value === undefined || value === '' ? fallback : String(value)
}

export function intakeFullName(details: IntakePayload, fallback = '—') {
  return asText(readIntakeValue(details, 'full_name') ?? readIntakeValue(details, 'name'), fallback)
}

export function intakeCountryCode(details: IntakePayload) {
  return asText(readIntakeValue(details, 'country_code') ?? readIntakeValue(details, 'country'), '')
}

export function intakeRequestedAmount(details: IntakePayload, fallback = '—') {
  return asText(readIntakeValue(details, 'requested_amount'), fallback)
}

export function intakeFinanceType(details: IntakePayload, fallback = '—') {
  return asText(readIntakeValue(details, 'finance_type'), fallback)
}

export function intakeNotes(details: IntakePayload, fallback = 'No additional notes were submitted.') {
  return asText(readIntakeValue(details, 'notes'), fallback)
}

export function intakeEmail(details: IntakePayload, fallback = '—') {
  return asText(readIntakeValue(details, 'email'), fallback)
}

export function intakePhoneCountryCode(details: IntakePayload) {
  return asText(readIntakeValue(details, 'phone_country_code'), '').trim()
}

export function intakePhoneNumber(details: IntakePayload) {
  return asText(readIntakeValue(details, 'phone_number'), '').trim()
}

export function intakePhoneDisplay(details: IntakePayload, fallback = '—') {
  const code = intakePhoneCountryCode(details)
  const phone = intakePhoneNumber(details)
  const parts = [code, phone].filter(Boolean)

  return parts.length ? parts.join(' ') : fallback
}

export function intakeUnifiedNumber(details: IntakePayload, fallback = '—') {
  return asText(readIntakeValue(details, 'unified_number'), fallback)
}

export function intakeNationalAddressNumber(details: IntakePayload, fallback = '—') {
  return asText(readIntakeValue(details, 'national_address_number'), fallback)
}

export function intakeAddress(details: IntakePayload, fallback = '—') {
  return asText(readIntakeValue(details, 'address'), fallback)
}
export function intakeCompanyCrNumber(details: IntakePayload, fallback = '—') {
  return asText(readIntakeValue(details, 'company_cr_number'), fallback)
}
