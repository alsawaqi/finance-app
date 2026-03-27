<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const requestId = computed(() => Number(route.params.id))
const canSign = computed(() => requestId.value === 101)
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">Contract Signature</span>
      <h1 class="client-hero-title">Review and sign the contract only after the admin has approved your request.</h1>
      <p class="client-hero-text">
        This page is request-specific and should only be opened when the request stage becomes <strong>Waiting for Signature</strong>.
      </p>
    </section>

    <section class="client-card-grid client-reveal-left">
      <article class="client-sign-card">
        <div class="client-card-head">
          <div>
            <h3>Contract Preview</h3>
            <p class="client-subtext">Request #REQ-2026-{{ requestId }} · The final generated contract preview will appear here later.</p>
          </div>
          <span class="client-badge" :class="canSign ? 'client-badge--purple' : 'client-badge--amber'">
            {{ canSign ? 'Signature Required' : 'Not Available Yet' }}
          </span>
        </div>

        <div class="client-signature-pad">
          <div>
            <strong>Signature Pad Area</strong>
            <p class="client-muted">Your existing signature pad package can be connected here in the next step.</p>
          </div>
        </div>

        <div class="client-inline-actions">
          <button type="button" class="client-btn-primary" :disabled="!canSign">Submit Signature</button>
          <RouterLink :to="{ name: 'client-request-details', params: { id: requestId } }" class="client-btn-secondary">Back to Request</RouterLink>
        </div>
      </article>
    </section>
  </div>
</template>
