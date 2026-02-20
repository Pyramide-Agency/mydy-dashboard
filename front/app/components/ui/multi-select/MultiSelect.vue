<script setup lang="ts">
import { Check, ChevronDown, X } from 'lucide-vue-next'
import { cn } from '@/lib/utils'

interface Option {
  label: string
  value: string | number
}

const props = defineProps<{
  options: Option[]
  modelValue: (string | number)[]
  placeholder?: string
  disabled?: boolean
  error?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [(string | number)[]]
}>()

const isOpen = ref(false)
const containerRef = ref<HTMLElement | null>(null)

const selected = computed(() => props.modelValue ?? [])

function toggle(value: string | number) {
  if (selected.value.includes(value)) {
    emit('update:modelValue', selected.value.filter(v => v !== value))
  } else {
    emit('update:modelValue', [...selected.value, value])
  }
}

function remove(value: string | number, e: Event) {
  e.stopPropagation()
  emit('update:modelValue', selected.value.filter(v => v !== value))
}

function isSelected(value: string | number) {
  return selected.value.includes(value)
}

function handleOutsideClick(e: MouseEvent) {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    isOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', handleOutsideClick))
onUnmounted(() => document.removeEventListener('mousedown', handleOutsideClick))
</script>

<template>
  <div ref="containerRef" class="relative">
    <button
      type="button"
      :disabled="disabled"
      @click="isOpen = !isOpen"
      :class="cn(
        'flex min-h-10 w-full items-start justify-between gap-2 rounded-md border bg-background px-3 py-2 text-sm ring-offset-background transition-colors',
        'hover:bg-accent/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        error ? 'border-destructive focus-visible:ring-destructive' : 'border-input',
        isOpen && !error ? 'ring-2 ring-ring ring-offset-2' : '',
      )"
    >
      <div class="flex flex-wrap gap-1 flex-1 text-left">
        <span v-if="!selected.length" class="text-muted-foreground self-center">
          {{ placeholder ?? 'Select...' }}
        </span>
        <span
          v-else
          v-for="val in selected"
          :key="val"
          class="inline-flex items-center gap-1 bg-primary/10 text-primary text-xs px-2 py-0.5 rounded-full"
        >
          {{ options.find(o => o.value === val)?.label }}
          <button
            type="button"
            @click="remove(val, $event)"
            class="hover:text-destructive transition-colors leading-none"
          >
            <X class="w-3 h-3" />
          </button>
        </span>
      </div>
      <ChevronDown
        class="w-4 h-4 text-muted-foreground shrink-0 mt-0.5 transition-transform duration-150"
        :class="isOpen ? 'rotate-180' : ''"
      />
    </button>

    <Transition name="ms-dropdown">
      <div
        v-if="isOpen"
        class="absolute z-50 mt-1 w-full rounded-md border border-border bg-popover text-popover-foreground shadow-md"
      >
        <div class="max-h-60 overflow-auto p-1">
          <button
            v-for="option in options"
            :key="option.value"
            type="button"
            @click="toggle(option.value)"
            class="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-sm cursor-pointer hover:bg-accent hover:text-accent-foreground transition-colors"
          >
            <div
              :class="cn(
                'flex h-4 w-4 shrink-0 items-center justify-center rounded border transition-colors',
                isSelected(option.value) ? 'bg-primary border-primary' : 'border-input bg-background',
              )"
            >
              <Check v-if="isSelected(option.value)" class="w-3 h-3 text-primary-foreground" />
            </div>
            {{ option.label }}
          </button>
          <p v-if="!options.length" class="px-3 py-4 text-center text-sm text-muted-foreground">
            No options available
          </p>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.ms-dropdown-enter-active,
.ms-dropdown-leave-active {
  transition: opacity 0.1s ease, transform 0.1s ease;
}
.ms-dropdown-enter-from,
.ms-dropdown-leave-to {
  opacity: 0;
  transform: translateY(-4px) scale(0.98);
}
</style>
