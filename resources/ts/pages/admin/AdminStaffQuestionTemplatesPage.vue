<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import type {
  FinanceStaffQuestionTemplateItem,
  FinanceStaffQuestionTemplatePayload,
  StaffQuestionType,
} from '@/services/financeStaffQuestionTemplates'
import {
  createFinanceStaffQuestionTemplate,
  listFinanceStaffQuestionTemplates,
  reorderFinanceStaffQuestionTemplates,
  toggleFinanceStaffQuestionTemplateActive,
  updateFinanceStaffQuestionTemplate,
} from '@/services/financeStaffQuestionTemplates'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'

type StaffQuestionForm = {
  id: number | null
  code: string
  question_text_en: string
  question_text_ar: string
  question_type: StaffQuestionType
  placeholder_en: string
  placeholder_ar: string
  help_text_en: string
  help_text_ar: string
  validation_rules: string
  sort_order: number
  is_required: boolean
  is_active: boolean
  options_text: string
}

const { t, locale } = useI18n()

const questionTypeOptions = computed<Array<{ value: StaffQuestionType; label: string; helper: string }>>(() => [
  { value: 'text', label: t('adminStaffQuestionTemplatesPage.types.text.label'), helper: t('adminStaffQuestionTemplatesPage.types.text.helper') },
  { value: 'textarea', label: t('adminStaffQuestionTemplatesPage.types.textarea.label'), helper: t('adminStaffQuestionTemplatesPage.types.textarea.helper') },
  { value: 'select', label: t('adminStaffQuestionTemplatesPage.types.select.label'), helper: t('adminStaffQuestionTemplatesPage.types.select.helper') },
  { value: 'radio', label: t('adminStaffQuestionTemplatesPage.types.radio.label'), helper: t('adminStaffQuestionTemplatesPage.types.radio.helper') },
  { value: 'checkbox', label: t('adminStaffQuestionTemplatesPage.types.checkbox.label'), helper: t('adminStaffQuestionTemplatesPage.types.checkbox.helper') },
  { value: 'number', label: t('adminStaffQuestionTemplatesPage.types.number.label'), helper: t('adminStaffQuestionTemplatesPage.types.number.helper') },
  { value: 'date', label: t('adminStaffQuestionTemplatesPage.types.date.label'), helper: t('adminStaffQuestionTemplatesPage.types.date.helper') },
  { value: 'email', label: t('adminStaffQuestionTemplatesPage.types.email.label'), helper: t('adminStaffQuestionTemplatesPage.types.email.helper') },
  { value: 'phone', label: t('adminStaffQuestionTemplatesPage.types.phone.label'), helper: t('adminStaffQuestionTemplatesPage.types.phone.helper') },
  { value: 'currency', label: t('adminStaffQuestionTemplatesPage.types.currency.label'), helper: t('adminStaffQuestionTemplatesPage.types.currency.helper') },
])

const optionDrivenTypes: StaffQuestionType[] = ['select', 'radio', 'checkbox']

const templates = ref<FinanceStaffQuestionTemplateItem[]>([])
const pagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 20 })
const isLoading = ref(false)
const isSaving = ref(false)
const isReordering = ref(false)
const formError = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<StaffQuestionForm>(createDefaultForm())

const isEditing = computed(() => form.value.id !== null)
const needsOptions = computed(() => optionDrivenTypes.includes(form.value.question_type))
const parsedOptions = computed(() =>
  form.value.options_text
    .split('\n')
    .map((item) => item.trim())
    .filter(Boolean),
)

const stats = computed(() => {
  const total = pagination.value.total
  const active = templates.value.filter((item) => item.is_active).length
  const required = templates.value.filter((item) => item.is_required).length
  const bilingual = templates.value.filter((item) => !!item.question_text_ar?.trim()).length

  return [
    { label: t('adminStaffQuestionTemplatesPage.stats.totalQuestions'), value: String(total), tone: 'violet' },
    { label: t('adminStaffQuestionTemplatesPage.stats.activeQuestions'), value: String(active), tone: 'emerald' },
    { label: t('adminStaffQuestionTemplatesPage.stats.requiredQuestions'), value: String(required), tone: 'blue' },
    { label: t('adminStaffQuestionTemplatesPage.stats.bilingualReady'), value: String(bilingual), tone: 'amber' },
  ]
})

