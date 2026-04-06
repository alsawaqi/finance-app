<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { QuestionFinanceType, QuestionType } from '@/services/requestQuestions'

type QuestionForm = {
  id: number | null
  code: string
  question_text: string
  question_type: QuestionType
  finance_type: QuestionFinanceType
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
  financeTypeOptions: Array<{ value: QuestionFinanceType; label: string }>
  options: string[]
  showOptions: boolean
}>()

const { t } = useI18n()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ t('adminSharedWidgets.questionPreview.eyebrow') }}</span>
        <h2>{{ t('adminSharedWidgets.questionPreview.title') }}</h2>
      </div>
    </div>

    <div class="admin-question-preview">
      <div class="admin-question-preview__card">
        <div class="admin-question-preview__meta">
          <span class="admin-status-pill">{{ form.question_type }}</span>
          <span class="admin-question-preview__code">{{ form.code || t('adminSharedWidgets.questionPreview.autoCode') }}</span>
        </div>

        <label class="admin-question-preview__label">
          {{ form.question_text || t('adminSharedWidgets.questionPreview.questionFallback') }}
          <span v-if="form.is_required" class="admin-question-preview__required">*</span>
        </label>

        <template v-if="showOptions">
          <div class="admin-question-preview__options">
            <label
              v-for="option in options.length ? options : [t('adminSharedWidgets.questionPreview.optionOne'), t('adminSharedWidgets.questionPreview.optionTwo')]"
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
            :placeholder="form.placeholder || t('adminSharedWidgets.questionPreview.longAnswerPlaceholder')"
            disabled
          ></textarea>
        </template>

        <template v-else>
          <input
            class="admin-question-preview__input"
            :type="form.question_type === 'currency' || form.question_type === 'phone' ? 'text' : form.question_type"
            :placeholder="form.placeholder || t('adminSharedWidgets.questionPreview.answerPreviewPlaceholder')"
            disabled
          />
        </template>

        <p v-if="form.help_text" class="admin-question-preview__help">{{ form.help_text }}</p>
      </div>

      <div class="admin-question-preview__notes">
        <div class="admin-question-preview__note">
          <span>{{ t('adminRequestQuestionsPage.financeTypeField.preview') }}</span>
          <strong>
            {{
              financeTypeOptions.find((option) => option.value === form.finance_type)?.label
              ?? t('adminRequestQuestionsPage.financeTypes.all')
            }}
          </strong>
        </div>
        <div class="admin-question-preview__note">
          <span>{{ t('adminSharedWidgets.questionPreview.validation') }}</span>
          <strong>{{ form.validation_rules || t('adminSharedWidgets.questionPreview.noValidation') }}</strong>
        </div>
        <div class="admin-question-preview__note">
          <span>{{ t('adminSharedWidgets.questionPreview.sortOrder') }}</span>
          <strong>{{ form.sort_order }}</strong>
        </div>
        <div class="admin-question-preview__note">
          <span>{{ t('adminSharedWidgets.questionPreview.status') }}</span>
          <strong>{{ form.is_active ? t('adminSharedWidgets.states.active') : t('adminSharedWidgets.states.inactive') }}</strong>
        </div>
      </div>
    </div>
  </section>
</template>
