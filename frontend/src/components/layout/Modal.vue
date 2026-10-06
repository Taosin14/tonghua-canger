<script setup>
defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: '' },
  wide: { type: Boolean, default: false },
})
const emit = defineEmits(['close'])
</script>

<template>
  <div v-if="open" class="modal">
    <div class="modal-mask" @click="emit('close')"></div>
    <div class="modal-dialog" :class="{ wide }">
      <div class="modal-head">
        <div class="modal-title">{{ title }}</div>
        <button class="modal-close" @click="emit('close')">&times;</button>
      </div>
      <div class="modal-body"><slot /></div>
    </div>
  </div>
</template>

<style scoped>
.modal { position: fixed; inset: 0; z-index: 900; display: flex; align-items: center; justify-content: center; }
.modal-mask { position: absolute; inset: 0; background: rgba(29, 53, 87, 0.45); }
.modal-dialog {
  position: relative;
  width: min(560px, 92vw);
  max-height: 82vh;
  display: flex;
  flex-direction: column;
  background: var(--paper);
  border-radius: 16px;
  box-shadow: 0 24px 70px rgba(29, 53, 87, 0.35);
  animation: fade-in-up 0.3s ease both;
}
.modal-dialog.wide { width: min(860px, 94vw); }
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid #e7ecf4;
}
.modal-title { font-size: 16px; font-weight: 700; color: var(--indigo-deep); }
.modal-close {
  border: none;
  background: none;
  font-size: 22px;
  cursor: pointer;
  color: #9aa6b8;
  line-height: 1;
}
.modal-close:hover { color: var(--danger); }
.modal-body { padding: 16px 18px; overflow-y: auto; }
</style>
