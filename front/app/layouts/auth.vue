<template>
  <div class="min-h-screen flex items-center justify-center relative overflow-hidden" style="background: hsl(224 71% 4%);">
    <!-- Background glow -->
    <div
      class="absolute inset-0 opacity-20"
      style="background: radial-gradient(ellipse 80% 60% at 50% 0%, hsl(243 75% 59%), transparent);"
    />
    <div
      class="absolute bottom-0 left-0 right-0 h-1/2 opacity-10"
      style="background: radial-gradient(ellipse 60% 50% at 50% 100%, hsl(262 83% 58%), transparent);"
    />

    <!-- Content -->
    <div class="relative z-10 w-full max-w-sm px-4 animate-slide-up">
      <slot />
    </div>
  </div>
</template>
