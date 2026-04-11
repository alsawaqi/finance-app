<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import type { DocumentStepFinanceType } from '@/services/documentUploadSteps'

type StepForm = {
  id: number | null
  code: string
  name: string
  finance_type: DocumentStepFinanceType
  description: string
  allowed_file_types_text: string
  max_file_size_mb: number | null
  sort_order: number
  is_required: boolean
  is_multiple: boolean
  is_active: boolean
}

const props = defineProps<{
  modelValue: StepForm
  financeTypeOptions: Array<{ value: DocumentStepFinanceType; label: string }>
  isEditing: boolean
  isSaving: boolean
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: StepForm): void
  (e: 'save'): void
  (e: 'reset'): void
}>()
const { t } = useI18n()

const form = computed({
  get: () => props.modelValue,
  set: (value: StepForm) => emit('update:modelValue', value),
})

function updateField<K extends keyof StepForm>(key: K, value: StepForm[K]) {
  form.value = {
    ...form.value,
    [key]: value,
  }
}

function firstError(field: string) {
  return props.errors?.[field]?.[0] ?? ''
}
</script>

<template>
  <section class="document-step-panel">
    <div class="document-step-panel__head">
      <div>
        <span class="document-step-panel__eyebrow">{{ isEditing ? t('adminDocumentStepBuilder.eyebrow.edit') : t('adminDocumentStepBuilder.eyebrow.create') }}</span>
        <h2>{{ t('adminDocumentStepBuilder.title') }}</h2>
      </div>
      <button type="button" class="document-step-ghost-btn" @click="$emit('reset')">
        {{ isEditing ? t('adminDocumentStepBuilder.actions.cancelEdit') : t('adminDocumentStepBuilder.actions.clear') }}
      </button>
    </div>

    <div class="document-step-form-grid document-step-form-grid--2">
      <label class="document-step-field">
        <span>{{ t('adminDocumentStepBuilder.fields.code') }}</span>
        <input
          :value="form.code"
          type="text"
          class="document-step-input"
          :class="{ 'has-error': firstError('code') }"
          :placeholder="t('adminDocumentStepBuilder.placeholders.code')"
          @input="updateField('code', ($event.target as HTMLInputElement).value)"
        />
        <small class="document-step-help">{{ t('adminDocumentStepBuilder.help.autoCode') }}</small>
        <small v-if="firstError('code')" class="document-step-error">{{ firstError('code') }}</small>
      </label>

      <label class="document-step-field">
        <span>{{ t('adminDocumentStepBuilder.fields.name') }}</span>
        <input
          :value="form.name"
          type="text"
          class="document-step-input"
          :class="{ 'has-error': firstError('name') }"
          :placeholder="t('adminDocumentStepBuilder.placeholders.name')"
          @input="updateField('name', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('name')" class="document-step-error">{{ firstError('name') }}</small>
      </label>

      <label class="document-step-field">
        <span>{{ t('adminDocumentStepBuilder.fields.financeType') }}</span>
        <select
          :value="form.finance_type"
          class="document-step-input"
          :class="{ 'has-error': firstError('finance_type') }"
          @change="updateField('finance_type', ($event.target as HTMLSelectElement).value as DocumentStepFinanceType)"
        >
          <option v-for="option in financeTypeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <small class="document-step-help">{{ t('adminDocumentStepBuilder.help.financeType') }}</small>
        <small v-if="firstError('finance_type')" class="document-step-error">{{ firstError('finance_type') }}</small>
      </label>

      <label class="document-step-field document-step-field--full">
        <span>{{ t('adminDocumentStepBuilder.fields.description') }}</span>
        <textarea
          :value="form.description"
          rows="4"
          class="document-step-textarea"
          :class="{ 'has-error': firstError('description') }"
          :placeholder="t('adminDocumentStepBuilder.placeholders.description')"
          @input="updateField('description', ($event.target as HTMLTextAreaElement).value)"
        ></textarea>
        <small v-if="firstError('description')" class="document-step-error">{{ firstError('description') }}</small>
      </label>

      <label class="document-step-field document-step-field--full">
        <span>{{ t('adminDocumentStepBuilder.fields.allowedFileTypes') }}</span>
        <textarea
          :value="form.allowed_file_types_text"
          rows="4"
          class="document-step-textarea"
          :class="{ 'has-error': firstError('allowed_file_types_json') }"
          :placeholder="t('adminDocumentStepBuilder.placeholders.allowedFileTypes')"
          @input="updateField('allowed_file_types_text', ($event.target as HTMLTextAreaElement).value)"
        ></textarea>
        <small class="document-step-help">{{ t('adminDocumentStepBuilder.help.allowedFileTypes') }}</small>
        <small v-if="firstError('allowed_file_types_json')" class="document-step-error">{{ firstError('allowed_file_types_json') }}</small>
      </label>

      <label class="document-step-field">
        <span>{{ t('adminDocumentStepBuilder.fields.maxFileSizeMb') }}</span>
        <input
          :value="form.max_file_size_mb ?? ''"
          type="number"
          min="1"
          class="document-step-input"
          :class="{ 'has-error': firstError('max_file_size_mb') }"
          :placeholder="t('adminDocumentStepBuilder.placeholders.maxFileSizeMb')"
          @input="updateField('max_file_size_mb', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : null)"
        />
        <small v-if="firstError('max_file_size_mb')" class="document-step-error">{{ firstError('max_file_size_mb') }}</small>
      </label>

      <label class="document-step-field">
        <span>{{ t('adminDocumentStepBuilder.fields.sortOrder') }}</span>
        <input
          :value="form.sort_order"
          type="number"
          min="0"
          class="document-step-input"
          :class="{ 'has-error': firstError('sort_order') }"
          @input="updateField('sort_order', Number(($event.target as HTMLInputElement).value || 0))"
        />
        <small v-if="firstError('sort_order')" class="document-step-error">{{ firstError('sort_order') }}</small>
      </label>

      <div class="document-step-switches document-step-field--full">
        <label class="document-step-switch-card">
          <input
            :checked="form.is_required"
            type="checkbox"
            @change="updateField('is_required', ($event.target as HTMLInputElement).checked)"
          />
          <div>
            <strong>{{ t('adminDocumentStepBuilder.switches.requiredTitle') }}</strong>
            <span>{{ t('adminDocumentStepBuilder.switches.requiredDesc') }}</span>
          </div>
        </label>

        <label class="document-step-switch-card">
          <input
            :checked="form.is_active"
            type="checkbox"
            @change="updateField('is_active', ($event.target as HTMLInputElement).checked)"
          />
          <div>
            <strong>{{ t('adminDocumentStepBuilder.switches.activeTitle') }}</strong>
            <span>{{ t('adminDocumentStepBuilder.switches.activeDesc') }}</span>
          </div>
        </label>

        <label class="document-step-switch-card">
          <input
            :checked="form.is_multiple"
            type="checkbox"
            @change="updateField('is_multiple', ($event.target as HTMLInputElement).checked)"
          />
          <div>
            <strong>{{ t('adminDocumentStepBuilder.switches.multipleTitle') }}</strong>
            <span>{{ t('adminDocumentStepBuilder.switches.multipleDesc') }}</span>
          </div>
        </label>
      </div>
    </div>

    <div class="document-step-actions">
      <button type="button" class="document-step-primary-btn" :disabled="isSaving" @click="$emit('save')">
        {{ isSaving ? (isEditing ? t('adminDocumentStepBuilder.actions.updating') : t('adminDocumentStepBuilder.actions.saving')) : isEditing ? t('adminDocumentStepBuilder.actions.updateStep') : t('adminDocumentStepBuilder.actions.saveStep') }}
      </button>
      <button type="button" class="document-step-secondary-btn" @click="$emit('reset')">
        {{ isEditing ? t('adminDocumentStepBuilder.actions.cancelEdit') : t('adminDocumentStepBuilder.actions.reset') }}
      </button>
    </div>
  </section>
</template>
