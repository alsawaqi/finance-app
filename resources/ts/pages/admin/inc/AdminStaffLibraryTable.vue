<script setup lang="ts">
import type { StaffUserItem } from '@/services/staffUsers'

defineProps<{
  rows: StaffUserItem[]
  loading: boolean
}>()

defineEmits<{
  (e: 'edit', row: StaffUserItem): void
  (e: 'toggle', row: StaffUserItem): void
}>()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">Staff directory</span>
        <h2>Team members</h2>
      </div>
      <span class="admin-panel__action is-static">{{ rows.length }} accounts</span>
    </div>

    <div v-if="loading" class="admin-table-empty">Loading staff accounts...</div>

    <template v-else>
      <div v-if="rows.length === 0" class="admin-table-empty">
        No staff accounts yet. Create the first internal team member from the form.
      </div>

      <div v-else class="admin-table-wrap">
        <table class="admin-table">
          <thead>
            <tr>
              <th>Staff</th>
              <th>Contact</th>
              <th>Role / Permissions</th>
              <th>Status</th>
              <th>Last login</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in rows" :key="row.id">
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.name }}</strong>
                  <small>#{{ row.id }} · {{ row.email }}</small>
                </div>
              </td>
              <td>
                <div class="admin-question-table__text">
                  <strong>{{ row.phone || 'No phone added' }}</strong>
                  <small>{{ row.created_at ? new Date(row.created_at).toLocaleDateString() : 'Recently created' }}</small>
                </div>
              </td>
              <td>
                <div class="admin-chip-list">
                  <span class="admin-chip admin-chip--violet">staff</span>
                  <span v-if="row.permissions_count > 0" class="admin-chip admin-chip--blue">
                    {{ row.permissions_count }} direct
                  </span>
                  <span v-else class="admin-chip admin-chip--muted">base role only</span>
                </div>
              </td>
              <td>
                <span class="admin-status-pill" :class="row.is_active ? 'is-success' : 'is-muted'">
                  {{ row.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td>{{ row.last_login_at ? new Date(row.last_login_at).toLocaleString() : 'Never' }}</td>
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
