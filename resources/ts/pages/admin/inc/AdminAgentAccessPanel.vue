<script setup lang="ts">
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const props = defineProps<{
  activeAssignments: any[]
  bankOptions: any[]
  agentOptions: any[]
  availableDocuments: any[]
  selectedBankId: number | null
  selectedAgentIds: number[]
  selectedAgentDocumentKeys: Record<number, string[]>
  reviewNote: string
  saving: boolean
  canSave: boolean
  dirty: boolean
  stageAfterSaveLabel: string
}>()

const emit = defineEmits<{
  (event: 'update:selectedBankId', value: number | null): void
  (event: 'update:reviewNote', value: string): void
  (event: 'toggle-agent', agentId: number, checked: boolean): void
  (event: 'toggle-document', agentId: number, documentKey: string, checked: boolean): void
  (event: 'select-all-documents', agentId: number): void
  (event: 'clear-documents', agentId: number): void
  (event: 'remove-agent', agentId: number): void
  (event: 'reset-draft'): void
  (event: 'submit'): void
  (event: 'preview-file', document: any): void
}>()

const { t } = useI18n()

const selectedBankIdProxy = computed({
  get: () => props.selectedBankId ?? '',
  set: (value: string | number) => {
    const id = Number(value)
    emit('update:selectedBankId', Number.isFinite(id) && id > 0 ? id : null)
  },
})

const activeAssignmentRows = computed(() =>
  (props.activeAssignments ?? []).map((assignment: any) => {
    const agent = assignment?.agent ?? {}
    const bank = assignment?.bank ?? agent?.bank ?? {}
    const agentId = Number(assignment?.agent_id ?? agent?.id ?? 0)

    return {
      id: Number(assignment?.id ?? agentId),
      agentId,
      name: agent?.name || t('adminRequestDetails.agentAssignments.unknownAgent'),
      bankName: bank?.name || agent?.company_name || t('adminRequestDetails.agentAssignments.noBankLinked'),
      contact: agent?.email || agent?.phone || t('adminRequestDetails.agentAssignments.noContactDetails'),
      assignedAt: assignment?.assigned_at,
      assignedBy: assignment?.assigned_by?.name || assignment?.assignedBy?.name || null,
      documents: Array.isArray(assignment?.allowed_documents) ? assignment.allowed_documents : [],
    }
  }),
)

const activeAgentIds = computed(() => new Set(activeAssignmentRows.value.map((item) => item.agentId).filter(Boolean)))

const selectedAgents = computed(() => {
  const optionsById = new Map((props.agentOptions ?? []).map((agent: any) => [Number(agent.id), agent]))
  const activeById = new Map(activeAssignmentRows.value.map((assignment) => [
    assignment.agentId,
    {
      id: assignment.agentId,
      name: assignment.name,
      bank_name: assignment.bankName,
      email: assignment.contact,
    },
  ]))

  return (props.selectedAgentIds ?? [])
    .map((id) => Number(id))
    .filter((id) => id > 0)
    .map((id) => optionsById.get(id) ?? activeById.get(id))
    .filter(Boolean)
})

const filteredAgents = computed(() => {
  const rows = props.agentOptions ?? []
  if (!props.selectedBankId) return rows

  return rows.filter((agent: any) => Number(agent.bank_id ?? 0) === Number(props.selectedBankId))
})

const selectedDocumentCount = computed(() =>
  Object.values(props.selectedAgentDocumentKeys ?? {}).reduce((total, keys) => total + (Array.isArray(keys) ? keys.length : 0), 0),
)

const activeDocumentCount = computed(() =>
  activeAssignmentRows.value.reduce((total, assignment) => total + assignment.documents.length, 0),
)

const selectedAgentsMissingFiles = computed(() =>
  selectedAgents.value.filter((agent: any) => !((props.selectedAgentDocumentKeys[Number(agent.id)] ?? []).length)),
)

function initials(value: string) {
  return String(value || '?')
    .split(/\s+/)
    .map((part) => part[0])
    .join('')
    .slice(0, 2)
    .toUpperCase()
}

function isAgentSelected(agentId: number) {
  return (props.selectedAgentIds ?? []).includes(Number(agentId))
}

function isDocumentSelected(agentId: number, documentKey: string) {
  return (props.selectedAgentDocumentKeys[Number(agentId)] ?? []).includes(documentKey)
}

function documentLabel(document: any) {
  return document?.document_label || document?.label || document?.file_name || t('adminRequestDetails.agentAssignments.requestFile')
}

function documentGroup(document: any) {
  return document?.group_label || t('adminRequestDetails.agentAssignments.requestFile')
}

function documentFileName(document: any) {
  return document?.file_name || t('adminRequestDetails.states.emptyValue')
}
</script>

