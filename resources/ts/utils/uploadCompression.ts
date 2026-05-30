export type UploadCompressionOptions = {
  allowedExtensions?: string[]
  imageQuality?: number
  maxWidthOrHeight?: number
  minSizeBytes?: number
  minSavingsRatio?: number
}

export type UploadCompressionResult = {
  file: File
  originalName: string
  originalSize: number
  compressedSize: number
  wasCompressed: boolean
  savingsPercent: number
  skippedReason?: string
}

const DEFAULT_IMAGE_QUALITY = 0.78
const DEFAULT_MAX_WIDTH_OR_HEIGHT = 1800
const DEFAULT_MIN_SIZE_BYTES = 180 * 1024
const DEFAULT_MIN_SAVINGS_RATIO = 0.05
// Keep PDFs and Office files untouched; changing those in-browser would break expected document formats.
const COMPRESSIBLE_IMAGE_MIME_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp'])

type FileLike = Pick<File, 'name' | 'type' | 'size'>

function normalizeExtension(value: string) {
  return value.trim().toLowerCase().replace(/^\./, '')
}

function fileExtension(name: string) {
  const index = name.lastIndexOf('.')
  return index >= 0 ? name.slice(index + 1).toLowerCase() : ''
}

function extensionForMimeType(mimeType: string) {
  if (mimeType === 'image/jpeg') return 'jpg'
  if (mimeType === 'image/png') return 'png'
  if (mimeType === 'image/webp') return 'webp'
  return fileExtension(mimeType)
}

function normalizedImageMimeType(file: FileLike) {
  if (file.type === 'image/jpg') return 'image/jpeg'
  if (COMPRESSIBLE_IMAGE_MIME_TYPES.has(file.type)) return file.type

  const extension = fileExtension(file.name)
  if (extension === 'jpg' || extension === 'jpeg') return 'image/jpeg'
  if (extension === 'png') return 'image/png'
  if (extension === 'webp') return 'image/webp'

  return file.type
}

function hasAllowedExtension(file: FileLike, allowedExtensions?: string[]) {
  if (!allowedExtensions?.length) return true
  const normalized = allowedExtensions.map(normalizeExtension)
  const extension = fileExtension(file.name)
  if ((extension === 'jpg' && normalized.includes('jpeg')) || (extension === 'jpeg' && normalized.includes('jpg'))) {
    return true
  }
  return normalized.includes(extension)
}

function canUseTargetExtension(targetMimeType: string, allowedExtensions?: string[]) {
  if (!allowedExtensions?.length) return true
  const normalized = allowedExtensions.map(normalizeExtension)
  const targetExtension = extensionForMimeType(targetMimeType)
  if (targetMimeType === 'image/jpeg') {
    return normalized.includes('jpg') || normalized.includes('jpeg')
  }
  return normalized.includes(targetExtension)
}

function compressedFileName(fileName: string, targetMimeType: string) {
  const targetExtension = extensionForMimeType(targetMimeType)
  const baseName = fileName.replace(/\.[^.]+$/, '')
  return `${baseName}.${targetExtension || fileExtension(fileName) || 'jpg'}`
}

function browserImageApisAvailable() {
  return typeof document !== 'undefined'
    && typeof URL !== 'undefined'
    && typeof File !== 'undefined'
}

async function loadImage(file: File): Promise<HTMLImageElement | ImageBitmap> {
  if (typeof createImageBitmap === 'function') {
    return createImageBitmap(file)
  }

  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const image = new Image()
    image.onload = () => {
      URL.revokeObjectURL(url)
      resolve(image)
    }
    image.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('Unable to load image for compression.'))
    }
    image.src = url
  })
}

function imageDimensions(image: HTMLImageElement | ImageBitmap) {
  if ('naturalWidth' in image) {
    return {
      width: image.naturalWidth,
      height: image.naturalHeight,
    }
  }

  return {
    width: image.width,
    height: image.height,
  }
}

function drawImageToCanvas(image: HTMLImageElement | ImageBitmap, maxWidthOrHeight: number, targetMimeType: string) {
  const { width, height } = imageDimensions(image)
  const scale = Math.min(1, maxWidthOrHeight / Math.max(width, height))
  const canvas = document.createElement('canvas')
  canvas.width = Math.max(1, Math.round(width * scale))
  canvas.height = Math.max(1, Math.round(height * scale))

  const context = canvas.getContext('2d')
  if (!context) {
    throw new Error('Unable to prepare image compression canvas.')
  }

  if (targetMimeType === 'image/jpeg') {
    context.fillStyle = '#ffffff'
    context.fillRect(0, 0, canvas.width, canvas.height)
  }

  context.drawImage(image, 0, 0, canvas.width, canvas.height)
  return canvas
}

