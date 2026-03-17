export const useFreelance = () => {
  const api = useApi()

  const getProjects = () => api.getFreelanceProjects()

  const createProject = (data: { name: string; color?: string; deadline?: string | null }) =>
    api.createFreelanceProject(data)

  const updateProject = (id: number, data: { name?: string; color?: string; deadline?: string | null }) =>
    api.updateFreelanceProject(id, data)

  const deleteProject = (id: number) => api.deleteFreelanceProject(id)

  const getActiveSession = () => api.getActiveFreelanceSession()

  const startTimer = (projectId: number) => api.startFreelanceTimer(projectId)

  const stopTimer = (note?: string) => api.stopFreelanceTimer(note)

  const pauseTimer = () => api.pauseFreelanceTimer()

  const resumeTimer = () => api.resumeFreelanceTimer()

  const getSessions = (params?: { project_id?: number; filter?: string }) =>
    api.getFreelanceSessions(params)

  const createSessionManual = (data: {
    project_id: number
    started_at: string
    ended_at: string
    note?: string
  }) => api.createFreelanceSession(data)

  const updateSession = (id: number, data: any) => api.updateFreelanceSession(id, data)

  const deleteSession = (id: number) => api.deleteFreelanceSession(id)

  const getStats = (filter: 'week' | 'month' = 'week') => api.getFreelanceStats(filter)

  const exportCSV = (params?: { project_id?: number; from?: string; to?: string }) => {
    const config = useRuntimeConfig()
    const base = config.public.apiBase
    const token = import.meta.client ? localStorage.getItem('auth_token') || '' : ''
    const q = new URLSearchParams()
    if (params?.project_id) q.set('project_id', String(params.project_id))
    if (params?.from) q.set('from', params.from)
    if (params?.to) q.set('to', params.to)
    const url = `${base}/freelance/export${q.toString() ? '?' + q.toString() : ''}`

    // Trigger download via fetch with auth header
    fetch(url, {
      headers: {
        Authorization: `Bearer ${token}`,
        'ngrok-skip-browser-warning': 'true',
      },
    })
      .then(res => res.blob())
      .then(blob => {
        const link = document.createElement('a')
        link.href = URL.createObjectURL(blob)
        link.download = 'freelance-sessions.csv'
        link.click()
        URL.revokeObjectURL(link.href)
      })
  }

  return {
    getProjects,
    createProject,
    updateProject,
    deleteProject,
    getActiveSession,
    startTimer,
    stopTimer,
    pauseTimer,
    resumeTimer,
    getSessions,
    createSessionManual,
    updateSession,
    deleteSession,
    getStats,
    exportCSV,
  }
}
