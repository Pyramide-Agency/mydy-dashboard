// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  srcDir: 'app/',
  compatibilityDate: '2025-07-15',
  devtools: { enabled: true },

  // SPA mode: avoids SSR issues with localStorage-based auth in middleware.
  // In production the app is generated as static files served by Nginx.
  ssr: false,
  components: [
    {
      path: '~/components',
      pathPrefix: false,
      global: true,
    },
  ],

  modules: [
    '@nuxtjs/tailwindcss',
    'shadcn-nuxt',
  ],

  shadcn: {
    prefix: '',
    componentDir: './components/ui',
  },

  tailwindcss: {
    cssPath: '~/assets/css/main.css',
    config: 'tailwind.config.js',
  },

  css: ['~/assets/css/main.css'],

  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://localhost:8000/api',
    },
  },

  app: {
    head: {
      title: 'Personal Dashboard',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
      ],
    },
  },

  imports: {
    dirs: ['composables/**'],
  },
})
