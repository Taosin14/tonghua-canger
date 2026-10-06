<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

// 眼睛跟随鼠标:移植自 GG(1) mouse_follow_mouse(atan2 计算朝向),元素改 v-for
const count = 24
const emojis = ['🐟', '🌸', '🦋', '⭐', '🐌', '🐦']
const items = ref([])

function onMove(e) {
  items.value.forEach((el) => {
    if (!el) return
    const r = el.getBoundingClientRect()
    const dx = e.clientX - (r.left + r.width / 2)
    const dy = e.clientY - (r.top + r.height / 2)
    el.style.transform = `rotate(${Math.atan2(dy, dx)}rad)`
  })
}

onMounted(() => window.addEventListener('mousemove', onMove))
onBeforeUnmount(() => window.removeEventListener('mousemove', onMove))
</script>

<template>
  <div class="mouse-grid" aria-hidden="true">
    <span
      v-for="n in count"
      :key="n"
      class="item"
      :ref="(el) => { if (el) items[n - 1] = el }"
    >{{ emojis[n % emojis.length] }}</span>
  </div>
</template>

<style scoped>
.mouse-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  justify-content: center;
  max-width: 420px;
  margin: 0 auto;
}
.item {
  display: inline-block;
  font-size: 20px;
  transition: transform 0.12s ease-out;
}
</style>
