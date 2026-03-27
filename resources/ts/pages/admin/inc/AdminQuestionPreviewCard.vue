<script setup lang="ts">
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

defineProps<{
  form: QuestionForm
  options: string[]
  showOptions: boolean
}>()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">Live preview</span>
        <h2>How the client will see this question</h2>
      </div>
    </div>

    <div class="admin-question-preview">
      <div class="admin-question-preview__card">
        <div class="admin-question-preview__meta">
          <span class="admin-status-pill">{{ form.question_type }}</span>
          <span class="admin-question-preview__code">{{ form.code || 'Auto code' }}</span>
        </div>

        <label class="admin-question-preview__label">
          {{ form.question_text || 'Your question text will appear here.' }}
          <span v-if="form.is_required" class="admin-question-preview__required">*</span>
        </label>

        <template v-if="showOptions">
          <div class="admin-question-preview__options">
            <label
              v-for="option in options.length ? options : ['Option one', 'Option two']"
              :key="option"
              class="admin-question-preview__option"
            >
              <input :type="form.question_type === 'checkbox' ? 'checkbox' : 'radio'" disabled />
              <span>{{ option }}</span>
            </label>
          </div>
        </template>

        <template v-else-if="form.question_type === 'textarea'">
          <textarea
            class="admin-question-preview__textarea"
            rows="4"
            :placeholder="form.placeholder || 'Client will type a longer answer here'"
            disabled
          ></textarea>
        </template>

        <template v-else>
          <input
            class="admin-question-preview__input"
            :type="form.question_type === 'currency' || form.question_type === 'phone' ? 'text' : form.question_type"
            :placeholder="form.placeholder || 'Client answer preview'"
            disabled
          />
        </template>

        <p v-if="form.help_text" class="admin-question-preview__help">{{ form.help_text }}</p>
      </div>

      <div class="admin-question-preview__notes">
        <div class="admin-question-preview__note">
          <span>Validation</span>
          <strong>{{ form.validation_rules || 'No validation rule entered yet' }}</strong>
        </div>
        <div class="admin-question-preview__note">
          <span>Sort order</span>
          <strong>{{ form.sort_order }}</strong>
        </div>
        <div class="admin-question-preview__note">
          <span>Status</span>
          <strong>{{ form.is_active ? 'Active' : 'Inactive' }}</strong>
        </div>
      </div>
    </div>
  </section>
</template>
