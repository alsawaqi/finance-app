import type { LocaleLike } from './countries'

type StatusMap = Record<string, { en: string; ar: string }>

const REQUEST_STATUS_LABELS: StatusMap = {
  draft: { en: 'Draft', ar: '\u0645\u0633\u0648\u062f\u0629' },
  submitted: { en: 'Submitted', ar: '\u0645\u0631\u0633\u0644' },
  active: { en: 'Active', ar: '\u0646\u0634\u0637' },
  pending: { en: 'Pending', ar: '\u0642\u064a\u062f \u0627\u0644\u0627\u0646\u062a\u0638\u0627\u0631' },
  in_progress: { en: 'In progress', ar: '\u062c\u0627\u0631\u064d \u0627\u0644\u062a\u0646\u0641\u064a\u0630' },
  on_hold: { en: 'On hold', ar: '\u0645\u0639\u0644\u0642' },
  rejected: { en: 'Rejected', ar: '\u0645\u0631\u0641\u0648\u0636' },
  completed: { en: 'Completed', ar: '\u0645\u0643\u062a\u0645\u0644' },
  cancelled: { en: 'Cancelled', ar: '\u0645\u0644\u063a\u064a' },
  blocked: { en: 'Blocked', ar: '\u0645\u062d\u0638\u0648\u0631' },
}

const CONTRACT_STATUS_LABELS: StatusMap = {
  draft: { en: 'Draft', ar: '\u0645\u0633\u0648\u062f\u0629' },
  admin_signed: { en: 'Admin signed', ar: '\u0645\u0648\u0642\u0639 \u0645\u0646 \u0627\u0644\u0625\u062f\u0627\u0631\u0629' },
  client_signed: { en: 'Client signed', ar: '\u0645\u0648\u0642\u0639 \u0645\u0646 \u0627\u0644\u0639\u0645\u064a\u0644' },
  fully_signed: { en: 'Fully signed', ar: '\u0645\u0648\u0642\u0639 \u0628\u0627\u0644\u0643\u0627\u0645\u0644' },
  rejected: { en: 'Rejected', ar: '\u0645\u0631\u0641\u0648\u0636' },
  cancelled: { en: 'Cancelled', ar: '\u0645\u0644\u063a\u064a' },
  expired: { en: 'Expired', ar: '\u0645\u0646\u062a\u0647\u064a \u0627\u0644\u0635\u0644\u0627\u062d\u064a\u0629' },
}

const UPDATE_BATCH_STATUS_LABELS: StatusMap = {
  open: { en: 'Open', ar: '\u0645\u0641\u062a\u0648\u062d\u0629' },
  partially_completed: { en: 'Partially completed', ar: '\u0645\u0643\u062a\u0645\u0644\u0629 \u062c\u0632\u0626\u064a\u0627' },
  completed: { en: 'Completed', ar: '\u0645\u0643\u062a\u0645\u0644\u0629' },
  cancelled: { en: 'Cancelled', ar: '\u0645\u0644\u063a\u0627\u0629' },
}

