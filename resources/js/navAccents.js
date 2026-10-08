/**
 * The colour of each navigation group (spec v4 section 11.4). Written in full
 * so that Tailwind's build includes every class; the group name in
 * config/menu.php carries the accent key.
 */
export const ACCENTS = {
    teal:    { tile: 'bg-teal-500/20 border-teal-400/40',     icon: 'text-teal-300',    bar: 'bg-teal-400',    text: 'text-teal-300',    headerTile: 'bg-teal-600',    chip: 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-200' },
    amber:   { tile: 'bg-amber-500/20 border-amber-400/40',   icon: 'text-amber-300',   bar: 'bg-amber-400',   text: 'text-amber-300',   headerTile: 'bg-amber-600',   chip: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' },
    sky:     { tile: 'bg-sky-500/20 border-sky-400/40',       icon: 'text-sky-300',     bar: 'bg-sky-400',     text: 'text-sky-300',     headerTile: 'bg-sky-600',     chip: 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-200' },
    indigo:  { tile: 'bg-indigo-500/20 border-indigo-400/40', icon: 'text-indigo-300',  bar: 'bg-indigo-400',  text: 'text-indigo-300',  headerTile: 'bg-indigo-600',  chip: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-200' },
    emerald: { tile: 'bg-emerald-500/20 border-emerald-400/40', icon: 'text-emerald-300', bar: 'bg-emerald-400', text: 'text-emerald-300', headerTile: 'bg-emerald-600', chip: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200' },
    violet:  { tile: 'bg-violet-500/20 border-violet-400/40', icon: 'text-violet-300',  bar: 'bg-violet-400',  text: 'text-violet-300',  headerTile: 'bg-violet-600',  chip: 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200' },
    slate:   { tile: 'bg-slate-500/20 border-slate-400/40',   icon: 'text-slate-300',   bar: 'bg-slate-400',   text: 'text-slate-300',   headerTile: 'bg-slate-600',   chip: 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-200' },
    rose:    { tile: 'bg-rose-500/20 border-rose-400/40',     icon: 'text-rose-300',    bar: 'bg-rose-400',    text: 'text-rose-300',    headerTile: 'bg-rose-600',    chip: 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200' },
};

export function accent(key) {
    return ACCENTS[key] || ACCENTS.slate;
}

/**
 * The breadcrumb for the current route, derived from the menu tree: a
 * top-level entry gives one crumb; a grouped entry gives group, then page; a
 * sub-grouped entry gives group, sub-group, then page. A page outside the
 * tree matches the nearest list by its route_check prefix.
 */
export function breadcrumb(menu, routeName) {
    if (!menu || !routeName) return [];
    const walk = (items, trail) => {
        for (const item of items) {
            const here = [...trail, item];
            if (item.route && (item.route === routeName || item.route_check === routeName)) return here;
            if (item.children && item.children.length) {
                const found = walk(item.children, here);
                if (found) return found;
            }
        }
        return null;
    };
    let found = walk(menu, []);
    if (!found) {
        // an edit or show page reached from a list: the list's prefix
        const prefix = routeName.split('.').slice(0, -1).join('.');
        const near = (items, trail) => {
            for (const item of items) {
                const here = [...trail, item];
                if (item.route && prefix && item.route.startsWith(prefix + '.')) return here;
                if (item.children && item.children.length) {
                    const f = near(item.children, here);
                    if (f) return f;
                }
            }
            return null;
        };
        found = near(menu, []);
    }
    return found || [];
}
