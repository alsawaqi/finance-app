<script setup lang="ts">
const steps = [
  {
    title: 'Tell us what you need',
    text: 'Choose the request category and answer the business or personal finance questions required for review.',
  },
  {
    title: 'Attach the initial supporting files',
    text: 'Upload only the files needed for the initial submission. Later documents will be requested by status.',
  },
  {
    title: 'Submit and monitor the workflow',
    text: 'After submission, the admin reviews the request and the next client action appears only when needed.',
  },
]

const sections = [
  'Request category and finance purpose',
  'Client details and financial background',
  'Requested amount, priority, and expected timeline',
  'Supporting notes and initial attachments',
]
</script>

<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">New Request</span>
      <h1 class="client-hero-title">Create a request with a guided flow that feels clear, premium, and easy to follow.</h1>
      <p class="client-hero-text">
        This page is designed around your real process. The client starts here, submits the request, and then follows later actions from the request details page.
      </p>
    </section>

    <section class="client-steps-grid client-reveal-left">
      <article v-for="step in steps" :key="step.title" class="client-step-card">
        <span class="client-eyebrow">Step</span>
        <h3>{{ step.title }}</h3>
        <p class="client-muted">{{ step.text }}</p>
      </article>
    </section>

    <section class="client-card-grid client-reveal-up">
      <article class="client-content-card client-content-card--full">
        <div class="client-card-head">
          <div>
            <h3>Request Form Structure</h3>
            <p class="client-subtext">This stays design-first for now, but it is already shaped for your question-based workflow.</p>
          </div>
          <span class="client-badge client-badge--purple">Design Stage</span>
        </div>

        <div class="client-upload-grid">
          <div v-for="section in sections" :key="section" class="client-upload-card">
            <h3>{{ section }}</h3>
            <p class="client-muted">This block will later map directly to real fields, question groups, and upload components.</p>
          </div>
        </div>

        <div class="client-inline-actions">
          <button type="button" class="client-btn-primary">Submit Request</button>
          <button type="button" class="client-btn-secondary">Save as Draft</button>
        </div>
      </article>
    </section>
  </div>
</template>
