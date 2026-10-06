import { ref } from 'vue'
import { toast } from './useToast'

/**
 * 浏览器中文语音合成(speechSynthesis)封装
 * 语速 0.85 适配幼儿;翻页/重读前自动打断上一段
 */
export function useSpeech() {
  const speaking = ref(false)
  let voice = null

  function pickVoice() {
    if (!('speechSynthesis' in window)) return
    const vs = speechSynthesis.getVoices()
    voice = vs.find((v) => /zh[-_]CN/i.test(v.lang)) || vs.find((v) => /^zh/i.test(v.lang)) || null
  }

  if ('speechSynthesis' in window) {
    pickVoice()
    speechSynthesis.onvoiceschanged = pickVoice
  }

  function speak(text, { rate = 0.85, pitch = 1.05 } = {}) {
    if (!('speechSynthesis' in window)) {
      toast('当前浏览器不支持语音朗读')
      return
    }
    const clean = (text || '').replace(/[*#`]/g, '').replace(/\s+/g, ' ')
    if (!clean) return
    speechSynthesis.cancel()
    const u = new SpeechSynthesisUtterance(clean)
    u.lang = 'zh-CN'
    u.rate = rate
    u.pitch = pitch
    if (voice) u.voice = voice
    u.onstart = () => { speaking.value = true }
    u.onend = () => { speaking.value = false }
    u.onerror = () => { speaking.value = false }
    speechSynthesis.speak(u)
  }

  function stop() {
    if ('speechSynthesis' in window) speechSynthesis.cancel()
    speaking.value = false
  }

  return { speak, stop, speaking }
}
