from ed import edit
p='app/Services/Scenario/ScenarioSetService.php'
edit(p, r"""        $asOf = $period ? CarbonImmutable::parse($period . '-01')->endOfMonth() : null;
        $g = function (string $key, string $default) use ($asOf) {
            try {
                return $this->governance->get($key, $asOf);
            } catch (Throwable) {
                return $default;
            }
        };
        $num = fn (string $v, float $d) => preg_match('/(\d+(?:\.\d+)?)/', $v, $m) ? (float) $m[1] : $d;
        $bounds = $g('scenario_weight_bounds', 'Base at least 40 percent; any single weight at most 60');
        preg_match_all('/(\d+(?:\.\d+)?)/', $bounds, $bm);

        return [
            'min_count' => (int) $num($g('scenario_minimum_count', '3'), 3),
            'base_floor' => (float) ($bm[1][0] ?? 40), 'single_ceiling' => (float) ($bm[1][1] ?? 60),
            'note_required' => stripos($g('scenario_calibration_note', 'Required'), 'required') !== false && stripos($g('scenario_calibration_note', 'Required'), 'not') === false,
            'overlay_needs_set' => stripos($g('overlay_requires_approved_set', 'Required'), 'required') !== false,
            'weighting' => $g('scenario_weighting_method', 'Weight the ECL across scenarios'),
        ];""", r"""        $asOf = $period ? CarbonImmutable::parse($period . '-01')->endOfMonth() : null;
        // read from the Governance Centre with no value in code: a missing approved value
        // stops the caller (GovernanceSettingMissingException), as spec 4.2 requires
        $g = fn (string $key) => $this->governance->get($key, $asOf);
        $count = $g('scenario_minimum_count');
        if (preg_match('/^\s*(\d+)/', $count, $m) !== 1) {
            throw new RuntimeException("The governed minimum scenario count '{$count}' names no number.");
        }
        $bounds = $g('scenario_weight_bounds');
        if (stripos($bounds, 'No bounds') === 0) {
            // "No bounds beyond summing to 100": it used to be read as 40 and 60, the default's figures
            [$floor, $ceiling] = [0.0, 100.0];
        } elseif (preg_match_all('/(\d+(?:\.\d+)?)/', $bounds, $bm) >= 2) {
            [$floor, $ceiling] = [(float) $bm[1][0], (float) $bm[1][1]];
        } else {
            throw new RuntimeException("The governed weight bounds '{$bounds}' state neither the two bounds nor 'No bounds'.");
        }
        $flag = function (string $key) use ($g): bool {
            $v = trim($g($key));
            // "Not required" and "Recommended, not enforced" are the other options; "Not required" used to read as required
            return $v === 'Required';
        };

        return [
            'min_count' => (int) $m[1],
            'base_floor' => $floor, 'single_ceiling' => $ceiling,
            'note_required' => $flag('scenario_calibration_note'),
            'overlay_needs_set' => $flag('overlay_requires_approved_set'),
            'weighting' => $g('scenario_weighting_method'),
        ];""")
