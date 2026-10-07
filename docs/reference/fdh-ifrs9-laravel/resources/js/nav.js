/**
 * Central navigation tree for the FDH IFRS 9 ECL platform.
 *
 * Single source of truth shared by the Sidebar (renders the tree) and the
 * AppLayout (derives the page title + breadcrumb from the active route).
 * Menu groups mirror the ZNBS / BBS Dupleix-suite convention
 * (Data Foundation -> Governance Centre -> Calculators/Engines -> Report Hub),
 * per SCOPING_NOTES B4/B5 and SYSTEM_DESIGN section 16.
 *
 * Each group carries an `accent` key (a Tailwind colour family) so every
 * section has its own colour for instant orientation. No figures live here.
 */

// Top-level links shown flat above the grouped navigation.
export const topItems = [
    { label: 'Dashboard', route: 'dashboard', icon: 'dashboard' },
    { label: 'Period Workspaces', route: 'workspace.index', icon: 'workspace' },
];

export const navGroups = [
    {
        id: 'data',
        label: 'Data Foundation',
        icon: 'foundation',
        accent: 'teal',
        items: [
            { label: 'Imports',        route: 'data.imports',        icon: 'import' },
            { label: 'Bulk Import',    route: 'data.bulk-import',     icon: 'layers' },
            { label: 'Exchange Rates', route: 'data.exchange-rates', icon: 'fx' },
            { label: 'FLI Statistics', route: 'data.macro.index',  icon: 'trend' },
            { label: 'Customers',      route: 'data.customers',      icon: 'users' },
            { label: 'Portfolios',     route: 'data.portfolios',     icon: 'portfolio' },
            { label: 'Ageing',         route: 'data.ageing',         icon: 'clock' },
            { label: 'Collateral',     route: 'data.collateral',     icon: 'shield' },
            { label: 'Repayments',     route: 'data.repayments',     icon: 'calendar' },
            { label: 'Reconciliation', route: 'data.reconciliation', icon: 'scale' },
        ],
    },
    {
        id: 'governance',
        label: 'Governance Centre',
        icon: 'governance',
        accent: 'amber',
        items: [
            { label: 'Parameter Register', route: 'governance.parameters',    icon: 'clipboard' },
            { label: 'Staging Rules',      route: 'governance.staging-rules', icon: 'steps' },
            { label: 'Model-Change Log',   route: 'governance.model-changes', icon: 'history' },
            { label: 'Evidence Vault',     route: 'governance.evidence',      icon: 'vault' },
            { label: 'Analysis Runs',      route: 'governance.analysis-runs', icon: 'beaker' },
            { label: 'FLI Registers',      route: 'governance.fli-registers', icon: 'trend' },
            { label: 'ECL Sign-off',       route: 'governance.ecl-signoff',   icon: 'signoff' },
        ],
    },
    // ZNBS Dupleix-suite grouping (adopted 2026-07-20): engines under
    // FINANCIAL MODELLING; risk surfaces + regulatory returns under RISK &
    // REGULATORY; watchlists/indicators under MONITORING. Items keep their
    // existing routes - only the grouping changed.
    {
        id: 'calculators',
        label: 'Financial Modelling',
        icon: 'calculator',
        accent: 'sky',
        items: [
            { label: 'Staging',                 route: 'calc.staging', icon: 'steps' },
            { label: 'PD / Transition Matrix',  route: 'calc.pd',      icon: 'grid' },
            { label: 'LGD / Collateral',        route: 'calc.lgd',     icon: 'shield' },
            { label: 'EAD',                     route: 'calc.ead',     icon: 'calculator' },
            { label: 'FLI / Correlation Finder', route: 'calc.fli',    icon: 'trend' },
            { label: 'ECL Run',                 route: 'calc.ecl-run', icon: 'cpu' },
            { label: 'EIR Calculator',          route: 'fin.eir',      icon: 'fx' },
            { label: 'Revenue Recognition',     route: 'fin.revenue',  icon: 'book' },
            { label: 'Proxy Materiality',       route: 'fin.eir-materiality', icon: 'scale' },
        ],
    },
    {
        id: 'risk-regulatory',
        label: 'Risk & Regulatory',
        icon: 'shield',
        accent: 'indigo',
        items: [
            { label: 'SICR',                   route: 'calc.sicr',                    icon: 'arrows' },
            { label: 'Stress Testing',         route: 'calc.stress',                  icon: 'bolt' },
            { label: 'Stress Report',          route: 'reports.stress',               icon: 'bolt' },
            { label: 'Sector Concentration',   route: 'reports.sector-concentration', icon: 'layers' },
            { label: 'IFRS 9 Disclosure',      route: 'reports.disclosure',           icon: 'document' },
            { label: 'Call Reports & Exports', route: 'reports.exports',              icon: 'download' },
        ],
    },
    {
        id: 'monitoring',
        label: 'Monitoring',
        icon: 'gauge',
        accent: 'emerald',
        items: [
            { label: 'EWS Watchlist', route: 'calc.ews',    icon: 'alert' },
            { label: 'EWS Report',    route: 'reports.ews', icon: 'alert' },
            { label: 'KRI',           route: 'reports.kri', icon: 'gauge' },
        ],
    },
    {
        id: 'reports',
        label: 'Report Hub',
        icon: 'reports',
        accent: 'violet',
        items: [
            { label: 'Loan Book Summary', route: 'reports.loan-book',     icon: 'book' },
            { label: 'ECL Dashboard',     route: 'reports.ecl-dashboard', icon: 'chartpie' },
        ],
    },
    {
        id: 'admin',
        label: 'Admin',
        icon: 'admin',
        accent: 'rose',
        items: [
            { label: 'Users',               route: 'admin.users',   icon: 'users' },
            { label: 'Reporting Periods',   route: 'admin.periods', icon: 'calendar' },
            { label: 'Maintenance Masters', route: 'admin.masters', icon: 'settings' },
        ],
    },
    {
        // Dedicated Manuals menu: clicking it expands the left rail to the two
        // manuals. User Manual is open to every role; Technical Manual is
        // Admin-only (server-gated + hidden from non-admins in the Sidebar).
        id: 'manuals',
        label: 'Manuals',
        icon: 'book',
        accent: 'cyan',
        items: [
            { label: 'User Manual',      route: 'system.manual',           icon: 'book' },
            { label: 'Technical Manual', route: 'system.technical-manual', icon: 'cpu', adminOnly: true },
        ],
    },
];

