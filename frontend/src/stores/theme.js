import { defineStore } from 'pinia'
import { ref, reactive } from 'vue'

export const useThemeStore = defineStore('theme', () => {
  // 洱海氛围背景开关
  const erhaiOn = ref(localStorage.getItem('canger_erhai') !== 'off')
  function toggleErhai() {
    erhaiOn.value = !erhaiOn.value
    localStorage.setItem('canger_erhai', erhaiOn.value ? 'on' : 'off')
  }

  // 生成参数
  const genParams = reactive({
    theme: '扎染',
    age_band: '4-5',
    page_count: 8,
    style: '水彩',
  })

  // 当前生成/载入的故事
  const currentStory = ref(null)
  const generating = ref(false)
  const lastAiUsed = ref(false)
  const lastMessage = ref('')

  return { erhaiOn, toggleErhai, genParams, currentStory, generating, lastAiUsed, lastMessage }
})
