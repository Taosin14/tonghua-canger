<script setup>
// 洱海氛围背景:移植自 GG(1) bg_bubbles(纯 CSS 气泡上升),配色改洱海蓝白
const bubbles = Array.from({ length: 16 }, (_, i) => ({
  left: `${(i * 53 + 11) % 92}%`,
  size: 40 + ((i * 19) % 120),
  delay: `${((i * 1.3) % 8).toFixed(1)}s`,
  dur: `${(7 + (i % 4)).toFixed(0)}s`,
}))
</script>

<template>
  <div class="erhai-ambient" aria-hidden="true">
    <i
      v-for="(b, i) in bubbles"
      :key="i"
      class="bubble"
      :style="{ left: b.left, width: b.size + 'px', height: b.size + 'px', animationDelay: b.delay, animationDuration: b.dur }"
    ></i>
    <div class="wave-line wave-line-1"></div>
    <div class="wave-line wave-line-2"></div>
  </div>
</template>

<style scoped>
.erhai-ambient {
  position: fixed;
  inset: 0;
  overflow: hidden;
  pointer-events: none;
  z-index: 0;
}
.bubble {
  position: absolute;
  list-style: none;
  bottom: -160px;
  background: rgba(43, 75, 124, 0.07);
  border: 1px solid rgba(43, 75, 124, 0.1);
  border-radius: 12px;
  animation: erhai-bubble 8s infinite alternate-reverse;
}
@keyframes erhai-bubble {
  0% {
    transform: translateY(0) rotate(0deg);
  }
  60% {
    border-radius: 50%;
    background: rgba(43, 75, 124, 0.1);
  }
  100% {
    transform: translateY(-115vh) rotate(360deg);
    border-radius: 50%;
    background: rgba(91, 127, 176, 0.16);
  }
}
/* 底部双层波浪(洱海意象) */
.wave-line {
  position: absolute;
  bottom: -40px;
  left: -10%;
  width: 120%;
  height: 90px;
  border-radius: 45% 45% 0 0;
  background: rgba(43, 75, 124, 0.06);
  animation: wave-sway 9s ease-in-out infinite alternate;
}
.wave-line-2 {
  bottom: -52px;
  background: rgba(43, 75, 124, 0.09);
  animation-duration: 12s;
  animation-direction: alternate-reverse;
}
@keyframes wave-sway {
  from { transform: translateX(-2%) rotate(0.5deg); }
  to { transform: translateX(2%) rotate(-0.5deg); }
}
</style>
