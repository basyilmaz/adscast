import fs from "node:fs";
import path from "node:path";

const requiredPages = [
  "src/app/(auth)/login/page.tsx",
  "src/app/(app)/workspaces/page.tsx",
  "src/app/(app)/dashboard/page.tsx",
  "src/app/(app)/ad-accounts/page.tsx",
  "src/app/(app)/campaigns/page.tsx",
  "src/app/(app)/campaigns/[id]/page.tsx",
  "src/app/(app)/alerts/page.tsx",
  "src/app/(app)/recommendations/page.tsx",
  "src/app/(app)/draft-builder/page.tsx",
  "src/app/(app)/drafts/[id]/page.tsx",
  "src/app/(app)/approvals/page.tsx",
  "src/app/(app)/audit-logs/page.tsx",
  "src/app/(app)/settings/meta/page.tsx",
];

const missing = requiredPages.filter((relativePath) => {
  const fullPath = path.resolve(process.cwd(), relativePath);
  return !fs.existsSync(fullPath);
});

if (missing.length > 0) {
  console.error("Smoke check basarisiz. Eksik sayfalar:");
  for (const file of missing) {
    console.error(`- ${file}`);
  }
  process.exit(1);
}

console.log("Smoke check basarili. Tum zorunlu sayfa iskeletleri mevcut.");
