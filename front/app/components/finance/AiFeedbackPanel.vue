<template>
  <div class="space-y-3">
    <Button
      variant="outline"
      class="w-full border-dashed"
      :disabled="loading"
      @click="getFeedback"
    >
      <Bot v-if="!loading" class="w-4 h-4 mr-2" />
      <Loader2 v-else class="w-4 h-4 mr-2 animate-spin" />
      {{ loading ? 'Анализирую расходы...' : 'Получить AI анализ за сегодня' }}
    </Button>

    <div
      v-if="analysis"
      class="bg-muted/50 rounded-lg p-4 text-sm text-foreground whitespace-pre-wrap leading-relaxed border border-border"
    >
      <div class="flex items-center gap-2 mb-2 text-primary font-medium">
        <Bot class="w-4 h-4" />
        AI Анализ
      </div>
      <span class="prose prose-sm dark:prose-invert max-w-none" v-html="renderMd(analysis)" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { Bot, Loader2 } from 'lucide-vue-next'
import { marked } from 'marked'

const renderMd = (text: string) => marked(text, { breaks: true }) as string

const api      = useApi()
const loading  = ref(false)
const analysis = ref('')

const getFeedback = async () => {
  loading.value  = true
  analysis.value = ''
  try {
    const res: any = await api.getAiFeedback()
    analysis.value  = res.analysis
  } catch (e: any) {
    const msg = e?.data?.error ?? e?.message ?? 'Неизвестная ошибка'
    analysis.value = `Ошибка: ${msg}`
  } finally {
    loading.value = false
  }
}
</script>
