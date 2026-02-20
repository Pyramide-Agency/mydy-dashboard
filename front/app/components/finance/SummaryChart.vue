<template>
  <div class="relative" :style="{ height: height + 'px' }">
    <Doughnut v-if="type === 'doughnut'" :data="chartData" :options="doughnutOptions" />
    <Bar v-else-if="type === 'bar'" :data="chartData" :options="barOptions" />
  </div>
</template>

<script setup lang="ts">
import {
  Chart as ChartJS,
  ArcElement,
  Tooltip,
  Legend,
  CategoryScale,
  LinearScale,
  BarElement,
} from 'chart.js'
import { Doughnut, Bar } from 'vue-chartjs'

ChartJS.register(ArcElement, Tooltip, Legend, CategoryScale, LinearScale, BarElement)

const props = defineProps<{
  type: 'doughnut' | 'bar'
  data: Record<string, any>
  height?: number
}>()

const height = computed(() => props.height || 200)

const doughnutPalette = [
  '#6366f1','#f59e0b','#10b981','#3b82f6','#8b5cf6',
  '#f43f5e','#14b8a6','#84cc16','#f97316','#06b6d4',
]

const chartData = computed(() => {
  if (props.type === 'doughnut') {
    const labels  = Object.keys(props.data)
    const values  = labels.map(k => props.data[k]?.total ?? props.data[k])
    const colors  = labels.map((_, i) =>
      props.data[Object.keys(props.data)[i]]?.color || doughnutPalette[i % doughnutPalette.length]
    )
    return {
      labels,
      datasets: [{ data: values, backgroundColor: colors, borderWidth: 1 }],
    }
  } else {
    const labels = Object.keys(props.data).sort()
    const values = labels.map(k => props.data[k])
    return {
      labels,
      datasets: [{
        label: 'Расходы',
        data: values,
        backgroundColor: '#6366f1',
        borderRadius: 4,
      }],
    }
  }
})

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom' as const } },
}

const barOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { display: false } },
  scales: { y: { beginAtZero: true } },
}
</script>
