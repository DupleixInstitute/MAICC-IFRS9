"""Spec v4 14.7: the system explains each transmission method and checks its preconditions live."""
p = r"C:\Users\wadza\OneDrive\2026\Projects\MAIIC\3. Project Execution\specs\MAIIC_EIR_Engine_Specification_v4_2026-10-07.md"
s = open(p, encoding="utf-8").read()
def rep(old, new):
    global s
    assert old in s, old[:70]
    s = s.replace(old, new)
add = '''**The system explains each method, in the screen, not only in this document.** On the FLI Adjustments screen and beside the setting in the Governance Centre, every method has a **method card** with the same five parts: what it does, in two or three plain sentences a finance officer can read; the formula, written out with its symbols named; what it implies (proportional or not, bounded or not, linear or not, which loans it moves most); its **preconditions, checked live against MAIIC's data** at the moment the card is opened, each shown as met or not met with the figure that decides it ("default-rate series: 24 months held, 36 needed"; "asset correlation: not yet governed"); and a **worked example** on one real loan of the book, showing the pre-FLI PD, the inputs, the arithmetic and the post-FLI PD under that method. A method whose preconditions are not met cannot be selected, and the card says exactly what has to be true before it can be. The same cards are reproduced in the help centre and in the impairment audit workbook, so that the choice of method is explained in the same words to the user, the Board and the auditor. The texts are seeded content, versioned like the help articles, not strings in the code.

'''
rep("**Switching.** A change of method is a governed change", add + "**Switching.** A change of method is a governed change")
rep("3. Each route produces adjustment rows; under the seeded method the loans' post-FLI PDs equal pre-FLI × (1 + adjustment), floored and capped, Stage 3 at 100 percent, as today; each alternative method reproduces a hand calculation on a sample, and a method whose preconditions are missing is declined with the reason.",
    "3. Each route produces adjustment rows; under the seeded method the loans' post-FLI PDs equal pre-FLI × (1 + adjustment), floored and capped, Stage 3 at 100 percent, as today; each alternative method reproduces a hand calculation on a sample, and a method whose preconditions are missing is declined with the reason.\n4. Every method has its card: description, formula, implications, live preconditions with the deciding figures, and a worked example on a real loan; a method with an unmet precondition cannot be selected and the card says what is missing; the help centre and the impairment workbook carry the same cards.")
# renumber following acceptance items 4,5 -> 5,6
rep("\n4. Every loan row names its route, parameter record, model version and scenario set; the ECL shows the overlay as its own line.\n5. The existing ECL tests pass unchanged on the regression route.",
    "\n5. Every loan row names its route, method, parameter record, model version and scenario set; the ECL shows the overlay as its own line.\n6. The existing ECL tests pass unchanged on the regression route and the seeded method.")
rep("the transmission-method setting with the nine reference methods ported and the logit and Vasicek methods behind their preconditions (one and a half days);",
    "the transmission-method setting with the nine reference methods ported, the logit and Vasicek methods behind their preconditions, and the method cards with live precondition checks and worked examples (two days);")
open(p, "w", encoding="utf-8").write(s); print("method cards added to 14.7; acceptance and effort updated")
