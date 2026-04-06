<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import { useI18n } from 'vue-i18n'
import AppPagination from '@/components/AppPagination.vue'
import type { FinanceRequestTypeItem, FinanceRequestTypePayload } from '@/services/financeRequestTypes'
import {
  createFinanceRequestType,
  listFinanceRequestTypes,
  toggleFinanceRequestTypeActive,
  updateFinanceRequestType,
} from '@/services/financeRequestTypes'
import { DEFAULT_PAGINATION, type PaginationMeta } from '@/types/pagination'

type FinanceRequestTypeForm = {
  id: number | null
  slug: string
  name_en: string
  name_ar: string
  description_en: string
  description_ar: string
  sort_order: number
  is_active: boolean
}

const { t, locale } = useI18n()

const rows = ref<FinanceRequestTypeItem[]>([])
const pagination = ref<PaginationMeta>({ ...DEFAULT_PAGINATION, per_page: 12 })
const isLoading = ref(false)
const isSaving = ref(false)
const formError = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const form = ref<FinanceRequestTypeForm>(createDefaultForm())

const isEditing = computed(() => form.value.id !== null)

const stats = computed(() => {
  const total = pagination.value.total
  const active = rows.value.filter((item) => item.is_active).length
  const inactive = rows.value.filter((item) => !item.is_active).length
  const bilingual = rows.value.filter((item) => item.name_en?.trim() && item.name_ar?.trim()).length

  return [
    { label: t('adminFinanceRequestTypesPage.stats.totalTypes'), value: String(total), tone: 'emerald' },
    { label: t('adminFinanceRequestTypesPage.stats.activeTypes'), value: String(active), tone: 'blue' },
    { label: t('adminFinanceRequestTypesPage.stats.inactiveTypes'), value: String(inactive), tone: 'amber' },
    { label: t('adminFinanceRequestTypesPage.stats.bilingualReady'), value: String(bilingual), tone: 'violet' },
  ]
})

onMounted(async () => {
  await fetchRows()
})

function createDefaultForm(): FinanceRequestTypeForm {
  return {
    id: null,
    slug: '',
    name_en: '',
    name_ar: '',
    description_en: '',
    description_ar: '',
    sort_order: rows.value.length + 1,
    is_active: true,
  }
}

function clearMessages() {
  formError.value = ''
  successMessage.value = ''
  fieldErrors.value = {}
}

function resetForm() {
  clearMessages()
  form.value = createDefaultForm()
}

function firstError(field: string) {
  return fieldErrors.value[field]?.[0] ?? ''
}

function localizedTypeName(row: FinanceRequestTypeItem) {
  const nameEn = String(row.name_en || '').trim()
  const nameAr = String((row as any).name_ar || '').trim()
  return locale.value === 'ar' ? (nameAr || nameEn) : (nameEn || nameAr)
}

function secondaryTypeName(row: FinanceRequestTypeItem) {
  const nameEn = String(row.name_en || '').trim()
  const nameAr = String((row as any).name_ar || '').trim()
  return locale.value === 'ar' ? (nameEn || nameAr) : (nameAr || nameEn)
}

function buildPayload(): FinanceRequestTypePayload {
  return {
    slug: form.value.slug.trim() || null,
    name_en: form.value.name_en.trim(),
    name_ar: form.value.name_ar.trim(),
    description_en: form.value.description_en.trim() || null,
    description_ar: form.value.description_ar.trim() || null,
    sort_order: Number(form.value.sort_order || 0),
    is_active: form.value.is_active,
  }
}

async function fetchRows(page = pagination.value.current_page) {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listFinanceRequestTypes({
      page,
      per_page: pagination.value.per_page,
    })
    rows.value = data.data
    pagination.value = data.pagination ?? { ...DEFAULT_PAGINATION, per_page: pagination.value.per_page }
    if (!isEditing.value) {
      form.value.sort_order = Math.max(pagination.value.total, rows.value.length) + 1
    }
  } catch (error) {
    formError.value = extractErrorMessage(error, t('adminFinanceRequestTypesPage.errors.loadFailed'))
  } finally {
    isLoading.value = false
  }
}

