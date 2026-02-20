<template>
  <div class="pointer-events-none fixed right-4 top-4 z-[60] flex w-[320px] max-w-[90vw] flex-col gap-2">
    <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2">
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto rounded-lg border bg-white px-4 py-3 text-sm shadow-lg"
        :class="toast.type === 'success'
          ? 'border-emerald-200 text-emerald-900'
          : 'border-rose-200 text-rose-900'"
      >
        <div class="flex items-start gap-2">
          <div
            class="mt-0.5 h-2.5 w-2.5 rounded-full"
            :class="toast.type === 'success' ? 'bg-emerald-500' : 'bg-rose-500'"
          />
          <p class="leading-snug">{{ toast.message }}</p>
        </div>
      </div>
    </TransitionGroup>
  </div>
</template>

<script setup lang="ts">
const { toasts } = useToast()
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.2s ease, transform 0.2s ease;
}
.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-6px);
}
</style>
