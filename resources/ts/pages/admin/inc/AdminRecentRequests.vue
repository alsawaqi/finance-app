<script setup lang="ts">
type RequestRow = {
  code: string
  client: string
  type: string
  status: string
  stage: string
}

defineProps<{
  rows: RequestRow[]
}>()
</script>

<template>
  <section class="admin-panel admin-reveal-up admin-reveal-delay-2">
    <div class="admin-panel__head">
      <div>
        <span class="admin-panel__eyebrow">Recent items</span>
        <h2>Latest requests</h2>
      </div>
      <button type="button" class="admin-panel__action">Open all</button>
    </div>

    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Request</th>
            <th>Client</th>
            <th>Type</th>
            <th>Status</th>
            <th>Stage</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.code">
            <td><strong>{{ row.code }}</strong></td>
            <td>{{ row.client }}</td>
            <td>{{ row.type }}</td>
            <td><span class="admin-status-pill">{{ row.status }}</span></td>
            <td>{{ row.stage }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>
