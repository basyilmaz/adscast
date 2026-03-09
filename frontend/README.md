# AdsCast Frontend

Next.js + TypeScript + Tailwind tabanli AdsCast panel uygulamasidir.

## Gelistirme

```bash
cp .env.example .env.local
npm install
npm run dev
```

Varsayilan adres: `http://localhost:3000`

## Build ve Lint

```bash
npm run lint
npm run build
```

## Beklenen Backend

Frontend, varsayilan olarak su API base adresini kullanir:

`NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api/v1`

Workspace baglami `X-Workspace-Id` header'i ile, auth ise Bearer token ile gonderilir.
