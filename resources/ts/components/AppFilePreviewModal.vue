<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import api from '@/services/api'
import { inferFilePreviewKind } from '@/utils/filePreview'

const props = withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  subtitle?: string
  fileName?: string | null
  mimeType?: string | null
  previewUrl?: string | null
  downloadUrl?: string | null
  localFile?: File | null
}>(), {
  title: '',
  subtitle: '',
  fileName: '',
  mimeType: '',
  previewUrl: '',
  downloadUrl: '',
  localFile: null,
})

const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
}>()
const { locale } = useI18n()

const localObjectUrl = ref('')
const remoteObjectUrl = ref('')
const resolvedRemoteMime = ref('')
const remotePreviewLoading = ref(false)
const remotePreviewError = ref('')
let previewRequestSequence = 0

function uiText(en: string, ar: string) {
  return locale.value === 'ar' ? ar : en
}

function close() {
  emit('update:modelValue', false)
}

function handleBackdropClick(event: MouseEvent) {
  if (event.target === event.currentTarget) {
    close()
  }
}

function handleEscape(event: KeyboardEvent) {
  if (event.key === 'Escape' && props.modelValue) {
    close()
  }
}

function revokeLocalObjectUrl() {
  if (!localObjectUrl.value) return
  URL.revokeObjectURL(localObjectUrl.value)
  localObjectUrl.value = ''
}

function revokeRemoteObjectUrl() {
  if (!remoteObjectUrl.value) return
  URL.revokeObjectURL(remoteObjectUrl.value)
  remoteObjectUrl.value = ''
}

function resetRemotePreviewState() {
  previewRequestSequence += 1
  resolvedRemoteMime.value = ''
  remotePreviewLoading.value = false
  remotePreviewError.value = ''
  revokeRemoteObjectUrl()
}

watch(
  () => props.localFile,
  (file) => {
    revokeLocalObjectUrl()
    if (file) {
      localObjectUrl.value = URL.createObjectURL(file)
    }
  },
  { immediate: true },
)

watch(
  () => props.modelValue,
  (open) => {
    if (typeof document !== 'undefined') {
      document.body.classList.toggle('app-file-preview-open', open)
    }

    if (!open) {
      resetRemotePreviewState()
    }
  },
  { immediate: true },
)

const remotePreviewSource = computed(() => String(props.previewUrl || '').trim())
const effectiveDownloadUrl = computed(() => String(props.downloadUrl || props.previewUrl || ''))
const effectiveMime = computed(() => props.localFile?.type || props.mimeType || resolvedRemoteMime.value || '')
const previewKind = computed(() => inferFilePreviewKind(props.fileName, effectiveMime.value))
const shouldFetchRemotePreview = computed(() =>
  props.modelValue
  && !props.localFile
  && Boolean(remotePreviewSource.value),
)
const effectivePreviewUrl = computed(() => {
  if (localObjectUrl.value) return localObjectUrl.value
  if (remoteObjectUrl.value) return remoteObjectUrl.value
  if (!shouldFetchRemotePreview.value) return remotePreviewSource.value
  return ''
})
const hasPreviewError = computed(() => Boolean(remotePreviewError.value))
const canRenderInline = computed(() =>
  !remotePreviewLoading.value
  && !hasPreviewError.value
  && Boolean(effectivePreviewUrl.value)
  && previewKind.value !== 'unsupported',
)

const modalTitle = computed(() => String(props.title || uiText('File preview', 'معاينة الملف')))
const closePreviewAria = computed(() => uiText('Close preview', 'إغلاق المعاينة'))
const imageAlt = computed(() => String(props.fileName || uiText('File preview', 'معاينة الملف')))
const unsupportedTitle = computed(() => uiText('Preview not available for this file type.', 'المعاينة غير متاحة لهذا النوع من الملفات.'))
const unsupportedBody = computed(() => uiText('Use download to open this file with your local application.', 'استخدم التنزيل لفتح هذا الملف عبر التطبيق المحلي لديك.'))
const previewLoadError = computed(() => uiText('Unable to load a preview for this file right now.', 'تعذر تحميل معاينة هذا الملف حالياً.'))
const previewLoadTitle = computed(() => uiText('Preview temporarily unavailable.', 'المعاينة غير متاحة مؤقتاً.'))
const loadingLabel = computed(() => uiText('Loading preview...', 'جارٍ تحميل المعاينة...'))
const emptyTitle = computed(() => (hasPreviewError.value ? previewLoadTitle.value : unsupportedTitle.value))
const emptyBody = computed(() => (hasPreviewError.value ? remotePreviewError.value : unsupportedBody.value))
const downloadLabel = computed(() => uiText('Download', 'تنزيل'))
const closeLabel = computed(() => uiText('Close', 'إغلاق'))

watch(
  [shouldFetchRemotePreview, remotePreviewSource],
  async ([shouldFetch, source]) => {
    if (!shouldFetch || !source) {
      resetRemotePreviewState()
      return
    }

    const requestSequence = ++previewRequestSequence
    remotePreviewLoading.value = true
    remotePreviewError.value = ''
    revokeRemoteObjectUrl()

    try {
      const response = await api.get(source, { responseType: 'blob' })
      if (requestSequence !== previewRequestSequence) return

      const blob = response.data
      if (!(blob instanceof Blob) || blob.size === 0) {
        throw new Error('empty-preview')
      }

      resolvedRemoteMime.value = String(blob.type || '').trim().toLowerCase()
      remoteObjectUrl.value = URL.createObjectURL(blob)
    } catch {
      if (requestSequence !== previewRequestSequence) return
      remotePreviewError.value = previewLoadError.value
    } finally {
      if (requestSequence === previewRequestSequence) {
        remotePreviewLoading.value = false
      }
    }
  },
  { immediate: true },
)

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleEscape)
  }
})

