<script setup lang="ts">
import type { ClientQuestion } from '@/services/clientRequests'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  question: ClientQuestion
  modelValue: unknown
  error?: string | null
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: unknown): void
}>()

const { t } = useI18n()

function updateText(event: Event) {
  emit('update:modelValue', (event.target as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement).value)
}

function updateCheckbox(option: string, checked: boolean) {
  const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []

  if (checked && !current.includes(option)) {
    current.push(option)
  }

  if (!checked) {
    const index = current.indexOf(option)
    if (index >= 0) current.splice(index, 1)
  }

  emit('update:modelValue', current)
}

function isChecked(option: string) {
  return Array.isArray(props.modelValue) ? props.modelValue.includes(option) : false
}
</script>

<template>
  <div class="client-form-group">
    <label class="client-form-label">
      {{ question.question_text }}
      <span v-if="question.is_required" class="client-required-mark">*</span>
    </label>

    <input
      v-if="['text', 'email', 'phone', 'number', 'currency', 'date'].includes(question.question_type)"
      :type="question.question_type === 'currency' ? 'number' : question.question_type === 'phone' ? 'tel' : question.question_type"
      class="client-form-control"
      :placeholder="question.placeholder || ''"
      :value="(modelValue as string | number | undefined) ?? ''"
      :step="question.question_type === 'currency' ? '0.01' : undefined"
      @input="updateText"
    />

    <textarea
      v-else-if="question.question_type === 'textarea'"
      class="client-form-control client-form-control--textarea"
      :placeholder="question.placeholder || ''"
      :value="(modelValue as string | undefined) ?? ''"
      @input="updateText"
    />

    <select
      v-else-if="question.question_type === 'select'"
      class="client-form-control"
      :value="(modelValue as string | undefined) ?? ''"
      @change="updateText"
    >
      <option value="">{{ t('clientQuestionField.chooseOption') }}</option>
      <option v-for="option in question.options_json || []" :key="option" :value="option">
        {{ option }}
      </option>
    </select>

    <div v-else-if="question.question_type === 'radio'" class="client-choice-grid">
      <label v-for="option in question.options_json || []" :key="option" class="client-choice-card">
        <input
          type="radio"
          :name="`question-${question.id}`"
          :checked="modelValue === option"
          :value="option"
          @change="emit('update:modelValue', option)"
        />
        <span>{{ option }}</span>
      </label>
    </div>

    <div v-else-if="question.question_type === 'checkbox'" class="client-choice-grid">
      <label v-for="option in question.options_json || []" :key="option" class="client-choice-card">
        <input
          type="checkbox"
          :checked="isChecked(option)"
          @change="updateCheckbox(option, ($event.target as HTMLInputElement).checked)"
        />
        <span>{{ option }}</span>
      </label>
    </div>

    <input
      v-else
      type="text"
      class="client-form-control"
      :placeholder="question.placeholder || ''"
      :value="(modelValue as string | undefined) ?? ''"
      @input="updateText"
    />

    <p v-if="question.help_text" class="client-form-help">{{ question.help_text }}</p>
    <p v-if="error" class="client-form-error">{{ error }}</p>
  </div>
</template>