function releaseLoadedImage(image: HTMLImageElement | ImageBitmap) {
  if ('close' in image && typeof image.close === 'function') {
    image.close()
  }
}

function canvasToBlob(canvas: HTMLCanvasElement, targetMimeType: string, quality: number): Promise<Blob> {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (!blob) {
        reject(new Error('Unable to compress selected image.'))
        return
      }

      resolve(blob)
    }, targetMimeType, quality)
  })
}

function resolveTargetMimeType(file: FileLike, allowedExtensions?: string[]) {
  const sourceMimeType = normalizedImageMimeType(file)

  if (canUseTargetExtension('image/jpeg', allowedExtensions)) {
    return 'image/jpeg'
  }

  if (sourceMimeType === 'image/webp' && canUseTargetExtension('image/webp', allowedExtensions)) {
    return 'image/webp'
  }

  if (sourceMimeType === 'image/png' && canUseTargetExtension('image/png', allowedExtensions)) {
    return 'image/png'
  }

  return sourceMimeType
}

export function isCompressibleImageFile(file: FileLike, options: UploadCompressionOptions = {}) {
  const minSizeBytes = options.minSizeBytes ?? DEFAULT_MIN_SIZE_BYTES
  const sourceMimeType = normalizedImageMimeType(file)

  return COMPRESSIBLE_IMAGE_MIME_TYPES.has(sourceMimeType)
    && file.size >= minSizeBytes
    && hasAllowedExtension(file, options.allowedExtensions)
}

export function compressionSavingsPercent(originalSize: number, compressedSize: number) {
  if (originalSize <= 0 || compressedSize >= originalSize) return 0
  return Math.round(((originalSize - compressedSize) / originalSize) * 100)
}

export function formatUploadBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${Number((bytes / 1024).toFixed(1))} KB`
  return `${Number((bytes / (1024 * 1024)).toFixed(2))} MB`
}

export async function optimizeClientUploadFile(
  file: File,
  options: UploadCompressionOptions = {},
): Promise<UploadCompressionResult> {
  const originalSize = file.size

  if (!isCompressibleImageFile(file, options)) {
    return {
      file,
      originalName: file.name,
      originalSize,
      compressedSize: originalSize,
      wasCompressed: false,
      savingsPercent: 0,
      skippedReason: 'not-compressible',
    }
  }

  if (!browserImageApisAvailable()) {
    return {
      file,
      originalName: file.name,
      originalSize,
      compressedSize: originalSize,
      wasCompressed: false,
      savingsPercent: 0,
      skippedReason: 'browser-unsupported',
    }
  }

  try {
    const targetMimeType = resolveTargetMimeType(file, options.allowedExtensions)
    const image = await loadImage(file)
    const canvas = (() => {
      try {
        return drawImageToCanvas(image, options.maxWidthOrHeight ?? DEFAULT_MAX_WIDTH_OR_HEIGHT, targetMimeType)
      } finally {
        releaseLoadedImage(image)
      }
    })()

    let blob: Blob
    try {
      blob = await canvasToBlob(canvas, targetMimeType, options.imageQuality ?? DEFAULT_IMAGE_QUALITY)
    } finally {
      canvas.width = 0
      canvas.height = 0
    }
    const minSavingsRatio = options.minSavingsRatio ?? DEFAULT_MIN_SAVINGS_RATIO

    if (blob.size >= originalSize || (originalSize - blob.size) / originalSize < minSavingsRatio) {
      return {
        file,
        originalName: file.name,
        originalSize,
        compressedSize: originalSize,
        wasCompressed: false,
        savingsPercent: 0,
        skippedReason: 'not-smaller',
      }
    }

    const optimizedFile = new File([blob], compressedFileName(file.name, targetMimeType), {
      type: targetMimeType,
      lastModified: Date.now(),
    })

    return {
      file: optimizedFile,
      originalName: file.name,
      originalSize,
      compressedSize: optimizedFile.size,
      wasCompressed: true,
      savingsPercent: compressionSavingsPercent(originalSize, optimizedFile.size),
    }
  } catch {
    return {
      file,
      originalName: file.name,
      originalSize,
      compressedSize: originalSize,
      wasCompressed: false,
      savingsPercent: 0,
      skippedReason: 'compression-failed',
    }
  }
}
