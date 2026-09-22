/** Shapes returned by the backend API. Monetary values are always integer cents. */

export interface User {
  id: number
  name: string
  email: string
}

export interface LoginCredentials {
  email: string
  password: string
}

export interface LoginResponse {
  token: string
  user: User
}

export interface DashboardSummary {
  income: number
  expense: number
  balance: number
}

export type TransactionType = 'income' | 'expense'

export interface Transaction {
  id: number
  /** ISO calendar date, `YYYY-MM-DD`. */
  date: string
  description: string
  amount: number
  type: TransactionType
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface Paginated<T> {
  data: T[]
  meta: PaginationMeta
}

export type ImportStatus = 'pending' | 'processing' | 'completed' | 'failed'

export interface ImportRowError {
  line: number
  message: string
}

export interface Import {
  id: number
  status: ImportStatus
  total_rows: number | null
  processed_rows: number
  failed_rows: number
  errors: ImportRowError[]
  created_at: string
  finished_at: string | null
}

/** Envelope used by Laravel API Resources. */
export interface DataEnvelope<T> {
  data: T
}

/** Laravel validation error body (HTTP 422). */
export interface ValidationErrorBody {
  message: string
  errors?: Record<string, string[]>
}
