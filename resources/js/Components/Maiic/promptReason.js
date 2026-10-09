// A styled stand-in for the browser's prompt() when a reason must be typed:
// resolves with the trimmed text, or null when cancelled (Escape, Cancel or a
// click outside the box).
//
//   import { promptReason } from '@/Components/Maiic/promptReason'
//   const reason = await promptReason({ title: 'Reject this overlay?', label: 'Reason', confirmLabel: 'Reject', tone: 'danger' })
//   if (!reason) return
import { createApp, h, ref } from 'vue'

const TONES = {
    danger: 'bg-red-600 hover:bg-red-700',
    warning: 'bg-amber-600 hover:bg-amber-700',
    primary: 'bg-maiic-600 hover:bg-maiic-700',
}

export function promptReason(options = {}) {
    const o = { title: 'Give a reason', message: '', label: 'Reason', confirmLabel: 'Continue', tone: 'primary', required: true, ...options }

    return new Promise((resolve) => {
        const host = document.createElement('div')
        document.body.appendChild(host)
        const text = ref('')
        let settled = false
        const onKey = (e) => { if (e.key === 'Escape') finish(null) }
        const finish = (answer) => {
            if (settled) return
            settled = true
            document.removeEventListener('keydown', onKey)
            resolve(answer)
            setTimeout(() => { app.unmount(); host.remove() }, 0)
        }

        const app = createApp({
            render: () => h('div', { class: 'fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/60 px-4', onClick: (e) => { if (e.target === e.currentTarget) finish(null) } }, [
                h('div', { class: 'w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl', role: 'dialog', 'aria-modal': 'true' }, [
                    h('div', { class: 'border-b border-gray-200 px-6 py-4' }, [
                        h('h3', { class: 'text-lg font-semibold text-gray-900' }, o.title),
                        o.message ? h('p', { class: 'mt-1 text-sm text-gray-500' }, o.message) : null,
                    ]),
                    h('div', { class: 'px-6 py-4' }, [
                        h('label', { class: 'maiic-flabel' }, o.label),
                        h('textarea', {
                            class: 'maiic-input w-full', rows: 3, maxlength: 500, value: text.value,
                            onInput: (e) => { text.value = e.target.value },
                            onVnodeMounted: (v) => v.el && v.el.focus(),
                        }),
                    ]),
                    h('div', { class: 'flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-6 py-3' }, [
                        h('button', { type: 'button', class: 'secondary-btn', onClick: () => finish(null) }, 'Cancel'),
                        h('button', {
                            type: 'button',
                            class: 'rounded-lg px-5 py-2 text-sm font-bold text-white shadow-sm transition disabled:opacity-50 ' + (TONES[o.tone] || TONES.primary),
                            disabled: o.required && !text.value.trim(),
                            onClick: () => finish(text.value.trim()),
                        }, o.confirmLabel),
                    ]),
                ]),
            ]),
        })
        app.mount(host)
        document.addEventListener('keydown', onKey)
    })
}

export default promptReason
