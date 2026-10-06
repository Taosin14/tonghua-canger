/**
 * FLIP 翻页动画:记录翻页前位置 -> 切换内容 -> 从旧位置反向滑入
 */
export function useFlip(containerRef) {
  let prev = null

  function capture() {
    if (containerRef.value) {
      prev = containerRef.value.getBoundingClientRect()
    }
  }

  function play(duration = 520) {
    if (!containerRef.value || !prev) return
    const next = containerRef.value.getBoundingClientRect()
    const dx = prev.left - next.left
    const dy = prev.top - next.top
    const el = containerRef.value
    el.animate(
      [
        { transform: `translate(${dx}px, ${dy}px)`, opacity: 0.6 },
        { transform: 'translate(0, 0)', opacity: 1 },
      ],
      { duration, easing: 'cubic-bezier(.4, 0, .2, 1)' }
    )
  }

  return { capture, play }
}