async function saveRow() {
  clearMessages()
  isSaving.value = true

  try {
    if (isEditing.value && form.value.id) {
      const { data } = await updateFinanceRequestType(form.value.id, buildPayload())
      successMessage.value = data.message
    } else {
      const { data } = await createFinanceRequestType(buildPayload())
      successMessage.value = data.message
    }

    await fetchRows()
    resetForm()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? t('adminFinanceRequestTypesPage.errors.saveFailed')
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = t('adminFinanceRequestTypesPage.errors.saveFailed')
    }
  } finally {
    isSaving.value = false
  }
}

function editRow(row: FinanceRequestTypeItem) {
  clearMessages()
  form.value = {
    id: row.id,
    slug: row.slug ?? '',
    name_en: row.name_en,
    name_ar: row.name_ar,
    description_en: row.description_en ?? '',
    description_ar: row.description_ar ?? '',
    sort_order: row.sort_order,
    is_active: row.is_active,
  }

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleRow(row: FinanceRequestTypeItem) {
  clearMessages()

  try {
    const { data } = await toggleFinanceRequestTypeActive(row.id)
    successMessage.value = data.message
    rows.value = rows.value.map((item) => (item.id === row.id ? data.data : item))
  } catch (error) {
    formError.value = extractErrorMessage(error, t('adminFinanceRequestTypesPage.errors.toggleFailed'))
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
        <span class="admin-hero__eyebrow">{{ t('adminFinanceRequestTypesPage.hero.eyebrow') }}</span>
        <h2>{{ t('adminFinanceRequestTypesPage.hero.title') }}</h2>
        <p>{{ t('adminFinanceRequestTypesPage.hero.subtitle') }}</p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveRow">
          {{
            isSaving
              ? (isEditing ? t('adminFinanceRequestTypesPage.actions.updating') : t('adminFinanceRequestTypesPage.actions.saving'))
              : (isEditing ? t('adminFinanceRequestTypesPage.actions.updateType') : t('adminFinanceRequestTypesPage.actions.createType'))
          }}
        </button>
        <button type="button" class="admin-secondary-btn" @click="resetForm">
          {{ isEditing ? t('adminFinanceRequestTypesPage.actions.cancelEdit') : t('adminFinanceRequestTypesPage.actions.resetForm') }}
        </button>
      </div>
    </section>

    <div class="admin-question-stats-grid admin-question-stats-grid--balanced admin-reveal-up admin-reveal-delay-1">
      <article v-for="stat in stats" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <div v-if="formError" class="admin-alert admin-alert--error">{{ formError }}</div>
    <div v-if="successMessage" class="admin-alert admin-alert--success">{{ successMessage }}</div>

    <div class="admin-question-builder-grid">
      <section class="admin-panel admin-reveal-up admin-reveal-delay-1">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">
              {{ isEditing ? t('adminFinanceRequestTypesPage.form.eyebrowEdit') : t('adminFinanceRequestTypesPage.form.eyebrowCreate') }}
            </span>
            <h2>{{ t('adminFinanceRequestTypesPage.form.title') }}</h2>
          </div>

          <button type="button" class="admin-panel__action" @click="resetForm">
            {{ isEditing ? t('adminFinanceRequestTypesPage.actions.cancelEdit') : t('adminFinanceRequestTypesPage.actions.clear') }}
          </button>
        </div>

        <div class="admin-form-grid admin-form-grid--2">
          <label class="admin-form-field">
            <span>{{ t('adminFinanceRequestTypesPage.form.fields.nameEn') }}</span>
            <input
              :value="form.name_en"
              type="text"
              class="admin-form-input"
              :class="{ 'has-error': firstError('name_en') }"
              :placeholder="t('adminFinanceRequestTypesPage.form.placeholders.nameEn')"
              @input="form.name_en = ($event.target as HTMLInputElement).value"
            />
            <small v-if="firstError('name_en')" class="admin-form-error">{{ firstError('name_en') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminFinanceRequestTypesPage.form.fields.nameAr') }}</span>
            <input
              :value="form.name_ar"
              type="text"
              class="admin-form-input"
              :class="{ 'has-error': firstError('name_ar') }"
              :placeholder="t('adminFinanceRequestTypesPage.form.placeholders.nameAr')"
              @input="form.name_ar = ($event.target as HTMLInputElement).value"
            />
            <small v-if="firstError('name_ar')" class="admin-form-error">{{ firstError('name_ar') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminFinanceRequestTypesPage.form.fields.slug') }}</span>
            <input
              :value="form.slug"
              type="text"
              class="admin-form-input"
              :class="{ 'has-error': firstError('slug') }"
              :placeholder="t('adminFinanceRequestTypesPage.form.placeholders.slug')"
              @input="form.slug = ($event.target as HTMLInputElement).value"
            />
            <small class="admin-form-helper">{{ t('adminFinanceRequestTypesPage.form.helpers.slug') }}</small>
            <small v-if="firstError('slug')" class="admin-form-error">{{ firstError('slug') }}</small>
          </label>

          <label class="admin-form-field">
            <span>{{ t('adminFinanceRequestTypesPage.form.fields.sortOrder') }}</span>
            <input
              :value="form.sort_order"
              type="number"
              min="0"
              class="admin-form-input"
              :class="{ 'has-error': firstError('sort_order') }"
              :placeholder="t('adminFinanceRequestTypesPage.form.placeholders.sortOrder')"
              @input="form.sort_order = Number(($event.target as HTMLInputElement).value || 0)"
            />
            <small v-if="firstError('sort_order')" class="admin-form-error">{{ firstError('sort_order') }}</small>
          </label>

          <label class="admin-form-field admin-form-field--full">
            <span>{{ t('adminFinanceRequestTypesPage.form.fields.descriptionEn') }}</span>
            <textarea
              :value="form.description_en"
              rows="3"
              class="admin-form-textarea"
              :class="{ 'has-error': firstError('description_en') }"
              :placeholder="t('adminFinanceRequestTypesPage.form.placeholders.descriptionEn')"
              @input="form.description_en = ($event.target as HTMLTextAreaElement).value"
            />
            <small v-if="firstError('description_en')" class="admin-form-error">{{ firstError('description_en') }}</small>
          </label>

          <label class="admin-form-field admin-form-field--full">
            <span>{{ t('adminFinanceRequestTypesPage.form.fields.descriptionAr') }}</span>
            <textarea
              :value="form.description_ar"
              rows="3"
              class="admin-form-textarea"
              :class="{ 'has-error': firstError('description_ar') }"
              :placeholder="t('adminFinanceRequestTypesPage.form.placeholders.descriptionAr')"
              @input="form.description_ar = ($event.target as HTMLTextAreaElement).value"
            />
            <small v-if="firstError('description_ar')" class="admin-form-error">{{ firstError('description_ar') }}</small>
          </label>

          <div class="admin-form-switches admin-form-field--full">
            <label class="admin-switch-card">
              <input :checked="form.is_active" type="checkbox" @change="form.is_active = ($event.target as HTMLInputElement).checked" />
              <div>
                <strong>{{ t('adminFinanceRequestTypesPage.form.switches.activeTitle') }}</strong>
                <span>{{ t('adminFinanceRequestTypesPage.form.switches.activeDesc') }}</span>
              </div>
            </label>
          </div>
        </div>

        <div class="admin-form-actions">
          <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveRow">
            {{
              isSaving
                ? (isEditing ? t('adminFinanceRequestTypesPage.actions.updating') : t('adminFinanceRequestTypesPage.actions.saving'))
                : (isEditing ? t('adminFinanceRequestTypesPage.actions.updateType') : t('adminFinanceRequestTypesPage.actions.createType'))
            }}
          </button>
          <button type="button" class="admin-secondary-btn" @click="resetForm">
            {{ isEditing ? t('adminFinanceRequestTypesPage.actions.cancelEdit') : t('adminFinanceRequestTypesPage.actions.resetForm') }}
          </button>
        </div>
      </section>

      <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">{{ t('adminFinanceRequestTypesPage.notes.eyebrow') }}</span>
            <h2>{{ t('adminFinanceRequestTypesPage.notes.title') }}</h2>
          </div>
        </div>

        <div class="admin-question-preview__notes">
          <article class="admin-question-preview__note">
            <span>{{ t('adminFinanceRequestTypesPage.notes.clientWizardLabel') }}</span>
            <strong>{{ t('adminFinanceRequestTypesPage.notes.clientWizardValue') }}</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>{{ t('adminFinanceRequestTypesPage.notes.reportingLabel') }}</span>
            <strong>{{ t('adminFinanceRequestTypesPage.notes.reportingValue') }}</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>{{ t('adminFinanceRequestTypesPage.notes.orderingLabel') }}</span>
            <strong>{{ t('adminFinanceRequestTypesPage.notes.orderingValue') }}</strong>
          </article>
        </div>

        <div class="admin-pill-list">
          <span class="admin-chip admin-chip--violet">{{ t('adminFinanceRequestTypesPage.chips.masterData') }}</span>
          <span class="admin-chip admin-chip--blue">{{ t('adminFinanceRequestTypesPage.chips.clientReady') }}</span>
          <span class="admin-chip admin-chip--emerald">{{ t('adminFinanceRequestTypesPage.chips.bilingual') }}</span>
        </div>
      </section>
    </div>

    <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
      <div class="admin-panel__head">
        <div>
          <span class="admin-panel__eyebrow">{{ t('adminFinanceRequestTypesPage.table.eyebrow') }}</span>
          <h2>{{ t('adminFinanceRequestTypesPage.table.title') }}</h2>
        </div>
        <span class="admin-panel__action is-static">
          {{ t('adminFinanceRequestTypesPage.table.count', { count: pagination.total }) }}
        </span>
      </div>

      <div v-if="isLoading" class="admin-table-empty">
        {{ t('adminFinanceRequestTypesPage.states.loading') }}
      </div>

      <template v-else>
        <div v-if="rows.length === 0" class="admin-table-empty">
          {{ t('adminFinanceRequestTypesPage.states.empty') }}
        </div>

        <div v-else class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>{{ t('adminFinanceRequestTypesPage.table.columns.name') }}</th>
                <th>{{ t('adminFinanceRequestTypesPage.table.columns.slug') }}</th>
                <th>{{ t('adminFinanceRequestTypesPage.table.columns.sortOrder') }}</th>
                <th>{{ t('adminFinanceRequestTypesPage.table.columns.status') }}</th>
                <th>{{ t('adminFinanceRequestTypesPage.table.columns.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                class="is-clickable-row"
                role="button"
                tabindex="0"
                @click="editRow(row)"
                @keydown.enter.prevent="editRow(row)"
                @keydown.space.prevent="editRow(row)"
              >
                <td>
                  <div class="admin-question-table__text">
                    <strong>{{ localizedTypeName(row) }}</strong>
                    <small>{{ secondaryTypeName(row) }}</small>
                  </div>
                </td>
                <td>{{ row.slug }}</td>
                <td>{{ row.sort_order }}</td>
                <td>
                  <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                    {{ row.is_active ? t('adminFinanceRequestTypesPage.states.active') : t('adminFinanceRequestTypesPage.states.inactive') }}
                  </span>
                </td>
                <td @click.stop>
                  <div class="admin-table-actions">
                    <button type="button" class="admin-inline-link" @click="editRow(row)">
                      {{ t('adminFinanceRequestTypesPage.table.actions.edit') }}
                    </button>
                    <button type="button" class="admin-inline-link" @click="toggleRow(row)">
                      {{ row.is_active ? t('adminFinanceRequestTypesPage.table.actions.deactivate') : t('adminFinanceRequestTypesPage.table.actions.activate') }}
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <AppPagination :pagination="pagination" :disabled="isLoading" @change="fetchRows" />
      </template>
    </section>
  </div>
</template>
