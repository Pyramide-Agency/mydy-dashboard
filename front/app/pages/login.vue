<template>
  <div>
    <!-- Brand -->
    <div class="text-center mb-8">
      <div
        class="w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center shadow-xl shadow-indigo-500/30"
        style="background: linear-gradient(135deg, hsl(243 75% 59%), hsl(262 83% 58%));"
      >
        <Zap class="w-7 h-7 text-white" />
      </div>
      <h1 class="text-2xl font-bold text-white mb-1">{{ $t('login.title') }}</h1>
      <p class="text-sm text-slate-400">{{ $t('login.subtitle') }}</p>
    </div>

    <!-- Card -->
    <div class="bg-white/5 backdrop-blur-sm border border-white/10 rounded-2xl p-6 shadow-2xl">
      <form @submit.prevent="handleLogin" class="space-y-4">
        <div>
          <label class="block text-sm font-medium text-slate-300 mb-1.5">{{ $t('login.password') }}</label>
          <input
            v-model="password"
            type="password"
            :placeholder="$t('login.passwordPlaceholder')"
            :disabled="loading"
            autofocus
            class="w-full px-3.5 py-2.5 rounded-xl text-sm text-white placeholder-slate-500 outline-none transition-all duration-150 border"
            style="background: hsl(224 71% 8%); border-color: hsl(217 33% 20%);"
            :style="isFocused ? 'border-color: hsl(243 75% 59%); box-shadow: 0 0 0 3px hsl(243 75% 59% / 0.15);' : ''"
            @focus="isFocused = true"
            @blur="isFocused = false"
          />
        </div>

        <Transition name="err">
          <p v-if="error" class="text-sm text-red-400 flex items-center gap-1.5">
            <AlertCircle class="w-3.5 h-3.5 shrink-0" />
            {{ error }}
          </p>
        </Transition>

        <button
          type="submit"
          :disabled="loading || !password"
          class="w-full py-2.5 rounded-xl text-sm font-semibold text-white transition-all duration-150 flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
          style="background: linear-gradient(135deg, hsl(243 75% 59%), hsl(262 83% 55%));"
          :style="!loading && password ? 'box-shadow: 0 4px 15px hsl(243 75% 59% / 0.4);' : ''"
        >
          <Loader2 v-if="loading" class="w-4 h-4 animate-spin" />
          {{ loading ? $t('login.signing') : $t('login.signIn') }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { Zap, Loader2, AlertCircle } from 'lucide-vue-next'

definePageMeta({ layout: 'auth' })

const { login } = useAuth()
const { $t }   = useLocale()
const password  = ref('')
const loading   = ref(false)
const error     = ref('')
const isFocused = ref(false)

const handleLogin = async () => {
  if (!password.value) return
  loading.value = true
  error.value   = ''
  try {
    await login(password.value)
    await navigateTo('/')
  } catch {
    error.value = $t('login.invalidPassword')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.err-enter-active, .err-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.err-enter-from { opacity: 0; transform: translateY(-4px); }
.err-leave-to   { opacity: 0; }
</style>
