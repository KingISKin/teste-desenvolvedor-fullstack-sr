import { apiClient } from './client'
import type { DataEnvelope, Import } from '@/types/api'

export const importsApi = {
  /** Uploads the CSV; the server queues it and answers 202 with the pending import. */
  async upload(file: File, onProgress?: (percent: number) => void): Promise<Import> {
    const form = new FormData()
    form.append('file', file)
    const { data } = await apiClient.post<DataEnvelope<Import>>('/imports', form, {
      onUploadProgress: (event) => {
        const total = event.total ?? file.size
        if (onProgress && total > 0) {
          onProgress(Math.min(100, Math.round((event.loaded * 100) / total)))
        }
      },
    })
    return data.data
  },
  async show(id: number): Promise<Import> {
    const { data } = await apiClient.get<DataEnvelope<Import>>(`/imports/${id}`)
    return data.data
  },
}
