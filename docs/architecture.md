# AdsCast - Mimari

## Ust Seviye Mimari

AdsCast iki uygulamadan olusur:

1. `backend` (Laravel 11 API)
2. `frontend` (Next.js panel)

Backend, domain merkezli moduler klasor yapisi ile tasarlanmistir:

- `app/Domain/Auth`
- `app/Domain/Tenants`
- `app/Domain/Meta`
- `app/Domain/Reporting`
- `app/Domain/Rules`
- `app/Domain/AI`
- `app/Domain/Drafts`
- `app/Domain/Approvals`
- `app/Domain/Audit`

## Sinirlar ve Sorumluluklar

1. Auth ve RBAC
   - Kullanici kimlik dogrulama
   - Workspace bazli rol atama ve yetki kontrolu
2. Tenants
   - Organization/workspace baglaminin cozumlenmesi
   - Veri izolasyonu middleware ve query katmani
3. Meta
   - Adapter uzerinden API entegrasyonu
   - Versionlanabilir connector siniri
   - Sync orchestration ve ham payload saklama
4. Reporting
   - Normalize insight verisinin sorgulanmasi
   - Dashboard metriklerinin hesaplanmasi
5. Rules
   - Deterministic sinyal uretimi
   - Alert olusturma ve severity/guven puani
6. AI
   - Provider abstraction
   - Prompt context ve output traceability
7. Drafts + Approvals
   - Campaign draft olusturma
   - Onay olmadan publish engeli
8. Audit
   - Kritik olaylarin actor + target + metadata ile kaydi

## Teknik Ilkeler

- Multi-tenant izolasyon varsayilan
- Idempotent sync jobs
- API contract odakli resource transformer kullanimi
- Token/secrets alanlarinda encryption at rest
- Queue tabanli async islemler (Redis + Horizon prod hedefi)
- Test olmadan feature tamamlanmis sayilmaz

## Frontend Mimari

- App Router (Next.js)
- UI katmani: reusable component'ler + feature page'ler
- Veri erisimi: API client ve server actions / fetch wrappers
- Workspace context: secili workspace uzerinden API cagri kapsami
- Sayfalar:
  - Giris
  - Workspace switcher
  - Dashboard
  - Accounts / Campaigns / Alerts / Recommendations
  - Draft wizard + review
  - Approval queue
  - Audit logs
  - Settings
