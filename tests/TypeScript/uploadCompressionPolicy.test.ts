import {
  compressionSavingsPercent,
  formatUploadBytes,
  isCompressibleImageFile,
} from '../../resources/ts/utils/uploadCompression'

const photo = {
  name: 'national-address.jpg',
  type: 'image/jpeg',
  size: 900 * 1024,
}

const smallPhoto = {
  name: 'small-photo.jpg',
  type: 'image/jpeg',
  size: 80 * 1024,
}

const pdf = {
  name: 'contract.pdf',
  type: 'application/pdf',
  size: 900 * 1024,
}

const photoWithoutMime = {
  name: 'scanned-document.jpeg',
  type: '',
  size: 900 * 1024,
}

if (!isCompressibleImageFile(photo)) {
  throw new Error('Large JPEG uploads should be eligible for browser compression.')
}

if (!isCompressibleImageFile(photoWithoutMime, { allowedExtensions: ['jpg'] })) {
  throw new Error('JPEG extension uploads should be compressible even when the browser omits the MIME type.')
}

if (isCompressibleImageFile(smallPhoto)) {
  throw new Error('Small images should not be recompressed because the savings are not worth the quality cost.')
}

if (isCompressibleImageFile(pdf)) {
  throw new Error('PDF files should not be recompressed in the browser.')
}

if (compressionSavingsPercent(1000, 700) !== 30) {
  throw new Error('Compression savings should be reported as a rounded percentage.')
}

if (formatUploadBytes(1536) !== '1.5 KB') {
  throw new Error('Upload byte formatting should use readable KB units.')
}
