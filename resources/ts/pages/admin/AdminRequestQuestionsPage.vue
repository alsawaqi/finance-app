<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
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

const questionTypeOptions: Array<{ value: QuestionType; label: string; helper: string }> = [
  { value: 'text', label: 'Text', helper: 'Single line answer' },
  { value: 'textarea', label: 'Textarea', helper: 'Longer free text answer' },
  { value: 'select', label: 'Select', helper: 'Dropdown choices from options_json' },
  { value: 'radio', label: 'Radio', helper: 'Single choice options' },
  { value: 'checkbox', label: 'Checkbox', helper: 'Multi-select options' },
  { value: 'number', label: 'Number', helper: 'Numeric answer' },
  { value: 'date', label: 'Date', helper: 'Date picker' },
  { value: 'email', label: 'Email', helper: 'Email-formatted answer' },
  { value: 'phone', label: 'Phone', helper: 'Phone number answer' },
  { value: 'currency', label: 'Currency', helper: 'Amount / finance value' },
]

const questions = ref<RequestQuestionItem[]>([])
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
  const total = questions.value.length
  const active = questions.value.filter((item) => item.is_active).length
  const required = questions.value.filter((item) => item.is_required).length
  const choiceBased = questions.value.filter((item) => optionDrivenTypes.includes(item.question_type)).length

  return [
    { label: 'Total questions', value: String(total), tone: 'violet' },
    { label: 'Active questions', value: String(active), tone: 'emerald' },
    { label: 'Required fields', value: String(required), tone: 'blue' },
    { label: 'Choice based', value: String(choiceBased), tone: 'amber' },
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

async function fetchQuestions() {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listRequestQuestions()
    questions.value = data.data

    if (!isEditing.value) {
      form.value.sort_order = questions.value.length + 1
    }
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to load request questions right now.')
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
      formError.value = error.response?.data?.message ?? 'Unable to save the request question.'
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = 'Unable to save the request question.'
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
    formError.value = extractErrorMessage(error, 'Unable to update question status right now.')
  }
}

async function reorderQuestions(orderedIds: number[]) {
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
    questions.value = data.data
    successMessage.value = data.message
  } catch (error) {
    questions.value = previous
    formError.value = extractErrorMessage(error, 'Unable to reorder request questions right now.')
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
        <span class="admin-hero__eyebrow">Request Question Builder</span>
        <h2>Define the request questions your clients will answer before submission.</h2>
        <p>
          This page is now wired to Laravel CRUD for the <code>request_questions</code> table, including
          list, create, update, activate/deactivate, and drag-and-drop sort order.
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveQuestion">
          {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update question' : 'Save question' }}
        </button>
        <button type="button" class="admin-secondary-btn" @click="resetForm">
          {{ isEditing ? 'Cancel edit' : 'Reset form' }}
        </button>
      </div>
    </section>

    <div v-if="formError" class="admin-alert admin-alert--error">
      {{ formError }}
    </div>

    <div v-if="successMessage" class="admin-alert admin-alert--success">
      {{ successMessage }}
    </div>

    <section class="admin-question-stats-grid admin-reveal-up admin-reveal-delay-1">
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
            <span class="admin-panel__eyebrow">Question type guide</span>
            <h2>How each field behaves on the client side</h2>
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
    </section>
  </div>
</template>