const previewQuestionText = computed(() =>
  locale.value === 'ar'
    ? form.value.question_text_ar.trim() || form.value.question_text_en.trim()
    : form.value.question_text_en.trim() || form.value.question_text_ar.trim(),
)

const previewPlaceholder = computed(() =>
  locale.value === 'ar'
    ? form.value.placeholder_ar.trim() || form.value.placeholder_en.trim()
    : form.value.placeholder_en.trim() || form.value.placeholder_ar.trim(),
)

const previewHelpText = computed(() =>
  locale.value === 'ar'
    ? form.value.help_text_ar.trim() || form.value.help_text_en.trim()
    : form.value.help_text_en.trim() || form.value.help_text_ar.trim(),
)

onMounted(async () => {
  await fetchTemplates()
})

function createDefaultForm(): StaffQuestionForm {
  return {
    id: null,
    code: '',
    question_text_en: '',
    question_text_ar: '',
    question_type: 'text',
    placeholder_en: '',
    placeholder_ar: '',
    help_text_en: '',
    help_text_ar: '',
    validation_rules: '',
    sort_order: templates.value.length + 1,
    is_required: true,
    is_active: true,
    options_text: '',
  }
}

function clearMessages() {
  formError.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
}

function firstFieldError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

function localizedQuestionText(row: FinanceStaffQuestionTemplateItem) {
  const en = String(row.question_text_en || '').trim()
  const ar = String(row.question_text_ar || '').trim()
  return locale.value === 'ar' ? (ar || en || '—') : (en || ar || '—')
}

function secondaryQuestionText(row: FinanceStaffQuestionTemplateItem) {
  const en = String(row.question_text_en || '').trim()
  const ar = String(row.question_text_ar || '').trim()
  return locale.value === 'ar' ? (en || ar || '—') : (ar || en || '—')
}

function questionTypeLabel(type: StaffQuestionType) {
  const matched = questionTypeOptions.value.find((option) => option.value === type)
  return matched?.label || type
}

async function fetchTemplates(page = pagination.value.current_page) {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listFinanceStaffQuestionTemplates({
      page,
      per_page: pagination.value.per_page,
    })
    templates.value = data.data
    pagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: pagination.value.per_page }

    if (!isEditing.value) {
      form.value.sort_order = Math.max(pagination.value.total, templates.value.length) + 1
    }
  } catch (error) {
    formError.value = extractErrorMessage(error, t('adminStaffQuestionTemplatesPage.errors.loadFailed'))
  } finally {
    isLoading.value = false
  }
}

function resetForm() {
  clearMessages()
  form.value = createDefaultForm()
}

function buildPayload(): FinanceStaffQuestionTemplatePayload {
  return {
    code: form.value.code.trim() || null,
    question_text_en: form.value.question_text_en.trim(),
    question_text_ar: form.value.question_text_ar.trim() || null,
    question_type: form.value.question_type,
    options_json: needsOptions.value ? parsedOptions.value : null,
    placeholder_en: form.value.placeholder_en.trim() || null,
    placeholder_ar: form.value.placeholder_ar.trim() || null,
    help_text_en: form.value.help_text_en.trim() || null,
    help_text_ar: form.value.help_text_ar.trim() || null,
    validation_rules: form.value.validation_rules.trim() || null,
    is_required: form.value.is_required,
    sort_order: form.value.sort_order,
    is_active: form.value.is_active,
  }
}

async function saveTemplate() {
  clearMessages()
  isSaving.value = true

  try {
    if (isEditing.value && form.value.id) {
      const { data } = await updateFinanceStaffQuestionTemplate(form.value.id, buildPayload())
      successMessage.value = data.message
    } else {
      const { data } = await createFinanceStaffQuestionTemplate(buildPayload())
      successMessage.value = data.message
    }

    await fetchTemplates()
    resetForm()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? t('adminStaffQuestionTemplatesPage.errors.saveFailed')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = t('adminStaffQuestionTemplatesPage.errors.saveFailed')
    }
  } finally {
    isSaving.value = false
  }
}

