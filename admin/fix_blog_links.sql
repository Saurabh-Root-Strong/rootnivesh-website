-- =============================================================
-- fix_blog_links.sql - repair internal links in the 10 new posts.
-- The blog stores TITLE-DERIVED slugs, but the post bodies linked
-- to short custom slugs that do not exist -> those links 404.
-- This rewrites each dead /blog/<my-slug> to the real stored slug.
-- Safe + idempotent: re-running changes nothing once fixed.
-- Run ONCE in phpMyAdmin (SQL tab).
-- =============================================================

UPDATE posts SET body = REPLACE(body, '/blog/ipo-gmp-grey-market-premium-meaning', '/blog/ipo-gmp-explained-what-grey-market-premium-really-tells-you-and-what-it-does-not');
UPDATE posts SET body = REPLACE(body, '/blog/ipo-allotment-status-how-to-check', '/blog/ipo-allotment-status-how-to-check-it-on-bse-nse-and-the-registrar');
UPDATE posts SET body = REPLACE(body, '/blog/sme-ipo-vs-mainboard-ipo-difference', '/blog/sme-ipo-vs-mainboard-ipo-the-differences-that-actually-cost-retail-money');
UPDATE posts SET body = REPLACE(body, '/blog/why-91-percent-fno-traders-lose-money-sebi-data', '/blog/why-91-of-f-o-traders-lose-money-what-sebi-s-own-data-actually-shows');
UPDATE posts SET body = REPLACE(body, '/blog/sebi-new-fno-rules-lot-size-weekly-expiry', '/blog/sebi-s-new-f-o-rules-bigger-lots-one-weekly-expiry-what-changed-for-retail');
UPDATE posts SET body = REPLACE(body, '/blog/capital-gains-tax-on-shares-stcg-ltcg-india', '/blog/capital-gains-tax-on-shares-in-india-stcg-ltcg-and-the-rules-after-budget-2024');
UPDATE posts SET body = REPLACE(body, '/blog/fno-income-itr-filing-turnover-audit', '/blog/f-o-income-and-itr-turnover-audit-and-which-form-to-file');
UPDATE posts SET body = REPLACE(body, '/blog/pe-ratio-pb-roe-valuation-explained', '/blog/p-e-p-b-and-roe-reading-a-stock-s-valuation-without-a-finance-degree');
UPDATE posts SET body = REPLACE(body, '/blog/dividend-bonus-split-buyback-difference', '/blog/dividend-bonus-split-and-buyback-what-each-one-actually-does-to-your-shares');