/**
 * Per-accent Tailwind class tokens. Kept as complete literal class strings so
 * Tailwind's JIT scanner picks them up at build time (no dynamic concatenation).
 */
export const accentClasses = {
    teal:   { bar: 'bg-teal-400',   text: 'text-teal-300',   activeBg: 'bg-teal-400/10',   tile: 'bg-teal-400/15 text-teal-300 ring-teal-400/30' },
    emerald: { bar: 'bg-emerald-400', text: 'text-emerald-300', activeBg: 'bg-emerald-400/10', tile: 'bg-emerald-400/15 text-emerald-300 ring-emerald-400/30' },
    amber:  { bar: 'bg-amber-400',  text: 'text-amber-300',  activeBg: 'bg-amber-400/10',  tile: 'bg-amber-400/15 text-amber-300 ring-amber-400/30' },
    sky:    { bar: 'bg-sky-400',    text: 'text-sky-300',    activeBg: 'bg-sky-400/10',    tile: 'bg-sky-400/15 text-sky-300 ring-sky-400/30' },
    violet: { bar: 'bg-violet-400', text: 'text-violet-300', activeBg: 'bg-violet-400/10', tile: 'bg-violet-400/15 text-violet-300 ring-violet-400/30' },
    rose:   { bar: 'bg-rose-400',   text: 'text-rose-300',   activeBg: 'bg-rose-400/10',   tile: 'bg-rose-400/15 text-rose-300 ring-rose-400/30' },
    indigo: { bar: 'bg-indigo-400', text: 'text-indigo-300', activeBg: 'bg-indigo-400/10', tile: 'bg-indigo-400/15 text-indigo-300 ring-indigo-400/30' },
    cyan:   { bar: 'bg-cyan-400',   text: 'text-cyan-300',   activeBg: 'bg-cyan-400/10',   tile: 'bg-cyan-400/15 text-cyan-300 ring-cyan-400/30' },
};
