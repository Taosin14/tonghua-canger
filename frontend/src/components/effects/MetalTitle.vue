<script setup>
// 金属字:移植自 GG(1) metal_text(background-clip:text + 多层阴影),蓝白金属质感
defineProps({
  size: { type: String, default: 'inherit' },
})
</script>

<template>
  <span class="metal-wrap" :style="{ fontSize: size }">
    <span class="metal-shadow" aria-hidden="true"><slot /></span>
    <span class="metal-main"><slot /></span>
  </span>
</template>

<style scoped>
.metal-wrap {
  position: relative;
  display: inline-block;
  font-weight: 800;
  letter-spacing: 4px;
}
.metal-main {
  position: relative;
  z-index: 2;
  background: linear-gradient(var(--indigo-deep) 10%, #9fb8dd 45%, var(--indigo-deep) 85%);
  background-clip: text;
  -webkit-background-clip: text;
  color: transparent;
}
.metal-shadow {
  position: absolute;
  left: 0;
  top: 0;
  z-index: 1;
  color: transparent;
  text-shadow:
    1px 1px 0 rgba(29, 53, 87, 0.85),
    2px 2px 0 rgba(29, 53, 87, 0.75),
    3px 3px 0 rgba(29, 53, 87, 0.6),
    4px 4px 0 rgba(29, 53, 87, 0.5),
    5px 5px 0 rgba(29, 53, 87, 0.4),
    6px 16px 18px rgba(0, 0, 0, 0.28),
    -1px -1px 0 #fff;
}
</style>
