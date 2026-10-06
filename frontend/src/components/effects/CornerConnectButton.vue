<script setup>
// 悬停边角连接按钮:移植自 GG(1) button_hover_corner_connect(四角方块悬停扩张)
defineProps({
  disabled: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
})
</script>

<template>
  <button class="cc-btn" :type="type" :disabled="disabled">
    <span class="cc-inner"><slot /></span>
  </button>
</template>

<style scoped>
.cc-btn {
  --w: 180px;
  --h: 50px;
  position: relative;
  width: var(--w);
  height: var(--h);
  line-height: var(--h);
  border: none;
  cursor: pointer;
  background: var(--indigo);
  color: #fff;
  font-size: 16px;
  letter-spacing: 4px;
  border-radius: 4px;
  transition: background 0.2s;
}
.cc-btn:hover { background: var(--indigo-deep); }
.cc-btn:disabled { cursor: not-allowed; opacity: 0.55; }

.cc-btn::before,
.cc-btn::after,
.cc-inner::before,
.cc-inner::after {
  content: '';
  position: absolute;
  width: 10px;
  height: 10px;
  background: var(--accent);
  border-radius: 2px;
  transition: width 0.55s, height 0.55s;
}
.cc-btn::before { top: -4px; left: -4px; }
.cc-btn::after { top: -4px; right: -4px; }
.cc-inner::before { bottom: -4px; left: -4px; }
.cc-inner::after { bottom: -4px; right: -4px; }

.cc-btn:hover::before,
.cc-btn:hover::after,
.cc-btn:hover .cc-inner::before,
.cc-btn:hover .cc-inner::after {
  width: calc((var(--w) + 8px) / 2);
  height: calc((var(--h) + 8px) / 2);
}
</style>
