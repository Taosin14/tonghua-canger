<script setup>
import { toasts } from '../../composables/useToast'
</script>

<template>
  <div class="toast-container">
    <div v-for="t in toasts" :key="t.id" class="toast" :class="'toast-' + t.type">
      <span class="toast-icon">{{ t.type === 'ok' ? '✅' : t.type === 'error' ? '⚠️' : '💡' }}</span>
      <span>{{ t.message }}</span>
    </div>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  top: calc(var(--topbar-h) + 12px);
  left: 50%;
  transform: translateX(-50%);
  z-index: 1000;
  display: flex;
  flex-direction: column;
  gap: 8px;
  align-items: center;
  pointer-events: none;
}
.toast {
  display: flex;
  align-items: center;
  gap: 8px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 10px 30px rgba(29, 53, 87, 0.22);
  padding: 10px 18px;
  font-size: 14px;
  max-width: 520px;
  animation: toast-in 0.3s ease both;
}
.toast-ok { border-left: 4px solid var(--ok); }
.toast-error { border-left: 4px solid var(--danger); }
.toast-info { border-left: 4px solid var(--indigo); }
</style>
