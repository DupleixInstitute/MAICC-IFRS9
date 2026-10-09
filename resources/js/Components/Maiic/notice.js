// A styled stand-in for the browser's alert(): the shared ConfirmDialog with
// one meaningful answer. Resolves when the box is closed.
//
//   import { notice } from '@/Components/Maiic/notice'
//   await notice('Upload failed', 'The file could not be attached.', 'danger')
import { confirmDialog } from '@/Components/confirmDialog'

export function notice(title, message = '', tone = 'primary') {
    return confirmDialog({ title, message, confirmLabel: 'OK', cancelLabel: 'Close', tone })
}

export default notice
