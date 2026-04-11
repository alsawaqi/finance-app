<script setup lang="ts">
import { useI18n } from 'vue-i18n'

defineProps<{
  name: string
  description: string
  financeType: 'all' | 'individual' | 'company'
  allowedFileTypes: string[]
  maxFileSizeMb: number | null
  isRequired: boolean
  isMultiple: boolean
  isActive: boolean
}>()

const { t } = useI18n()

function financeTypeLabel(financeType: 'all' | 'individual' | 'company') {
  if (financeType === 'individual') return t('adminDocumentUploadStepsPage.financeTypes.individual')
  if (financeType === 'company') return t('adminDocumentUploadStepsPage.financeTypes.company')
  return t('adminDocumentUploadStepsPage.financeTypes.all')
}
</script>

<template>
  <section class="document-step-panel">
    <div class="document-step-panel__head">
      <div>
        <span class="document-step-panel__eyebrow">{{ t('adminSharedWidgets.documentPreview.eyebrow') }}</span>
        <h2>{{ t('adminSharedWidgets.documentPreview.title') }}</h2>
      </div>
    </div>

    <div class="document-step-preview-card">
      <div class="document-step-preview-card__badges">
        <span class="document-step-pill" :class="isRequired ? 'is-required' : 'is-optional'">
          {{ isRequired ? t('adminSharedWidgets.states.required') : t('adminSharedWidgets.states.optional') }}
        </span>
        <span class="document-step-pill" :class="isActive ? 'is-active' : 'is-inactive'">
          {{ isActive ? t('adminSharedWidgets.states.active') : t('adminSharedWidgets.states.inactive') }}
        </span>
        <span class="document-step-pill is-info">
          {{ isMultiple ? t('adminSharedWidgets.states.multipleFiles') : t('adminSharedWidgets.states.singleFile') }}
        </span>
        <span class="document-step-pill is-info">
          {{ financeTypeLabel(financeType) }}
        </span>
      </div>

      <h3>{{ name || t('adminSharedWidgets.documentPreview.nameFallback') }}</h3>
      <p>
        {{ description || t('adminSharedWidgets.documentPreview.descriptionFallback') }}
      </p>

      <div class="document-step-preview-meta">
        <div>
          <strong>{{ t('adminSharedWidgets.documentPreview.allowedTypes') }}</strong>
          <div class="document-step-chip-row">
            <span v-for="item in allowedFileTypes" :key="item" class="document-step-chip">{{ item }}</span>
            <span v-if="!allowedFileTypes.length" class="document-step-chip is-muted">{{ t('adminSharedWidgets.documentPreview.anyFileType') }}</span>
          </div>
        </div>

        <div>
          <strong>{{ t('adminSharedWidgets.documentPreview.maxSize') }}</strong>
          <span class="document-step-meta-text">{{ maxFileSizeMb ? `${maxFileSizeMb} MB` : t('adminSharedWidgets.documentPreview.defaultSize') }}</span>
        </div>
      </div>

      <div class="document-step-upload-box">
        <span>{{ t('adminSharedWidgets.documentPreview.uploadArea') }}</span>
        <button type="button" class="document-step-secondary-btn" disabled>{{ t('adminSharedWidgets.documentPreview.selectFile') }}</button>
      </div>
    </div>
  </section>
</template>
