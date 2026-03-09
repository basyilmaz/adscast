"use client";

import { FormEvent, useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { apiRequest } from "@/lib/api";

type Connection = {
  id: string;
  provider: string;
  api_version: string;
  status: string;
  token_expires_at: string | null;
  last_synced_at: string | null;
};

type ConnectionResponse = {
  data: Connection[];
};

export default function MetaSettingsPage() {
  const [connections, setConnections] = useState<Connection[]>([]);
  const [accessToken, setAccessToken] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);

  const loadConnections = async () => {
    try {
      const response = await apiRequest<ConnectionResponse>("/meta/connections", {
        requireWorkspace: true,
      });
      setConnections(response.data ?? []);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Meta baglantilari alinamadi.");
    }
  };

  useEffect(() => {
    loadConnections();
  }, []);

  const onSaveConnection = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSaving(true);
    setError(null);
    try {
      await apiRequest("/meta/connections", {
        method: "POST",
        requireWorkspace: true,
        body: {
          access_token: accessToken,
          api_version: "v20.0",
        },
      });
      setAccessToken("");
      await loadConnections();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Baglanti kaydedilemedi.");
    } finally {
      setSaving(false);
    }
  };

  const runAssetSync = async (connectionId: string) => {
    try {
      await apiRequest(`/meta/connections/${connectionId}/sync-assets`, {
        method: "POST",
        requireWorkspace: true,
      });
      await loadConnections();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Asset sync basarisiz.");
    }
  };

  return (
    <div className="space-y-4">
      <Card>
        <h4 className="text-sm font-bold uppercase tracking-wide">Meta Baglantisi Ekle/Guncelle</h4>
        <form onSubmit={onSaveConnection} className="mt-3 flex flex-col gap-3 md:flex-row md:items-end">
          <div className="flex-1">
            <label className="mb-1 block text-sm font-semibold">Access Token</label>
            <Input
              value={accessToken}
              onChange={(e) => setAccessToken(e.target.value)}
              placeholder="Meta access token"
              required
            />
          </div>
          <Button type="submit" disabled={saving}>
            {saving ? "Kaydediliyor..." : "Baglantiyi Kaydet"}
          </Button>
        </form>
      </Card>

      <Card>
        <h4 className="text-sm font-bold uppercase tracking-wide">Aktif Meta Baglantilari</h4>
        {error ? <p className="mt-2 text-sm text-[var(--danger)]">{error}</p> : null}
        <div className="mt-3 space-y-2">
          {connections.map((connection) => (
            <div key={connection.id} className="rounded-md border border-[var(--border)] p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="font-semibold">
                    {connection.provider} / {connection.api_version}
                  </p>
                  <p className="text-xs muted-text">Son sync: {connection.last_synced_at ?? "-"}</p>
                </div>
                <div className="flex items-center gap-2">
                  <Badge
                    label={connection.status}
                    variant={connection.status === "active" ? "success" : "warning"}
                  />
                  <Button size="sm" variant="secondary" onClick={() => runAssetSync(connection.id)}>
                    Asset Sync
                  </Button>
                </div>
              </div>
            </div>
          ))}
          {connections.length === 0 ? (
            <p className="text-sm muted-text">Bu workspace icin Meta baglantisi bulunmuyor.</p>
          ) : null}
        </div>
      </Card>
    </div>
  );
}
