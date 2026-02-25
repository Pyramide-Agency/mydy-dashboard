<template>
  <header class="bg-white border-b border-border px-6 h-14 flex items-center justify-between shrink-0 shadow-sm">
    <div class="flex items-center gap-3">
      <div>
        <h1 class="text-base font-semibold text-foreground leading-tight">{{ title }}</h1>
        <p v-if="subtitle" class="text-xs text-muted-foreground">{{ subtitle }}</p>
      </div>
    </div>

    <div class="flex items-center gap-3">
      <div class="text-right hidden sm:block">
        <p class="text-xs font-medium text-foreground capitalize">{{ weekday }}</p>
        <p class="text-xs text-muted-foreground">{{ shortDate }}</p>
      </div>
      <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center">
        <User class="w-3.5 h-3.5 text-white" />
      </div>
    </div>
  </header>
</template>

<script setup lang="ts">
import { User } from 'lucide-vue-next'

const route = useRoute()
const { $t, locale } = useLocale()

const pageMap = computed(() => ({
  '/':               { title: $t('sidebar.dashboard'), subtitle: $t('header.overviewToday') },
  '/kanban':         { title: $t('sidebar.tasks'),     subtitle: $t('header.taskManagement') },
  '/kanban/archive': { title: $t('header.taskArchive'), subtitle: $t('header.completedTasks') },
  '/finance':        { title: $t('sidebar.finance'),   subtitle: $t('header.expenseTracking') },
  '/ai':             { title: $t('ai.title'),          subtitle: $t('header.personalAssistant') },
  '/settings':       { title: $t('sidebar.settings'),  subtitle: $t('header.configuration') },
}))

const current  = computed(() => (pageMap.value as any)[route.path] || { title: 'Dashboard' })
const title    = computed(() => current.value.title)
const subtitle = computed(() => current.value.subtitle)

const now = new Date()
const localeStr = computed(() => locale.value === 'en' ? 'en-US' : 'ru-RU')
const weekday   = computed(() => now.toLocaleDateString(localeStr.value, { weekday: 'long' }))
const shortDate = computed(() => now.toLocaleDateString(localeStr.value, { day: 'numeric', month: 'long' }))
</script>
