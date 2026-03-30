<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import AdminAgentBuilderForm from './inc/AdminAgentBuilderForm.vue'
import AdminAgentLibraryTable from './inc/AdminAgentLibraryTable.vue'
import type { AgentItem, AgentPayload } from '@/services/agents'
import { createAgent, listAgents, toggleAgentActive, updateAgent } from '@/services/agents'
import type { BankItem } from '@/services/banks'
import { listBanks } from '@/services/banks'

type AgentForm = {
  id: number | null
  name: string
  email: string
  phone: string
  company_name: string
  bank_id: number | null
  agent_type: string
  notes: string
  is_active: boolean
}

const rows = ref<AgentItem[]>([])
const banks = ref<BankItem[]>([])
const isLoading = ref(false)
const isLoadingBanks = ref(false)
const isSaving = ref(false)
const formError = ref('')
const successMessage = ref('')
const fieldErrors = ref<Record<string, string[]>>({})

const form = ref<AgentForm>(createDefaultForm())

const isEditing = computed(() => form.value.id !== null)
const activeBanks = computed(() => banks.value.filter((bank) => bank.is_active))
const stats = computed(() => {
  const total = rows.value.length
  const active = rows.value.filter((item) => item.is_active).length
  const linkedBanks = new Set(rows.value.map((item) => item.bank_id).filter((id): id is number => typeof id === 'number')).size
  const withoutBank = rows.value.filter((item) => !item.bank_id).length

  return [
    { label: 'Total agents', value: String(total), tone: 'emerald' },
    { label: 'Active agents', value: String(active), tone: 'blue' },
    { label: 'Banks linked', value: String(linkedBanks), tone: 'violet' },
    { label: 'Without bank', value: String(withoutBank), tone: 'amber' },
  ]
})

onMounted(async () => {
  await Promise.all([fetchRows(), fetchBanks()])
})

function createDefaultForm(): AgentForm {
  return {
    id: null,
    name: '',
    email: '',
    phone: '',
    company_name: '',
    bank_id: null,
    agent_type: 'bank',
    notes: '',
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

async function fetchRows() {
  isLoading.value = true
  formError.value = ''

  try {
    const { data } = await listAgents()
    rows.value = data.data
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to load agents right now.')
  } finally {
    isLoading.value = false
  }
}

async function fetchBanks() {
  isLoadingBanks.value = true

  try {
    const { data } = await listBanks()
    banks.value = data.data
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to load banks right now.')
  } finally {
    isLoadingBanks.value = false
  }
}

function buildPayload(): AgentPayload {
  return {
    name: form.value.name.trim(),
    email: form.value.email.trim() || null,
    phone: form.value.phone.trim() || null,
    company_name: form.value.company_name.trim() || null,
    bank_id: form.value.bank_id,
    agent_type: form.value.agent_type.trim() || null,
    notes: form.value.notes.trim() || null,
    is_active: form.value.is_active,
  }
}

async function saveRow() {
  clearMessages()
  isSaving.value = true

  try {
    if (isEditing.value && form.value.id) {
      const { data } = await updateAgent(form.value.id, buildPayload())
      successMessage.value = data.message
    } else {
      const { data } = await createAgent(buildPayload())
      successMessage.value = data.message
    }

    await fetchRows()
    resetForm()
  } catch (error) {
    if (axios.isAxiosError(error)) {
      formError.value = error.response?.data?.message ?? 'Unable to save the agent.'
      fieldErrors.value = error.response?.data?.errors ?? {}
    } else {
      formError.value = 'Unable to save the agent.'
    }
  } finally {
    isSaving.value = false
  }
}

function editRow(row: AgentItem) {
  clearMessages()
  form.value = {
    id: row.id,
    name: row.name,
    email: row.email ?? '',
    phone: row.phone ?? '',
    company_name: row.company_name ?? '',
    bank_id: row.bank_id ?? null,
    agent_type: row.agent_type ?? '',
    notes: row.notes ?? '',
    is_active: row.is_active,
  }

  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function toggleRow(row: AgentItem) {
  clearMessages()

  try {
    const { data } = await toggleAgentActive(row.id)
    successMessage.value = data.message
    rows.value = rows.value.map((item) => (item.id === row.id ? data.data : item))
  } catch (error) {
    formError.value = extractErrorMessage(error, 'Unable to update agent status right now.')
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
        <span class="admin-hero__eyebrow">Agent Management</span>
        <h2>Create the external contacts you will later use in request communications.</h2>
        <p>
          Agents are stored separately from users. Link each agent to a bank so staff can later filter
          contacts by bank before sending emails about finance requests.
        </p>
      </div>

      <div class="admin-hero__actions">
        <button type="button" class="admin-primary-btn" :disabled="isSaving || isLoadingBanks" @click="saveRow">
          {{ isSaving ? (isEditing ? 'Updating...' : 'Saving...') : isEditing ? 'Update agent' : 'Create agent' }}
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
      <AdminAgentBuilderForm
        v-model="form"
        :banks="banks"
        :is-editing="isEditing"
        :is-saving="isSaving"
        :errors="fieldErrors"
        @save="saveRow"
        @reset="resetForm"
      />

      <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
        <div class="admin-panel__head">
          <div>
            <span class="admin-panel__eyebrow">Why agents matter</span>
            <h2>External workflow contacts</h2>
          </div>
        </div>

        <div class="admin-question-preview__notes">
          <article class="admin-question-preview__note">
            <span>Request email flow</span>
            <strong>Agents will be linked later when the team sends request-related emails.</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>Bank linkage</span>
            <strong>{{ activeBanks.length }} active banks are currently available for agent mapping.</strong>
          </article>
          <article class="admin-question-preview__note">
            <span>Availability</span>
            <strong>Inactive agents remain stored but can be hidden from active workflows.</strong>
          </article>
        </div>

        <div class="admin-pill-list">
          <span class="admin-chip admin-chip--violet">agent table is separate from users</span>
          <span class="admin-chip admin-chip--blue">bank-linked contacts</span>
          <span class="admin-chip admin-chip--emerald">ready for request-email integration</span>
        </div>
      </section>
    </div>

    <AdminAgentLibraryTable :rows="rows" :loading="isLoading" @edit="editRow" @toggle="toggleRow" />
  </div>
</template>
