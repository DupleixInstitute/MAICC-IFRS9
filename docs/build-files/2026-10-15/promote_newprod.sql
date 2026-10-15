-- Promote the fresh bootstrap (maiic_ifrs9_newprod) to production (maiic_ifrs9), 14 Oct 2026.
-- The old production tables are kept, renamed into maiic_ifrs9_pre_promotion_20261014 (also dumped to OneDrive).

-- 1. carry over: the administrator's login (password, two-factor, theme) from the old production
UPDATE maiic_ifrs9_newprod.users n JOIN maiic_ifrs9.users o ON o.id = n.id AND o.email = n.email
   SET n.password = o.password, n.two_factor_secret = o.two_factor_secret,
       n.two_factor_recovery_codes = o.two_factor_recovery_codes, n.remember_token = o.remember_token,
       n.theme_preference = o.theme_preference
 WHERE n.id = 1;

-- 2. carry over: the project tickets and their updates (the demo holds the latest: 14 tickets, 24 updates)
INSERT INTO maiic_ifrs9_newprod.tickets SELECT * FROM maiic_ifrs9_demo.tickets;
INSERT INTO maiic_ifrs9_newprod.ticket_updates SELECT * FROM maiic_ifrs9_demo.ticket_updates;
