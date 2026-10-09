<template>
  <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
    <div class="border-b p-4">
      <h3 class="font-semibold text-gray-900">{{ title }}</h3>
      <p class="mt-1 text-xs text-gray-500">{{ subtitle }}</p>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full">
        <thead>
          <tr>
            <th class="th">#</th>
            <th class="th">Due date</th>
            <th class="th text-right">Opening balance</th>
            <th class="th text-right" title="Interest added to the balance since the previous instalment, in months with no instalment or during a moratorium">Interest capitalised</th>
            <th class="th text-right">Principal</th>
            <th class="th text-right">Interest</th>
            <th class="th text-right">Fees</th>
            <th class="th text-right">Instalment</th>
            <th class="th text-right">Closing balance</th>
            <th class="th">Source</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(r,i) in lines" :key="r.id ?? i">
            <td class="td">{{ i+1 }}</td>
            <td class="td whitespace-nowrap">{{ date(r.due_date) }}</td>
            <td class="td text-right">{{ money(r.opening) }}</td>
            <td class="td text-right" :class="r.capitalised>0.5?'text-amber-700':'text-gray-400'">{{ r.capitalised===null ? '-' : money(r.capitalised) }}</td>
            <td class="td text-right">{{ money(r.principal_due) }}</td>
            <td class="td text-right">{{ money(r.interest_due) }}</td>
            <td class="td text-right">{{ money(r.fee_due) }}</td>
            <td class="td text-right font-semibold">{{ money(r.instalment) }}</td>
            <td class="td text-right">{{ money(r.closing) }}<span v-if="r.closingPrinted" class="ml-1 text-[10px] text-gray-400" title="Balance as the source schedule prints it">printed</span></td>
            <td class="td">
              <div>{{ r.schedule_source || r.source_system }}</div>
              <div class="text-xs text-gray-500">{{ r.source_reference }}</div>
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td colspan="10" class="p-8 text-center text-sm text-gray-500">No schedule rows available.</td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length">
          <tr class="bg-gray-100 font-semibold">
            <td class="td" colspan="3">Total · {{ totals.rows }} rows</td>
            <td class="td text-right">{{ money(capitalisedTotal) }}</td>
            <td class="td text-right">{{ money(totals.principal) }}</td>
            <td class="td text-right">{{ money(totals.interest) }}</td>
            <td class="td text-right">{{ money(totals.fees) }}</td>
            <td class="td text-right">{{ money(totals.total) }}</td>
            <td class="td" colspan="2"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </section>
</template>

<script>
export default {
  props: {
    title: String,
    subtitle: String,
    rows: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({}) },
    // The balance before the first row: the amount lent for an original
    // schedule. Left null where it is not known, as for a schedule that
    // starts part-way through the loan.
    startBalance: { type: Number, default: null },
  },
  computed: {
    lines() {
      const n = (v) => Number(v || 0)
      // Principal still to be repaid after each row, worked from the end. It is
      // the closing balance of any schedule that retires its own principal, and
      // the fallback wherever the source did not print a balance.
      const after = new Array(this.rows.length).fill(0)
      for (let i = this.rows.length - 2; i >= 0; i--) after[i] = after[i + 1] + n(this.rows[i + 1].principal_due)

      let previous = this.startBalance
      return this.rows.map((r, i) => {
        const printed = r.closing_balance !== null && r.closing_balance !== undefined && r.closing_balance !== ''
        const closing = printed ? n(r.closing_balance) : after[i]
        const opening = closing + n(r.principal_due)
        // Anything the balance gained since the last instalment is interest
        // added to it: the months between quarterly instalments, or a moratorium.
        // Differences under half a kwacha are rounding in the source, not interest.
        const gained = previous === null ? null : opening - previous
        const capitalised = gained === null ? null : (gained > 0.5 ? gained : 0)
        previous = closing
        return { ...r, opening, closing, capitalised, closingPrinted: printed,
          instalment: n(r.principal_due) + n(r.interest_due) + n(r.fee_due) }
      })
    },
    capitalisedTotal() {
      return this.lines.reduce((sum, l) => sum + (l.capitalised || 0), 0)
    },
  },
  methods: {
    money(v){ return v===null||v===undefined?'-':Number(v).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) },
    date(v){ return v?String(v).slice(0,10):'-' },
  },
}
</script>