onBeforeUnmount(() => {
  previewRequestSequence += 1
  revokeLocalObjectUrl()
  revokeRemoteObjectUrl()

  if (typeof document !== 'undefined') {
    document.body.classList.remove('app-file-preview-open')
  }

  if (typeof window !== 'undefined') {
    window.removeEventListener('keydown', handleEscape)
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="app-file-preview-fade">
      <div v-if="modelValue" class="app-file-preview-backdrop" @click="handleBackdropClick">
        <div class="app-file-preview-modal" role="dialog" aria-modal="true">
          <div class="app-file-preview-head">
            <div>
              <h2>{{ modalTitle }}</h2>
              <p v-if="subtitle">{{ subtitle }}</p>
              <small v-if="fileName">{{ fileName }}</small>
            </div>
            <button type="button" class="app-file-preview-close" :aria-label="closePreviewAria" @click="close">
              <i class="fas fa-times"></i>
            </button>
          </div>

          <div class="app-file-preview-body">
            <div v-if="remotePreviewLoading" class="app-file-preview-loading">
              <span class="app-file-preview-spinner" aria-hidden="true"></span>
              <p>{{ loadingLabel }}</p>
            </div>
            <img
              v-else-if="canRenderInline && previewKind === 'image'"
              :src="effectivePreviewUrl"
              :alt="imageAlt"
              class="app-file-preview-image"
            >
            <iframe
              v-else-if="canRenderInline"
              :src="effectivePreviewUrl"
              class="app-file-preview-frame"
              :title="fileName || modalTitle"
            ></iframe>
            <div v-else class="app-file-preview-empty">
              <h3>{{ emptyTitle }}</h3>
              <p>{{ emptyBody }}</p>
            </div>
          </div>

          <div class="app-file-preview-actions">
            <slot name="actions">
              <a
                v-if="effectiveDownloadUrl"
                class="ghost-btn"
                :href="effectiveDownloadUrl"
                target="_blank"
                rel="noopener"
              >
                {{ downloadLabel }}
              </a>
              <button type="button" class="primary-btn" @click="close">{{ closeLabel }}</button>
            </slot>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.app-file-preview-backdrop {
  position: fixed;
  inset: 0;
  z-index: 95;
  display: grid;
  place-items: center;
  padding: clamp(0.8rem, 2.2vw, 1.5rem);
  background: rgba(2, 6, 23, 0.62);
  backdrop-filter: blur(2px);
}

.app-file-preview-modal {
  width: min(1200px, 100%);
  max-height: calc(100vh - clamp(1.6rem, 5vh, 2.8rem));
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  border-radius: 20px;
  border: 1px solid rgba(148, 163, 184, 0.28);
  background: #f8fafc;
  box-shadow: 0 36px 78px rgba(15, 23, 42, 0.26);
  overflow: hidden;
}

.app-file-preview-head {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem 1.2rem;
  border-bottom: 1px solid rgba(148, 163, 184, 0.25);
  background: #ffffff;
}

.app-file-preview-head h2 {
  margin: 0;
  font-size: 1.02rem;
}

.app-file-preview-head p {
  margin: 0.35rem 0 0;
  color: #64748b;
  font-size: 0.92rem;
}

.app-file-preview-head small {
  display: block;
  margin-top: 0.45rem;
  color: #475569;
  font-size: 0.82rem;
  word-break: break-word;
}

.app-file-preview-close {
  border: 1px solid rgba(148, 163, 184, 0.35);
  border-radius: 10px;
  background: #ffffff;
  color: #0f172a;
  width: 2rem;
  height: 2rem;
  cursor: pointer;
}

.app-file-preview-body {
  min-height: 0;
  background: #e2e8f0;
}

.app-file-preview-loading {
  height: 100%;
  display: grid;
  place-content: center;
  gap: 0.65rem;
  text-align: center;
  color: #334155;
}

.app-file-preview-loading p {
  margin: 0;
}

.app-file-preview-spinner {
  width: 2rem;
  height: 2rem;
  border-radius: 999px;
  border: 3px solid rgba(148, 163, 184, 0.35);
  border-top-color: #4f46e5;
  justify-self: center;
  animation: app-file-preview-spin 0.8s linear infinite;
}

.app-file-preview-image,
.app-file-preview-frame {
  width: 100%;
  height: 100%;
  border: none;
  display: block;
  background: #f8fafc;
}

.app-file-preview-image {
  object-fit: contain;
}

.app-file-preview-empty {
  height: 100%;
  display: grid;
  place-content: center;
  gap: 0.45rem;
  text-align: center;
  padding: 1.2rem;
  color: #334155;
}

.app-file-preview-empty h3 {
  margin: 0;
  font-size: 1rem;
}

.app-file-preview-empty p {
  margin: 0;
}

.app-file-preview-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.55rem;
  padding: 0.9rem 1.2rem;
  border-top: 1px solid rgba(148, 163, 184, 0.25);
  background: #ffffff;
}

.app-file-preview-fade-enter-active,
.app-file-preview-fade-leave-active {
  transition: opacity 0.2s ease;
}

.app-file-preview-fade-enter-from,
.app-file-preview-fade-leave-to {
  opacity: 0;
}

@keyframes app-file-preview-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 768px) {
  .app-file-preview-modal {
    max-height: calc(100vh - 1rem);
    border-radius: 16px;
  }

  .app-file-preview-head,
  .app-file-preview-actions {
    padding-inline: 0.85rem;
  }

  .app-file-preview-actions {
    flex-wrap: wrap;
  }

  .app-file-preview-actions .ghost-btn,
  .app-file-preview-actions .primary-btn {
    flex: 1 1 180px;
  }
}
</style>