function editTemplate(row: FinanceStaffQuestionTemplateItem) {
  clearMessages()
  form.value = {
    id: row.id,
    code: row.code ?? '',
    question_text_en: row.question_text_en,
    question_text_ar: row.question_text_ar ?? '',
    question_type: row.question_type,
    placeholder_en: row.placeholder_en ?? '',
    placeholder_ar: row.placeholder_ar ?? '',
    help_text_en: row.help_text_en ?? '',
    help_text_ar: row.help_text_ar ?? '',
    validation_rules: row.validation_rules ?? '',
    sort_order: row.sort_order,
    is_required: row.is_required,
    is_active: row.is_active,
    options_text: row.options_json.join('\n'),
  }

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleTemplate(row: FinanceStaffQuestionTemplateItem) {
  clearMessages()

  try {
    const { data } = await toggleFinanceStaffQuestionTemplateActive(row.id)
    successMessage.value = data.message
    templates.value = templates.value.map((item) => (item.id === row.id ? data.data : item))
  } catch (error) {
    formError.value = extractErrorMessage(error, t('adminStaffQuestionTemplatesPage.errors.toggleFailed'))
  }
}

async function reorderTemplates(direction: 'up' | 'down', currentIndex: number) {
  if (pagination.value.last_page > 1) {
    formError.value = t('adminStaffQuestionTemplatesPage.errors.reorderNeedsSinglePage')
    return
  }

  if (
    isReordering.value ||
    (direction === 'up' && currentIndex === 0) ||
    (direction === 'down' && currentIndex === templates.value.length - 1)
  ) {
    return
  }

  clearMessages()
  isReordering.value = true

  const previous = [...templates.value]
  const updated = [...templates.value]
  const swapIndex = direction === 'up' ? currentIndex - 1 : currentIndex + 1
  const [movedItem] = updated.splice(currentIndex, 1)
  updated.splice(swapIndex, 0, movedItem)

  templates.value = updated.map((item, index) => ({
    ...item,
    sort_order: index + 1,
  }))

  try {
    const orderedIds = templates.value.map((item) => item.id)
    const { data } = await reorderFinanceStaffQuestionTemplates(orderedIds)
    successMessage.value = data.message
    await fetchTemplates(pagination.value.current_page)
  } catch (error) {
    templates.value = previous
    formError.value = extractErrorMessage(error, t('adminStaffQuestionTemplatesPage.errors.reorderFailed'))
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
        <span class="admin-hero__eyebrow">{{ t('adminStaffQuestionTemplatesPage.hero.eyebrow') }}</span>
        <h2>{{ t('adminStaffQuestionTemplatesPage.hero.title') }}</h2>
        <p>{{ t('adminStaffQuestionTemplatesPage.hero.subtitle') }}</p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveTemplate">
          {{
            isSaving
              ? isEditing
                ? t('adminStaffQuestionTemplatesPage.actions.updating')
                : t('adminStaffQuestionTemplatesPage.actions.saving')
              : isEditing
                ? t('adminStaffQuestionTemplatesPage.actions.updateQuestion')
                : t('adminStaffQuestionTemplatesPage.actions.saveQuestion')
          }}
        </button>
        <button type="button" class="admin-secondary-btn" @click="resetForm">
          {{ isEditing ? t('adminStaffQuestionTemplatesPage.actions.cancelEdit') : t('adminStaffQuestionTemplatesPage.actions.resetForm') }}
        </button>
      </div>
    </section>

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

    <div v-if="formError" class="admin-alert admin-alert--error">{{ formError }}</div>
    <div v-if="successMessage" class="admin-alert admin-alert--success">{{ successMessage }}</div>

    <section class="admin-question-builder-grid">
      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ t('adminStaffQuestionTemplatesPage.form.eyebrow') }}</span>
            <h2>{{ t('adminStaffQuestionTemplatesPage.form.title') }}</h2>
          </div>
          <button type="button" class="admin-panel__action" @click="resetForm">
            {{ isEditing ? t('adminStaffQuestionTemplatesPage.actions.cancelEdit') : t('adminStaffQuestionTemplatesPage.actions.resetForm') }}
          </button>
        </div>

        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-form-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.code') }}</span>
            <input v-model="form.code" type="text" class="admin-form-input" :class="{ 'has-error': firstFieldError('code') }">
            <small v-if="firstFieldError('code')" class="admin-form-error">{{ firstFieldError('code') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.type') }}</span>
            <select v-model="form.question_type" class="admin-form-select" :class="{ 'has-error': firstFieldError('question_type') }">
              <option v-for="type in questionTypeOptions" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
            <small v-if="firstFieldError('question_type')" class="admin-form-error">{{ firstFieldError('question_type') }}</small>
          </label>

          <label class="admin-form-field admin-form-field--full">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.questionEn') }}</span>
            <textarea v-model="form.question_text_en" rows="3" class="admin-form-textarea" :class="{ 'has-error': firstFieldError('question_text_en') }"></textarea>
            <small v-if="firstFieldError('question_text_en')" class="admin-form-error">{{ firstFieldError('question_text_en') }}</small>
          </label>

          <label class="admin-form-field admin-form-field--full">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.questionAr') }}</span>
            <textarea v-model="form.question_text_ar" rows="3" class="admin-form-textarea" :class="{ 'has-error': firstFieldError('question_text_ar') }" dir="rtl"></textarea>
            <small v-if="firstFieldError('question_text_ar')" class="admin-form-error">{{ firstFieldError('question_text_ar') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.placeholderEn') }}</span>
            <input v-model="form.placeholder_en" type="text" class="admin-form-input" :class="{ 'has-error': firstFieldError('placeholder_en') }">
            <small v-if="firstFieldError('placeholder_en')" class="admin-form-error">{{ firstFieldError('placeholder_en') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.placeholderAr') }}</span>
            <input v-model="form.placeholder_ar" type="text" class="admin-form-input" :class="{ 'has-error': firstFieldError('placeholder_ar') }" dir="rtl">
            <small v-if="firstFieldError('placeholder_ar')" class="admin-form-error">{{ firstFieldError('placeholder_ar') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.helpTextEn') }}</span>
            <textarea v-model="form.help_text_en" rows="3" class="admin-form-textarea" :class="{ 'has-error': firstFieldError('help_text_en') }"></textarea>
            <small v-if="firstFieldError('help_text_en')" class="admin-form-error">{{ firstFieldError('help_text_en') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.helpTextAr') }}</span>
            <textarea v-model="form.help_text_ar" rows="3" class="admin-form-textarea" :class="{ 'has-error': firstFieldError('help_text_ar') }" dir="rtl"></textarea>
            <small v-if="firstFieldError('help_text_ar')" class="admin-form-error">{{ firstFieldError('help_text_ar') }}</small>
          </label>

          <label v-if="needsOptions" class="admin-form-field admin-form-field--full">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.options') }}</span>
            <textarea
              v-model="form.options_text"
              rows="5"
              class="admin-form-textarea"
              :class="{ 'has-error': firstFieldError('options_json') }"
              :placeholder="t('adminStaffQuestionTemplatesPage.form.optionsPlaceholder')"
            ></textarea>
            <small v-if="firstFieldError('options_json')" class="admin-form-error">{{ firstFieldError('options_json') }}</small>
          </label>

          <label class="admin-form-field admin-form-field--full">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.validationRules') }}</span>
            <input
              v-model="form.validation_rules"
              type="text"
              class="admin-form-input"
              :class="{ 'has-error': firstFieldError('validation_rules') }"
              :placeholder="t('adminStaffQuestionTemplatesPage.form.validationRulesPlaceholder')"
            >
            <small v-if="firstFieldError('validation_rules')" class="admin-form-error">{{ firstFieldError('validation_rules') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.sortOrder') }}</span>
            <input v-model.number="form.sort_order" type="number" min="0" class="admin-form-input" :class="{ 'has-error': firstFieldError('sort_order') }">
            <small v-if="firstFieldError('sort_order')" class="admin-form-error">{{ firstFieldError('sort_order') }}</small>
          </label>

          <div class="admin-form-switches admin-form-field--full">
            <label class="admin-switch-card">
              <input v-model="form.is_required" type="checkbox">
              <div>
                <strong>{{ t('adminStaffQuestionTemplatesPage.form.required') }}</strong>
                <span>{{ t('adminStaffQuestionTemplatesPage.preview.required') }}</span>
              </div>
            </label>
            <label class="admin-switch-card">
              <input v-model="form.is_active" type="checkbox">
              <div>
                <strong>{{ t('adminStaffQuestionTemplatesPage.form.active') }}</strong>
                <span>{{ t('adminStaffQuestionTemplatesPage.preview.active') }}</span>
              </div>
            </label>
          </div>
        </div>

        <div class="admin-form-actions">
          <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveTemplate">
            {{
              isSaving
                ? isEditing
                  ? t('adminStaffQuestionTemplatesPage.actions.updating')
                  : t('adminStaffQuestionTemplatesPage.actions.saving')
                : isEditing
                  ? t('adminStaffQuestionTemplatesPage.actions.updateQuestion')
                  : t('adminStaffQuestionTemplatesPage.actions.saveQuestion')
            }}
          </button>
          <button type="button" class="admin-secondary-btn" @click="resetForm">
            {{ isEditing ? t('adminStaffQuestionTemplatesPage.actions.cancelEdit') : t('adminStaffQuestionTemplatesPage.actions.resetForm') }}
          </button>
        </div>
      </section>

      <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ t('adminStaffQuestionTemplatesPage.preview.eyebrow') }}</span>
            <h2>{{ t('adminStaffQuestionTemplatesPage.preview.title') }}</h2>
          </div>
        </div>

        <div class="admin-question-preview">
          <div class="admin-question-preview__card">
            <div class="admin-question-preview__meta">
              <span class="admin-status-pill">{{ form.question_type }}</span>
              <span class="admin-question-preview__code">{{ form.code || t('adminStaffQuestionTemplatesPage.preview.autoCode') }}</span>
              <span class="admin-status-pill" :class="{ 'is-muted': !form.is_required }">
                {{ form.is_required ? t('adminStaffQuestionTemplatesPage.preview.required') : t('adminStaffQuestionTemplatesPage.preview.optional') }}
              </span>
              <span class="admin-status-pill" :class="form.is_active ? 'is-success' : 'is-muted'">
                {{ form.is_active ? t('adminStaffQuestionTemplatesPage.preview.active') : t('adminStaffQuestionTemplatesPage.preview.inactive') }}
              </span>
            </div>

            <label class="admin-question-preview__label">
              {{ previewQuestionText || t('adminStaffQuestionTemplatesPage.preview.emptyQuestion') }}
              <span v-if="form.is_required" class="admin-question-preview__required">*</span>
            </label>

            <p v-if="previewHelpText" class="admin-question-preview__help">{{ previewHelpText }}</p>

            <div v-if="needsOptions && parsedOptions.length" class="admin-question-preview__options">
              <label v-for="option in parsedOptions" :key="option" class="admin-question-preview__option">
                <input :type="form.question_type === 'checkbox' ? 'checkbox' : 'radio'" disabled>
                <span>{{ option }}</span>
              </label>
            </div>

            <input
              v-else-if="!['textarea', 'select', 'checkbox', 'radio'].includes(form.question_type)"
              type="text"
              class="admin-question-preview__input"
              :placeholder="previewPlaceholder || t('adminStaffQuestionTemplatesPage.preview.placeholderFallback')"
              disabled
            >

            <textarea
              v-else-if="form.question_type === 'textarea'"
              rows="4"
              class="admin-question-preview__textarea"
              :placeholder="previewPlaceholder || t('adminStaffQuestionTemplatesPage.preview.placeholderFallback')"
              disabled
            ></textarea>

            <select v-else-if="form.question_type === 'select'" class="admin-form-select" disabled>
              <option>{{ previewPlaceholder || t('adminStaffQuestionTemplatesPage.preview.selectPlaceholder') }}</option>
            </select>

            <div v-else class="admin-question-preview__options">
              <label
                v-for="option in parsedOptions.length ? parsedOptions : [t('adminStaffQuestionTemplatesPage.preview.choiceFallback')]"
                :key="option"
                class="admin-question-preview__option"
              >
                <input :type="form.question_type === 'checkbox' ? 'checkbox' : 'radio'" disabled>
                <span>{{ option }}</span>
              </label>
            </div>
          </div>

          <div class="admin-question-preview__notes">
            <article class="admin-question-preview__note">
              <span>{{ t('adminStaffQuestionTemplatesPage.form.validationRules') }}</span>
              <strong>{{ form.validation_rules || t('adminStaffQuestionTemplatesPage.preview.noValidationRules') }}</strong>
            </article>
            <article class="admin-question-preview__note">
              <span>{{ t('adminStaffQuestionTemplatesPage.form.sortOrder') }}</span>
              <strong>{{ form.sort_order }}</strong>
            </article>
            <article class="admin-question-preview__note">
              <span>{{ t('adminStaffQuestionTemplatesPage.table.status') }}</span>
              <strong>{{ form.is_active ? t('adminStaffQuestionTemplatesPage.table.active') : t('adminStaffQuestionTemplatesPage.table.inactive') }}</strong>
            </article>
          </div>
        </div>
      </section>
    </section>

    <section class="admin-question-library-grid">
      <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ t('adminStaffQuestionTemplatesPage.preview.eyebrow') }}</span>
            <h2>{{ t('adminStaffQuestionTemplatesPage.preview.title') }}</h2>
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
      </section>

      <section class="admin-panel admin-reveal-up">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ t('adminStaffQuestionTemplatesPage.library.eyebrow') }}</span>
            <h2>{{ t('adminStaffQuestionTemplatesPage.library.title') }}</h2>
          </div>
          <span class="admin-panel__action is-static">{{ pagination.total }}</span>
        </div>

        <div v-if="isLoading" class="admin-table-empty">{{ t('adminStaffQuestionTemplatesPage.states.loading') }}</div>

        <template v-else>
          <div v-if="templates.length === 0" class="admin-table-empty">
            {{ t('adminStaffQuestionTemplatesPage.states.empty') }}
          </div>

          <div v-else class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>{{ t('adminStaffQuestionTemplatesPage.table.code') }}</th>
                  <th>{{ t('adminStaffQuestionTemplatesPage.table.questionEn') }}</th>
                  <th>{{ t('adminStaffQuestionTemplatesPage.table.questionAr') }}</th>
                  <th>{{ t('adminStaffQuestionTemplatesPage.table.type') }}</th>
                  <th>{{ t('adminStaffQuestionTemplatesPage.table.required') }}</th>
                  <th>{{ t('adminStaffQuestionTemplatesPage.table.status') }}</th>
                  <th>{{ t('adminStaffQuestionTemplatesPage.table.actions') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, index) in templates" :key="row.id">
                  <td>{{ index + 1 }}</td>
                  <td>{{ row.code || '—' }}</td>
                  <td>
                    <div class="admin-question-table__text">
                      <strong>{{ localizedQuestionText(row) }}</strong>
                    </div>
                  </td>
                  <td>
                    <div class="admin-question-table__text" :dir="locale === 'ar' ? 'ltr' : 'rtl'">
                      <strong>{{ secondaryQuestionText(row) }}</strong>
                    </div>
                  </td>
                  <td>{{ questionTypeLabel(row.question_type) }}</td>
                  <td>{{ row.is_required ? t('adminStaffQuestionTemplatesPage.table.yes') : t('adminStaffQuestionTemplatesPage.table.no') }}</td>
                  <td>
                    <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                      {{ row.is_active ? t('adminStaffQuestionTemplatesPage.table.active') : t('adminStaffQuestionTemplatesPage.table.inactive') }}
                    </span>
                  </td>
                  <td>
                    <div class="admin-table-actions">
                      <button type="button" class="admin-inline-link" @click="editTemplate(row)">
                        {{ t('adminStaffQuestionTemplatesPage.table.edit') }}
                      </button>
                      <button type="button" class="admin-inline-link" @click="toggleTemplate(row)">
                        {{ row.is_active ? t('adminStaffQuestionTemplatesPage.table.deactivate') : t('adminStaffQuestionTemplatesPage.table.activate') }}
                      </button>
                      <button type="button" class="admin-inline-link" :disabled="index === 0 || isReordering" @click="reorderTemplates('up', index)">
                        {{ t('adminStaffQuestionTemplatesPage.table.up') }}
                      </button>
                      <button type="button" class="admin-inline-link" :disabled="index === templates.length - 1 || isReordering" @click="reorderTemplates('down', index)">
                        {{ t('adminStaffQuestionTemplatesPage.table.down') }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <AppPagination :pagination="pagination" :disabled="isLoading || isReordering" @change="fetchTemplates" />
        </template>
      </section>
    </section>
  </div>
</template>
