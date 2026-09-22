<script setup lang="ts">
import { computed } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import { formatCents } from '@/utils/money'

const dashboard = useDashboardStore()

const balanceClass = computed(() => {
  const balance = dashboard.summary?.balance ?? 0
  if (balance > 0) return 'positive'
  if (balance < 0) return 'negative'
  return ''
})

const display = (cents: number | undefined) => (cents === undefined ? '—' : formatCents(cents))
</script>

<template>
  <section aria-labelledby="summary-title">
    <h2 id="summary-title" class="visually-hidden">Summary</h2>
    <p v-if="dashboard.error" class="alert alert-error" role="alert">{{ dashboard.error }}</p>
    <div class="cards" :aria-busy="dashboard.loading">
      <article class="card">
        <h3 class="card-label">Income</h3>
        <p class="card-value positive" data-test="income">{{ display(dashboard.summary?.income) }}</p>
      </article>
      <article class="card">
        <h3 class="card-label">Expense</h3>
        <p class="card-value negative" data-test="expense">{{ display(dashboard.summary?.expense) }}</p>
      </article>
      <article class="card">
        <h3 class="card-label">Balance</h3>
        <p class="card-value" :class="balanceClass" data-test="balance">
          {{ display(dashboard.summary?.balance) }}
        </p>
      </article>
    </div>
  </section>
</template>

<style scoped>
.alert {
  margin: 0 0 1rem;
}

.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
  gap: 1rem;
}

.card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: 1.1rem 1.25rem;
}

.card-label {
  margin: 0;
  font-size: 0.8rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-muted);
}

.card-value {
  margin: 0.4rem 0 0;
  font-size: 1.5rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}

.cards[aria-busy='true'] .card-value {
  opacity: 0.55;
}
</style>
