import json, re

D = 'C:/xampp/htdocs/MAICC-IFRS9/docs/compliance/'
T = {
    'reg': 'Tests\\Feature\\Eir\\RestructureRegisterTest::test_a_confirmed_modification_needs_two_people_and_loads_schedule_version_two',
    'mod': 'Tests\\Feature\\Eir\\ModificationAccountingTest::test_a_modification_below_the_threshold_resets_the_gross_carrying_amount_at_the_original_eir_with_a_gain_or_loss',
    'fees': 'Tests\\Feature\\Eir\\ModificationAccountingTest::test_fees_on_a_modification_adjust_the_carrying_amount_and_revise_the_eir',
    'der': 'Tests\\Feature\\Eir\\ModificationAccountingTest::test_derecognition_recognises_version_two_at_its_fair_value_with_a_new_eir',
    'derfv': 'Tests\\Feature\\Eir\\ModificationAccountingTest::test_a_change_at_or_above_the_threshold_derecognises_and_fails_closed_without_a_fair_value',
    'qual': 'Tests\\Feature\\Eir\\ModificationAccountingTest::test_a_qualitative_factor_derecognises_whatever_the_ten_percent_test_says',
    'poci': 'Tests\\Feature\\Eir\\ModificationAccountingTest::test_a_credit_impaired_loan_derecognised_is_poci_and_fails_closed',
    'cu': 'Tests\\Feature\\Eir\\ModificationAccountingTest::test_a_revised_estimate_takes_the_b546_catch_up_and_keeps_the_eir',
    'reset': 'Tests\\Feature\\Eir\\RateResetTest::test_the_detector_splits_plr_resets_from_spread_changes_and_sends_a_fixed_rate_move_to_the_register',
    'resetmc': 'Tests\\Feature\\Eir\\RateResetTest::test_maker_checker_on_a_reset_and_a_negotiated_move_becomes_a_restructure_candidate',
    'resetlock': 'Tests\\Feature\\Eir\\RateResetTest::test_a_reset_in_a_locked_month_is_refused',
    'rbm': 'Tests\\Feature\\Eir\\RestructureRegisterTest::test_the_rbm_section_d_reports_only_confirmed_restructures_and_names_a_missing_board_approval',
    'cv10': 'Tests\\Feature\\Eir\\RestructureRegisterTest::test_a_restructure_in_the_migration_worksheet_is_a_candidate_and_once_confirmed_is_history_not_a_new_gain_or_loss',
    'float': 'Tests\\Feature\\Ecl\\TimePhasedEclFloatingRateTest::test_a_floating_loan_is_discounted_at_the_eir_in_force_at_the_reporting_date',
    'floatnull': 'Tests\\Feature\\Ecl\\TimePhasedEclFloatingRateTest::test_a_floating_loan_with_no_rate_charged_for_the_month_is_unresolved_never_discounted_at_the_original_eir',
}
REG_WHERE = 'EIR Data, Restructures & Resets: /eir-restructures'
NOTE = 'Until 14 October 2026 (the restructure build) this row was Outstanding.'

