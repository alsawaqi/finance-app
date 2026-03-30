<script setup lang="ts">
import type { BankItem } from '@/services/banks'

defineProps<{
  rows: BankItem[]
  loading: boolean
}>()

defineEmits<{
  (e: 'edit', row: BankItem): void
  (e: 'toggle', row: BankItem): void
}>()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">Bank directory</span>
        <h2>Banks available for agent mapping</h2>
      </div>
      <span class="admin-panel__action is-static">{{ rows.length }} banks</span>
    </div>

    <div v-if="loading" class="admin-table-empty">Loading banks...</div>

    <template v-else>
      <div v-if="rows.length === 0" class="admin-table-empty">
        No banks added yet. Create your first bank from the form.
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Bank</th>
              <th>Code</th>
              <th>Short name</th>
              <th>Linked agents</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.name }}</strong>
                  <small>{{ row.creator_name || 'System' }}</small>
                </div>
              </td>
              <td>{{ row.code || '—' }}</td>
              <td>{{ row.short_name || '—' }}</td>
              <td>{{ row.agents_count }}</td>
              <td>
                <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                  {{ row.is_active ? 'Active' : 'Inactive' }}
                </span>
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
