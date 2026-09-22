<script setup lang="ts">
import { onMounted } from 'vue'
import AppHeader from '@/components/AppHeader.vue'
import SummaryCards from '@/components/SummaryCards.vue'
import TransactionsTable from '@/components/TransactionsTable.vue'
import UploadCsv from '@/components/UploadCsv.vue'
import { useDashboardStore } from '@/stores/dashboard'
import { useTransactionsStore } from '@/stores/transactions'

const dashboard = useDashboardStore()
const transactions = useTransactionsStore()

onMounted(() => {
  void dashboard.fetchSummary()
})

/**
 * An import finished (completed, or failed and rolled back): totals may have
 * changed and the newest rows belong on page 1.
 */
function onImportFinished(): void {
  void dashboard.fetchSummary()
  void transactions.fetchPage(1)
}
</script>

<template>
  <AppHeader />
  <main class="container home">
    <SummaryCards />
    <UploadCsv @finished="onImportFinished" />
    <TransactionsTable />
  </main>
</template>

<style scoped>
.home {
  display: grid;
  gap: 1.5rem;
  padding-top: 1.5rem;
  padding-bottom: 3rem;
}
</style>
