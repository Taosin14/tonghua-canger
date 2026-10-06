<script setup>
// 水波球加载动画:移植自 GG(1) wave_ball_loading(方圆旋转产生波浪),改蓝白配色
defineProps({
  text: { type: String, default: '正在生成…' },
})
const sizes = [110, 74, 52]
</script>

<template>
  <div class="wave-loading">
    <div v-for="s in sizes" :key="s" class="circle" :style="{ width: s + 'px', height: s + 'px' }">
      <div class="wave"></div>
    </div>
    <p class="wave-tip">{{ text }}</p>
  </div>
</template>

<style scoped>
.wave-loading {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-direction: column;
  gap: 22px;
  padding: 36px 0;
}
.circle {
  position: relative;
  background: #c9d8ee;
  border: 4px solid #fff;
  box-shadow: 0 0 0 4px var(--indigo);
  border-radius: 50%;
  overflow: hidden;
}
.wave {
  position: relative;
  width: 100%;
  height: 100%;
  background: var(--indigo);
  border-radius: 50%;
  box-shadow: inset 0 0 40px rgba(29, 53, 87, 0.5);
}
.wave::before,
.wave::after {
  content: '';
  position: absolute;
  width: 200%;
  height: 200%;
  top: 0;
  left: 50%;
  transform: translate(-50%, -75%);
}
.wave::before {
  border-radius: 45%;
  background: rgba(255, 255, 255, 0.95);
  animation: wave-rotate 5s linear infinite;
}
.wave::after {
  border-radius: 40%;
  background: rgba(255, 255, 255, 0.5);
  animation: wave-rotate 10s linear infinite;
}
@keyframes wave-rotate {
  0% { transform: translate(-50%, -75%) rotate(0deg); }
  100% { transform: translate(-50%, -75%) rotate(360deg); }
}
.wave-tip {
  font-size: 14px;
  color: var(--indigo-soft);
  letter-spacing: 2px;
}
</style>
