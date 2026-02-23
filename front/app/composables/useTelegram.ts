export const useTelegram = () => {
  const getTg = () =>
    import.meta.client ? (window as any).Telegram?.WebApp : null

  const isInTelegram = computed(() => !!getTg())

  const showBackButton = (callback: () => void) => {
    const tg = getTg()
    if (!tg) return
    tg.BackButton.onClick(callback)
    tg.BackButton.show()
  }

  const hideBackButton = () => {
    const tg = getTg()
    if (!tg) return
    tg.BackButton.hide()
  }

  const hapticFeedback = (type: 'light' | 'medium' | 'heavy' | 'selection' = 'light') => {
    const tg = getTg()
    if (!tg?.HapticFeedback) return
    if (type === 'selection') tg.HapticFeedback.selectionChanged()
    else tg.HapticFeedback.impactOccurred(type)
  }

  return { isInTelegram, showBackButton, hideBackButton, hapticFeedback }
}
