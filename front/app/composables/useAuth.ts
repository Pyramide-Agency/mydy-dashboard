export const useAuth = () => {
  const token = useState<string | null>('auth_token', () => null)
  const api = useApi()

  const isAuthenticated = computed(() => !!token.value)

  const login = async (password: string) => {
    const res: any = await api.login(password)
    token.value = res.token
    if (import.meta.client) {
      localStorage.setItem('auth_token', res.token)
    }
    return res
  }

  const logout = async () => {
    try { await api.logout() } catch {}
    token.value = null
    if (import.meta.client) {
      localStorage.removeItem('auth_token')
    }
    await navigateTo('/login')
  }

  const initAuth = () => {
    if (import.meta.client) {
      const stored = localStorage.getItem('auth_token')
      if (stored) token.value = stored
    }
  }

  return { token, isAuthenticated, login, logout, initAuth }
}
