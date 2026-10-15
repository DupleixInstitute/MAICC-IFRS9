from ed import edit
p='app/Models/StagingThreshold.php'
edit(p, r"""    protected $fillable = [
        'facility_class',
        'min_tenor_months',
        'stage2_dpd',
        'stage3_dpd',
        'rebuttal_basis',
        'effective_from',
    ];""", r"""    public const STATUS_PROPOSED = 'PROPOSED';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';

    protected $fillable = [
        'facility_class',
        'min_tenor_months',
        'stage2_dpd',
        'stage3_dpd',
        'rebuttal_basis',
        'effective_from',
        // maker-checker (system audit of 9 October 2026, H11): staging reads only approved rows
        'status',
        'proposed_by',
        'approved_by',
        'approved_at',
        'reason',
    ];""")
edit(p, r"""        'effective_from'   => 'date',
    ];""", r"""        'effective_from'   => 'date',
        'approved_at'      => 'datetime',
    ];""")
edit(p, r"""    /**
     * The threshold row governing a facility: the most specific class whose
     * minimum tenor the loan meets, latest effective_from wins. Falls back
     * to DEFAULT so staging never silently loses its rule set.""", r"""    /**
     * The threshold row governing a facility: the most specific class whose
     * minimum tenor the loan meets, latest effective_from wins. Falls back
     * to the DEFAULT class so staging never silently loses its rule set; with
     * no row at all it returns null and the caller stops. Only APPROVED rows
     * govern: a proposal waits for a second person (H11).""")
edit(p, r"""        return static::query()
            ->whereIn('facility_class', array_filter([$facilityClass, 'DEFAULT']))""", r"""        $query = static::query();
        if (\Illuminate\Support\Facades\Schema::hasColumn('staging_thresholds', 'status')) {
            $query->where('status', self::STATUS_APPROVED);
        }

        return $query
            ->whereIn('facility_class', array_filter([$facilityClass, 'DEFAULT']))""")
