<script setup lang="ts">
import { useI18n } from 'vue-i18n'
import type { AgentItem } from '@/services/agents'

defineProps<{
  rows: AgentItem[]
  loading: boolean
}>()

defineEmits<{
  (e: 'edit', row: AgentItem): void
  (e: 'toggle', row: AgentItem): void
}>()
const { t } = useI18n()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">{{ t('adminAgentLibrary.eyebrow') }}</span>
        <h2>{{ t('adminAgentLibrary.title') }}</h2>
      </div>
      <span class="admin-panel__action is-static">{{ t('adminAgentLibrary.count', { count: rows.length }) }}</span>
    </div>

    <div v-if="loading" class="admin-table-empty">{{ t('adminAgentLibrary.states.loading') }}</div>

    <template v-else>
      <div v-if="rows.length === 0" class="admin-table-empty">
        {{ t('adminAgentLibrary.states.empty') }}
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>{{ t('adminAgentLibrary.columns.agent') }}</th>
              <th>{{ t('adminAgentLibrary.columns.bank') }}</th>
              <th>{{ t('adminAgentLibrary.columns.company') }}</th>
              <th>{{ t('adminAgentLibrary.columns.type') }}</th>
              <th>{{ t('adminAgentLibrary.columns.status') }}</th>
              <th>{{ t('adminAgentLibrary.columns.notes') }}</th>
              <th>{{ t('adminAgentLibrary.columns.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.name }}</strong>
                  <small>{{ row.email || t('adminAgentLibrary.states.noEmail') }} · {{ row.phone || t('adminAgentLibrary.states.noPhone') }}</small>
                </div>
              </td>
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.bank_name || t('adminAgentLibrary.states.noBankLinked') }}</strong>
                  <small>{{ row.bank_short_name || row.bank_code || t('adminAgentLibrary.states.emptyValue') }}</small>
                </div>
              </td>
              <td>{{ row.company_name || t('adminAgentLibrary.states.emptyValue') }}</td>
              <td>
                <span class="admin-chip admin-chip--blue">{{ row.agent_type || t('adminAgentLibrary.states.general') }}</span>
              </td>
              <td>
                <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                  {{ row.is_active ? t('adminAgentLibrary.states.active') : t('adminAgentLibrary.states.inactive') }}
                </span>
              </td>
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.notes ? row.notes.slice(0, 44) + (row.notes.length > 44 ? '…' : '') : t('adminAgentLibrary.states.noNotes') }}</strong>
                  <small>{{ row.creator_name || t('adminAgentLibrary.states.system') }}</small>
                </div>
              </td>
              <td>
                <div class="admin-table-actions">
                  <button type="button" class="admin-inline-link" @click="$emit('edit', row)">{{ t('adminAgentLibrary.actions.edit') }}</button>
                  <button type="button" class="admin-inline-link" @click="$emit('toggle', row)">
                    {{ row.is_active ? t('adminAgentLibrary.actions.deactivate') : t('adminAgentLibrary.actions.activate') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>