const UPDATE_ITEM_STATUS_LABELS: StatusMap = {
  pending: { en: 'Waiting for client', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0627\u0644\u0639\u0645\u064a\u0644' },
  updated: { en: 'Submitted for review', ar: '\u062a\u0645 \u0627\u0644\u0625\u0631\u0633\u0627\u0644 \u0644\u0644\u0645\u0631\u0627\u062c\u0639\u0629' },
  approved: { en: 'Approved', ar: '\u062a\u0645\u062a \u0627\u0644\u0645\u0648\u0627\u0641\u0642\u0629' },
  rejected: { en: 'Rejected', ar: '\u0645\u0631\u0641\u0648\u0636' },
}

const EMAIL_DELIVERY_STATUS_LABELS: StatusMap = {
  queued: { en: 'Queued', ar: '\u0642\u064a\u062f \u0627\u0644\u0627\u0646\u062a\u0638\u0627\u0631' },
  sent: { en: 'Sent', ar: '\u062a\u0645 \u0627\u0644\u0625\u0631\u0633\u0627\u0644' },
  delivered: { en: 'Delivered', ar: '\u062a\u0645 \u0627\u0644\u062a\u0633\u0644\u064a\u0645' },
  failed: { en: 'Failed', ar: '\u0641\u0634\u0644 \u0627\u0644\u0625\u0631\u0633\u0627\u0644' },
  bounced: { en: 'Bounced', ar: '\u0645\u0631\u062a\u062f' },
  opened: { en: 'Opened', ar: '\u062a\u0645 \u0627\u0644\u0641\u062a\u062d' },
}

const UNDERSTUDY_STATUS_LABELS: StatusMap = {
  draft: { en: 'Draft', ar: '\u0645\u0633\u0648\u062f\u0629' },
  submitted: { en: 'Submitted', ar: '\u0645\u0631\u0633\u0644' },
  approved: { en: 'Approved', ar: '\u062a\u0645\u062a \u0627\u0644\u0645\u0648\u0627\u0641\u0642\u0629' },
  rejected: { en: 'Rejected', ar: '\u0645\u0631\u0641\u0648\u0636' },
  in_review: { en: 'In review', ar: '\u0642\u064a\u062f \u0627\u0644\u0645\u0631\u0627\u062c\u0639\u0629' },
}

const ADDITIONAL_DOCUMENT_STATUS_LABELS: StatusMap = {
  pending: { en: 'Pending', ar: '\u0628\u0627\u0646\u062a\u0638\u0627\u0631 \u0627\u0644\u0631\u0641\u0639' },
  uploaded: { en: 'Uploaded', ar: '\u062a\u0645 \u0627\u0644\u0631\u0641\u0639' },
  approved: { en: 'Approved', ar: '\u062a\u0645\u062a \u0627\u0644\u0645\u0648\u0627\u0641\u0642\u0629' },
  rejected: { en: 'Rejected', ar: '\u0645\u0631\u0641\u0648\u0636' },
  change_requested: { en: 'Change requested', ar: '\u0637\u0644\u0628 \u062a\u0639\u062f\u064a\u0644' },
}

function normalizeLocale(locale?: LocaleLike) {
  if (typeof locale === 'string' && locale.trim() !== '') return locale.trim()

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
  }

  return 'en'
}

function isArabic(locale?: LocaleLike) {
  return normalizeLocale(locale).toLowerCase().startsWith('ar')
}

function titleCase(value: string) {
  return value
    .split(/[_\s]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ')
}

function humanizeFallback(normalized: string, locale?: LocaleLike) {
  if (!normalized) return '-'
  if (isArabic(locale)) return normalized.replaceAll('_', ' ')
  return titleCase(normalized)
}

function formatMappedStatus(source: StatusMap, value: unknown, locale?: LocaleLike, fallback = '-') {
  const normalized = String(value ?? '').trim().toLowerCase()
  if (!normalized) return fallback

  const mapped = source[normalized]
  if (mapped) return isArabic(locale) ? mapped.ar : mapped.en

  return humanizeFallback(normalized, locale)
}

export function formatRequestStatus(value: unknown, locale?: LocaleLike, fallback = '-') {
  return formatMappedStatus(REQUEST_STATUS_LABELS, value, locale, fallback)
}

export function formatContractStatus(value: unknown, locale?: LocaleLike, fallback = '-') {
  return formatMappedStatus(CONTRACT_STATUS_LABELS, value, locale, fallback)
}

export function formatUpdateBatchStatus(value: unknown, locale?: LocaleLike, fallback = '-') {
  return formatMappedStatus(UPDATE_BATCH_STATUS_LABELS, value, locale, fallback)
}

export function formatUpdateItemStatus(value: unknown, locale?: LocaleLike, fallback = '-') {
  return formatMappedStatus(UPDATE_ITEM_STATUS_LABELS, value, locale, fallback)
}

export function formatEmailDeliveryStatus(value: unknown, locale?: LocaleLike, fallback = '-') {
  return formatMappedStatus(EMAIL_DELIVERY_STATUS_LABELS, value, locale, fallback)
}

export function formatUnderstudyStatus(value: unknown, locale?: LocaleLike, fallback = '-') {
  return formatMappedStatus(UNDERSTUDY_STATUS_LABELS, value, locale, fallback)
}

export function formatAdditionalDocumentStatus(value: unknown, locale?: LocaleLike, fallback = '-') {
  return formatMappedStatus(ADDITIONAL_DOCUMENT_STATUS_LABELS, value, locale, fallback)
}
