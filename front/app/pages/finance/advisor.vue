<template>
  <div class="flex flex-col h-full max-w-3xl mx-auto">
    <Card class="flex-1 flex flex-col overflow-hidden">
      <CardHeader class="border-b border-border pb-4">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 bg-primary/10 rounded-full flex items-center justify-center">
            <Bot class="w-5 h-5 text-primary" />
          </div>
          <div>
            <CardTitle class="text-base">AI Финансовый советник</CardTitle>
            <CardDescription>Задайте любой вопрос о ваших финансах</CardDescription>
          </div>
        </div>
      </CardHeader>

      <!-- Messages -->
      <div ref="messagesRef" class="flex-1 overflow-y-auto p-4 space-y-4">
        <div v-if="messages.length === 0" class="text-center py-12 text-muted-foreground">
          <Bot class="w-12 h-12 mx-auto mb-3 opacity-30" />
          <p class="text-sm">Начните разговор с AI советником</p>
          <p class="text-xs mt-1">Он знает о ваших расходах и может дать персональные советы</p>
        </div>

        <div
          v-for="(msg, i) in messages"
          :key="i"
          class="flex gap-3"
          :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
        >
          <div
            v-if="msg.role === 'assistant'"
            class="w-7 h-7 bg-primary/10 rounded-full flex items-center justify-center shrink-0 mt-1"
          >
            <Bot class="w-4 h-4 text-primary" />
          </div>

          <div
            class="max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
            :class="msg.role === 'user'
              ? 'bg-primary text-primary-foreground rounded-tr-sm'
              : 'bg-muted text-foreground rounded-tl-sm'"
          >
            <span v-if="msg.role === 'user'" class="whitespace-pre-wrap">{{ msg.content }}</span>
            <span v-else class="prose prose-sm dark:prose-invert max-w-none" v-html="renderMd(msg.content)" />
            <span v-if="msg.streaming" class="animate-pulse">▋</span>
          </div>

          <div
            v-if="msg.role === 'user'"
            class="w-7 h-7 bg-secondary rounded-full flex items-center justify-center shrink-0 mt-1"
          >
            <User class="w-4 h-4" />
          </div>
        </div>
      </div>

      <!-- Input -->
      <div class="border-t border-border p-4">
        <form @submit.prevent="sendMessage" class="flex gap-2">
          <Input
            v-model="input"
            placeholder="Спросите что-нибудь о ваших финансах..."
            :disabled="streaming"
            class="flex-1"
            @keydown.enter.exact.prevent="sendMessage"
          />
          <Button type="submit" :disabled="!input.trim() || streaming">
            <Loader2 v-if="streaming" class="w-4 h-4 animate-spin" />
            <Send v-else class="w-4 h-4" />
          </Button>
        </form>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { Bot, User, Send, Loader2 } from 'lucide-vue-next'
import { marked } from 'marked'

const renderMd = (text: string) => marked(text, { breaks: true }) as string

definePageMeta({ middleware: 'auth' })

const api          = useApi()
const config       = useRuntimeConfig()
const messages     = ref<{ role: string; content: string; streaming?: boolean }[]>([])
const input        = ref('')
const streaming    = ref(false)
const messagesRef  = ref<HTMLElement | null>(null)

onMounted(async () => {
  try {
    const res: any = await api.getConversation()
    messages.value = res.messages || []
    scrollToBottom()
  } catch {}
})

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesRef.value) {
      messagesRef.value.scrollTop = messagesRef.value.scrollHeight
    }
  })
}

const sendMessage = async () => {
  const text = input.value.trim()
  if (!text || streaming.value) return

  input.value    = ''
  streaming.value = true

  messages.value.push({ role: 'user', content: text })

  const assistantMsg = reactive({ role: 'assistant', content: '', streaming: true })
  messages.value.push(assistantMsg)
  scrollToBottom()

  try {
    const token   = import.meta.client ? localStorage.getItem('auth_token') : ''
    const response = await fetch(`${config.public.apiBase}/finance/ai-conversation`, {
      method:  'POST',
      headers: {
        'Content-Type':  'application/json',
        'Accept':        'text/event-stream',
        'Authorization': `Bearer ${token}`,
      },
      body: JSON.stringify({ message: text }),
    })

    const reader  = response.body!.getReader()
    const decoder = new TextDecoder()
    let sseBuffer = ''
    let finished  = false

    while (!finished) {
      const { done, value } = await reader.read()
      if (done) break

      // {stream: true} handles multi-byte chars (Cyrillic) split across chunks
      sseBuffer += decoder.decode(value, { stream: true })

      // Process only complete SSE events (separated by \n\n)
      let sep: number
      while ((sep = sseBuffer.indexOf('\n\n')) !== -1) {
        const event = sseBuffer.slice(0, sep)
        sseBuffer   = sseBuffer.slice(sep + 2)

        for (const line of event.split('\n')) {
          if (!line.startsWith('data: ')) continue
          const data = line.slice(6)
          if (data === '[DONE]') { finished = true; break }
          try {
            const parsed = JSON.parse(data)
            if (parsed.chunk) { assistantMsg.content += parsed.chunk; scrollToBottom() }
            if (parsed.error) { assistantMsg.content = `Ошибка: ${parsed.error}` }
          } catch {}
        }
        if (finished) break
      }
    }
  } catch (e) {
    assistantMsg.content = 'Ошибка соединения. Попробуйте ещё раз.'
  } finally {
    assistantMsg.streaming = false
    streaming.value = false
    scrollToBottom()
  }
}
</script>
