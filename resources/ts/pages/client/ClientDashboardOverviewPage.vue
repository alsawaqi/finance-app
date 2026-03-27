<script setup lang="ts">
const kpis = [
  { label: 'Total Requests', value: '08', badge: 'Updated today', badgeClass: 'client-badge--blue' },
  { label: 'Action Needed', value: '02', badge: 'Needs your attention', badgeClass: 'client-badge--amber' },
  { label: 'Waiting for Signature', value: '01', badge: 'Contract ready', badgeClass: 'client-badge--purple' },
  { label: 'Waiting for Documents', value: '01', badge: 'Upload requested', badgeClass: 'client-badge--green' },
]

const actionCards = [
  {
    title: 'Create a New Request',
    text: 'Start a new request and answer the required questions before submitting it to the admin team.',
    route: { name: 'client-new-request' },
    action: 'Start Request',
  },
  {
    title: 'Review Existing Requests',
    text: 'Track status changes, open request details, and respond quickly when the workflow needs your input.',
    route: { name: 'client-requests' },
    action: 'Open Requests',
  },
  {
    title: 'Complete Pending Client Actions',
    text: 'Sign contracts or upload documents only when a request reaches the stage where you are asked to act.',
    route: { name: 'client-requests' },
    action: 'See Pending Actions',
  },
]

const spotlightRequests = [
  {
    id: 101,
    title: 'SME Working Capital Support',
    code: 'REQ-2026-101',
    stage: 'Waiting for Signature',
    badgeClass: 'client-badge--purple',
    progress: '82%',
    note: 'Admin approved the request. Your signature is now required to continue.',
    actionLabel: 'Sign Contract',
    actionRoute: { name: 'client-request-sign', params: { id: 101 } },
  },
  {
    id: 102,
    title: 'Personal Finance Restructuring',
    code: 'REQ-2026-102',
    stage: 'Waiting for Documents',
    badgeClass: 'client-badge--green',
    progress: '88%',
    note: 'Your contract is signed. Upload the requested documents to move the file forward.',
    actionLabel: 'Upload Documents',
    actionRoute: { name: 'client-request-documents', params: { id: 102 } },
  },
]

const timeline = [
  { title: 'Working Capital Support approved by admin', meta: 'Today · 10:35 AM' },
  { title: 'Signature requested for REQ-2026-101', meta: 'Today · 10:38 AM' },
  { title: 'Documents requested for REQ-2026-102', meta: 'Yesterday · 03:20 PM' },
]

const activities = [
  { title: 'Request submitted successfully', meta: 'REQ-2026-104 · 2 hours ago', tag: 'Submitted', tagClass: 'client-badge--blue' },
  { title: 'Contract signed and stored', meta: 'REQ-2026-099 · Yesterday', tag: 'Signed', tagClass: 'client-badge--purple' },
  { title: 'Admin asked for supporting ID copy', meta: 'REQ-2026-102 · Yesterday', tag: 'Action Needed', tagClass: 'client-badge--amber' },
]
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">Client Dashboard</span>
      <h1 class="client-hero-title">Follow each request clearly from submission to approval, signature, and document upload.</h1>
      <p class="client-hero-text">
        This dashboard is now focused on the real client workflow: create a request, monitor its progress, and act only when the request reaches a stage that needs your response.
      </p>
      <div class="client-hero-actions">
        <RouterLink :to="{ name: 'client-new-request' }" class="client-btn-primary">Create New Request</RouterLink>
        <RouterLink :to="{ name: 'client-requests' }" class="client-btn-secondary">View My Requests</RouterLink>
      </div>
    </section>

    <section class="client-kpi-grid client-reveal-up">
      <article v-for="kpi in kpis" :key="kpi.label" class="client-kpi-card">
        <div class="client-kpi-value">{{ kpi.value }}</div>
        <h3>{{ kpi.label }}</h3>
        <span class="client-badge" :class="kpi.badgeClass">{{ kpi.badge }}</span>
      </article>
    </section>

    <section class="client-action-grid client-reveal-left">
      <article v-for="card in actionCards" :key="card.title" class="client-action-card">
        <h3>{{ card.title }}</h3>
        <p class="client-muted">{{ card.text }}</p>
        <RouterLink :to="card.route" class="client-btn-link">{{ card.action }}</RouterLink>
      </article>
    </section>

    <section class="client-card-grid client-reveal-left">
      <article v-for="request in spotlightRequests" :key="request.id" class="client-request-card">
        <div class="client-card-head">
          <div>
            <h3>{{ request.title }}</h3>
            <p class="client-meta">{{ request.code }}</p>
          </div>
          <span class="client-badge" :class="request.badgeClass">{{ request.stage }}</span>
        </div>

        <p class="client-muted">{{ request.note }}</p>

        <div class="client-progress">
          <span :style="{ width: request.progress }"></span>
        </div>

        <div class="client-row-between">
          <span class="client-meta">Progress {{ request.progress }}</span>
          <RouterLink :to="request.actionRoute" class="client-btn-secondary">{{ request.actionLabel }}</RouterLink>
        </div>
      </article>
    </section>

    <section class="client-card-grid client-reveal-up">
      <article class="client-content-card client-content-card--half">
        <div class="client-card-head">
          <div>
            <h3>Recent Workflow Updates</h3>
            <p class="client-subtext">A simple timeline showing what changed most recently.</p>
          </div>
        </div>
        <div class="client-timeline">
          <div v-for="item in timeline" :key="item.title" class="client-timeline-item">
            <div>
              <strong>{{ item.title }}</strong>
              <p class="client-meta">{{ item.meta }}</p>
            </div>
          </div>
        </div>
      </article>

      <article class="client-content-card client-content-card--half">
        <div class="client-card-head">
          <div>
            <h3>Recent Activity</h3>
            <p class="client-subtext">Helpful notices and recent client-side actions.</p>
          </div>
        </div>
        <div class="client-list">
          <div v-for="item in activities" :key="item.title" class="client-list-item">
            <div>
              <strong>{{ item.title }}</strong>
              <p class="client-meta">{{ item.meta }}</p>
            </div>
            <span class="client-badge" :class="item.tagClass">{{ item.tag }}</span>
          </div>
        </div>
      </article>
    </section>
  </div>
</template>
