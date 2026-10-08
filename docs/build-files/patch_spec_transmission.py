"""Spec v4 section 14: a governed transmission method (14.7), with the alternatives and their preconditions; renumber 14.7-14.9 to 14.8-14.10."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
for old, new in [("### 14.9 Acceptance and order of work", "### 14.10 Acceptance and order of work"),
                 ("### 14.8 Where it lives, and the settings", "### 14.9 Where it lives, and the settings"),
                 ("### 14.7 Lineage on the loan", "### 14.8 Lineage on the loan")]:
    rep(old, new)
sec = '''### 14.7 How the adjustment reaches the PD: the transmission method

The route of 14.6 says where the adjustment comes from. The transmission method says how it moves a loan's PD once it exists. MAIIC has one method today, the multiplicative scalar; it is kept as the default, and the alternatives are built as governed choices with the data each one needs, so that the method can change when the history can support it without a change to the code.

**What the scalar does, and what it implies.** The adjustment is a ratio to the base period (predicted proxy in the window over predicted proxy today, less one), and the loan's PD is multiplied by one plus that ratio. It is proportional, the same for every loan whatever its starting PD, and linear in the macro variable. Real credit risk is neither: a downturn moves weak borrowers far more than strong ones, and loss rates accelerate as conditions worsen. The first effect is why the cap at 100 percent is needed at all; the second is recovered at the scenario level by weighting the ECL across scenarios (15.5) even though the transmission inside each scenario stays linear. With two years of core-banking history, the scalar is the honest choice: the richer methods below need a default-rate series the institution does not yet have.

**The governed setting**, `fli_transmission_method`, with the preconditions the guardrail checks before a method may run. A method whose preconditions are not met is declined with the reason and the PD holds, exactly as a failed fit is declined.

| Method | Transmission | Needs | Status |
|---|---|---|---|
| **Multiplicative scalar on the proxy ratio** (seeded) | post-FLI PD = pre-FLI PD × (1 + adjustment); the adjustment is predicted proxy(window) / predicted proxy(base) − 1 | An approved fit, or an overlay | MAIIC's method; kept |
| Segment-specific scalar | The same, with one fit and one adjustment per portfolio or product group, from the segment proxies the deriver produces | A derivable proxy per segment (two or more periods each) | The natural next step; the deriver of 14.4 makes it possible |
| The nine reference methods | The suite's governed producers of a factor from a fit crossed with the scenario-weighted driver: the weighted statistic; the annual difference and the annual change in the driver; both scaled by the correlation; the absolute PD forecast from the equation; the forecast-over-base ratio (MAIIC's scalar is this one); and the two correlation-scaled difference and change variants | An approved fit with slope, intercept and correlation; the scenario-weighted driver | Ported as they are; selectable one at a time; each returns nothing rather than a fabricated figure when an input is undefined |
| Logit-linear PD model | logit(PD) = α + β × macro; the move is in log-odds, so proportional in the odds rather than the probability and bounded by construction | A grade-level or loan-level default series long enough to fit (the guardrail's minimum observations, at the grade level) | Available when the history allows; declined until then |
| Vasicek single-factor Z-shift | The through-the-cycle PD is moved through the latent systematic factor: PIT PD = Φ[(Φ⁻¹(TTC PD) − √ρ × Z) / √(1 − ρ)], with Z from the macro fit and ρ governed; the shift is largest for mid-range PDs and bounded by construction | A governed asset correlation ρ per portfolio and a Z series calibrated on a default-rate history | Available when the history allows; declined until then |

Whichever method is in force, three things do not change: Stage 3 is 100 percent; Stage 1 takes the 12-month window and Stage 2 the lifetime window; and the result is floored at 0 and capped at 100 percent. The method runs once per scenario under the weighting of 15.5, and the loan row records the method beside the route (14.8), so two loans adjusted under different methods in different periods can both be explained.

**Switching.** A change of method is a governed change with an effective date, under maker-checker, like every other setting. The first period run under a new method shows the ECL under both methods and the difference, which is itself a disclosure the auditors will want, as it is for the scenario weighting.

'''
rep("### 14.8 Lineage on the loan", sec + "### 14.8 Lineage on the loan")
rep("Four columns are added to the loan-book row beside the adjustment and the post-FLI PD: the route used, the parameter record, the model version and the scenario set.",
    "Five columns are added to the loan-book row beside the adjustment and the post-FLI PD: the route used, the transmission method, the parameter record, the model version and the scenario set.")
rep("| Expected-sign test on a regression pair (`fli_expected_sign_test`) | Required | Recommendation (section 14.4) | new |",
    "| How the adjustment reaches the PD (`fli_transmission_method`) | Multiplicative scalar on the proxy ratio | Recommendation (section 14.7); the segment scalar, the nine reference methods, the logit model and the Vasicek shift are the alternatives, each declined until its data exists | new |\n| Asset correlation for the Vasicek shift (`fli_asset_correlation`) | 0.12 per portfolio | A starting value in the range regulators use for corporate exposures; used only under the Vasicek method | new |\n| Expected-sign test on a regression pair (`fli_expected_sign_test`) | Required | Recommendation (section 14.4) | new |")
rep("3. Each route produces adjustment rows; the loans' post-FLI PDs equal pre-FLI × (1 + adjustment), floored and capped, Stage 3 at 100 percent, as today.",
    "3. Each route produces adjustment rows; under the seeded method the loans' post-FLI PDs equal pre-FLI × (1 + adjustment), floored and capped, Stage 3 at 100 percent, as today; each alternative method reproduces a hand calculation on a sample, and a method whose preconditions are missing is declined with the reason.")
rep("FL-3 the FLI Adjustments screen, the overlay register and the route setting (one day);",
    "FL-3 the FLI Adjustments screen, the overlay register, the route setting and the transmission-method setting with the nine reference methods ported and the logit and Vasicek methods behind their preconditions (one and a half days);")
rep("- **Overlay**: a forward-looking adjustment applied by judgement",
    "- **Transmission method**: how an adjustment moves a loan's PD once it exists: a multiplicative scalar, a segment scalar, one of the nine reference producers, a logit-linear model or a Vasicek shift; governed, and declined where its data does not exist.\n- **Overlay**: a forward-looking adjustment applied by judgement")
open(p, "w", encoding="utf-8").write(s); print("14.7 transmission method added; 14.8-14.10 renumbered; settings, lineage, acceptance, glossary updated")