<template>
  <article class="agent-access-panel" :class="{ 'is-dirty': dirty }">
    <header class="agent-access-hero">
      <div>
        <span>{{ t('adminRequestDetails.agentAssignments.kicker') }}</span>
        <h2>{{ t('adminRequestDetails.agentAssignments.title') }}</h2>
        <p>{{ t('adminRequestDetails.agentAssignments.subtitle') }}</p>
      </div>
      <div class="agent-access-stage">
        <small>{{ t('adminRequestDetails.agentAssignments.stats.stageAfterSave') }}</small>
        <strong>{{ stageAfterSaveLabel }}</strong>
      </div>
    </header>

    <div class="agent-access-stat-grid">
      <div>
        <span>{{ t('adminRequestDetails.agentAssignments.stats.activeAgents') }}</span>
        <strong>{{ activeAssignmentRows.length }}</strong>
      </div>
      <div>
        <span>{{ t('adminRequestDetails.agentAssignments.stats.activeFiles') }}</span>
        <strong>{{ activeDocumentCount }}</strong>
      </div>
      <div>
        <span>{{ t('adminRequestDetails.agentAssignments.stats.selectedAgents') }}</span>
        <strong>{{ selectedAgents.length }}</strong>
      </div>
      <div>
        <span>{{ t('adminRequestDetails.agentAssignments.stats.selectedFiles') }}</span>
        <strong>{{ selectedDocumentCount }}</strong>
      </div>
    </div>

    <div class="agent-access-layout">
      <section class="agent-access-current" aria-labelledby="active-agent-access-title">
        <div class="agent-access-section-head">
          <div>
            <h3 id="active-agent-access-title">{{ t('adminRequestDetails.agentAssignments.assignedNow') }}</h3>
            <p>{{ t('adminRequestDetails.agentAssignments.assignedNowHint') }}</p>
          </div>
          <span>{{ activeAssignmentRows.length }}</span>
        </div>

        <div v-if="activeAssignmentRows.length" class="agent-active-list">
          <article v-for="assignment in activeAssignmentRows" :key="assignment.id" class="agent-active-card">
            <div class="agent-active-card__main">
              <div class="agent-avatar">{{ initials(assignment.name) }}</div>
              <div>
                <strong>{{ assignment.name }}</strong>
                <p>{{ assignment.bankName }}</p>
                <span>{{ assignment.contact }}</span>
              </div>
              <button
                type="button"
                class="agent-mini-btn"
                @click="emit('remove-agent', assignment.agentId)"
              >
                {{ t('adminRequestDetails.agentAssignments.removeAgent') }}
              </button>
            </div>

            <div v-if="assignment.documents.length" class="agent-doc-chip-list">
              <span
                v-for="document in assignment.documents.slice(0, 5)"
                :key="`${assignment.id}-${document.id}`"
              >
                {{ documentLabel(document) }}
              </span>
              <span v-if="assignment.documents.length > 5">
                {{ t('adminRequestDetails.agentAssignments.moreFiles', { count: assignment.documents.length - 5 }) }}
              </span>
            </div>
            <p v-else class="agent-muted-line">{{ t('adminRequestDetails.agentAssignments.noFilesAssigned') }}</p>
          </article>
        </div>

        <p v-else class="empty-state">{{ t('adminRequestDetails.agentAssignments.noAssignedAgentsYet') }}</p>
      </section>

      <section class="agent-access-builder" aria-labelledby="agent-access-builder-title">
        <div class="agent-access-toolbar">
          <div>
            <h3 id="agent-access-builder-title">{{ t('adminRequestDetails.agentAssignments.manageAccess') }}</h3>
            <p>{{ t('adminRequestDetails.agentAssignments.manageAccessHint') }}</p>
          </div>
          <label class="agent-bank-filter">
            <span>{{ t('adminRequestDetails.agentAssignments.bankFilter') }}</span>
            <select v-model="selectedBankIdProxy" class="admin-select">
              <option value="">{{ t('adminRequestDetails.agentAssignments.allBanks') }}</option>
              <option v-for="bank in bankOptions" :key="bank.id" :value="bank.id">
                {{ bank.name }}<template v-if="bank.short_name"> - {{ bank.short_name }}</template>
              </option>
            </select>
          </label>
        </div>

        <div class="agent-builder-grid">
          <div class="agent-directory-panel">
            <div class="agent-access-section-head agent-access-section-head--compact">
              <h4>{{ t('adminRequestDetails.agentAssignments.selectAgents') }}</h4>
              <span>{{ filteredAgents.length }}</span>
            </div>

            <div v-if="filteredAgents.length" class="agent-directory-list">
              <label
                v-for="agent in filteredAgents"
                :key="agent.id"
                class="agent-directory-row"
                :class="{ 'is-selected': isAgentSelected(agent.id), 'is-active-now': activeAgentIds.has(Number(agent.id)) }"
              >
                <input
                  :checked="isAgentSelected(agent.id)"
                  type="checkbox"
                  @change="emit('toggle-agent', Number(agent.id), ($event.target as HTMLInputElement).checked)"
                >
                <span class="agent-directory-row__avatar">{{ initials(agent.name) }}</span>
                <span class="agent-directory-row__body">
                  <strong>{{ agent.name }}</strong>
                  <small>{{ agent.bank_name || t('adminRequestDetails.agentAssignments.noBankLinked') }}</small>
                  <em>{{ agent.email || agent.phone || agent.company_name || t('adminRequestDetails.agentAssignments.noContactDetails') }}</em>
                </span>
                <span v-if="activeAgentIds.has(Number(agent.id))" class="agent-live-badge">
                  {{ t('adminRequestDetails.agentAssignments.activeNow') }}
                </span>
              </label>
            </div>

            <p v-else class="empty-state">{{ t('adminRequestDetails.agentAssignments.noActiveAgentsForFilter') }}</p>
          </div>

          <div class="agent-document-panel">
            <div class="agent-access-section-head agent-access-section-head--compact">
              <h4>{{ t('adminRequestDetails.agentAssignments.linkFilesPerAgent') }}</h4>
              <span>{{ availableDocuments.length }}</span>
            </div>

            <div v-if="selectedAgents.length" class="agent-document-agent-list">
              <article v-for="agent in selectedAgents" :key="agent.id" class="agent-document-agent-card">
                <header>
                  <div>
                    <strong>{{ agent.name }}</strong>
                    <p>{{ agent.bank_name || t('adminRequestDetails.agentAssignments.noBankLinked') }}</p>
                  </div>
                  <div class="agent-card-actions">
                    <button type="button" class="agent-mini-btn" @click="emit('select-all-documents', Number(agent.id))">
                      {{ t('adminRequestDetails.agentAssignments.selectAllFiles') }}
                    </button>
                    <button type="button" class="agent-mini-btn" @click="emit('clear-documents', Number(agent.id))">
                      {{ t('adminRequestDetails.agentAssignments.clearFiles') }}
                    </button>
                    <button type="button" class="agent-mini-btn agent-mini-btn--danger" @click="emit('remove-agent', Number(agent.id))">
                      {{ t('adminRequestDetails.agentAssignments.removeAgent') }}
                    </button>
                  </div>
                </header>

                <div v-if="availableDocuments.length" class="agent-document-list">
                  <label
                    v-for="document in availableDocuments"
                    :key="`${agent.id}-${document.key}`"
                    class="agent-document-row"
                    :class="{ 'is-selected': isDocumentSelected(Number(agent.id), document.key) }"
                  >
                    <input
                      :checked="isDocumentSelected(Number(agent.id), document.key)"
                      type="checkbox"
                      @change="emit('toggle-document', Number(agent.id), document.key, ($event.target as HTMLInputElement).checked)"
                    >
                    <span>
                      <strong>{{ documentLabel(document) }}</strong>
                      <small>{{ documentGroup(document) }}</small>
                      <em>{{ documentFileName(document) }}</em>
                    </span>
                    <span v-if="document.download_url" class="agent-document-actions">
                      <button
                        type="button"
                        class="agent-mini-btn"
                        @click.prevent.stop="emit('preview-file', document)"
                      >
                        {{ t('adminRequestDetails.agentAssignments.previewFile') }}
                      </button>
                      <a :href="document.download_url" target="_blank" rel="noopener" class="agent-mini-btn" @click.stop>
                        {{ t('adminRequestDetails.actions.download') }}
                      </a>
                    </span>
                  </label>
                </div>

                <p v-else class="empty-state">{{ t('adminRequestDetails.agentAssignments.noAvailableFiles') }}</p>
              </article>
            </div>

            <p v-else class="empty-state">{{ t('adminRequestDetails.agentAssignments.selectAgentsFirst') }}</p>
          </div>
        </div>
      </section>
    </div>

    <footer class="agent-access-footer">
      <label class="client-form-group agent-note-field">
        <span class="client-form-label">{{ t('adminRequestDetails.agentAssignments.adminNote') }}</span>
        <textarea
          :value="reviewNote"
          rows="3"
          class="client-form-control client-form-control--textarea"
          :placeholder="t('adminRequestDetails.agentAssignments.adminNotePlaceholder')"
          @input="emit('update:reviewNote', ($event.target as HTMLTextAreaElement).value)"
        />
      </label>

      <div class="agent-access-footer__actions">
        <p v-if="selectedAgentsMissingFiles.length" class="form-help form-help--error">
          {{ t('adminRequestDetails.agentAssignments.mustLinkAtLeastOneFile') }}
        </p>
        <p v-else-if="dirty" class="agent-muted-line">
          {{ t('adminRequestDetails.agentAssignments.pendingChanges') }}
        </p>
        <p v-else class="agent-muted-line">
          {{ t('adminRequestDetails.agentAssignments.noPendingChanges') }}
        </p>

        <div class="approve-actions">
          <button type="button" class="ghost-btn" :disabled="!dirty || saving" @click="emit('reset-draft')">
            {{ t('adminRequestDetails.agentAssignments.resetDraft') }}
          </button>
          <button type="button" class="primary-btn" :disabled="!canSave" @click="emit('submit')">
            {{ saving ? t('adminRequestDetails.actions.saving') : t('adminRequestDetails.agentAssignments.saveAllowedAgents') }}
          </button>
        </div>
      </div>
    </footer>
  </article>
</template>
