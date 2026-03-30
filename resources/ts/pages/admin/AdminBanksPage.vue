<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import AdminBankBuilderForm from './inc/AdminBankBuilderForm.vue'
import AdminBankLibraryTable from './inc/AdminBankLibraryTable.vue'
import type { BankItem, BankPayload } from '@/services/banks'
import { createBank, listBanks, toggleBankActive, updateBank } from '@/services/banks'

type BankForm = {
  id: number | null
  name: string
  code: string
  short_name: string
  is_active: boolean
}

const rows = ref<BankItem[]>([])
const isLoading = ref(false)
const isSaving = ref(false)
const formError = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})
const form = ref<BankForm>(createDefaultForm())

const isEditing = computed(() => form.value.id !== null)
const stats = computed(() => {
  const total = rows.value.length
  const active = rows.value.filter((item) => item.is_active).length
  const inactive = total - active
  const linkedAgents = rows.value.reduce((sum, item) => sum + item.agents_count, 0)

  return [
    { label: 'Total banks', value: String(total), tone: 'emerald' },
    { label: 'Active banks', value: String(active), tone: 'blue' },
    { label: 'Inactive banks', value: String(inactive), tone: 'amber' },
    { label: 'Linked agents', value: String(linkedAgents), tone: 'violet' },
  ]
})

onMounted(async () => {
  await fetchRows()
})

function createDefaultForm(): BankForm {
  return {
    id: null,
    name: '',
    code: '',
    short_name: '',
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

function buildPayload(): BankPayload {
  return {
    name: form.value.name.trim(),
    code: form.value.code.trim() || null,
    short_name: form.value.short_name.trim() || null,
    is_active: form.value.is_active,
  }
}

async function fetchRows() {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listBanks()
    rows.value = data.data
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to load banks right now.')
  } finally {
    isLoading.value = false
  }
}

async function saveRow() {
  clearMessages()
  isSaving.value = true

  try {
    if (isEditing.value && form.value.id) {
      const { data } = await updateBank(form.value.id, buildPayload())
      successMessage.value = data.message
    } else {
      const { data } = await createBank(buildPayload())
      successMessage.value = data.message
    }

    await fetchRows()
    resetForm()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? 'Unable to save the bank.'
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = 'Unable to save the bank.'
    }
  } finally {
    isSaving.value = false
  }
}

function editRow(row: BankItem) {
  clearMessages()
  form.value = {
    id: row.id,
    name: row.name,
    code: row.code ?? '',
    short_name: row.short_name ?? '',
    is_active: row.is_active,
  }

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleRow(row: BankItem) {
  clearMessages()

  try {
    const { data } = await toggleBankActive(row.id)
    successMessage.value = data.message
    rows.value = rows.value.map((item) => (item.id === row.id ? data.data : item))
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to update bank status right now.')
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
        <span class="admin-hero__eyebrow">Bank Management</span>
        <h2>Create the banks first, then link each agent to the correct bank from the agent page.</h2>
        <p>
          This page is for the main admin to manage the bank master list. Once the banks are available,
          agent records can be linked cleanly and later filtered in request communications and reporting.
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving" @click="saveRow">
          {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update bank' : 'Create bank' }}
        </button>
        <button type="button" class="admin-secondary-btn" @click="resetForm">
          {{ isEditing ? 'Cancel edit' : 'Reset form' }}
        </button>
      </div>
    </section>

    <div class="admin-question-stats-grid admin-reveal-up admin-reveal-delay-1">
      <article v-for="stat in stats" :key="stat.label" class="admin-question-stat" :class="`tone-${stat.tone}`">
        <strong>{{ stat.value }}</strong>
        <span>{{ stat.label }}</span>
      </article>
    </div>

    <div v-if="formError" class="admin-alert admin-alert--error">{{ formError }}</div>
    <div v-if="successMessage" class="admin-alert admin-alert--success">{{ successMessage }}</div>

    <div class="admin-question-builder-grid">
      <AdminBankBuilderForm
        v-model="form"
        :is-editing="isEditing"
        :is-saving="isSaving"
        :errors="fieldErrors"
        @save="saveRow"
        @reset="resetForm"
      />

      <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">Why banks matter</span>
            <h2>Master list before agents</h2>
          </div>
        </div>

        <div class="admin-question-preview__notes">
          <article class="admin-question-preview__note">
            <span>Agent linkage</span>
            <strong>Every agent can be linked to a bank from the agent builder form.</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>Staff composer</span>
            <strong>Staff will later filter agents by bank before preparing request emails.</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>Reporting</span>
            <strong>Bank-linked agents will later support admin reporting by bank, agent, and request.</strong>
          </article>
        </div>

        <div class="admin-pill-list">
          <span class="admin-chip admin-chip--violet">admin master data</span>
          <span class="admin-chip admin-chip--blue">agent linkage ready</span>
          <span class="admin-chip admin-chip--emerald">reporting foundation</span>
        </div>
      </section>
    </div>

    <AdminBankLibraryTable :rows="rows" :loading="isLoading" @edit="editRow" @toggle="toggleRow" />
  </div>
</template>
