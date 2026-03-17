<template>
  <aside class="w-56 flex flex-col shrink-0 border-r" style="background-color: hsl(var(--sidebar-bg)); border-color: hsl(var(--sidebar-border));">

    <!-- Brand -->
    <div class="px-4 py-4 border-b" style="border-color: hsl(var(--sidebar-border));">
      <NuxtLink to="/" class="flex items-center gap-2.5">
        <img src="~/assets/logo/vektron-mark.svg" alt="Vektron" class="w-7 h-7 shrink-0" />
        <span class="font-heading text-xl font-bold tracking-tight text-white">Vektron</span>
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
        <span>{{ $t('sidebar.dashboard') }}</span>
      </NuxtLink>

      <!-- Задачи (with submenu) -->
      <div>
        <button
          class="nav-item w-full"
          :class="isSection('/kanban') ? 'nav-item--active' : 'nav-item--default'"
          @click="toggle('tasks')"
        >
          <Kanban class="nav-icon" />
          <span class="flex-1 text-left">{{ $t('sidebar.tasks') }}</span>
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
                {{ $t('sidebar.today') }}
              </NuxtLink>
              <NuxtLink
                to="/kanban/archive"
                class="sub-item"
                :class="route.path === '/kanban/archive' ? 'sub-item--active' : 'sub-item--default'"
              >
                <Archive class="w-3.5 h-3.5 shrink-0" />
                {{ $t('sidebar.archive') }}
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
          <span class="flex-1 text-left">{{ $t('sidebar.finance') }}</span>
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
                {{ $t('sidebar.expenses') }}
              </NuxtLink>
              <NuxtLink
                to="/finance/history"
                class="sub-item"
                :class="route.path === '/finance/history' ? 'sub-item--active' : 'sub-item--default'"
              >
                <History class="w-3.5 h-3.5 shrink-0" />
                {{ $t('sidebar.history') }}
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- LMS (with submenu) -->
      <div>
        <button
          class="nav-item w-full"
          :class="isSection('/lms') ? 'nav-item--active' : 'nav-item--default'"
          @click="toggle('lms')"
        >
          <GraduationCap class="nav-icon" />
          <span class="flex-1 text-left">{{ $t('sidebar.lms') }}</span>
          <ChevronRight
            class="w-3.5 h-3.5 transition-transform duration-200 shrink-0"
            :class="open.lms ? 'rotate-90' : ''"
          />
        </button>

        <div class="submenu-wrap" :class="{ 'submenu-wrap--open': open.lms }">
          <div class="submenu-inner">
            <div class="ml-3 mt-0.5 pl-3 border-l border-slate-700/60 space-y-0.5 pb-1">
              <NuxtLink
                to="/lms"
                class="sub-item"
                :class="route.path === '/lms' ? 'sub-item--active' : 'sub-item--default'"
              >
                <AlarmClock class="w-3.5 h-3.5 shrink-0" />
                {{ $t('sidebar.deadlines') }}
              </NuxtLink>
              <NuxtLink
                to="/lms/assignments"
                class="sub-item"
                :class="route.path === '/lms/assignments' ? 'sub-item--active' : 'sub-item--default'"
              >
                <ClipboardList class="w-3.5 h-3.5 shrink-0" />
                {{ $t('sidebar.assignments') }}
              </NuxtLink>
              <NuxtLink
                to="/lms/calendar"
                class="sub-item"
                :class="route.path === '/lms/calendar' ? 'sub-item--active' : 'sub-item--default'"
              >
                <Calendar class="w-3.5 h-3.5 shrink-0" />
                {{ $t('sidebar.calendar') }}
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- Работа (with submenu) -->
      <div>
        <button
          class="nav-item w-full"
          :class="isSection('/work') || isSection('/freelance') ? 'nav-item--active' : 'nav-item--default'"
          @click="toggle('work')"
        >
          <BriefcaseBusiness class="nav-icon" />
          <span class="flex-1 text-left">{{ $t('sidebar.work') }}</span>
          <ChevronRight
            class="w-3.5 h-3.5 transition-transform duration-200 shrink-0"
            :class="open.work ? 'rotate-90' : ''"
          />
        </button>

        <div class="submenu-wrap" :class="{ 'submenu-wrap--open': open.work }">
          <div class="submenu-inner">
            <div class="ml-3 mt-0.5 pl-3 border-l border-slate-700/60 space-y-0.5 pb-1">
              <NuxtLink
                to="/work"
                class="sub-item"
                :class="route.path === '/work' ? 'sub-item--active' : 'sub-item--default'"
              >
                <Clock class="w-3.5 h-3.5 shrink-0" />
                {{ $t('sidebar.workCheckin') }}
              </NuxtLink>
              <NuxtLink
                to="/freelance"
                class="sub-item"
                :class="route.path.startsWith('/freelance') ? 'sub-item--active' : 'sub-item--default'"
              >
                <Briefcase class="w-3.5 h-3.5 shrink-0" />
                {{ $t('sidebar.freelance') }}
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- AI чат (flat link) -->
      <NuxtLink
        to="/ai"
        class="nav-item"
        :class="route.path.startsWith('/ai') ? 'nav-item--active' : 'nav-item--default'"
      >
        <Bot class="nav-icon" />
        <span>{{ $t('sidebar.aiChat') }}</span>
      </NuxtLink>

      <!-- Настройки (flat link) -->
      <NuxtLink
        to="/settings"
        class="nav-item"
        :class="route.path === '/settings' ? 'nav-item--active' : 'nav-item--default'"
      >
        <Settings class="nav-icon" />
        <span>{{ $t('sidebar.settings') }}</span>
      </NuxtLink>

    </nav>

    <!-- Logout -->
    <div class="px-2.5 py-3 border-t border-slate-800">
      <button
        class="nav-item w-full text-slate-500 hover:bg-red-500/10 hover:text-red-400 group"
        @click="handleLogout"
      >
        <LogOut class="w-4 h-4 shrink-0 transition-transform duration-150 group-hover:translate-x-0.5" />
        <span>{{ $t('sidebar.logout') }}</span>
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
  BriefcaseBusiness,
  Settings,
  LogOut,
  ChevronRight,
  Archive,
  CalendarCheck,
  PlusCircle,
  History,
  GraduationCap,
  AlarmClock,
  ClipboardList,
  Calendar,
  Clock,
  Briefcase,
} from 'lucide-vue-next'

const route      = useRoute()
const { logout } = useAuth()
const { $t }    = useLocale()

// Which submenu is open
const open = reactive({ tasks: false, finance: false, lms: false, work: false })

// Auto-open section based on current route
watch(
  () => route.path,
  (path) => {
    if (path.startsWith('/kanban'))    open.tasks   = true
    if (path.startsWith('/finance'))   open.finance = true
    if (path.startsWith('/lms'))       open.lms     = true
    if (path.startsWith('/work') || path.startsWith('/freelance')) open.work = true
  },
  { immediate: true },
)

const toggle = (key: 'tasks' | 'finance' | 'lms' | 'work') => {
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
