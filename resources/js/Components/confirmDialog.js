// A drop-in replacement for the browser's confirm(), showing the shared
// ConfirmDialog instead. It returns a Promise that resolves true (confirmed)
// or false (cancelled, Escape, or a click outside the box).
//
//   import { confirmDialog } from '@/Components/confirmDialog'
//   if (!(await confirmDialog({
//       title: 'Delete this template?',
//       message: 'The template is removed for everyone. This cannot be undone.',
//       confirmLabel: 'Delete',
//       tone: 'danger',
//   }))) return
//
// A plain string works too: confirmDialog('Delete this template?').
import { createApp, h, ref } from 'vue'
import ConfirmDialog from './ConfirmDialog.vue'

export function confirmDialog(options = {}) {
    const opts = typeof options === 'string' ? { title: options } : { ...options }

    return new Promise((resolve) => {
        const host = document.createElement('div')
        document.body.appendChild(host)
        const show = ref(true)
        let settled = false
        const onKey = (e) => {
            if (e.key === 'Escape') finish(false)
        }

        const finish = (answer) => {
            if (settled) return
            settled = true
            document.removeEventListener('keydown', onKey)
            show.value = false
            resolve(answer)
            // let the leave transition run before the box is removed
            setTimeout(() => {
                app.unmount()
                host.remove()
            }, 200)
        }

        const app = createApp({
            render: () => h(ConfirmDialog, {
                ...opts,
                show: show.value,
                onConfirm: () => finish(true),
                onCancel: () => finish(false),
            }),
        })
        app.mount(host)
        document.addEventListener('keydown', onKey)
    })
}

export default confirmDialog
