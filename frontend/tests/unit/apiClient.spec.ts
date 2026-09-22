import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { AxiosError, AxiosHeaders, type AxiosAdapter, type InternalAxiosRequestConfig } from 'axios'
import { createApiClient, setUnauthorizedHandler } from '@/api/client'
import { extractErrorMessage } from '@/api/errors'
import { tokenStorage } from '@/utils/tokenStorage'

function respond(status: number, data: unknown = {}): AxiosAdapter {
  return async (config: InternalAxiosRequestConfig) => {
    const response = { status, statusText: '', data, headers: new AxiosHeaders(), config }
    if (status >= 400) {
      throw new AxiosError(`Status ${status}`, AxiosError.ERR_BAD_REQUEST, config, null, response)
    }
    return response
  }
}

describe('api client', () => {
  beforeEach(() => sessionStorage.clear())
  afterEach(() => setUnauthorizedHandler(null))

  it('uses /api as base URL and attaches the bearer token', async () => {
    tokenStorage.set('abc')
    const client = createApiClient()
    const adapter = vi.fn(respond(200))
    client.defaults.adapter = adapter

    await client.get('/dashboard')

    const config = adapter.mock.calls[0]![0]
    expect(config.baseURL).toBe('/api')
    expect(config.headers.get('Authorization')).toBe('Bearer abc')
  })

  it('does not send an Authorization header without a token', async () => {
    const client = createApiClient()
    const adapter = vi.fn(respond(200))
    client.defaults.adapter = adapter

    await client.get('/dashboard')

    expect(adapter.mock.calls[0]![0].headers.has('Authorization')).toBe(false)
  })

  it('clears the token and notifies the handler on 401', async () => {
    tokenStorage.set('expired')
    const onUnauthorized = vi.fn()
    setUnauthorizedHandler(onUnauthorized)
    const client = createApiClient()
    client.defaults.adapter = respond(401, { message: 'Unauthenticated.' })

    await expect(client.get('/dashboard')).rejects.toBeInstanceOf(AxiosError)

    expect(tokenStorage.get()).toBeNull()
    expect(onUnauthorized).toHaveBeenCalledOnce()
  })

  it('keeps the session on other errors', async () => {
    tokenStorage.set('abc')
    const onUnauthorized = vi.fn()
    setUnauthorizedHandler(onUnauthorized)
    const client = createApiClient()
    client.defaults.adapter = respond(422, { message: 'Invalid', errors: { file: ['Bad file.'] } })

    const error = await client.post('/imports').catch((e: unknown) => e)

    expect(onUnauthorized).not.toHaveBeenCalled()
    expect(tokenStorage.get()).toBe('abc')
    expect(extractErrorMessage(error)).toBe('Bad file.')
  })

  it('maps throttling and network failures to friendly messages', async () => {
    const client = createApiClient()
    client.defaults.adapter = respond(429)
    const throttled = await client.post('/auth/login').catch((e: unknown) => e)
    expect(extractErrorMessage(throttled)).toMatch(/Too many attempts/)

    client.defaults.adapter = async (config) => {
      throw new AxiosError('Network Error', AxiosError.ERR_NETWORK, config)
    }
    const offline = await client.get('/dashboard').catch((e: unknown) => e)
    expect(extractErrorMessage(offline)).toMatch(/Network error/)
  })
})
