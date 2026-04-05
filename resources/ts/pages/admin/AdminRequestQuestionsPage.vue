<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import AdminQuestionBuilderForm from './inc/AdminQuestionBuilderForm.vue'
import AdminQuestionPreviewCard from './inc/AdminQuestionPreviewCard.vue'
import AdminQuestionLibraryTable from './inc/AdminQuestionLibraryTable.vue'
import type { QuestionType, RequestQuestionItem, RequestQuestionPayload } from '@/services/requestQuestions'
import {
  createRequestQuestion,
  listRequestQuestions,
  reorderRequestQuestions,
  toggleRequestQuestionActive,
  updateRequestQuestion,
} from '@/services/requestQuestions'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'

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

const { t } = useI18n()

const questionTypeOptions = computed<Array<{ value: QuestionType; label: string; helper: string }>>(() => [
  { value: 'text', label: t('adminRequestQuestionsPage.types.text.label'), helper: t('adminRequestQuestionsPage.types.text.helper') },
  { value: 'textarea', label: t('adminRequestQuestionsPage.types.textarea.label'), helper: t('adminRequestQuestionsPage.types.textarea.helper') },
  { value: 'select', label: t('adminRequestQuestionsPage.types.select.label'), helper: t('adminRequestQuestionsPage.types.select.helper') },
  { value: 'radio', label: t('adminRequestQuestionsPage.types.radio.label'), helper: t('adminRequestQuestionsPage.types.radio.helper') },
  { value: 'checkbox', label: t('adminRequestQuestionsPage.types.checkbox.label'), helper: t('adminRequestQuestionsPage.types.checkbox.helper') },
  { value: 'number', label: t('adminRequestQuestionsPage.types.number.label'), helper: t('adminRequestQuestionsPage.types.number.helper') },
  { value: 'date', label: t('adminRequestQuestionsPage.types.date.label'), helper: t('adminRequestQuestionsPage.types.date.helper') },
  { value: 'email', label: t('adminRequestQuestionsPage.types.email.label'), helper: t('adminRequestQuestionsPage.types.email.helper') },
  { value: 'phone', label: t('adminRequestQuestionsPage.types.phone.label'), helper: t('adminRequestQuestionsPage.types.phone.helper') },
  { value: 'currency', label: t('adminRequestQuestionsPage.types.currency.label'), helper: t('adminRequestQuestionsPage.types.currency.helper') },
])

const questions = ref<RequestQuestionItem[]>([])
const pagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 20 })
const isLoading = ref(false)
const isSaving = ref(false)
const isReordering = ref(false)
const formError = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const optionDrivenTypes: QuestionType[] = ['select', 'radio', 'checkbox']

const form = ref<QuestionForm>(createDefaultForm())

const isEditing = computed(() => form.value.id !== null)
const parsedOptions = computed(() =>
  form.value.options_text
    .split('\n')
    .map((item) => item.trim())
    .filter(Boolean),
)
const needsOptions = computed(() => optionDrivenTypes.includes(form.value.question_type))

const stats = computed(() => {
  const total = pagination.value.total
  const active = questions.value.filter((item) => item.is_active).length
  const required = questions.value.filter((item) => item.is_required).length
  const choiceBased = questions.value.filter((item) => optionDrivenTypes.includes(item.question_type)).length

  return [
    { label: t('adminRequestQuestionsPage.stats.totalQuestions'), value: String(total), tone: 'violet' },
    { label: t('adminRequestQuestionsPage.stats.activeQuestions'), value: String(active), tone: 'emerald' },
    { label: t('adminRequestQuestionsPage.stats.requiredFields'), value: String(required), tone: 'blue' },
    { label: t('adminRequestQuestionsPage.stats.choiceBased'), value: String(choiceBased), tone: 'amber' },
  ]
})

onMounted(async () => {
  await fetchQuestions()
})

function createDefaultForm(): QuestionForm {
  return {
    id: null,
    code: '',
    question_text: '',
    question_type: 'text',
    placeholder: '',
    help_text: '',
    validation_rules: '',
    sort_order: questions.value.length + 1,
    is_required: false,
    is_active: true,
    options_text: '',
  }
}

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

function clearMessages() {
  formError.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
}

async function fetchQuestions(page = pagination.value.current_page) {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listRequestQuestions({
      page,
      per_page: pagination.value.per_page,
    })
    questions.value = data.data
    pagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: pagination.value.per_page }

    if (!isEditing.value) {
      form.value.sort_order = Math.max(pagination.value.total, questions.value.length) + 1
    }
  } catch (error) {
    formError.value = extractErrorMessage(error, t('adminRequestQuestionsPage.errors.loadFailed'))
  } finally {
    isLoading.value = false
  }
}

function resetForm() {
  clearMessages()
  form.value = createDefaultForm()
}

function buildPayload(): RequestQuestionPayload {
  return {
    code: form.value.code.trim() || null,
    question_text: form.value.question_text.trim(),
    question_type: form.value.question_type,
    options_json: needsOptions.value ? parsedOptions.value : null,
    placeholder: form.value.placeholder.trim() || null,
    help_text: form.value.help_text.trim() || null,
    validation_rules: form.value.validation_rules.trim() || null,
    is_required: form.value.is_required,
    sort_order: form.value.sort_order,
    is_active: form.value.is_active,
  }
}

