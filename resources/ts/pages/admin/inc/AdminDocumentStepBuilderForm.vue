<script setup lang="ts">
import { computed } from 'vue'

type StepForm = {
  id: number | null
  code: string
  name: string
  description: string
  allowed_file_types_text: string
  max_file_size_mb: number | null
  sort_order: number
  is_required: boolean
  is_active: boolean
}

const props = defineProps<{
  modelValue: StepForm
  isEditing: boolean
  isSaving: boolean
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: StepForm): void
  (e: 'save'): void
  (e: 'reset'): void
}>()

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
        <span class="document-step-panel__eyebrow">{{ isEditing ? 'Edit step' : 'Create step' }}</span>
        <h2>Document upload step</h2>
      </div>
      <button type="button" class="document-step-ghost-btn" @click="$emit('reset')">
        {{ isEditing ? 'Cancel edit' : 'Clear' }}
      </button>
    </div>

    <div class="document-step-form-grid document-step-form-grid--2">
      <label class="document-step-field">
        <span>Code</span>
        <input
          :value="form.code"
          type="text"
          class="document-step-input"
          :class="{ 'has-error': firstError('code') }"
          placeholder="Optional auto code, e.g. DOC-ID-001"
          @input="updateField('code', ($event.target as HTMLInputElement).value)"
        />
        <small class="document-step-help">Leave blank to auto-generate a code after save.</small>
        <small v-if="firstError('code')" class="document-step-error">{{ firstError('code') }}</small>
      </label>

      <label class="document-step-field">
        <span>Name</span>
        <input
          :value="form.name"
          type="text"
          class="document-step-input"
          :class="{ 'has-error': firstError('name') }"
          placeholder="National ID, Salary Certificate, Bank Statement"
          @input="updateField('name', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('name')" class="document-step-error">{{ firstError('name') }}</small>
      </label>

      <label class="document-step-field document-step-field--full">
        <span>Description</span>
        <textarea
          :value="form.description"
          rows="4"
          class="document-step-textarea"
          :class="{ 'has-error': firstError('description') }"
          placeholder="Explain exactly what the client should upload for this document step"
          @input="updateField('description', ($event.target as HTMLTextAreaElement).value)"
        ></textarea>
        <small v-if="firstError('description')" class="document-step-error">{{ firstError('description') }}</small>
      </label>

      <label class="document-step-field document-step-field--full">
        <span>Allowed file types</span>
        <textarea
          :value="form.allowed_file_types_text"
          rows="4"
          class="document-step-textarea"
          :class="{ 'has-error': firstError('allowed_file_types_json') }"
          placeholder="One type per line, for example:\npdf\njpg\npng"
          @input="updateField('allowed_file_types_text', ($event.target as HTMLTextAreaElement).value)"
        ></textarea>
        <small class="document-step-help">These are stored in <code>allowed_file_types_json</code>.</small>
        <small v-if="firstError('allowed_file_types_json')" class="document-step-error">{{ firstError('allowed_file_types_json') }}</small>
      </label>

      <label class="document-step-field">
        <span>Max file size (MB)</span>
        <input
          :value="form.max_file_size_mb ?? ''"
          type="number"
          min="1"
          class="document-step-input"
          :class="{ 'has-error': firstError('max_file_size_mb') }"
          placeholder="10"
          @input="updateField('max_file_size_mb', ($event.target as HTMLInputElement).value ? Number(($event.target as HTMLInputElement).value) : null)"
        />
        <small v-if="firstError('max_file_size_mb')" class="document-step-error">{{ firstError('max_file_size_mb') }}</small>
      </label>

      <label class="document-step-field">
        <span>Sort order</span>
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
            <strong>Required step</strong>
            <span>The client must upload this document to continue.</span>
          </div>
        </label>

        <label class="document-step-switch-card">
          <input
            :checked="form.is_active"
            type="checkbox"
            @change="updateField('is_active', ($event.target as HTMLInputElement).checked)"
          />
          <div>
            <strong>Active</strong>
            <span>Show this step when requests reach the document stage.</span>
          </div>
        </label>
      </div>
    </div>

    <div class="document-step-actions">
      <button type="button" class="document-step-primary-btn" :disabled="isSaving" @click="$emit('save')">
        {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update step' : 'Save step' }}
      </button>
      <button type="button" class="document-step-secondary-btn" @click="$emit('reset')">
        {{ isEditing ? 'Cancel edit' : 'Reset' }}
      </button>
    </div>
  </section>
</template>
