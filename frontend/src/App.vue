<script setup>
import TopBar from './components/layout/TopBar.vue'
import ErhaiAmbient from './components/effects/ErhaiAmbient.vue'
import ToastHost from './components/layout/ToastHost.vue'
import { useThemeStore } from './stores/theme'

const themeStore = useThemeStore()
</script>

<template>
  <ErhaiAmbient v-if="themeStore.erhaiOn" />
  <TopBar />
  <main class="app-main">
    <router-view />
  </main>
  <ToastHost />
</template>
