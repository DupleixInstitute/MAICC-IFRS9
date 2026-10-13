// System codes in plain words for the screen. The codes stay what the engines
// store and read; only what a person sees changes. A code with no entry here is
// turned into a sentence ("SCHEDULE_NOT_APPROVED" reads "Schedule not approved").
const LABELS = {
    LIVE_AT_DRAWDOWN: 'The rate live at drawdown',
    AS_CAPTURED: 'As captured in E-Banker',
    SUPPLIED: 'Supplied by MAIIC',
    TAKEON_BALANCE: 'Take-on balance',
    MAIIC_EXTRACT_A: 'MAIIC extract A',
    MAIIC_EXTRACT_B: 'MAIIC extract B',
    MAIIC_EXTRACT_C: 'MAIIC extract C',
    ROUTE_1_MANUAL: 'Uploaded by hand',
    ROUTE_2_FEED: 'From the feed folder',
    ROUTE_2_FOLDER: 'From the feed folder',
    SCHEDULE_NOT_APPROVED: 'Schedule not approved',
    ORIGINAL_SCHEDULE_MISSING: 'Original schedule missing',
}

export function plainCode(value) {
    if (value === null || value === undefined || value === '') return '-'
    const v = String(value)
    if (LABELS[v]) return LABELS[v]
    if (/^[A-Z0-9]+(_[A-Z0-9]+)+$/.test(v)) {
        const s = v.toLowerCase().replace(/_/g, ' ')
        return s.charAt(0).toUpperCase() + s.slice(1)
    }
    return v
}

// The Governance Centre seeds its defaults as in force from 1 January 1900:
// on screen that is "the seeded default", not a date.
export function plainSince(date) {
    if (!date) return '-'
    return String(date).startsWith('1900') ? 'the seeded default' : String(date).slice(0, 10)
}
