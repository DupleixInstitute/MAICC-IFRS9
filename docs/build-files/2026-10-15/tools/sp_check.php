<?php
foreach (\App\Models\InternalGradeProfile::with('mappings.tenorPds')->get() as $p) {
  echo "profile {$p->id} {$p->name} active=" . (int)$p->is_active . ' grades=' . $p->mappings->count() . PHP_EOL;
  foreach ($p->mappings as $m) echo '  ' . $m->grade_code . ' ' . $m->sp_rating . ' ' . $m->rbm_class . ' y1=' . optional($m->tenorPds->firstWhere('tenor_years', 1))->pd_probability . ' y10=' . optional($m->tenorPds->firstWhere('tenor_years', 10))->pd_probability . PHP_EOL;
}
