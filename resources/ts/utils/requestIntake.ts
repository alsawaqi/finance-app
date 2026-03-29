export type IntakePayload = Record<string, unknown> | null | undefined

export function intakeFullName(details: IntakePayload, fallback = '—') {
  return String(details?.full_name ?? details?.name ?? fallback)
}

export function intakeCountryCode(details: IntakePayload) {
  return String(details?.country_code ?? details?.country ?? '')
}

export function intakeRequestedAmount(details: IntakePayload, fallback = '—') {
  const value = details?.requested_amount
  return value === null || value === undefined || value === '' ? fallback : String(value)
}

export function intakeFinanceType(details: IntakePayload, fallback = '—') {
  return String(details?.finance_type ?? fallback)
}

export function intakeNotes(details: IntakePayload, fallback = 'No additional notes were submitted.') {
  const value = details?.notes
  return value === null || value === undefined || value === '' ? fallback : String(value)
}
