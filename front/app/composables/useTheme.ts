export type ThemeAccent = 'violet' | 'emerald'
export type ThemeMode  = 'dark' | 'light'

const accent = ref<ThemeAccent>('violet')
const mode   = ref<ThemeMode>('dark')

const applyTheme = () => {
  const html = document.documentElement
  // Apply accent
  html.setAttribute('data-theme', accent.value)
  // Apply dark/light
  if (mode.value === 'dark') {
    html.classList.add('dark')
  } else {
    html.classList.remove('dark')
  }
}

export const useTheme = () => {
  const setAccent = (value: ThemeAccent) => {
    accent.value = value
    localStorage.setItem('vektron-accent', value)
    applyTheme()
  }

  const setMode = (value: ThemeMode) => {
    mode.value = value
    localStorage.setItem('vektron-mode', value)
    applyTheme()
  }

  const initTheme = () => {
    const savedAccent = (localStorage.getItem('vektron-accent') as ThemeAccent) || 'violet'
    const savedMode   = (localStorage.getItem('vektron-mode')   as ThemeMode)   || 'dark'
    accent.value = savedAccent
    mode.value   = savedMode
    applyTheme()
  }

  return { accent, mode, setAccent, setMode, initTheme }
}
