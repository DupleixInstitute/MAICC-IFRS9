from ed import edit
edit('app/Models/Scheme.php', r"""        'default_moratorium_type',
        'effective_from',
    ];""", r"""        'default_moratorium_type',
        'effective_from',
        // lineage: the scheme master SM_01, or DD_10 where SM_01 lacks the scheme or the field
        'scheme_name',
        'source',
    ];""")
