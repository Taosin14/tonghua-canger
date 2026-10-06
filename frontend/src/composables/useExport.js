import html2canvas from 'html2canvas'
import { jsPDF } from 'jspdf'
import JSZip from 'jszip'

/** 绘本导出:PNG 单页 / PDF / ZIP 整本(纯前端,离线可用) */
export function useExport() {
  async function capture(el) {
    return html2canvas(el, { scale: 2, useCORS: true, backgroundColor: '#fffdf8' })
  }

  async function exportPng(el, name) {
    const c = await capture(el)
    const a = document.createElement('a')
    a.href = c.toDataURL('image/png')
    a.download = `${name}.png`
    a.click()
  }

  async function exportPdf(el, name) {
    const c = await capture(el)
    const img = c.toDataURL('image/jpeg', 0.92)
    const pdf = new jsPDF({ orientation: 'landscape', unit: 'px', format: [c.width, c.height] })
    pdf.addImage(img, 'JPEG', 0, 0, c.width, c.height)
    pdf.save(`${name}.pdf`)
  }

  async function exportZip(els, name) {
    const zip = new JSZip()
    for (let i = 0; i < els.length; i++) {
      const c = await capture(els[i])
      zip.file(`第${String(i + 1).padStart(2, '0')}页.png`, c.toDataURL('image/png').split(',')[1], { base64: true })
    }
    const blob = await zip.generateAsync({ type: 'blob' })
    const a = document.createElement('a')
    a.href = URL.createObjectURL(blob)
    a.download = `${name}.zip`
    a.click()
    URL.revokeObjectURL(a.href)
  }

  return { exportPng, exportPdf, exportZip }
}
