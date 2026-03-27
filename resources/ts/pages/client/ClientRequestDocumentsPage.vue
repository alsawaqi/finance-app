<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const requestId = computed(() => Number(route.params.id))
const documentStageOpen = computed(() => requestId.value === 102)

const requiredDocuments = [
  { title: 'National ID Copy', status: 'Pending Upload', badgeClass: 'client-badge--amber' },
  { title: 'Latest Bank Statement', status: 'Pending Upload', badgeClass: 'client-badge--amber' },
  { title: 'Salary Certificate / Income Proof', status: 'Uploaded', badgeClass: 'client-badge--green' },
]
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">Request Documents</span>
      <h1 class="client-hero-title">Upload required documents only when the request reaches the correct workflow status.</h1>
      <p class="client-hero-text">
        This upload page belongs to a single request. It should open only when the admin has already approved the request and the contract stage has been completed.
      </p>
      <div class="client-empty-note" v-if="!documentStageOpen">
        Document upload is not available for this request yet because the request has not reached the required status.
      </div>
    </section>

    <section class="client-doc-grid client-reveal-left">
      <article v-for="doc in requiredDocuments" :key="doc.title" class="client-doc-card">
        <div class="client-card-head">
          <div>
            <h3>{{ doc.title }}</h3>
            <p class="client-subtext">Upload status is tracked per request and can be updated again later if admin asks for changes.</p>
          </div>
          <span class="client-badge" :class="doc.badgeClass">{{ doc.status }}</span>
        </div>

        <div class="client-inline-actions">
          <button type="button" class="client-btn-primary" :disabled="!documentStageOpen">Upload File</button>
          <button type="button" class="client-btn-secondary">View Requirement</button>
        </div>
      </article>
    </section>

    <section class="client-card-grid client-reveal-up">
      <article class="client-content-card client-content-card--full">
        <div class="client-card-head">
          <div>
            <h3>Document Workflow Notes</h3>
            <p class="client-subtext">Keep the document upload logic attached to the request itself, not as a general client page.</p>
          </div>
        </div>
        <div class="client-doc-list">
          <div class="client-doc-item">
            <span>Step 1</span>
            <strong>Admin approves the request.</strong>
          </div>
          <div class="client-doc-item">
            <span>Step 2</span>
            <strong>Client signs the generated contract.</strong>
          </div>
          <div class="client-doc-item">
            <span>Step 3</span>
            <strong>Document upload becomes available for that request.</strong>
          </div>
          <div class="client-doc-item">
            <span>Step 4</span>
            <strong>Admin may later request document updates if needed.</strong>
          </div>
        </div>

        <div class="client-inline-actions">
          <RouterLink :to="{ name: 'client-request-details', params: { id: requestId } }" class="client-btn-secondary">Back to Request</RouterLink>
        </div>
      </article>
    </section>
  </div>
</template>