async function saveQuestion() {
  clearMessages()
  isSaving.value = true

  try {
    if (isEditing.value && form.value.id) {
      const { data } = await updateRequestQuestion(form.value.id, buildPayload())
      successMessage.value = data.message
    } else {
      const { data } = await createRequestQuestion(buildPayload())
      successMessage.value = data.message
    }

    await fetchQuestions()
    resetForm()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? t('adminRequestQuestionsPage.errors.saveFailed')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = t('adminRequestQuestionsPage.errors.saveFailed')
    }
  } finally {
    isSaving.value = false
  }
}

function editQuestion(row: RequestQuestionItem) {
  clearMessages()
  form.value = {
    id: row.id,
    code: row.code ?? '',
    question_text: row.question_text,
    question_type: row.question_type,
    placeholder: row.placeholder ?? '',
    help_text: row.help_text ?? '',
    validation_rules: row.validation_rules ?? '',
    sort_order: row.sort_order,
    is_required: row.is_required,
    is_active: row.is_active,
    options_text: row.options_json.join('\n'),
  }

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleQuestion(row: RequestQuestionItem) {
  clearMessages()

  try {
    const { data } = await toggleRequestQuestionActive(row.id)
    successMessage.value = data.message
    questions.value = questions.value.map((question) => (question.id === row.id ? data.data : question))
  } catch (error) {
    formError.value = extractErrorMessage(error, t('adminRequestQuestionsPage.errors.toggleFailed'))
  }
}

async function reorderQuestions(orderedIds: number[]) {
  if (pagination.value.last_page > 1) {
    formError.value = t('adminRequestQuestionsPage.errors.reorderNeedsSinglePage')
    return
  }

  clearMessages()
  isReordering.value = true

  const previous = [...questions.value]
  const orderedMap = new Map(previous.map((item) => [item.id, item]))
  questions.value = orderedIds
    .map((id, index) => {
      const existing = orderedMap.get(id)
      if (!existing) return null
      return {
        ...existing,
        sort_order: index + 1,
      }
    })
    .filter((item): item is RequestQuestionItem => item !== null)

  try {
    const { data } = await reorderRequestQuestions(orderedIds)
    successMessage.value = data.message
    await fetchQuestions(pagination.value.current_page)
  } catch (error) {
    questions.value = previous
    formError.value = extractErrorMessage(error, t('adminRequestQuestionsPage.errors.reorderFailed'))
  } finally {
    isReordering.value = false
  }
}

function extractErrorMessage(error: unknown, fallback: string) {
  if (axios.isAxiosError(error)) {
    return error.response?.data?.message ?? fallback
  }

  return fallback
}
</script>

<template>
  <div class="admin-question-page">
    <section class="admin-hero admin-reveal-up">
      <div class="admin-hero__content">
        <span class="admin-hero__eyebrow">{{ t('adminRequestQuestionsPage.hero.eyebrow') }}</span>
        <h2>{{ t('adminRequestQuestionsPage.hero.title') }}</h2>
        <p>
          {{ t('adminRequestQuestionsPage.hero.subtitle') }}
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveQuestion">
          {{ isSaving ? (isEditing ? t('adminRequestQuestionsPage.actions.updating') : t('adminRequestQuestionsPage.actions.saving')) : isEditing ? t('adminRequestQuestionsPage.actions.updateQuestion') : t('adminRequestQuestionsPage.actions.saveQuestion') }}
        </button>
        <button type="button" class="admin-secondary-btn" @click="resetForm">
          {{ isEditing ? t('adminRequestQuestionsPage.actions.cancelEdit') : t('adminRequestQuestionsPage.actions.resetForm') }}
        </button>
      </div>
    </section>

    <div v-if="formError" class="admin-alert admin-alert--error">
      {{ formError }}
    </div>

    <div v-if="successMessage" class="admin-alert admin-alert--success">
      {{ successMessage }}
    </div>

    <section class="admin-question-stats-grid admin-question-stats-grid--balanced admin-reveal-up admin-reveal-delay-1">
      <article
        v-for="stat in stats"
        :key="stat.label"
        class="admin-question-stat"
        :class="`tone-${stat.tone}`"
      >
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </section>

    <section class="admin-question-builder-grid">
      <AdminQuestionBuilderForm
        v-model="form"
        :question-type-options="questionTypeOptions"
        :show-options="needsOptions"
        :is-editing="isEditing"
        :is-saving="isSaving"
        :errors="fieldErrors"
        @save="saveQuestion"
        @reset="resetForm"
      />

      <AdminQuestionPreviewCard
        :form="form"
        :options="parsedOptions"
        :show-options="needsOptions"
      />
    </section>

    <section class="admin-question-library-grid">
      <div class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ t('adminRequestQuestionsPage.guide.eyebrow') }}</span>
            <h2>{{ t('adminRequestQuestionsPage.guide.title') }}</h2>
          </div>
        </div>

        <div class="admin-question-type-grid">
          <article
            v-for="option in questionTypeOptions"
            :key="option.value"
            class="admin-question-type-card"
          >
            <strong>{{ option.label }}</strong>
            <p>{{ option.helper }}</p>
          </article>
        </div>
      </div>

      <AdminQuestionLibraryTable
        :rows="questions"
        :loading="isLoading"
        :reordering="isReordering"
        @edit="editQuestion"
        @toggle="toggleQuestion"
        @reorder="reorderQuestions"
      />
      <AppPagination :pagination="pagination" :disabled="isLoading || isReordering" @change="fetchQuestions" />
    </section>
  </div>
</template>
