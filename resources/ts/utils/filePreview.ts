export type FilePreviewKind = 'image' | 'pdf' | 'text' | 'unsupported'

const IMAGE_EXTENSIONS = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'])
const PDF_EXTENSIONS = new Set(['pdf'])
const TEXT_EXTENSIONS = new Set(['txt', 'csv', 'json', 'md', 'log'])

function extensionFromName(fileName?: string | null) {
  const value = String(fileName || '').trim().toLowerCase()
  if (!value.includes('.')) return ''
  return value.split('.').pop() || ''
}

export function inferFilePreviewKind(fileName?: string | null, mimeType?: string | null): FilePreviewKind {
  const mime = String(mimeType || '').toLowerCase().trim()
  const extension = extensionFromName(fileName)

  if (mime.startsWith('image/') || IMAGE_EXTENSIONS.has(extension)) return 'image'
  if (mime === 'application/pdf' || PDF_EXTENSIONS.has(extension)) return 'pdf'
  if (
    mime.startsWith('text/')
    || mime === 'application/json'
    || mime === 'application/csv'
    || TEXT_EXTENSIONS.has(extension)
  ) {
    return 'text'
  }

  return 'unsupported'
}

export function buildPreviewUrl(downloadUrl: string): string {
  const source = String(downloadUrl || '').trim()
  if (!source) return ''

  try {
    const baseOrigin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost'
    const parsed = new URL(source, baseOrigin)
    parsed.searchParams.set('preview', '1')

    const isAbsolute = /^https?:\/\//i.test(source)
    return isAbsolute
      ? parsed.toString()
      : `${parsed.pathname}${parsed.search}${parsed.hash}`
  } catch {
    const separator = source.includes('?') ? '&' : '?'
    return `${source}${separator}preview=1`
  }
}
