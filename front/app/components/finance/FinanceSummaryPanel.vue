<template>
  <div class="space-y-4">
    <!-- Income vs Expense mini-bar -->
    <div v-if="hasActivity" class="flex gap-2 items-center text-xs">
      <span class="text-emerald-600 font-medium whitespace-nowrap">{{ currency }}{{ summary.total_income || '0.00' }}</span>
      <div class="flex-1 h-2 rounded-full overflow-hidden bg-muted flex">
        <div
          class="h-full bg-emerald-400 transition-all duration-500"
          :style="{ width: incomePercent + '%' }"
        />
        <div
          class="h-full bg-red-400 transition-all duration-500"
          :style="{ width: expensePercent + '%' }"
        />
      </div>
      <span class="text-red-500 font-medium whitespace-nowrap">{{ currency }}{{ summary.total_expense || '0.00' }}</span>
    </div>

    <!-- Charts -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <Card v-if="hasCategories">
        <CardHeader class="pb-2">
          <CardTitle class="text-sm">{{ $t('finance.summaryByCategory') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <SummaryChart type="doughnut" :data="summary.by_category || {}" :height="200" />
        </CardContent>
      </Card>

      <Card v-if="showBar && hasDays">
        <CardHeader class="pb-2">
          <CardTitle class="text-sm">{{ $t('finance.byDay') }}</CardTitle>
        </CardHeader>
        <CardContent>
          <SummaryChart type="bar" :data="byDayExpense" :height="200" />
        </CardContent>
      </Card>
    </div>

    <!-- Empty state -->
    <div v-if="!hasCategories && !hasDays" class="text-center py-6 text-sm text-muted-foreground">
      {{ $t('finance.noDataForPeriod') }}
    </div>
  </div>
</template>

<script setup lang="ts">
const { $t } = useLocale()

const props = defineProps<{
  summary: any
  currency: string
  showBar?: boolean
}>()

const hasCategories = computed(() => Object.keys(props.summary?.by_category || {}).length > 0)
const hasDays       = computed(() => Object.keys(props.summary?.by_day || {}).length > 1)
const hasActivity   = computed(() =>
  (props.summary?.total_income ?? 0) > 0 || (props.summary?.total_expense ?? 0) > 0
)

const totalFlow = computed(() =>
  (props.summary?.total_income ?? 0) + (props.summary?.total_expense ?? 0)
)
const incomePercent  = computed(() =>
  totalFlow.value > 0 ? Math.round(((props.summary?.total_income ?? 0) / totalFlow.value) * 100) : 0
)
const expensePercent = computed(() => 100 - incomePercent.value)

// Extract only expense totals for the bar chart (by_day is now {income, expense})
const byDayExpense = computed(() => {
  const byDay = props.summary?.by_day || {}
  const result: Record<string, number> = {}
  for (const [date, val] of Object.entries(byDay)) {
    result[date] = typeof val === 'object' ? (val as any).expense ?? 0 : (val as number)
  }
  return result
})
</script>
