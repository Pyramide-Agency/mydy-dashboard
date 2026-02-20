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

const pageMap: Record<string, { title: string; subtitle?: string }> = {
  '/':                { title: 'Дашборд',     subtitle: 'Обзор на сегодня' },
  '/kanban':          { title: 'Канбан',       subtitle: 'Управление задачами' },
  '/kanban/archive':  { title: 'Архив задач',  subtitle: 'Завершённые задачи' },
  '/finance':         { title: 'Финансы',      subtitle: 'Учёт расходов' },
  '/finance/advisor': { title: 'AI Советник',  subtitle: 'Финансовый помощник' },
  '/settings':        { title: 'Настройки',    subtitle: 'Конфигурация' },
}

const current  = computed(() => pageMap[route.path] || { title: 'Dashboard' })
const title    = computed(() => current.value.title)
const subtitle = computed(() => current.value.subtitle)

const now = new Date()
const weekday   = now.toLocaleDateString('ru-RU', { weekday: 'long' })
const shortDate = now.toLocaleDateString('ru-RU', { day: 'numeric', month: 'long' })
</script>
