-- =============================================================
-- rename_blog_slugs.sql - switch the 10 new posts to clean, short,
-- keyword-focused slugs. Do this BEFORE Google indexes the long
-- auto-generated ones (renaming later needs 301 redirects).
--
-- BONUS: the post bodies already link to these short slugs, so once
-- the slugs are short, every internal link resolves with no body
-- edits. You do NOT also need fix_blog_links.sql.
--
-- DIAGNOSTIC: if any line errors with "cannot update a generated
-- column", your slug column is auto-derived from the title and
-- cannot be customised -> stop, and run fix_blog_links.sql instead.
-- If the lines succeed, run  SELECT slug FROM posts;  to confirm
-- they stuck (a rare BEFORE UPDATE trigger could revert them).
--
-- Run ONCE in phpMyAdmin (SQL tab). Idempotent.
-- =============================================================

UPDATE posts SET slug = 'ipo-gmp-grey-market-premium-meaning' WHERE slug = 'ipo-gmp-explained-what-grey-market-premium-really-tells-you-and-what-it-does-not';
UPDATE posts SET slug = 'ipo-allotment-status-how-to-check' WHERE slug = 'ipo-allotment-status-how-to-check-it-on-bse-nse-and-the-registrar';
UPDATE posts SET slug = 'sme-ipo-vs-mainboard-ipo-difference' WHERE slug = 'sme-ipo-vs-mainboard-ipo-the-differences-that-actually-cost-retail-money';
UPDATE posts SET slug = 'why-91-percent-fno-traders-lose-money-sebi-data' WHERE slug = 'why-91-of-f-o-traders-lose-money-what-sebi-s-own-data-actually-shows';
UPDATE posts SET slug = 'sebi-new-fno-rules-lot-size-weekly-expiry' WHERE slug = 'sebi-s-new-f-o-rules-bigger-lots-one-weekly-expiry-what-changed-for-retail';
UPDATE posts SET slug = 'capital-gains-tax-on-shares-stcg-ltcg-india' WHERE slug = 'capital-gains-tax-on-shares-in-india-stcg-ltcg-and-the-rules-after-budget-2024';
UPDATE posts SET slug = 'fno-income-itr-filing-turnover-audit' WHERE slug = 'f-o-income-and-itr-turnover-audit-and-which-form-to-file';
UPDATE posts SET slug = 'pe-ratio-pb-roe-valuation-explained' WHERE slug = 'p-e-p-b-and-roe-reading-a-stock-s-valuation-without-a-finance-degree';
UPDATE posts SET slug = 'dividend-bonus-split-buyback-difference' WHERE slug = 'dividend-bonus-split-and-buyback-what-each-one-actually-does-to-your-shares';
