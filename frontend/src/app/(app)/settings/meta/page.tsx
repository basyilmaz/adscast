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
  connected_user_name: string | null;
  connection_mode: string | null;
  token_status: string;
  ad_accounts_count?: number;
  pages_count?: number;
  pixels_count?: number;
  businesses_count?: number;
};

type ConnectionResponse = {
  data: Connection[];
};

type ConnectorStatus = {
  mode: string;
  oauth_ready: boolean;
  app_id_configured: boolean;
  app_secret_configured: boolean;
  redirect_uri_configured: boolean;
  default_api_version: string;
  raw_payload_retention_days: number;
};

type ConnectorStatusResponse = {
  data: ConnectorStatus;
};

export default function MetaSettingsPage() {
  const [connections, setConnections] = useState<Connection[]>([]);
  const [connectorStatus, setConnectorStatus] = useState<ConnectorStatus | null>(null);
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

  const loadConnectorStatus = async () => {
    try {
      const response = await apiRequest<ConnectorStatusResponse>("/meta/connector-status", {
        requireWorkspace: true,
      });
      setConnectorStatus(response.data);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Meta connector durumu alinamadi.");
    }
  };

  useEffect(() => {
    loadConnections();
    loadConnectorStatus();
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
          api_version: connectorStatus?.default_api_version ?? "v20.0",
        },
      });
      setAccessToken("");
      await loadConnections();
      await loadConnectorStatus();
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
        <h4 className="text-sm font-bold uppercase tracking-wide">Meta Connector Durumu</h4>
        {connectorStatus ? (
          <>
            <div className="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
              <div className="rounded-md border border-[var(--border)] p-3">
                <p className="text-xs uppercase muted-text">Calisma Modu</p>
                <p className="mt-1 font-semibold">{connectorStatus.mode}</p>
              </div>
              <div className="rounded-md border border-[var(--border)] p-3">
                <p className="text-xs uppercase muted-text">OAuth Hazirligi</p>
                <div className="mt-1">
                  <Badge
                    label={connectorStatus.oauth_ready ? "Hazir" : "Eksik"}
                    variant={connectorStatus.oauth_ready ? "success" : "warning"}
                  />
                </div>
              </div>
              <div className="rounded-md border border-[var(--border)] p-3">
                <p className="text-xs uppercase muted-text">Varsayilan API</p>
                <p className="mt-1 font-semibold">{connectorStatus.default_api_version}</p>
              </div>
              <div className="rounded-md border border-[var(--border)] p-3">
                <p className="text-xs uppercase muted-text">Raw Payload Retention</p>
                <p className="mt-1 font-semibold">{connectorStatus.raw_payload_retention_days} gun</p>
              </div>
            </div>
            <div className="mt-3 flex flex-wrap gap-2 text-xs">
              <Badge
                label={connectorStatus.app_id_configured ? "APP ID OK" : "APP ID Eksik"}
                variant={connectorStatus.app_id_configured ? "success" : "warning"}
              />
              <Badge
                label={connectorStatus.app_secret_configured ? "APP SECRET OK" : "APP SECRET Eksik"}
                variant={connectorStatus.app_secret_configured ? "success" : "warning"}
              />
              <Badge
                label={connectorStatus.redirect_uri_configured ? "REDIRECT URI OK" : "REDIRECT URI Eksik"}
                variant={connectorStatus.redirect_uri_configured ? "success" : "warning"}
              />
            </div>
          </>
        ) : (
          <p className="mt-2 text-sm muted-text">Connector durumu yukleniyor.</p>
        )}
      </Card>

      <Card>
        <h4 className="text-sm font-bold uppercase tracking-wide">Meta Baglantisi Ekle/Guncelle</h4>
        <p className="mt-2 text-sm muted-text">
          Bu fazda manuel user access token ile baglanti kaydedilir. Canli modda token kayit oncesi dogrulanir.
        </p>
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
                  <p className="text-xs muted-text">
                    Bagli kullanici: {connection.connected_user_name ?? "-"} | Son sync: {connection.last_synced_at ?? "-"}
                  </p>
                  <p className="text-xs muted-text">
                    Business: {connection.businesses_count ?? 0} | Hesaplar: {connection.ad_accounts_count ?? 0} | Sayfalar: {connection.pages_count ?? 0} | Pixel: {connection.pixels_count ?? 0}
                  </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <Badge
                    label={connection.status}
                    variant={connection.status === "active" ? "success" : "warning"}
                  />
                  <Badge
                    label={connection.token_status}
                    variant={connection.token_status === "active" ? "success" : "warning"}
                  />
                  <Badge
                    label={connection.connection_mode ?? "bilinmiyor"}
                    variant={connection.connection_mode === "live" ? "success" : "neutral"}
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
