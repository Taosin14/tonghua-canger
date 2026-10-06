import { reactive } from 'vue'

export const toasts = reactive([])
let seq = 0

export function toast(message, type = 'info', duration = 3200) {
  const id = ++seq
  toasts.push({ id, message, type })
  setTimeout(() => {
    const i = toasts.findIndex((t) => t.id === id)
    if (i >= 0) toasts.splice(i, 1)
  }, duration)
}

export const toastOk = (m) => toast(m, 'ok')
export const toastErr = (m) => toast(m, 'error', 6000) // 错误提示多留几秒,长文案能读完
