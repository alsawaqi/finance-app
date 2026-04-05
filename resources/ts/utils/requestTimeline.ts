type RequestTimelineEntry = Record<string, any>

export type RequestTimelineRow = {
  entry: RequestTimelineEntry
  gapLabel: string | null
}

function localeTag(locale: string) {
  return locale === 'ar' ? 'ar-SA' : 'en-US'
}

function parseTimelineDate(value: unknown): Date | null {
  if (!value) return null

  const parsed = new Date(String(value))
  if (Number.isNaN(parsed.getTime())) return null

  return parsed
}

function formatUnit(value: number, locale: string, singular: string, plural: string, singularAr: string, pluralAr: string) {
  const formatted = new Intl.NumberFormat(localeTag(locale)).format(value)

  if (locale === 'ar') {
    const unit = value === 1 ? singularAr : pluralAr
    return `${formatted} ${unit}`
  }

  const unit = value === 1 ? singular : plural
  return `${formatted} ${unit}`
}

function formatDurationLabel(from: Date | null, to: Date | null, locale: string) {
  if (!from || !to) return null

  const diffMs = to.getTime() - from.getTime()
  if (diffMs <= 0) {
    return locale === 'ar'
      ? '\u062d\u062f\u062b \u0645\u0628\u0627\u0634\u0631 \u0628\u0639\u062f \u0627\u0644\u062d\u062f\u062b \u0627\u0644\u0633\u0627\u0628\u0642'
      : 'Immediately after previous event'
  }

  const dayMs = 24 * 60 * 60 * 1000
  const hourMs = 60 * 60 * 1000
  const minuteMs = 60 * 1000

  const days = Math.floor(diffMs / dayMs)
  const hours = Math.floor((diffMs % dayMs) / hourMs)
  const minutes = Math.floor((diffMs % hourMs) / minuteMs)

  const parts: string[] = []

  if (days > 0) {
    parts.push(formatUnit(days, locale, 'day', 'days', '\u064a\u0648\u0645', '\u0623\u064a\u0627\u0645'))
  }

  if (hours > 0 && parts.length < 2) {
    parts.push(formatUnit(hours, locale, 'hour', 'hours', '\u0633\u0627\u0639\u0629', '\u0633\u0627\u0639\u0627\u062a'))
  }

  if (parts.length === 0 && minutes > 0) {
    parts.push(formatUnit(minutes, locale, 'minute', 'minutes', '\u062f\u0642\u064a\u0642\u0629', '\u062f\u0642\u0627\u0626\u0642'))
  }

  if (parts.length === 0) {
    return locale === 'ar'
      ? '\u0623\u0642\u0644 \u0645\u0646 \u062f\u0642\u064a\u0642\u0629 \u0628\u064a\u0646 \u0627\u0644\u062d\u062f\u062b\u064a\u0646'
      : 'Less than a minute between events'
  }

  const duration = locale === 'ar' ? parts.join(' \u0648 ') : parts.join(' ')
  return locale === 'ar'
    ? `\u0627\u0644\u0641\u0627\u0635\u0644 \u0627\u0644\u0632\u0645\u0646\u064a: ${duration}`
    : `Elapsed: ${duration}`
}

export function formatTimelineDate(value: unknown, locale: string) {
  const parsed = parseTimelineDate(value)
  if (!parsed) return '-'
  return parsed.toLocaleString(localeTag(locale))
}

export function buildTimelineRows(entries: unknown, locale: string): RequestTimelineRow[] {
  const rows = Array.isArray(entries) ? [...entries] as RequestTimelineEntry[] : []

  const decorated = rows.map((entry, index) => ({
    entry,
    originalIndex: index,
    date: parseTimelineDate(entry?.created_at),
  }))

  decorated.sort((a, b) => {
    if (!a.date && !b.date) return a.originalIndex - b.originalIndex
    if (!a.date) return 1
    if (!b.date) return -1
    return a.date.getTime() - b.date.getTime()
  })

  return decorated.map((item, index) => {
    const previous = index > 0 ? decorated[index - 1] : null
    return {
      entry: item.entry,
      gapLabel: previous ? formatDurationLabel(previous.date, item.date, locale) : null,
    }
  })
}
