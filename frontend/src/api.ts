export function apiUrl(path: string): string {
  const p = path.replace(/^\//, '')
  if (import.meta.env.DEV) {
    return `/api/${p}`
  }
  return new URL(`../api/${p}`, window.location.href).href
}

export async function apiFetch<T>(
  path: string,
  init?: RequestInit & { json?: unknown }
): Promise<T> {
  const { json, headers, ...rest } = init ?? {}
  const h = new Headers(headers)
  if (json !== undefined) {
    h.set('Content-Type', 'application/json')
  }
  const res = await fetch(apiUrl(path), {
    ...rest,
    headers: h,
    credentials: 'include',
    body: json !== undefined ? JSON.stringify(json) : rest.body,
  })
  const text = await res.text()
  let data: unknown = null
  if (text) {
    try {
      data = JSON.parse(text) as unknown
    } catch {
      data = { raw: text }
    }
  }
  if (!res.ok) {
    const err = data as { error?: string }
    throw new Error(err?.error || `HTTP ${res.status}`)
  }
  return data as T
}
