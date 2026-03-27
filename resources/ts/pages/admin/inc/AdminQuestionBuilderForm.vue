<script setup lang="ts">
import { computed } from 'vue'
import type { QuestionType } from '@/services/requestQuestions'

type QuestionForm = {
  id: number | null
  code: string
  question_text: string
  question_type: QuestionType
  placeholder: string
  help_text: string
  validation_rules: string
  sort_order: number
  is_required: boolean
  is_active: boolean
  options_text: string
}

const props = defineProps<{
  modelValue: QuestionForm
  questionTypeOptions: Array<{ value: QuestionType; label: string; helper: string }>
  showOptions: boolean
  isEditing: boolean
  isSaving: boolean
  errors?: Record<string, string[]>
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: QuestionForm): void
  (e: 'save'): void
  (e: 'reset'): void
}>()

const form = computed({
  get: () => props.modelValue,
  set: (value: QuestionForm) => emit('update:modelValue', value),
})

function updateField<K extends keyof QuestionForm>(key: K, value: QuestionForm[K]) {
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
  <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ isEditing ? 'Edit question' : 'Create question' }}</span>
        <h2>Request question setup</h2>
      </div>
      <button type="button" class="admin-panel__action" @click="$emit('reset')">
        {{ isEditing ? 'Cancel edit' : 'Clear' }}
      </button>
    </div>

    <div class="admin-form-grid admin-form-grid--2">
      <label class="admin-form-field">
        <span>Question code</span>
        <input
          :value="form.code"
          type="text"
          class="admin-form-input"
          :class="{ 'has-error': firstError('code') }"
          placeholder="RQ-QUESTION-001"
          @input="updateField('code', ($event.target as HTMLInputElement).value)"
        />
        <small class="admin-form-help">Leave blank to auto-generate after save.</small>
        <small v-if="firstError('code')" class="admin-form-error">{{ firstError('code') }}</small>
      </label>

      <label class="admin-form-field">
        <span>Question type</span>
        <select
          :value="form.question_type"
          class="admin-form-select"
          :class="{ 'has-error': firstError('question_type') }"
          @change="updateField('question_type', ($event.target as HTMLSelectElement).value as QuestionType)"
        >
          <option
            v-for="option in questionTypeOptions"
            :key="option.value"
            :value="option.value"
          >
            {{ option.label }}
          </option>
        </select>
        <small v-if="firstError('question_type')" class="admin-form-error">{{ firstError('question_type') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>Question text</span>
        <textarea
          :value="form.question_text"
          rows="3"
          class="admin-form-textarea"
          :class="{ 'has-error': firstError('question_text') }"
          placeholder="Enter the exact question that the client should answer"
          @input="updateField('question_text', ($event.target as HTMLTextAreaElement).value)"
        ></textarea>
        <small v-if="firstError('question_text')" class="admin-form-error">{{ firstError('question_text') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>Placeholder</span>
        <input
          :value="form.placeholder"
          type="text"
          class="admin-form-input"
          :class="{ 'has-error': firstError('placeholder') }"
          placeholder="Helpful placeholder for the client input"
          @input="updateField('placeholder', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('placeholder')" class="admin-form-error">{{ firstError('placeholder') }}</small>
      </label>

      <label v-if="showOptions" class="admin-form-field admin-form-field--full">
        <span>Options list</span>
        <textarea
          :value="form.options_text"
          rows="5"
          class="admin-form-textarea"
          :class="{ 'has-error': firstError('options_json') }"
          placeholder="One option per line, for example:\nSalaried\nSelf employed\nRetired"
          @input="updateField('options_text', ($event.target as HTMLTextAreaElement).value)"
        ></textarea>
        <small class="admin-form-help">These lines are saved into <code>options_json</code>.</small>
        <small v-if="firstError('options_json')" class="admin-form-error">{{ firstError('options_json') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>Help text</span>
        <textarea
          :value="form.help_text"
          rows="3"
          class="admin-form-textarea"
          :class="{ 'has-error': firstError('help_text') }"
          placeholder="Extra note shown to the client under the field"
          @input="updateField('help_text', ($event.target as HTMLTextAreaElement).value)"
        ></textarea>
        <small v-if="firstError('help_text')" class="admin-form-error">{{ firstError('help_text') }}</small>
      </label>

      <label class="admin-form-field admin-form-field--full">
        <span>Validation rules</span>
        <input
          :value="form.validation_rules"
          type="text"
          class="admin-form-input"
          :class="{ 'has-error': firstError('validation_rules') }"
          placeholder="required|numeric|min:0"
          @input="updateField('validation_rules', ($event.target as HTMLInputElement).value)"
        />
        <small v-if="firstError('validation_rules')" class="admin-form-error">{{ firstError('validation_rules') }}</small>
      </label>

      <label class="admin-form-field">
        <span>Sort order</span>
        <input
          :value="form.sort_order"
          type="number"
          min="0"
          class="admin-form-input"
          :class="{ 'has-error': firstError('sort_order') }"
          @input="updateField('sort_order', Number(($event.target as HTMLInputElement).value || 0))"
        />
        <small v-if="firstError('sort_order')" class="admin-form-error">{{ firstError('sort_order') }}</small>
      </label>

      <div class="admin-form-switches">
        <label class="admin-switch-card">
          <input
            :checked="form.is_required"
            type="checkbox"
            @change="updateField('is_required', ($event.target as HTMLInputElement).checked)"
          />
          <div>
            <strong>Required</strong>
            <span>Client must answer this question.</span>
          </div>
        </label>

        <label class="admin-switch-card">
          <input
            :checked="form.is_active"
            type="checkbox"
            @change="updateField('is_active', ($event.target as HTMLInputElement).checked)"
          />
          <div>
            <strong>Active</strong>
            <span>Show this question in the request form.</span>
          </div>
        </label>
      </div>
    </div>

    <div class="admin-form-actions">
      <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="$emit('save')">
        {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update question' : 'Save question' }}
      </button>
      <button type="button" class="admin-secondary-btn" @click="$emit('reset')">
        {{ isEditing ? 'Cancel edit' : 'Reset' }}
      </button>
    </div>
  </section>
</template>
