<template>
    <div class="min-h-screen bg-slate-100 p-8">
      <div class="mx-auto max-w-4xl rounded-2xl bg-white p-8 shadow">
        <h1 class="text-3xl font-bold text-slate-900">Home Page</h1>
        <p class="mt-3 text-slate-600">
          Tailwind CSS is working with Laravel + Vue SPA.
        </p>
  
        <div class="mt-6 flex flex-wrap gap-3">
          <RouterLink to="/" class="rounded-lg bg-slate-900 px-4 py-2 text-white">Home</RouterLink>
          <RouterLink to="/about" class="rounded-lg border border-slate-300 px-4 py-2 text-slate-700">About</RouterLink>
          <RouterLink to="/login" class="rounded-lg border border-slate-300 px-4 py-2 text-slate-700">Login</RouterLink>
          <RouterLink to="/dashboard" class="rounded-lg border border-slate-300 px-4 py-2 text-slate-700">Client</RouterLink>
          <RouterLink to="/admin" class="rounded-lg border border-slate-300 px-4 py-2 text-slate-700">Admin</RouterLink>
          <RouterLink to="/staff" class="rounded-lg border border-slate-300 px-4 py-2 text-slate-700">Staff</RouterLink>
        </div>
      </div>
    </div>
  </template>
  
  <script setup lang="ts">
  </script>