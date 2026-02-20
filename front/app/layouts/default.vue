<template>
  <div class="flex h-screen bg-background overflow-hidden">
    <AppSidebar />
    <div class="flex-1 flex flex-col overflow-hidden">
      <AppHeader />
      <main class="flex-1 overflow-y-auto p-6">
        <Transition name="page" mode="out-in">
          <div :key="route.path">
            <slot />
          </div>
        </Transition>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import AppSidebar from '~/components/layout/AppSidebar.vue';
import AppHeader from '~/components/layout/AppHeader.vue';

const route = useRoute()
</script>

<style scoped>
.page-enter-active,
.page-leave-active {
  transition: opacity 0.18s ease, transform 0.18s ease;
}
.page-enter-from {
  opacity: 0;
  transform: translateY(8px);
}
.page-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
