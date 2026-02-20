type ToastType = 'success' | 'error'

interface ToastItem {
  id: string
  type: ToastType
  message: string
}

const createId = () => `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`

export const useToast = () => {
  const toasts = useState<ToastItem[]>('toasts', () => [])

  const remove = (id: string) => {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  const add = (type: ToastType, message: string, duration = 3500) => {
    const id = createId()
    toasts.value = [...toasts.value, { id, type, message }]
    if (import.meta.client) {
      setTimeout(() => remove(id), duration)
    }
  }

  return {
    toasts,
    remove,
    success: (message: string) => add('success', message),
    error: (message: string) => add('error', message, 4500),
  }
}
