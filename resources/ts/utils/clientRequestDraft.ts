const CLIENT_REQUEST_DRAFT_PREFIX = 'finance:client-request-wizard'
const CLIENT_REQUEST_DRAFT_VERSION = 1

export type ClientRequestWizardStep = 1 | 2

export type ClientRequestWizardDetails = {
  finance_type: 'individual' | 'company'
  finance_request_type_id: string | number | ''
  country: string
  requested_amount: string
  company_name: string
  company_cr_number: string
  email: string
  phone_country_iso: string
  phone_country_code: string
  phone_number: string
  unified_number: string
  national_address_number: string
  address: string
  notes: string
}

export type ClientRequestWizardShareholderDraft = {
  name: string
  phone_country_iso: string
  phone_country_code: string
  phone_number: string
  id_number: string
}

export type ClientRequestWizardAnswersDraft = Record<number, unknown>

export type ClientRequestWizardDraftPayload = {
  version: number
  savedAt: string
  currentStep: ClientRequestWizardStep
  details: ClientRequestWizardDetails
  answers: ClientRequestWizardAnswersDraft
  shareholders: ClientRequestWizardShareholderDraft[]
}

type DraftUserId = number | string | null | undefined

function canUseLocalStorage() {
  return typeof window !== 'undefined' && typeof window.localStorage !== 'undefined'
}

function normalizeUserId(userId: DraftUserId) {
  if (userId === null || userId === undefined || userId === '') {
    return 'guest'
  }

  return String(userId)
}

export function getClientRequestDraftStorageKey(userId: DraftUserId) {
  return `${CLIENT_REQUEST_DRAFT_PREFIX}:${normalizeUserId(userId)}`
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function asString(value: unknown, fallback = '') {
  return value === null || value === undefined ? fallback : String(value)
}

function normalizeStep(value: unknown): ClientRequestWizardStep {
  return Number(value) === 2 ? 2 : 1
}

function sanitizeAnswerValue(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map((item) => asString(item)).filter((item) => item.trim() !== '')
  }

  if (typeof value === 'boolean') {
    return value
  }

  if (typeof value === 'number') {
    return Number.isFinite(value) ? value : ''
  }

  return asString(value)
}

function normalizeAnswers(value: unknown): ClientRequestWizardAnswersDraft {
  if (!isPlainObject(value)) return {}

  const normalized: ClientRequestWizardAnswersDraft = {}

  Object.entries(value).forEach(([rawKey, rawValue]) => {
    const numericKey = Number(rawKey)

    if (!Number.isInteger(numericKey) || numericKey <= 0) return

    normalized[numericKey] = sanitizeAnswerValue(rawValue)
  })

  return normalized
}

function normalizeDetails(value: unknown): ClientRequestWizardDetails {
  const source = isPlainObject(value) ? value : {}

  return {
    finance_type: source.finance_type === 'company' ? 'company' : 'individual',
    finance_request_type_id: asString(source.finance_request_type_id, ''),

    country: asString(source.country),
    requested_amount: asString(source.requested_amount),
    company_name: asString(source.company_name),
    company_cr_number: asString(source.company_cr_number),
    email: asString(source.email),
    phone_country_iso: asString(source.phone_country_iso, 'SA'),
    phone_country_code: asString(source.phone_country_code, '+966'),
    phone_number: asString(source.phone_number),
    unified_number: asString(source.unified_number),
    national_address_number: asString(source.national_address_number),
    address: asString(source.address),
    notes: asString(source.notes),
  }
}

function normalizeShareholders(value: unknown): ClientRequestWizardShareholderDraft[] {
  if (!Array.isArray(value)) return []

  return value.map((item) => {
    const source = isPlainObject(item) ? item : {}

    return {
      name: asString(source.name),
      phone_country_iso: asString(source.phone_country_iso, 'SA'),
      phone_country_code: asString(source.phone_country_code, '+966'),
      phone_number: asString(source.phone_number),
      id_number: asString(source.id_number),
    }
  })
}

