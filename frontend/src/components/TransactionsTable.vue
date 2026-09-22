<script setup lang="ts">
import { onMounted } from 'vue'
import { useTransactionsStore } from '@/stores/transactions'
import { formatDate } from '@/utils/date'
import { formatSignedCents } from '@/utils/money'

const transactions = useTransactionsStore()
const integer = new Intl.NumberFormat('pt-BR')

onMounted(() => {
  void transactions.fetchPage(1)
})
</script>

<template>
  <section class="panel" aria-labelledby="transactions-title">
    <h2 id="transactions-title" class="panel-title">Transactions</h2>

    <p v-if="transactions.error" class="alert alert-error" role="alert" data-test="table-error">
      {{ transactions.error }}
      <button type="button" class="btn btn-link" @click="transactions.refresh()">Retry</button>
    </p>

    <p
      v-else-if="!transactions.loading && transactions.items.length === 0"
      class="empty muted"
      data-test="table-empty"
    >
      No transactions yet. Import a CSV file to get started.
    </p>

    <div v-else class="table-wrap" :aria-busy="transactions.loading">
      <table>
        <thead>
          <tr>
            <th scope="col">Date</th>
            <th scope="col">Description</th>
            <th scope="col">Type</th>
            <th scope="col" class="num">Amount</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="transactions.loading && transactions.items.length === 0">
            <td colspan="4" class="muted" data-test="table-loading">Loading transactions…</td>
          </tr>
          <tr v-for="tx in transactions.items" :key="tx.id" data-test="transaction-row">
            <td class="nowrap">{{ formatDate(tx.date) }}</td>
            <td class="description">{{ tx.description }}</td>
            <td>
              <span class="badge" :class="`badge-${tx.type}`">
                {{ tx.type === 'income' ? 'Income' : 'Expense' }}
              </span>
            </td>
            <td class="num nowrap" :class="tx.type === 'income' ? 'positive' : 'negative'">
              {{ formatSignedCents(tx.amount, tx.type === 'expense') }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav
      v-if="transactions.meta && transactions.meta.total > 0"
      class="pagination"
      aria-label="Transactions pagination"
    >
      <button
        type="button"
        class="btn"
        :disabled="!transactions.hasPrevious || transactions.loading"
        data-test="prev-page"
        @click="transactions.previousPage()"
      >
        Previous
      </button>
      <span class="page-info" aria-live="polite" data-test="page-info">
        Page {{ integer.format(transactions.currentPage) }} of {{ integer.format(transactions.lastPage) }}
        <span class="muted">· {{ integer.format(transactions.meta.total) }} transactions</span>
      </span>
      <button
        type="button"
        class="btn"
        :disabled="!transactions.hasNext || transactions.loading"
        data-test="next-page"
        @click="transactions.nextPage()"
      >
        Next
      </button>
    </nav>
  </section>
</template>

<style scoped>
.table-wrap {
  margin-top: 1rem;
  overflow-x: auto;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
}

.table-wrap[aria-busy='true'] tbody {
  opacity: 0.55;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.925rem;
}

th,
td {
  padding: 0.65rem 0.9rem;
  text-align: left;
  border-bottom: 1px solid var(--color-border);
}

tbody tr:last-child td {
  border-bottom: 0;
}

th {
  background: var(--color-surface-muted);
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--color-muted);
}

.num {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.nowrap {
  white-space: nowrap;
}

.description {
  min-width: 12rem;
}

.badge {
  display: inline-block;
  padding: 0.1rem 0.55rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.badge-income {
  background: var(--color-income-soft);
  color: var(--color-income);
}

.badge-expense {
  background: var(--color-expense-soft);
  color: var(--color-expense);
}

.empty {
  margin: 1rem 0 0;
}

.pagination {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  margin-top: 1rem;
}

.page-info {
  text-align: center;
  font-size: 0.9rem;
}
</style>
