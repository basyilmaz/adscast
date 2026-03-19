"use client";

import { useEffect, useMemo, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { clearMetaOAuthState, getMetaOAuthState } from "@/lib/session";

type Status = "loading" | "success" | "error";

export function MetaOAuthCallbackClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [status, setStatus] = useState<Status>("loading");
  const [message, setMessage] = useState("Meta baglantisi tamamlanıyor.");

  const query = useMemo(() => ({
    code: searchParams.get("code"),
    state: searchParams.get("state"),
    error: searchParams.get("error"),
    errorDescription: searchParams.get("error_description"),
  }), [searchParams]);

  useEffect(() => {
    const run = async () => {
      if (query.error) {
        setStatus("error");
        setMessage(query.errorDescription ?? "Meta yetkilendirme islemi kullanici tarafinda iptal edildi.");
        clearMetaOAuthState();
        return;
      }

      if (!query.code || !query.state) {
        setStatus("error");
        setMessage("OAuth callback parametreleri eksik.");
        clearMetaOAuthState();
        return;
      }

      const storedState = getMetaOAuthState();

      if (!storedState || storedState !== query.state) {
        setStatus("error");
        setMessage("OAuth state eslesmiyor. Guvenlik nedeniyle islem durduruldu.");
        clearMetaOAuthState();
        return;
      }

      try {
        await apiRequest("/meta/oauth/exchange", {
          method: "POST",
          requireWorkspace: true,
          body: {
            code: query.code,
            state: query.state,
          },
        });

        clearMetaOAuthState();
        setStatus("success");
        setMessage("Meta baglantisi olusturuldu. Ayarlar ekranina yonlendiriliyorsunuz.");

        window.setTimeout(() => {
          router.replace("/settings/meta");
        }, 1200);
      } catch (err) {
        clearMetaOAuthState();
        setStatus("error");
        setMessage(err instanceof Error ? err.message : "Meta baglantisi tamamlanamadi.");
      }
    };

    run();
  }, [query, router]);

  return (
    <Card className="max-w-2xl">
      <h4 className="text-sm font-bold uppercase tracking-wide">Meta OAuth Callback</h4>
      <p className="mt-3 text-sm">{message}</p>
      <div className="mt-4 flex gap-3">
        {status !== "loading" ? (
          <Button type="button" onClick={() => router.push("/settings/meta")}>
            Meta Ayarlarina Don
          </Button>
        ) : null}
      </div>
    </Card>
  );
}
