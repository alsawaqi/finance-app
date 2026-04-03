<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
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
  const total = templates.value.length
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

async function fetchTemplates() {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listFinanceStaffQuestionTemplates()
    templates.value = data.data

    if (!isEditing.value) {
      form.value.sort_order = templates.value.length + 1
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
    templates.value = data.data
    successMessage.value = data.message
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

    <section class="admin-stats-grid admin-reveal-up">
      <article
        v-for="stat in stats"
        :key="stat.label"
        class="admin-stat-card"
        :class="`admin-stat-card--${stat.tone}`"
      >
        <div class="admin-stat-card__label">{{ stat.label }}</div>
        <div class="admin-stat-card__value">{{ stat.value }}</div>
      </article>
    </section>

    <section v-if="formError" class="admin-feedback admin-feedback--error admin-reveal-up">
      <i class="fas fa-circle-exclamation"></i>
      <span>{{ formError }}</span>
    </section>

    <section v-if="successMessage" class="admin-feedback admin-feedback--success admin-reveal-up">
      <i class="fas fa-circle-check"></i>
      <span>{{ successMessage }}</span>
    </section>

    <div class="admin-question-layout admin-reveal-up">
      <section class="admin-question-form-card">
        <header class="admin-section-heading">
          <div>
            <span class="admin-section-heading__eyebrow">{{ t('adminStaffQuestionTemplatesPage.form.eyebrow') }}</span>
            <h3>{{ t('adminStaffQuestionTemplatesPage.form.title') }}</h3>
          </div>
          <span class="admin-chip">{{ isEditing ? t('adminStaffQuestionTemplatesPage.form.editing') : t('adminStaffQuestionTemplatesPage.form.creating') }}</span>
        </header>

        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.code') }}</span>
            <input v-model="form.code" type="text" class="admin-input" />
            <small class="admin-field__hint">{{ firstFieldError('code') }}</small>
          </label>

          <label class="admin-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.type') }}</span>
            <select v-model="form.question_type" class="admin-input">
              <option v-for="type in questionTypeOptions" :key="type.value" :value="type.value">
                {{ type.label }}
              </option>
            </select>
            <small class="admin-field__hint">{{ firstFieldError('question_type') }}</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.questionEn') }}</span>
            <textarea v-model="form.question_text_en" rows="3" class="admin-input admin-input--textarea" />
            <small class="admin-field__hint">{{ firstFieldError('question_text_en') }}</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.questionAr') }}</span>
            <textarea v-model="form.question_text_ar" rows="3" class="admin-input admin-input--textarea" dir="rtl" />
            <small class="admin-field__hint">{{ firstFieldError('question_text_ar') }}</small>
          </label>

          <label class="admin-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.placeholderEn') }}</span>
            <input v-model="form.placeholder_en" type="text" class="admin-input" />
            <small class="admin-field__hint">{{ firstFieldError('placeholder_en') }}</small>
          </label>

          <label class="admin-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.placeholderAr') }}</span>
            <input v-model="form.placeholder_ar" type="text" class="admin-input" dir="rtl" />
            <small class="admin-field__hint">{{ firstFieldError('placeholder_ar') }}</small>
          </label>

          <label class="admin-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.helpTextEn') }}</span>
            <textarea v-model="form.help_text_en" rows="3" class="admin-input admin-input--textarea" />
            <small class="admin-field__hint">{{ firstFieldError('help_text_en') }}</small>
          </label>

          <label class="admin-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.helpTextAr') }}</span>
            <textarea v-model="form.help_text_ar" rows="3" class="admin-input admin-input--textarea" dir="rtl" />
            <small class="admin-field__hint">{{ firstFieldError('help_text_ar') }}</small>
          </label>

          <label class="admin-field admin-field--full" v-if="needsOptions">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.options') }}</span>
            <textarea
              v-model="form.options_text"
              rows="5"
              class="admin-input admin-input--textarea"
              :placeholder="t('adminStaffQuestionTemplatesPage.form.optionsPlaceholder')"
            />
            <small class="admin-field__hint">{{ firstFieldError('options_json') }}</small>
          </label>

          <label class="admin-field admin-field--full">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.validationRules') }}</span>
            <input
              v-model="form.validation_rules"
              type="text"
              class="admin-input"
              :placeholder="t('adminStaffQuestionTemplatesPage.form.validationRulesPlaceholder')"
            />
            <small class="admin-field__hint">{{ firstFieldError('validation_rules') }}</small>
          </label>

          <label class="admin-field">
            <span>{{ t('adminStaffQuestionTemplatesPage.form.sortOrder') }}</span>
            <input v-model.number="form.sort_order" type="number" min="0" class="admin-input" />
            <small class="admin-field__hint">{{ firstFieldError('sort_order') }}</small>
          </label>

          <div class="admin-field admin-field--checkboxes">
            <label class="admin-checkbox">
              <input v-model="form.is_required" type="checkbox" />
              <span>{{ t('adminStaffQuestionTemplatesPage.form.required') }}</span>
            </label>

            <label class="admin-checkbox">
              <input v-model="form.is_active" type="checkbox" />
              <span>{{ t('adminStaffQuestionTemplatesPage.form.active') }}</span>
            </label>
          </div>
        </div>
      </section>

      <section class="admin-question-preview-card">
        <header class="admin-section-heading">
          <div>
            <span class="admin-section-heading__eyebrow">{{ t('adminStaffQuestionTemplatesPage.preview.eyebrow') }}</span>
            <h3>{{ t('adminStaffQuestionTemplatesPage.preview.title') }}</h3>
          </div>
        </header>

        <div class="admin-question-preview">
          <div class="admin-question-preview__meta">
            <span class="admin-chip">{{ form.question_type }}</span>
            <span class="admin-chip" :class="{ 'admin-chip--muted': !form.is_required }">
              {{ form.is_required ? t('adminStaffQuestionTemplatesPage.preview.required') : t('adminStaffQuestionTemplatesPage.preview.optional') }}
            </span>
            <span class="admin-chip" :class="{ 'admin-chip--muted': !form.is_active }">
              {{ form.is_active ? t('adminStaffQuestionTemplatesPage.preview.active') : t('adminStaffQuestionTemplatesPage.preview.inactive') }}
            </span>
          </div>

          <h4>{{ previewQuestionText || t('adminStaffQuestionTemplatesPage.preview.emptyQuestion') }}</h4>

          <p v-if="previewHelpText" class="admin-question-preview__help">
            {{ previewHelpText }}
          </p>

          <div v-if="needsOptions && parsedOptions.length" class="admin-question-preview__options">
            <span v-for="option in parsedOptions" :key="option" class="admin-question-preview__option">
              {{ option }}
            </span>
          </div>

          <div class="admin-question-preview__input">
            <input
              v-if="!['textarea', 'checkbox', 'radio', 'select'].includes(form.question_type)"
              type="text"
              class="admin-input"
              :placeholder="previewPlaceholder || t('adminStaffQuestionTemplatesPage.preview.placeholderFallback')"
              disabled
            />

            <textarea
              v-else-if="form.question_type === 'textarea'"
              rows="4"
              class="admin-input admin-input--textarea"
              :placeholder="previewPlaceholder || t('adminStaffQuestionTemplatesPage.preview.placeholderFallback')"
              disabled
            />

            <select v-else-if="form.question_type === 'select'" class="admin-input" disabled>
              <option>{{ previewPlaceholder || t('adminStaffQuestionTemplatesPage.preview.selectPlaceholder') }}</option>
            </select>

            <div v-else class="admin-question-preview__choice-list">
              <label
                v-for="option in parsedOptions.length ? parsedOptions : [t('adminStaffQuestionTemplatesPage.preview.choiceFallback')]"
                :key="option"
                class="admin-choice-row"
              >
                <input :type="form.question_type === 'checkbox' ? 'checkbox' : 'radio'" disabled />
                <span>{{ option }}</span>
              </label>
            </div>
          </div>
        </div>
      </section>
    </div>

    <section class="admin-library-card admin-reveal-up">
      <header class="admin-section-heading">
        <div>
          <span class="admin-section-heading__eyebrow">{{ t('adminStaffQuestionTemplatesPage.library.eyebrow') }}</span>
          <h3>{{ t('adminStaffQuestionTemplatesPage.library.title') }}</h3>
        </div>
        <span class="admin-chip">{{ templates.length }}</span>
      </header>

      <div v-if="isLoading" class="admin-empty-state">
        <i class="fas fa-spinner fa-spin"></i>
        <span>{{ t('adminStaffQuestionTemplatesPage.states.loading') }}</span>
      </div>

      <div v-else-if="templates.length === 0" class="admin-empty-state">
        <i class="fas fa-clipboard-question"></i>
        <span>{{ t('adminStaffQuestionTemplatesPage.states.empty') }}</span>
      </div>

      <div v-else class="admin-library-table-wrap">
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
              <td>{{ row.question_text_en }}</td>
              <td dir="rtl">{{ row.question_text_ar || '—' }}</td>
              <td>{{ row.question_type }}</td>
              <td>{{ row.is_required ? t('adminStaffQuestionTemplatesPage.table.yes') : t('adminStaffQuestionTemplatesPage.table.no') }}</td>
              <td>
                <span class="admin-chip" :class="{ 'admin-chip--muted': !row.is_active }">
                  {{ row.is_active ? t('adminStaffQuestionTemplatesPage.table.active') : t('adminStaffQuestionTemplatesPage.table.inactive') }}
                </span>
              </td>
              <td>
                <div class="admin-table-actions">
                  <button type="button" class="admin-table-btn" @click="editTemplate(row)">
                    {{ t('adminStaffQuestionTemplatesPage.table.edit') }}
                  </button>
                  <button type="button" class="admin-table-btn" @click="toggleTemplate(row)">
                    {{ row.is_active ? t('adminStaffQuestionTemplatesPage.table.deactivate') : t('adminStaffQuestionTemplatesPage.table.activate') }}
                  </button>
                  <button type="button" class="admin-table-btn" :disabled="index === 0 || isReordering" @click="reorderTemplates('up', index)">
                    {{ t('adminStaffQuestionTemplatesPage.table.up') }}
                  </button>
                  <button type="button" class="admin-table-btn" :disabled="index === templates.length - 1 || isReordering" @click="reorderTemplates('down', index)">
                    {{ t('adminStaffQuestionTemplatesPage.table.down') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>