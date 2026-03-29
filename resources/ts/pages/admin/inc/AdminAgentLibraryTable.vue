<script setup lang="ts">
import type { AgentItem } from '@/services/agents'

defineProps<{
  rows: AgentItem[]
  loading: boolean
}>()

defineEmits<{
  (e: 'edit', row: AgentItem): void
  (e: 'toggle', row: AgentItem): void
}>()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">Agent directory</span>
        <h2>Agents and external contacts</h2>
      </div>
      <span class="admin-panel__action is-static">{{ rows.length }} agents</span>
    </div>

    <div v-if="loading" class="admin-table-empty">Loading agents...</div>

    <template v-else>
      <div v-if="rows.length === 0" class="admin-table-empty">
        No agents added yet. Create your first contact from the form.
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Agent</th>
              <th>Company</th>
              <th>Type</th>
              <th>Status</th>
              <th>Notes</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.name }}</strong>
                  <small>{{ row.email || 'No email' }} · {{ row.phone || 'No phone' }}</small>
                </div>
              </td>
              <td>{{ row.company_name || '—' }}</td>
              <td>
                <span class="admin-chip admin-chip--blue">{{ row.agent_type || 'general' }}</span>
              </td>
              <td>
                <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                  {{ row.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.notes ? row.notes.slice(0, 44) + (row.notes.length > 44 ? '…' : '') : 'No notes' }}</strong>
                  <small>{{ row.creator_name || 'System' }}</small>
                </div>
              </td>
              <td>
                <div class="admin-table-actions">
                  <button type="button" class="admin-inline-link" @click="$emit('edit', row)">Edit</button>
                  <button type="button" class="admin-inline-link" @click="$emit('toggle', row)">
                    {{ row.is_active ? 'Deactivate' : 'Activate' }}
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