updates = {
    'IFRS9_EIR_Amortised_Cost_Compliance_Audit': {
        '5.4.3': dict(status='Partially done',
            engine_comment='Built 14 October 2026. A restructure is applied only when a person classified the candidate as a modification and a different person approved it (RestructureRegisterService, the register at /eir-restructures). On approval E-Banker\'s chart after the event is loaded as schedule version N+1 and a contract_modifications row is opened; the revenue run of the event\'s month (EirRevenueService with ModificationAccountingService) values the modified flows at the original EIR (as re-estimated under B5.4.5 for a floating loan), sets the gross carrying amount to that present value and records the modification gain or loss on the row (modification_adjustment) and on contract_modifications; fees or costs on the modification adjust the carrying amount and revise the EIR. A confirmed event the run cannot apply stops its month and every later one, with the reason. An event before the take-on of 31 July 2024 is history only: the balance taken on already carries it. On the clone of the fresh bootstrap with pack 9: 70 candidates, none confirmed.',
            general_comment=NOTE + ' The modified flows are E-Banker\'s chart after the event; a chart that amortises more than the ledger owes fails closed.',
            compliance_comment='Partially done: built and tested; no event is confirmed yet. MAIIC classifies the candidates; Dr Thom and Deloitte confirm O16 and O17.',
            where_to_see=REG_WHERE + '; EIR Calculations, EIR as at a Date: /eir-as-at',
            governance_setting='modification_threshold; rate_change_classification; maker_checker_admin_override',
            test='; '.join([T['mod'], T['fees'], T['reg']])),
        'B5.4.5': dict(
            engine_comment='A floating-rate loan\'s EIR for each month is the locked nominal EIR moved by the change from the rate its schedule was built on to the rate E-Banker charged in the month (EirRevenueService::eirInForce, decision D38), stored as eir_annual on the roll-forward row. Since 14 October 2026 every move is recorded on rate_reset_events (RateResetService): the PLR at both month-ends and the spread; a move with the PLR is a PLR_RESET, a moved spread a SPREAD_CHANGE for a person to classify; maker-checker; a move in a locked month is refused; a move approved as negotiated becomes a restructure candidate. The re-estimation is not gated on that approval. The time-phased ECL discounts a floating loan at the same current EIR (B5.5.44). The mid-month PLR and reset-trigger settings are not read.',
            general_comment='Until 14 October 2026 this row was labelled B5.4.4 and marked Done; rate reset events were not written until the restructure build of 14 October 2026.',
            compliance_comment='Partially done: re-estimation and the reset register are built; no move is approved yet and O17 waits for Deloitte.',
            where_to_see='EIR Data, Restructures & Resets (Rate resets tab): /eir-restructures?tab=resets; EIR Data, Reference Rates: /eir-reference-rates; EIR Calculations, EIR as at a Date: /eir-as-at',
            governance_setting='rate_change_classification; plr_mid_period (not read yet); reset_trigger (not read yet)',
            test='Tests\\Feature\\Eir\\EirRevenueServiceTest::test_a_floating_rate_loan_accrues_at_the_eir_moved_with_the_rate_charged; ' + T['reset'] + '; ' + T['resetmc'] + '; ' + T['resetlock']),
        'B5.4.6': dict(status='Partially done',
            engine_comment='Built 14 October 2026 for a confirmed event: a candidate classified as a revised estimate, or a contractual reset of a fixed-rate loan, loads E-Banker\'s revised chart as the next schedule version, and the revenue run of its month sets the gross carrying amount to the present value of the revised flows at the original EIR and books the difference as the catch-up (treatment CATCH_UP); the EIR is unchanged. Not built: re-estimating the expected flows of a credit-impaired loan without a confirmed event.',
            general_comment=NOTE,
            compliance_comment='Partially done: the catch-up is built for confirmed revisions; none is confirmed.',
            where_to_see=REG_WHERE, governance_setting='', test=T['cu']),
        '3.3.2': dict(status='Partially done',
            engine_comment='Built 14 October 2026 as MAIIC\'s asset derecognition policy (review item R14: 3.3.2 and B3.3.6 are written for liabilities and applied by analogy). For a confirmed modification the revenue run compares the present value of the modified flows at the original EIR with the carrying amount before; at or above modification_threshold, or with a qualitative factor recorded on the register (a new borrower, a change of currency, a feature failing SPPI, a change of instrument), the loan is derecognised and version N+1 recognised at the fair value the register records, with a new EIR; the difference is the derecognition gain or loss. With no fair value the month fails closed; a loan credit-impaired when derecognised is purchased or originated credit-impaired and fails closed (no expected flows for a credit-adjusted EIR).',
            general_comment=NOTE, compliance_comment='Partially done: built and tested; O16 (the threshold) waits for Dr Thom and Deloitte, and no event is confirmed.',
            where_to_see=REG_WHERE, governance_setting='modification_threshold',
            test='; '.join([T['der'], T['derfv'], T['qual'], T['poci']])),
        'B3.3.6': dict(status='Partially done',
            engine_comment='The 10 percent test is read from the governed modification_threshold in force at the month-end and recorded on contract_modifications (change_pct, threshold_pct) for every confirmed modification. The migration worksheet (CV_10) holds neither the carrying amount before its eight pre-take-on restructures nor their old flows, so the test cannot be re-performed for them; they are history.',
            general_comment=NOTE, compliance_comment='Partially done: O16 to be confirmed.',
            where_to_see=REG_WHERE, governance_setting='modification_threshold', test=T['der'] + '; ' + T['mod']),
    },
    'IFRS7_IAS1_Disclosure_Compliance_Audit': {
        '7.20A': dict(status='Partially done',
            engine_comment='Built 14 October 2026: RestructureRegisterService::ifrs7Disclosures gives, for a period, the loans derecognised by a substantial modification with their carrying amount and the derecognition gain or loss, from the applied contract_modifications rows; shown on the restructure register. Not yet a line of the IFRS 9 note (Ifrs9NoteService).',
            general_comment=NOTE, compliance_comment='Partially done: the figures exist; the note line is to be added; no event is confirmed.',
            where_to_see=REG_WHERE, governance_setting='modification_threshold', test=T['der']),
    },
    'RBM_DFI_Directive_2018_Compliance_Audit': {
        '16(1)': dict(status='Partially done',
            engine_comment='Built 14 October 2026: the restructure register records, for every confirmed modification, the Board approval (minute and date) paragraph 16(1) requires; section D of the return (RbmReturnService) lists the facilities of the return with a restructure confirmed by the period end, their balance, those restructured in the period, those without a Board approval recorded (an exception to 16(1)), and the candidates awaiting a person\'s confirmation, which are never reported as restructured. Sources: the reschedule history (RS_02a to RS_02f), the rate changes of fixed-rate loans and the migration worksheet CV_10 (eight restructures before the take-on).',
            general_comment=NOTE, compliance_comment='Partially done: built; MAIIC confirms the candidates and records the Board approvals.',
            where_to_see=REG_WHERE + '; Risk & Regulatory, RBM Return: /rbm-return', governance_setting='rate_change_classification; modification_threshold',
            test=T['rbm'] + '; ' + T['cv10']),
    },
}
NEW_ROWS = {
    'IFRS7_IAS1_Disclosure_Compliance_Audit': ('7.35I', dict(part=None, reference='7.35J', section_name='Modified financial assets',
        requirement='For financial assets modified while the loss allowance was lifetime expected credit losses: the amortised cost before the modification and the net modification gain or loss; and the gross carrying amount at the reporting date of such assets whose allowance has since become 12-month expected credit losses.',
        status='Partially done',
        engine_comment='Built 14 October 2026: RestructureRegisterService::ifrs7Disclosures gives both amounts from the applied contract_modifications rows (lifetime_ecl_before, the stage when modified) and the loan book at the period end; shown on the restructure register. Not yet a line of the IFRS 9 note (Ifrs9NoteService). No modification is confirmed, so both are nil.',
        general_comment='Added 14 October 2026 with the restructure build.', compliance_comment='Partially done: the figures exist; the note line is to be added.',
        where_to_see=REG_WHERE, governance_setting='', test=T['mod'])),
}
FINDINGS = {
    'IFRS9_EIR_Amortised_Cost_Compliance_Audit': ('F3', 'Built 14 October 2026: the restructure register (candidates from RS_02a to RS_02f, the rate changes and CV_10, confirmed under maker-checker), schedule version N+1, the 5.4.3 gain or loss, the B5.4.6 catch-up and the modification test (modification_threshold). Open until MAIIC confirms the candidates (70 on the clone, none confirmed) and O16 and O17 are confirmed. (Until 14 October 2026 this finding said nothing was built.)',
                                                  'MAIIC classifies and approves the candidates on EIR Data, Restructures & Resets; Dr Thom and Deloitte confirm O16 and O17; re-run the revenue from each confirmed event\'s month.', 'MAIIC Credit and Finance / Dr Thom'),
    'RBM_DFI_Directive_2018_Compliance_Audit': ('F1', 'Built 14 October 2026: the register records the Board approval of each confirmed restructure and section D of the return reports them, with the candidates awaiting confirmation counted (70 on the clone, none confirmed). (Until 14 October 2026 this finding said no register existed.)',
                                                'MAIIC Credit confirms the candidates and records each Board approval; Risk supplies the prescribed form.', 'MAIIC Credit / Risk'),
}


