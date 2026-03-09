"use client";

import { useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { apiRequest } from "@/lib/api";

type AuditItem = {
  id: string;
  action: string;
  target_type: string;
  target_id: string | null;
  actor_id: string | null;
  occurred_at: string;
};

type Response = {
  data: {
    data: AuditItem[];
  };
};

export default function AuditLogsPage() {
  const [items, setItems] = useState<AuditItem[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        const response = await apiRequest<Response>("/audit-logs", {
          requireWorkspace: true,
        });
        setItems(response.data.data ?? []);
      } catch (err) {
        setError(err instanceof Error ? err.message : "Audit loglari alinamadi.");
      }
    };

    load();
  }, []);

  return (
    <Card>
      {error ? <p className="mb-3 text-sm text-[var(--danger)]">{error}</p> : null}
      <div className="overflow-x-auto">
        <table className="min-w-full text-sm">
          <thead>
            <tr className="border-b border-[var(--border)] text-left">
              <th className="px-3 py-2">Aksiyon</th>
              <th className="px-3 py-2">Hedef</th>
              <th className="px-3 py-2">Actor</th>
              <th className="px-3 py-2">Zaman</th>
            </tr>
          </thead>
          <tbody>
            {items.map((item) => (
              <tr key={item.id} className="border-b border-[var(--border)]">
                <td className="px-3 py-2 font-semibold">{item.action}</td>
                <td className="px-3 py-2">
                  {item.target_type} {item.target_id ? `(${item.target_id})` : ""}
                </td>
                <td className="px-3 py-2">{item.actor_id ?? "-"}</td>
                <td className="px-3 py-2">{item.occurred_at}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {items.length === 0 ? <p className="mt-3 text-sm muted-text">Audit kaydi yok.</p> : null}
    </Card>
  );
}
