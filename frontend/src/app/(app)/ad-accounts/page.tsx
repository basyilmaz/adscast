"use client";

import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { apiRequest } from "@/lib/api";

type AdAccount = {
  id: string;
  account_id: string;
  name: string;
  currency: string | null;
  status: string;
  is_active: boolean;
  last_synced_at: string | null;
};

type Response = {
  data: {
    data: AdAccount[];
  };
};

export default function AdAccountsPage() {
  const [items, setItems] = useState<AdAccount[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const response = await apiRequest<Response>("/meta/ad-accounts", {
          requireWorkspace: true,
        });
        setItems(response.data.data ?? []);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Hesaplar alinamadi.");
      }
    };

    load();
  }, []);

  return (
    <Card>
      {error ? <p className="mb-4 text-sm text-[var(--danger)]">{error}</p> : null}
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b border-[var(--border)] text-left">
              <th className="px-3 py-2">Hesap Adi</th>
              <th className="px-3 py-2">Account ID</th>
              <th className="px-3 py-2">Para Birimi</th>
              <th className="px-3 py-2">Durum</th>
              <th className="px-3 py-2">Son Senkron</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.id} className="border-b border-[var(--border)]">
                <td className="px-3 py-2 font-semibold">{item.name}</td>
                <td className="px-3 py-2 font-mono text-xs">{item.account_id}</td>
                <td className="px-3 py-2">{item.currency ?? "-"}</td>
                <td className="px-3 py-2">
                  <Badge label={item.status} variant={item.is_active ? "success" : "warning"} />
                </td>
                <td className="px-3 py-2">{item.last_synced_at ?? "-"}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 ? (
        <p className="mt-4 text-sm muted-text">Bu workspace icin henuz reklam hesabi bulunmuyor.</p>
      ) : null}
    </Card>
  );
}
