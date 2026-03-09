# AdsCast - Uygulama Plani ve Gorev Sirasi

## 1. Milestone Plani

1. Milestone A - Foundation (Phase 1)
   - Monorepo, auth, tenant/workspace/rbac, base migration, API/UI shell
2. Milestone B - Meta Connector (Phase 2)
   - Meta connection, asset sync, sync-run ve raw payload altyapisi
3. Milestone C - Reporting (Phase 3)
   - Dashboard, campaign list/detay, trend endpointleri
4. Milestone D - Rules & AI (Phase 4)
   - Deterministic alertler, recommendation pipeline, AI generation kaydi
5. Milestone E - Draft & Approval (Phase 5)
   - Draft wizard, review, approval queue, publish scaffold
6. Milestone F - Stabilizasyon (Phase 6)
   - Test kapsami, dokumantasyon, quality gate sertlestirme

## 2. Dosya Bazli Implementasyon Sirasi

1. Root docs ve kalite scriptleri
2. Backend config + middleware bootstrap
3. Backend migration dosyalari
4. Backend model/domain contract dosyalari
5. Backend API route, request, resource, controller dosyalari
6. Backend job/service/rules/ai/draft scaffolding
7. Frontend app shell, route gruplari, temel UI componentleri
8. Frontend feature sayfalari
9. Seed/factory/test dosyalari
10. Son kalite gate ve dokumantasyon guncellemeleri

## 3. Ilk 20 Somut Gorev

1. Repo klasorlerini olustur ve root README yaz.
2. Backend Laravel kurulumunu finalize et.
3. Frontend Next.js kurulumunu finalize et.
4. Kalite kapisi scriptini ekle.
5. `docs/product-overview.md` yaz.
6. `docs/architecture.md` yaz.
7. `docs/data-model.md` yaz.
8. `docs/meta-integration.md` yaz.
9. Sanctum tabanli auth temellerini kur.
10. Tenant migration'larini yaz (`organizations`, `workspaces`).
11. RBAC migration'larini yaz (`roles`, `permissions`, `role_permissions`, `user_workspace_roles`).
12. Meta baglanti migration'larini yaz.
13. Reporting ve ops migration'larini yaz.
14. Campaign/draft/approval migration'larini yaz.
15. UUID tabanli model iliskilerini kur.
16. Workspace context middleware yaz.
17. Auth + tenant API endpointlerini yaz.
18. Meta adapter interface ve versiyonlu implementasyon iskeletini yaz.
19. Seed/factory ile demo data olustur.
20. Feature/unit testlerin ilk setini yaz ve quality gate calistir.

## 4. Ilk Olusturulacak Dosyalar

- `README.md`
- `docs/implementation-plan.md`
- `docs/meta-integration.md`
- `scripts/run-quality-gate.ps1`
- `backend/database/migrations/*` (phase-1 cekirdek)
- `backend/app/Domain/Auth/*`
- `backend/app/Domain/Tenants/*`
- `backend/app/Domain/Meta/Contracts/*`
- `backend/routes/api.php`
- `frontend/src/app/(auth)/login/page.tsx`
- `frontend/src/app/(app)/dashboard/page.tsx`

## 5. Kodlama Oncesi Kilit Varsayimlar

1. Lokal ortamda PHP 8.2 ile gelistirme, production hedefinde PHP 8.3+.
2. Horizon production/Linux hedefli; local Windows ortaminda limitler dokumante edilir.
3. MVP publish akisinda approval zorunlu, otomatik publish yok.
4. Workspace bazli izolasyon tum API endpointlerinde zorunlu.
5. UI metinleri Turkce, kod sozlesmeleri Ingilizce olacak.