def row_line(r):
    return '| {} | {} | {} | {} | {} |'.format(r['reference'], r['section_name'], r['status'], r['governance_setting'], r['test'])


def detail(r):
    out = ['**{}. {}** - *{}*'.format(r['reference'], r['section_name'], r['status']), '',
           '- **What it requires:** ' + r['requirement'],
           '- **What the engine does:** ' + r['engine_comment']]
    if r.get('general_comment'):
        out.append('- **General comment:** ' + r['general_comment'])
    out.append('- **Compliance comment:** ' + r['compliance_comment'])
    out.append('- **Where to see it:** ' + (r.get('where_to_see') or ''))
    if r.get('governance_setting'):
        out.append('- **Governance setting:** `' + r['governance_setting'] + '`')
    if r.get('test'):
        out.append('- **Test that proves it:** `' + r['test'] + '`')
    out.append('- **Reviewer sign-off:** ____________________   Date: ____________')
    return '\n'.join(out)


for stem in set(updates) | set(NEW_ROWS) | set(FINDINGS):
    jp = D + stem + '.json'
    mp = D + stem + '.md'
    data = json.load(open(jp, encoding='utf-8'))
    md = open(mp, encoding='utf-8', newline='').read()
    nl = '\r\n' if '\r\n' in md else '\n'
    md = md.replace('\r\n', '\n')
    rows = {r['reference']: r for r in data['rows']}
    for ref, ch in updates.get(stem, {}).items():
        r = rows[ref]
        old_line, old_detail = row_line(r), detail(r)
        assert md.count(old_line) == 1, (stem, ref, 'line')
        assert md.count(old_detail) == 1, (stem, ref, 'detail')
        r.update(ch)
        md = md.replace(old_line, row_line(r)).replace(old_detail, detail(r))
    if stem in NEW_ROWS:
        after, new = NEW_ROWS[stem]
        if new['reference'] not in rows:
            prev = rows[after]
            new['part'] = prev['part']
            idx = data['rows'].index(prev)
            data['rows'].insert(idx + 1, new)
            assert md.count(row_line(prev)) == 1 and md.count(detail(prev)) == 1
            md = md.replace(row_line(prev), row_line(prev) + '\n' + row_line(new))
            md = md.replace(detail(prev), detail(prev) + '\n\n' + detail(new))
    if stem in FINDINGS:
        num, found, action, owner = FINDINGS[stem]
        f = [x for x in data['findings'] if x['number'] == num][0]
        old_found, old_action, old_owner = f['what_was_found'], f['recommended_action'], f['owner']
        assert md.count('- **What was found:** ' + old_found) == 1, (stem, num)
        f['what_was_found'] = found; f['recommended_action'] = action; f['owner'] = owner
        md = md.replace('- **What was found:** ' + old_found, '- **What was found:** ' + found)
        md = md.replace('- **Recommended action:** ' + old_action, '- **Recommended action:** ' + action, 1)
        md = md.replace('- **Owner:** ' + old_owner + '\n', '- **Owner:** ' + owner + '\n', 1)
    # the status summary, recounted from the rows
    counts = {}
    for r in data['rows']:
        counts[r['status']] = counts.get(r['status'], 0) + 1
    def fix(m):
        name = m.group(1)
        return '| **{}** | {} | {} |'.format(name, m.group(2), counts.get(name, 0))
    md = re.sub(r'^\| \*\*(Done|Partially done|Outstanding|Not applicable, documented|Evidence needed from MAIIC)\*\* \| (.*?) \| \d+ \|$', fix, md, flags=re.M)
    md = re.sub(r'^\| \*\*Total\*\* \| \| \d+ \|$', '| **Total** | | {} |'.format(len(data['rows'])), md, flags=re.M)
    json.dump(data, open(jp, 'w', encoding='utf-8', newline='\n'), indent=1, ensure_ascii=False)
    open(mp, 'w', encoding='utf-8', newline='').write(md.replace('\n', nl))
    print(stem, counts, len(data['rows']))
