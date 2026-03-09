# AdsCast - Veri Modeli

## Kimliklendirme ve Anahtar Stratejisi

- Tum ana domain tablolari UUID PK kullanir.
- Meta dis kaynak kimlikleri (`meta_*_id`, `account_id`) ayri kolonlarda tutulur.
- Workspace izolasyonu icin workspace bagli tablolarda `workspace_id` zorunludur.

## Cekirdek Tablolar

1. Tenant / Auth
   - `organizations`
   - `workspaces`
   - `users`
   - `roles`
   - `permissions`
   - `role_permissions`
   - `user_workspace_roles`
2. Meta baglanti ve varlik
   - `meta_connections`
   - `meta_businesses`
   - `meta_ad_accounts`
   - `meta_pages`
   - `meta_pixels`
   - `campaigns`
   - `ad_sets`
   - `ads`
   - `creatives`
3. Raporlama ve operasyon
   - `insight_daily`
   - `sync_runs`
   - `raw_api_payloads`
4. Karar, AI, yayin akis
   - `alerts`
   - `recommendations`
   - `ai_generations`
   - `campaign_drafts`
   - `campaign_draft_items`
   - `approvals`
5. Platform operasyon
   - `audit_logs`
   - `settings`

## Onemli Iliskiler

- `organizations (1) -> (n) workspaces`
- `users (n) <-> (n) workspaces` role baglamiyla (`user_workspace_roles`)
- `roles (n) <-> (n) permissions` (`role_permissions`)
- `workspaces (1) -> (n) meta_connections`
- `meta_connections (1) -> (n) meta_ad_accounts/pages/pixels`
- `meta_ad_accounts (1) -> (n) campaigns`
- `campaigns (1) -> (n) ad_sets`
- `ad_sets (1) -> (n) ads`
- `creatives (1) -> (n) ads` (opsiyonel)
- `workspaces (1) -> (n) insight_daily/alerts/recommendations/audit_logs`

## Raporlama Kolonlari

`insight_daily` asagidaki temel metrikleri normalize eder:

- spend
- impressions
- reach
- frequency
- clicks
- link_clicks
- ctr
- cpc
- cpm
- results
- cost_per_result
- leads
- purchases
- roas
- conversions
- actions_json

## Index Stratejisi

- Sorgu agir kisimlar:
  - `insight_daily(workspace_id, date, level, entity_external_id)`
  - `alerts(workspace_id, status, severity, date_detected)`
  - `campaigns(workspace_id, meta_campaign_id)`
  - `sync_runs(workspace_id, type, status, started_at)`
- Ham payload debug:
  - `raw_api_payloads(workspace_id, resource_type, captured_at)`
