export const useApi = () => {
  const config = useRuntimeConfig()
  const base = config.public.apiBase
  const toast = useToast()

  const token = () => {
    if (import.meta.client) {
      return localStorage.getItem('auth_token') || ''
    }
    return ''
  }

  const headers = () => ({
    Authorization: `Bearer ${token()}`,
    'Content-Type': 'application/json',
    'ngrok-skip-browser-warning': 'true',
    Accept: 'application/json',
  })

  const pickSuccessMessage = (data: any) => {
    if (data && typeof data.message === 'string' && data.message.trim()) return data.message
    if (data && typeof data.success === 'string' && data.success.trim()) return data.success
    return ''
  }

  const pickErrorMessage = (err: any, fallback = 'Ошибка запроса') => {
    const data = err?.data
    if (data && typeof data.message === 'string' && data.message.trim()) return data.message
    if (data && typeof data.error === 'string' && data.error.trim()) return data.error
    if (typeof err?.message === 'string' && err.message.trim()) return err.message
    return fallback
  }

  // Unwrap standard {statusCode, status, timestamp, message, data} envelope
  const unwrap = (raw: any) => {
    if (raw && typeof raw === 'object' && !Array.isArray(raw) && 'statusCode' in raw) {
      return raw.data ?? null
    }
    return raw
  }

  const request = async <T>(
    path: string,
    options: Record<string, any> = {},
    toastOptions?: { success?: boolean | string; error?: boolean | string },
  ) => {
    try {
      const raw = await $fetch<any>(path, { baseURL: base, headers: headers(), ...options })
      if (toastOptions?.success) {
        // Use raw (pre-unwrap) to read top-level message for toast
        const message = typeof toastOptions.success === 'string'
          ? toastOptions.success
          : pickSuccessMessage(raw)
        if (message) toast.success(message)
      }
      return unwrap(raw) as T
    } catch (err: any) {
      if (toastOptions?.error) {
        const message = typeof toastOptions.error === 'string'
          ? toastOptions.error
          : pickErrorMessage(err)
        if (message) toast.error(message)
      }
      throw err
    }
  }

  const get = (path: string, params?: Record<string, any>) =>
    request(path, { params })

  const post = (path: string, body?: any) =>
    request(path, { method: 'POST', body }, { success: true, error: true })

  const put = (path: string, body?: any) =>
    request(path, { method: 'PUT', body }, { success: true, error: true })

  const del = (path: string) =>
    request(path, { method: 'DELETE' }, { success: true, error: true })

  return {
    // Auth
    login: (password: string) => request('/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: { password },
    }, { success: true, error: true }),
    logout: () => post('/auth/logout'),
    checkAuth: () => get('/auth/check'),

    // Boards
    getBoards: () => get('/boards'),
    getBoard: (id: number) => get(`/boards/${id}`),
    createBoard: (data: any) => post('/boards', data),
    updateBoard: (id: number, data: any) => put(`/boards/${id}`, data),
    deleteBoard: (id: number) => del(`/boards/${id}`),

    // Columns
    getColumns: (boardId: number) => get(`/boards/${boardId}/columns`),
    createColumn: (boardId: number, data: any) => post(`/boards/${boardId}/columns`, data),
    updateColumn: (id: number, data: any) => put(`/columns/${id}`, data),
    deleteColumn: (id: number) => del(`/columns/${id}`),

    // Tasks
    createTask: (data: any) => post('/tasks', data),
    updateTask: (id: number, data: any) => put(`/tasks/${id}`, data),
    deleteTask: (id: number) => del(`/tasks/${id}`),
    moveTask: (id: number, data: any) => post(`/tasks/${id}/move`, data),
    archiveDone: (boardId?: number) => post('/tasks/archive-done', boardId ? { board_id: boardId } : {}),
    getArchived: () => get('/tasks/archived'),

    // Finance entries
    getEntries: (params?: any) => get('/finance/entries', params),
    createEntry: (data: any) => post('/finance/entries', data),
    updateEntry: (id: number, data: any) => put(`/finance/entries/${id}`, data),
    deleteEntry: (id: number) => del(`/finance/entries/${id}`),

    // Finance categories
    getCategories: () => get('/finance/categories'),
    createCategory: (data: any) => post('/finance/categories', data),
    updateCategory: (id: number, data: any) => put(`/finance/categories/${id}`, data),
    deleteCategory: (id: number) => del(`/finance/categories/${id}`),

    // Finance summary & AI
    getSummary: (period: string) => get('/finance/summary', { period }),
    getAiFeedback: () => post('/finance/ai-feedback'),
    listConversations: () => get('/finance/conversations'),
    createConversation: () => post('/finance/conversations'),
    deleteConversation: (id: number) => del(`/finance/conversations/${id}`),
    getConversation: (id?: number) => get('/finance/ai-conversation', id ? { id } : undefined),

    // Settings
    getSettings: () => get('/settings'),
    updateSettings: (data: any) => put('/settings', data),

    // Telegram
    registerTelegram: (token: string) => post('/telegram/register', { token }),

    // Raw base URL for streaming
    baseUrl: base,
    getHeaders: headers,
  }
}
