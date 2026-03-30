<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
 
import { getAdminCategorization } from '@/services/adminCategorization'

const loading = ref(true)
const errorMessage = ref('')
const summary = ref({
  total_requests: 0,
  submitted_requests: 0,
  active_requests: 0,
  completed_requests: 0,
  total_clients: 0,
  total_staff: 0,
  total_agents: 0,
  with_additional_document_requests: 0,
})
const statusBreakdown = ref<Record<string, number>>({})

const cards = computed(() => [
  { label: 'Submitted Queue', value: summary.value.submitted_requests, tone: 'amber', route: { name: 'admin-new-requests' } },
  { label: 'Active Requests', value: summary.value.active_requests, tone: 'blue', route: { name: 'admin-categorization' } },
  { label: 'Staff Members', value: summary.value.total_staff, tone: 'violet', route: { name: 'admin-staff' } },
  { label: 'Linked Agents', value: summary.value.total_agents, tone: 'emerald', route: { name: 'admin-agents' } },
])

const topStatuses = computed(() => Object.entries(statusBreakdown.value).sort((a, b) => b[1] - a[1]).slice(0, 5))

async function load() {
  loading.value = true
  errorMessage.value = ''
  try {
    const data = await getAdminCategorization()
    summary.value = data.summary
    statusBreakdown.value = data.status_breakdown ?? {}
  } catch (error: any) {
    errorMessage.value = error?.response?.data?.message || 'Failed to load admin dashboard summary.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <section class="admin-page-shell admin-dashboard-page">
    <section class="admin-hero admin-reveal-up">
      <div class="admin-hero__content">
        <span class="admin-hero__eyebrow">Operations Console</span>
        <h2>Keep the review queue, assignments, and relationship catalog tidy from one place.</h2>
        <p>
          This dashboard now focuses on real operational data so you can move quickly into the next action instead of scrolling through crowded screens.
        </p>
      </div>

      <div class="admin-hero__actions admin-hero__actions--stacked">
        <RouterLink :to="{ name: 'admin-new-requests' }" class="admin-primary-btn">Open review queue</RouterLink>
        <RouterLink :to="{ name: 'admin-categorization' }" class="admin-secondary-btn">Open categorization</RouterLink>
      </div>
    </section>

    <div v-if="errorMessage" class="admin-alert admin-alert--error">{{ errorMessage }}</div>

    <div class="admin-question-stats-grid admin-reveal-up admin-reveal-delay-1">
      <article v-for="card in cards" :key="card.label" class="admin-question-stat" :class="`tone-${card.tone}`">
        <strong>{{ loading ? '…' : card.value }}</strong>
        <span>{{ card.label }}</span>
        <RouterLink :to="card.route" class="admin-inline-link">Open</RouterLink>
      </article>
    </div>

    <div class="admin-dashboard-grid">
      <article class="panel-card catalog-highlight-card">
        <div class="panel-head"><h2>Quick distribution</h2></div>
        <div class="catalog-chip-grid">
          <span v-for="entry in topStatuses" :key="entry[0]" class="soft-tag">{{ entry[0] }} · {{ entry[1] }}</span>
        </div>
      </article>

      <article class="panel-card catalog-highlight-card">
        <div class="panel-head"><h2>Current totals</h2></div>
        <div class="catalog-mini-stats">
          <div>
            <span>Total requests</span>
            <strong>{{ loading ? '…' : summary.total_requests }}</strong>
          </div>
          <div>
            <span>Completed</span>
            <strong>{{ loading ? '…' : summary.completed_requests }}</strong>
          </div>
          <div>
            <span>Clients</span>
            <strong>{{ loading ? '…' : summary.total_clients }}</strong>
          </div>
          <div>
            <span>Additional docs</span>
            <strong>{{ loading ? '…' : summary.with_additional_document_requests }}</strong>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
