import { Suspense } from "react";
import { MetaOAuthCallbackClient } from "./meta-oauth-callback-client";

export default function MetaOAuthCallbackPage() {
  return (
    <Suspense fallback={<div className="surface-card p-4">Meta baglantisi hazirlaniyor.</div>}>
      <MetaOAuthCallbackClient />
    </Suspense>
  );
}
