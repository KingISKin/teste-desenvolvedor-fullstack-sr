<script setup lang="ts">
import { computed, ref } from 'vue'
import { importsApi } from '@/api/imports'
import { extractErrorMessage } from '@/api/errors'
import { useImportPolling } from '@/composables/useImportPolling'
import type { Import } from '@/types/api'
import { validateCsvFile } from '@/utils/csvFile'

/** `finished` fires on `completed` and `failed`: either way the data may have changed. */
const emit = defineEmits<{ finished: [result: Import] }>()

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const validationError = ref<string | null>(null)
const uploadError = ref<string | null>(null)
const uploading = ref(false)
const uploadProgress = ref(0)

const {
  current: currentImport,
  isPolling,
  error: pollingError,
  start: startPolling,
  reset: resetPolling,
} = useImportPolling({
  onSettled: (result) => emit('finished', result),
})

const busy = computed(() => uploading.value || isPolling.value)

/** Header is line 1, so data rows start at line 2; line 0 is a file-level problem. */
const FIRST_DATA_LINE = 2
const fileErrors = computed(() => currentImport.value?.errors.filter((e) => e.line < FIRST_DATA_LINE) ?? [])
const rowErrors = computed(() => currentImport.value?.errors.filter((e) => e.line >= FIRST_DATA_LINE) ?? [])
const numberFormat = new Intl.NumberFormat('pt-BR')
const n = (value: number) => numberFormat.format(value)

const statusMessage = computed(() => {
  const current = currentImport.value
  if (uploading.value) return `Uploading… ${uploadProgress.value}%`
  if (!current) return ''
  switch (current.status) {
    case 'pending':
      return 'File received. Waiting in the queue…'
    case 'processing': {
      const total = current.total_rows !== null ? ` of ${n(current.total_rows)}` : ''
      return `Processing… ${n(current.processed_rows)}${total} rows processed, ${n(current.failed_rows)} failed.`
    }
    case 'completed':
      return `Import completed: ${n(current.processed_rows)} rows imported, ${n(current.failed_rows)} rows rejected.`
    case 'failed':
      return 'Import failed.'
    default:
      return ''
  }
})

function onFileChange(event: Event): void {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0] ?? null
  uploadError.value = null
  validationError.value = file ? validateCsvFile(file) : null
  selectedFile.value = file && !validationError.value ? file : null
}

async function submit(): Promise<void> {
  const file = selectedFile.value
  if (!file || busy.value) return

  uploading.value = true
  uploadProgress.value = 0
  uploadError.value = null
  // A new attempt must never show the outcome of the previous import.
  resetPolling()

  try {
    const created = await importsApi.upload(file, (percent) => {
      uploadProgress.value = percent
    })
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    startPolling(created)
  } catch (e) {
    uploadError.value = extractErrorMessage(e, 'Upload failed. Please try again.')
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <section class="panel upload" aria-labelledby="upload-title">
    <h2 id="upload-title" class="panel-title">Import transactions</h2>
    <p id="upload-hint" class="muted">
      CSV with the header <code>date,description,amount,type</code>. Max 20 MB.
    </p>

    <form class="upload-form" @submit.prevent="submit">
      <label for="csv-file" class="field-label">CSV file</label>
      <div class="upload-row">
        <input
          id="csv-file"
          ref="fileInput"
          type="file"
          accept=".csv,text/csv"
          aria-describedby="upload-hint"
          :aria-invalid="validationError ? 'true' : undefined"
          :disabled="busy"
          @change="onFileChange"
        />
        <button type="submit" class="btn btn-primary" :disabled="!selectedFile || busy">
          {{ uploading ? 'Uploading…' : 'Upload' }}
        </button>
      </div>
    </form>

    <p v-if="validationError" class="alert alert-error" role="alert" data-test="validation-error">
      {{ validationError }}
    </p>

    <div class="upload-status" role="status" aria-live="polite" data-test="upload-status">
      <template v-if="uploading">
        <progress class="progress" max="100" :value="uploadProgress" aria-label="Upload progress" />
      </template>
      <p
        v-if="statusMessage"
        class="status-line"
        :class="currentImport && !uploading ? `status-${currentImport.status}` : 'status-uploading'"
        data-test="status-message"
      >
        <span v-if="isPolling" class="spinner" aria-hidden="true" />
        {{ statusMessage }}
      </p>
      <p v-if="uploadError" class="alert alert-error" data-test="upload-error">{{ uploadError }}</p>
      <p v-if="pollingError" class="alert alert-error" data-test="polling-error">
        {{ pollingError }}
      </p>
    </div>

    <template v-if="!uploading">
      <p
        v-for="fileError in fileErrors"
        :key="`file-${fileError.line}-${fileError.message}`"
        class="alert alert-error"
        data-test="import-error"
      >
        {{ fileError.message }}
      </p>
    </template>

    <div v-if="!uploading && rowErrors.length > 0" class="row-errors">
      <h3 class="row-errors-title">Rejected rows</h3>
      <ul data-test="row-errors">
        <li v-for="rowError in rowErrors" :key="`${rowError.line}-${rowError.message}`">
          <span class="line">Line {{ rowError.line }}:</span> {{ rowError.message }}
        </li>
      </ul>
    </div>
  </section>
</template>

<style scoped>
.upload-form {
  margin-top: 1rem;
}

.upload-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  align-items: center;
}

.upload-row input[type='file'] {
  flex: 1 1 16rem;
  min-width: 0;
  font: inherit;
}

.upload-status {
  margin-top: 1rem;
}

.progress {
  width: 100%;
  height: 0.5rem;
  accent-color: var(--color-accent);
}

.status-line {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  margin: 0.5rem 0 0;
  font-weight: 500;
}

.status-completed {
  color: var(--color-income);
}

.status-failed {
  color: var(--color-expense);
}

.spinner {
  width: 0.9rem;
  height: 0.9rem;
  border: 2px solid var(--color-border);
  border-top-color: var(--color-accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  flex: none;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .spinner {
    animation: none;
  }
}

.row-errors {
  margin-top: 1rem;
}

.row-errors-title {
  font-size: 0.9rem;
  margin: 0 0 0.5rem;
}

.row-errors ul {
  margin: 0;
  padding: 0.5rem 0.75rem;
  list-style: none;
  max-height: 12rem;
  overflow-y: auto;
  background: var(--color-surface-muted);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  font-size: 0.875rem;
}

.row-errors li + li {
  margin-top: 0.25rem;
}

.row-errors .line {
  font-weight: 600;
}
</style>