function normalizeDraft(value: unknown): ClientRequestWizardDraftPayload | null {
  if (!isPlainObject(value)) return null

  const version = Number(value.version)

  if (version !== CLIENT_REQUEST_DRAFT_VERSION) {
    return null
  }

  return {
    version: CLIENT_REQUEST_DRAFT_VERSION,
    savedAt: asString(value.savedAt, new Date().toISOString()),
    currentStep: normalizeStep(value.currentStep),
    details: normalizeDetails(value.details),
    answers: normalizeAnswers(value.answers),
    shareholders: normalizeShareholders(value.shareholders),
  }
}

export function buildClientRequestDraftPayload(input: {
  currentStep: unknown
  details: unknown
  answers: unknown
  shareholders: unknown
}): ClientRequestWizardDraftPayload {
  return {
    version: CLIENT_REQUEST_DRAFT_VERSION,
    savedAt: new Date().toISOString(),
    currentStep: normalizeStep(input.currentStep),
    details: normalizeDetails(input.details),
    answers: normalizeAnswers(input.answers),
    shareholders: normalizeShareholders(input.shareholders),
  }
}

export function saveClientRequestDraft(
  userId: DraftUserId,
  input: {
    currentStep: unknown
    details: unknown
    answers: unknown
    shareholders: unknown
  },
) {
  if (!canUseLocalStorage()) return

  const key = getClientRequestDraftStorageKey(userId)
  const payload = buildClientRequestDraftPayload(input)

  window.localStorage.setItem(key, JSON.stringify(payload))
}

export function loadClientRequestDraft(userId: DraftUserId): ClientRequestWizardDraftPayload | null {
  if (!canUseLocalStorage()) return null

  const key = getClientRequestDraftStorageKey(userId)
  const raw = window.localStorage.getItem(key)

  if (!raw) return null

  try {
    const parsed = JSON.parse(raw)
    const normalized = normalizeDraft(parsed)

    if (!normalized) {
      window.localStorage.removeItem(key)
      return null
    }

    return normalized
  } catch {
    window.localStorage.removeItem(key)
    return null
  }
}

export function clearClientRequestDraft(userId: DraftUserId) {
  if (!canUseLocalStorage()) return

  window.localStorage.removeItem(getClientRequestDraftStorageKey(userId))
}

export function hasClientRequestDraft(userId: DraftUserId) {
  return loadClientRequestDraft(userId) !== null
}


function hasMeaningfulString(value: unknown) {
  return String(value ?? '').trim() !== ''
}

function answersContainMeaningfulData(value: unknown) {
  if (!isPlainObject(value)) return false

  return Object.values(value).some((item) => {
    if (Array.isArray(item)) {
      return item.some((entry) => String(entry ?? '').trim() !== '')
    }

    if (typeof item === 'boolean') {
      return item === true
    }

    if (typeof item === 'number') {
      return Number.isFinite(item)
    }

    return String(item ?? '').trim() !== ''
  })
}

function shareholdersContainMeaningfulData(value: unknown) {
  if (!Array.isArray(value)) return false

  return value.some((item) => {
    if (!isPlainObject(item)) return false

    return (
      hasMeaningfulString(item.name) ||
      hasMeaningfulString(item.phone_number) ||
      hasMeaningfulString(item.id_number)
    )
  })
}

export function hasMeaningfulClientRequestDraftData(input: {
  currentStep: unknown
  details: unknown
  answers: unknown
  shareholders: unknown
}) {
  const details = normalizeDetails(input.details)

  return (
    normalizeStep(input.currentStep) > 1 ||
    answersContainMeaningfulData(input.answers) ||
    shareholdersContainMeaningfulData(input.shareholders) ||
    
    details.finance_type === 'company' ||
    hasMeaningfulString(details.finance_request_type_id) ||
    hasMeaningfulString(details.country) ||
    hasMeaningfulString(details.requested_amount) ||
    hasMeaningfulString(details.company_name) ||
    hasMeaningfulString(details.company_cr_number) ||
    hasMeaningfulString(details.unified_number) ||
    hasMeaningfulString(details.national_address_number) ||
    hasMeaningfulString(details.address) ||
    hasMeaningfulString(details.notes)
  )
}