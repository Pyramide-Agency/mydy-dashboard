<template>
  <div class="flex flex-col bg-background" style="height: 100dvh; overflow: hidden;">
    <!-- Page content -->
    <main
      class="flex-1 overflow-y-auto"
      style="padding-bottom: calc(60px + env(safe-area-inset-bottom, 0px));"
    >
      <Transition name="page" mode="out-in">
        <div :key="route.path">
          <slot />
        </div>
      </Transition>
    </main>

    <!-- Bottom navigation -->
    <nav
      class="fixed bottom-0 left-0 right-0 bg-white border-t border-border z-50 flex items-stretch"
      style="padding-bottom: env(safe-area-inset-bottom, 0px); height: calc(60px + env(safe-area-inset-bottom, 0px));"
    >
      <NuxtLink
        v-for="tab in tabs"
        :key="tab.to"
        :to="tab.to"
        class="flex-1 flex flex-col items-center justify-center gap-0.5 transition-colors"
        :class="isActiveTab(tab.to) ? 'text-primary' : 'text-muted-foreground hover:text-foreground'"
        @click="hapticFeedback('selection')"
      >
        <component :is="tab.icon" class="w-5 h-5" />
        <span class="text-[10px] font-medium">{{ tab.label }}</span>
      </NuxtLink>
    </nav>

    <ToastContainer />
  </div>
</template>

<script setup lang="ts">
import { LayoutDashboard, LayoutList, Wallet, Bot, Settings } from 'lucide-vue-next'

const route = useRoute()
const { hapticFeedback } = useTelegram()

const tabs = [
  { to: '/tma/',         label: 'Главная',   icon: LayoutDashboard },
  { to: '/tma/kanban',   label: 'Задачи',    icon: LayoutList      },
  { to: '/tma/finance',  label: 'Финансы',   icon: Wallet          },
  { to: '/tma/ai',       label: 'AI',        icon: Bot             },
  { to: '/tma/settings', label: 'Настройки', icon: Settings        },
]

const isActiveTab = (to: string) => {
  const path = route.path
  if (to === '/tma/' || to === '/tma') {
    return path === '/tma/' || path === '/tma'
  }
  return path.startsWith(to)
}

onMounted(() => {
  if (!import.meta.client) return
  const tg = (window as any).Telegram?.WebApp
  if (tg) {
    tg.ready()
    tg.expand()
    tg.disableVerticalSwipes?.()
  }
})

useHead({
  script: [{ src: 'https://telegram.org/js/telegram-web-app.js' }],
  meta: [
    { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' },
  ],
})
</script>

<style scoped>
.page-enter-active,
.page-leave-active {
  transition: opacity 0.15s ease;
}
.page-enter-from,
.page-leave-to {
  opacity: 0;
}
</style>
