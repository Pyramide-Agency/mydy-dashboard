<template>
  <aside class="w-56 bg-slate-900 flex flex-col shrink-0 border-r border-slate-800">

    <!-- Brand -->
    <div class="px-4 py-4 border-b border-slate-800">
      <NuxtLink to="/" class="flex items-center gap-2.5 group">
        <div
          class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0 shadow-lg shadow-indigo-500/20 transition-transform duration-200 group-hover:scale-105"
          style="background: linear-gradient(135deg, #6366f1, #9333ea);"
        >
          <Zap class="w-4 h-4 text-white" />
        </div>
        <div>
          <p class="text-sm font-bold text-slate-100 leading-tight">Dashboard</p>
          <p class="text-xs text-slate-500">личный</p>
        </div>
      </NuxtLink>
    </div>

    <!-- Nav -->
    <nav class="flex-1 px-2.5 py-3 space-y-0.5 overflow-y-auto">

      <!-- Dashboard (flat link) -->
      <NuxtLink
        to="/"
        class="nav-item"
        :class="route.path === '/' ? 'nav-item--active' : 'nav-item--default'"
      >
        <LayoutDashboard class="nav-icon" />
        <span>Дашборд</span>
      </NuxtLink>

      <!-- Задачи (with submenu) -->
      <div>
        <button
          class="nav-item w-full"
          :class="isSection('/kanban') ? 'nav-item--active' : 'nav-item--default'"
          @click="toggle('tasks')"
        >
          <Kanban class="nav-icon" />
          <span class="flex-1 text-left">Задачи</span>
          <ChevronRight
            class="w-3.5 h-3.5 transition-transform duration-200 shrink-0"
            :class="open.tasks ? 'rotate-90' : ''"
          />
        </button>

        <!-- Submenu -->
        <div class="submenu-wrap" :class="{ 'submenu-wrap--open': open.tasks }">
          <div class="submenu-inner">
            <div class="ml-3 mt-0.5 pl-3 border-l border-slate-700/60 space-y-0.5 pb-1">
              <NuxtLink
                to="/kanban"
                class="sub-item"
                :class="route.path === '/kanban' ? 'sub-item--active' : 'sub-item--default'"
              >
                <CalendarCheck class="w-3.5 h-3.5 shrink-0" />
                Сегодня
              </NuxtLink>
              <NuxtLink
                to="/kanban/archive"
                class="sub-item"
                :class="route.path === '/kanban/archive' ? 'sub-item--active' : 'sub-item--default'"
              >
                <Archive class="w-3.5 h-3.5 shrink-0" />
                Архив
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- Финансы (with submenu) -->
      <div>
        <button
          class="nav-item w-full"
          :class="isSection('/finance') ? 'nav-item--active' : 'nav-item--default'"
          @click="toggle('finance')"
        >
          <Wallet class="nav-icon" />
          <span class="flex-1 text-left">Финансы</span>
          <ChevronRight
            class="w-3.5 h-3.5 transition-transform duration-200 shrink-0"
            :class="open.finance ? 'rotate-90' : ''"
          />
        </button>

        <div class="submenu-wrap" :class="{ 'submenu-wrap--open': open.finance }">
          <div class="submenu-inner">
            <div class="ml-3 mt-0.5 pl-3 border-l border-slate-700/60 space-y-0.5 pb-1">
              <NuxtLink
                to="/finance"
                class="sub-item"
                :class="route.path === '/finance' ? 'sub-item--active' : 'sub-item--default'"
              >
                <PlusCircle class="w-3.5 h-3.5 shrink-0" />
                Расходы
              </NuxtLink>
              <NuxtLink
                to="/finance/history"
                class="sub-item"
                :class="route.path === '/finance/history' ? 'sub-item--active' : 'sub-item--default'"
              >
                <History class="w-3.5 h-3.5 shrink-0" />
                История
              </NuxtLink>
              <NuxtLink
                to="/finance/advisor"
                class="sub-item"
                :class="route.path === '/finance/advisor' ? 'sub-item--active' : 'sub-item--default'"
              >
                <Bot class="w-3.5 h-3.5 shrink-0" />
                Спросить AI
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- Настройки (flat link) -->
      <NuxtLink
        to="/settings"
        class="nav-item"
        :class="route.path === '/settings' ? 'nav-item--active' : 'nav-item--default'"
      >
        <Settings class="nav-icon" />
        <span>Настройки</span>
      </NuxtLink>

    </nav>

    <!-- Logout -->
    <div class="px-2.5 py-3 border-t border-slate-800">
      <button
        class="nav-item w-full text-slate-500 hover:bg-red-500/10 hover:text-red-400 group"
        @click="handleLogout"
      >
        <LogOut class="w-4 h-4 shrink-0 transition-transform duration-150 group-hover:translate-x-0.5" />
        <span>Выйти</span>
      </button>
    </div>

  </aside>
</template>

<script setup lang="ts">
import {
  LayoutDashboard,
  Kanban,
  Wallet,
  Bot,
  Settings,
  LogOut,
  Zap,
  ChevronRight,
  Archive,
  CalendarCheck,
  PlusCircle,
  History,
} from 'lucide-vue-next'

const route     = useRoute()
const { logout } = useAuth()

// Which submenu is open
const open = reactive({ tasks: false, finance: false })

// Auto-open section based on current route
watch(
  () => route.path,
  (path) => {
    if (path.startsWith('/kanban'))  open.tasks   = true
    if (path.startsWith('/finance')) open.finance = true
  },
  { immediate: true },
)

const toggle = (key: 'tasks' | 'finance') => {
  open[key] = !open[key]
}

// Returns true if current route is within a section (for parent highlight)
const isSection = (prefix: string) => route.path.startsWith(prefix)

const handleLogout = async () => await logout()
</script>

<style scoped>
/* ── Base nav item ── */
.nav-item {
  display: flex;
  align-items: center;
  gap: 0.625rem;
  padding: 0.45rem 0.625rem;
  border-radius: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 500;
  transition: background 0.13s ease, color 0.13s ease;
  cursor: pointer;
  text-decoration: none;
}
.nav-item--default {
  color: hsl(215 20% 45%);
}
.nav-item--default:hover {
  background: hsl(217 33% 13%);
  color: hsl(213 31% 80%);
}
.nav-item--active {
  background: rgb(99 102 241 / 0.14);
  color: rgb(129 140 248);
}
.nav-icon {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
}

/* ── Sub-item ── */
.sub-item {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.35rem 0.5rem;
  border-radius: 0.375rem;
  font-size: 0.75rem;
  font-weight: 500;
  transition: background 0.13s ease, color 0.13s ease;
  text-decoration: none;
}
.sub-item--default {
  color: hsl(215 20% 40%);
}
.sub-item--default:hover {
  background: hsl(217 33% 13%);
  color: hsl(213 31% 75%);
}
.sub-item--active {
  color: rgb(129 140 248);
  background: rgb(99 102 241 / 0.1);
}

/* ── Submenu accordion ── */
.submenu-wrap {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.22s ease;
}
.submenu-wrap--open {
  grid-template-rows: 1fr;
}
.submenu-inner {
  overflow: hidden;
}
</style>
