import en from '~/i18n/en'
import ru from '~/i18n/ru'

const translations = { en, ru }

export function useLocale() {
  const locale = useState<'en' | 'ru'>('locale', () => {
    if (process.client) {
      return (localStorage.getItem('locale') as 'en' | 'ru') ?? 'ru'
    }
    return 'ru'
  })

  function $t(key: string, defaultValue?: string): string {
    const keys = key.split('.')
    let result: any = translations[locale.value]

    for (const k of keys) {
      result = result?.[k]
      if (result === undefined) break
    }

    return result ?? defaultValue ?? key
  }

  function setLocale(lang: 'en' | 'ru') {
    locale.value = lang
    if (process.client) {
      localStorage.setItem('locale', lang)
    }
  }

  function plural(n: number, one: string, few: string, many: string): string {
    const mod10 = n % 10
    const mod100 = n % 100
    if (mod10 === 1 && mod100 !== 11) return one
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few
    return many
  }

  function $p(n: number, key: string): string {
    const singular = `${key}.singular` || key
    const few = `${key}.few` || key
    const many = `${key}.many` || key

    return plural(n, $t(singular), $t(few), $t(many))
  }

  return { $t, locale, setLocale, plural, $p }
}
