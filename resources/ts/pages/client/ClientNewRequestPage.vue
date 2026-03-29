<template>
  <div class="client-page-grid">
    <section class="client-hero-card client-reveal-up">
      <span class="client-eyebrow">New Request</span>
      <h1 class="client-hero-title">Create a request with a guided flow.</h1>
      <p class="client-hero-text">
        Start the request wizard to answer the configured questions, add your basic details, attach initial files,
        and send everything to the admin review stage.
      </p>
    </section>

    <section class="client-card-grid client-reveal-left">
      <article class="client-content-card client-content-card--full client-start-card">
        <div class="client-card-head">
          <div>
            <h3>Create a Request with a Guided Flow</h3>
            <p class="client-subtext">
              Keep this first screen simple. When you are ready, tap start and continue in the step-by-step wizard.
            </p>
          </div>
          <span class="client-badge client-badge--purple">2 Steps</span>
        </div>

        <div class="client-empty-note">
          You will first answer the request questions prepared by the admin, then add your basic details and initial attachments before submitting.
        </div>

        <div class="client-inline-actions">
          <RouterLink :to="{ name: 'client-request-wizard' }" class="client-btn-primary">Start</RouterLink>
        </div>
      </article>
    </section>
  </div>
</template>
