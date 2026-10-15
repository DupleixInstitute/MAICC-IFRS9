
// ECL build-up: before FLI, the forward-looking effect of the model, the
// overlays the route applied, the booked ECL. Bars are a waterfall on one
// scale (the largest running total).
const buildSteps = computed(() => {
    const b = props.eclBuildUp
    if (!b) return []
    const final = Number(b.final || 0)
    if (!b.available) {
        return [{ label: 'ECL booked', value: final, sign: '', pct: null, left: 0, width: 100, bar: 'bg-maiic-600', total: true, note: null }]
    }
    const pre = Number(b.pre_fli || 0), model = Number(b.fli_model || 0), ov = Number(b.overlays || 0)
    const max = Math.max(pre, pre + model, final, 1)
    const seg = (from, delta) => ({ left: (Math.min(from, from + delta) / max) * 100, width: (Math.abs(delta) / max) * 100 })
    const pct = (v) => pre ? ((v > 0 ? '+' : '') + (v / pre * 100).toFixed(2) + '% on before FLI') : null
    const overlayNote = (b.overlay_lines || []).length
        ? (b.overlay_lines.length + ' approved overlay' + (b.overlay_lines.length === 1 ? '' : 's') + ' from the register')
        : 'No approved overlay applied to these loans'
    return [
        { label: 'ECL before FLI', value: pre, sign: '', pct: null, ...seg(0, pre), bar: 'bg-slate-400', total: false, note: 'EAD x PD before FLI over the horizon x LGD' },
        { label: 'Forward-looking effect', value: model, sign: model > 0 ? '+' : '', pct: pct(model), ...seg(pre, model), bar: model >= 0 ? 'bg-amber-500' : 'bg-maiic-400', total: false, note: 'The macro adjustment to the PD, weighted across the scenario set' },
        { label: 'Manual overlays', value: ov, sign: ov > 0 ? '+' : '', pct: ov ? pct(ov) : null, ...seg(pre + model, ov), bar: 'bg-maiicgold-500', total: false, note: overlayNote },
        { label: 'ECL booked', value: final, sign: '', pct: null, ...seg(0, final), bar: 'bg-maiic-600', total: true, note: null },
    ]
})
const ranUnder = computed(() => {
    const l = (props.eclBuildUp?.lineage || [])[0]
    if (!l || !l.route) return null
    const parts = ['the ' + l.route.toLowerCase() + ' route' + (l.method ? ' (' + l.method.toLowerCase() + ')' : '')]
    if (l.fit) parts.push('fit ' + l.fit.id + (l.fit.relationship ? ', ' + l.fit.relationship : '') + (l.fit.status ? ', ' + l.fit.status.toLowerCase() : ''))
    if (l.set) parts.push('scenario set ' + l.set.id + (l.set.name ? ', ' + l.set.name : '') + (l.set.status ? ', ' + l.set.status.toLowerCase() : ''))
    const more = (props.eclBuildUp.lineage || []).length - 1
    return parts.join('; ') + (more > 0 ? '; and ' + more + ' other combination' + (more === 1 ? '' : 's') : '') + '.'
})

const breakdownViews = [
    { key: 'product', label: 'Product group' },
    { key: 'sector', label: 'Sector' },
    { key: 'rbm', label: 'RBM class' },
]
const breakdownView = ref('product')
const breakdown = computed(() => (props.eclBreakdown || {})[breakdownView.value] || { rows: [], total: { loans: 0, ead: 0, ecl: 0 } })
const breakdownCaption = computed(() => breakdownView.value === 'rbm'
    ? 'By the Reserve Bank of Malawi classes (days past due by term)'
    : 'The six largest by ECL, the rest as one line')
// the breakdown and the tiles must agree: the same loans, the same total
const breakdownTies = computed(() => Math.abs(Number(breakdown.value.total.ecl || 0) - Number(summary.value.total_ecl || 0)) <= Math.max(1, 0.01 * Number(breakdown.value.total.loans || 0)))

