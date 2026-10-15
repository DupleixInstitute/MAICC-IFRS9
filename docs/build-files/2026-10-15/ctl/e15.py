from ed import edit
edit('database/seeders/EbankerQuerySeeder.php', r""" * account and date columns, and whether it is pulled incrementally by key.
 * Idempotent on query_id. The SQL text itself is loaded from the committed
 * query files by the bootstrap; here is the shape.
 */""", r""" * account and date columns, and whether it is pulled incrementally by key.
 * Idempotent on query_id. The register holds the shape only: its sql and
 * sha256 columns are not filled by this seeder or by the bootstrap. The SQL
 * text lives in the committed files under docs/bootstrap/queries, which the
 * E-Banker Feed screen hands out as one download (EirFeedController::queries)
 * (corrected 14 October 2026; it said the bootstrap loads the SQL text).
 */""")
