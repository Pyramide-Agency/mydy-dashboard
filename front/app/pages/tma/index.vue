<template>
  <div class="p-4 space-y-4">

    <!-- Stat cards skeleton -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="bg-white rounded-xl p-4 shadow-sm border border-border">
        <div class="skeleton h-3 w-28 mb-3" />
        <div class="skeleton h-7 w-20 mb-2" />
        <div class="skeleton h-3 w-36" />
      </div>
    </div>

    <!-- Stat cards -->
    <div v-else class="space-y-3">
      <div
        class="bg-white rounded-xl p-4 shadow-sm border border-border border-l-4"
        style="border-left-color: #6366f1;"
      >
        <div class="flex items-center justify-between mb-2">
          <p class="text-sm font-medium text-muted-foreground">Активные задачи</p>
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgb(99 102 241 / 0.1);">
            <LayoutList class="w-4 h-4" style="color: #6366f1;" />
          </div>
        </div>
        <p class="text-2xl font-bold text-foreground">{{ stats.activeTasks }}</p>
        <p class="text-xs text-muted-foreground mt-1">{{ stats.doneTasks }} в статусе «Готово»</p>
      </div>

      <div
        class="bg-white rounded-xl p-4 shadow-sm border border-border border-l-4"
        style="border-left-color: #10b981;"
      >
        <div class="flex items-center justify-between mb-2">
          <p class="text-sm font-medium text-muted-foreground">Расходы сегодня</p>
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgb(16 185 129 / 0.1);">
            <Wallet class="w-4 h-4" style="color: #10b981;" />
          </div>
        </div>
        <p class="text-2xl font-bold text-foreground">{{ currency }} {{ formatMoney(stats.todaySpending) }}</p>
        <p class="text-xs text-muted-foreground mt-1">{{ stats.todayCount }} {{ plural(stats.todayCount, 'транзакция', 'транзакции', 'транзакций') }}</p>
      </div>

      <div
        class="bg-white rounded-xl p-4 shadow-sm border border-border border-l-4"
        style="border-left-color: #f59e0b;"
      >
        <div class="flex items-center justify-between mb-2">
          <p class="text-sm font-medium text-muted-foreground">За месяц</p>
          <div class="w-8 h-8 rounded-lg flex items-center justify-center" style="background: rgb(245 158 11 / 0.1);">
            <TrendingUp class="w-4 h-4" style="color: #f59e0b;" />
          </div>
        </div>
        <p class="text-2xl font-bold text-foreground">{{ currency }} {{ formatMoney(stats.monthSpending) }}</p>
        <p class="text-xs text-muted-foreground mt-1">{{ stats.monthCount }} {{ plural(stats.monthCount, 'транзакция', 'транзакции', 'транзакций') }}</p>
      </div>
    </div>

    <!-- Quick add -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
      <div class="px-4 py-3 border-b border-border flex items-center gap-2">
        <Plus class="w-4 h-4 text-muted-foreground" />
        <h2 class="text-sm font-semibold text-foreground">Быстрый расход</h2>
      </div>
      <div class="p-4">
        <ExpenseForm @submitted="onExpenseAdded" />
      </div>
    </div>

    <!-- Recent entries -->
    <div class="bg-white rounded-xl shadow-sm border border-border overflow-hidden">
      <div class="px-4 py-3 border-b border-border flex items-center justify-between">
        <div class="flex items-center gap-2">
          <Clock class="w-4 h-4 text-muted-foreground" />
          <h2 class="text-sm font-semibold text-foreground">Последние расходы</h2>
        </div>
        <NuxtLink
          to="/tma/finance"
          class="text-xs font-medium text-indigo-600 hover:text-indigo-700 transition-colors flex items-center gap-1"
        >
          Все
          <ArrowRight class="w-3 h-3" />
        </NuxtLink>
      </div>

      <div v-if="loading" class="p-4 space-y-3">
        <div v-for="i in 4" :key="i" class="flex items-center justify-between">
          <div class="skeleton h-3 w-32" />
          <div class="skeleton h-3 w-12" />
        </div>
      </div>

      <div v-else-if="recentEntries.length === 0" class="flex flex-col items-center justify-center py-8 text-center">
        <Receipt class="w-8 h-8 text-muted-foreground/30 mb-2" />
        <p class="text-sm font-medium text-foreground">Нет расходов сегодня</p>
      </div>

      <div v-else class="divide-y divide-border">
        <div
          v-for="entry in recentEntries.slice(0, 6)"
          :key="entry.id"
          class="flex items-center justify-between px-4 py-3"
        >
          <div class="flex items-center gap-2.5 min-w-0">
            <span
              class="w-2 h-2 rounded-full shrink-0"
              :style="{ background: entry.category?.color || '#9ca3af' }"
            />
            <div class="min-w-0">
              <p class="text-sm text-foreground truncate">{{ entry.description || 'Без описания' }}</p>
              <p class="text-xs text-muted-foreground">{{ entry.category?.name || 'Без категории' }}</p>
            </div>
          </div>
          <span class="text-sm font-semibold text-foreground ml-3 shrink-0">
            {{ currency }}{{ formatMoney(entry.amount) }}
          </span>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { Wallet, TrendingUp, LayoutList, Plus, Clock, ArrowRight, Receipt } from 'lucide-vue-next'

definePageMeta({ layout: 'telegram', middleware: 'tma-auth' })

const api     = useApi()
const loading = ref(true)

const stats = reactive({
  activeTasks:   0,
  doneTasks:     0,
  todaySpending: '0.00',
  todayCount:    0,
  monthSpending: '0.00',
  monthCount:    0,
})

const recentEntries = ref<any[]>([])
const currency      = ref('₽')

const plural = (n: number, one: string, few: string, many: string) => {
  const mod10  = n % 10
  const mod100 = n % 100
  if (mod10 === 1 && mod100 !== 11) return one
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return few
  return many
}

const formatMoney = (value: any) => {
  if (value === null || value === undefined || value === '') return '0.00'
  const num = typeof value === 'number'
    ? value
    : parseFloat(String(value).replace(',', '.'))
  if (Number.isNaN(num)) return '0.00'
  return new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num)
}

onMounted(async () => {
  try {
    const [boards, todaySummary, monthSummary, entries, settings] = await Promise.all([
      api.getBoards(),
      api.getSummary('today'),
      api.getSummary('month'),
      api.getEntries({ from: new Date().toISOString().split('T')[0] }),
      api.getSettings(),
    ])

    const defaultBoard: any = (boards as any[]).find((b: any) => b.is_default) || (boards as any[])[0]
    if (defaultBoard) {
      const board: any = await api.getBoard(defaultBoard.id)
      const allTasks   = board.columns?.flatMap((c: any) => c.tasks || []) || []
      stats.activeTasks = allTasks.length
      stats.doneTasks   = board.columns?.find((c: any) => c.status_key === 'done')?.tasks?.length || 0
    }

    stats.todaySpending = (todaySummary as any).total
    stats.todayCount    = (todaySummary as any).count
    stats.monthSpending = (monthSummary as any).total
    stats.monthCount    = (monthSummary as any).count
    recentEntries.value = (entries as any).data || []
    currency.value      = (settings as any).currency_symbol || '$'
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
})

const onExpenseAdded = async () => {
  const [entries, summary] = await Promise.all([
    api.getEntries({ from: new Date().toISOString().split('T')[0] }),
    api.getSummary('today'),
  ])
  recentEntries.value = (entries as any).data || []
  stats.todaySpending = (summary as any).total
  stats.todayCount    = (summary as any).count
}
</script>
