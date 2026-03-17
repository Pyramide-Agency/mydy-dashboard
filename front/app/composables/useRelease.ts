const REPO = 'Pyramide-Agency/mydy-dashboard'
const CACHE_KEY = 'vektron_release_cache'
const CACHE_TTL = 1000 * 60 * 60 // 1 hour

export interface GithubRelease {
  tag_name: string
  name: string
  body: string
  html_url: string
  published_at: string
  prerelease: boolean
}

export const useRelease = () => {
  const release    = useState<GithubRelease | null>('release_latest', () => null)
  const hasUpdate  = useState<boolean>('release_has_update', () => false)
  const showDialog = useState<boolean>('release_dialog', () => false)

  const fetchRelease = async () => {
    if (!import.meta.client) return

    try {
      const cached = localStorage.getItem(CACHE_KEY)
      if (cached) {
        const { data, ts } = JSON.parse(cached)
        if (Date.now() - ts < CACHE_TTL) {
          release.value   = data
          hasUpdate.value = !!data
          return
        }
      }
    } catch {}

    try {
      const res = await $fetch<GithubRelease>(
        `https://api.github.com/repos/${REPO}/releases/latest`,
        { headers: { Accept: 'application/vnd.github+json' } }
      )
      if (res?.tag_name && !res.prerelease) {
        release.value   = res
        hasUpdate.value = true
        localStorage.setItem(CACHE_KEY, JSON.stringify({ data: res, ts: Date.now() }))
      }
    } catch {}
  }

  const open  = () => { showDialog.value = true }
  const close = () => { showDialog.value = false }

  return { release, hasUpdate, showDialog, fetchRelease, open, close }
}
