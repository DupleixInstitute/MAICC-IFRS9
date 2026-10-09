// Which menu entry, and which tab of a section, the current page belongs to.
// A route named "x.index" also claims its siblings "x.*" (show, edit, create),
// so a detail page keeps its entry and tab lit. When two tabs claim a page
// (users.* and users.roles.*), the longer, more specific pattern wins.

function patternsFor(routeName) {
    if (!routeName) return []
    const out = [routeName]
    if (routeName.endsWith('.index')) out.push(routeName.slice(0, -'.index'.length) + '.*')
    return out
}

function matches(pattern) {
    try {
        return route().current(pattern)
    } catch (e) {
        return false
    }
}

// The length of the most specific pattern of this route that matches, or 0.
function score(routeName) {
    return patternsFor(routeName).reduce((best, p) => (matches(p) ? Math.max(best, p.length) : best), 0)
}

// A tab with params (eir-data.index + tab=cashflows) is one view inside a
// screen: it is current when the address carries those params; with none in
// the address, the first such tab of that screen is current.
function paramsState(tab, tabs) {
    const params = tab.params || {}
    const keys = Object.keys(params)
    if (!keys.length) return 'none'
    const query = new URLSearchParams(typeof window !== 'undefined' ? window.location.search : '')
    if (keys.every(k => query.get(k) === String(params[k]))) return 'match'
    const unset = keys.every(k => !query.get(k))
    const first = tabs.find(t => t.route === tab.route && Object.keys(t.params || {}).length)
    return unset && first === tab ? 'default' : 'miss'
}

export function activeTab(section) {
    const tabs = section?.tabs || []
    let best = null
    let bestScore = 0
    for (const tab of tabs) {
        let s = score(tab.route)
        if (!s) continue
        const state = paramsState(tab, tabs)
        if (state === 'miss') continue
        if (state === 'match') s += 1000
        if (state === 'default') s += 500
        if (s > bestScore) {
            best = tab
            bestScore = s
        }
    }
    return best
}

export function isCurrent(node) {
    if (!node) return false
    if (node.tabs && node.tabs.length) return activeTab(node) !== null
    if (node.route && score(node.route) > 0) return true
    if (node.route_check && matches(node.route_check)) return true
    return (node.match || []).some(matches)
}

export function containsCurrent(node) {
    if (isCurrent(node)) return true
    return (node.children || []).some(containsCurrent)
}

// The tabbed section the current page sits in, searched through the whole menu.
export function currentSection(menu) {
    for (const item of menu || []) {
        if (item.tabs && item.tabs.length && activeTab(item)) return item
        const inner = currentSection(item.children)
        if (inner) return inner
    }
    return null
}
