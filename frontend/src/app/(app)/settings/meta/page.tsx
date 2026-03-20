"use client";

import { FormEvent, useState } from "react";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { apiRequest } from "@/lib/api";
import { invalidateApiCache } from "@/lib/api-cache";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { useApiQuery } from "@/hooks/use-api-query";
import { setMetaOAuthState } from "@/lib/session";

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
  const [accessToken, setAccessToken] = useState("");
  const [actionError, setActionError] = useState<string | null>(null);
  const [saving, setSaving] = useState(false);
  const [oauthLoading, setOauthLoading] = useState(false);
  const connectionQuery = useApiQuery<ConnectionResponse, Connection[]>("/meta/connections", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.metaConnections,
    select: (response) => response.data ?? [],
  });
  const connections = connectionQuery.data ?? [];
  const {
    error: connectionsError,
    isLoading: connectionsLoading,
    reload: reloadConnections,
  } = connectionQuery;
  const connectorStatusQuery = useApiQuery<ConnectorStatusResponse, ConnectorStatus>("/meta/connector-status", {
    requestOptions: {
      requireWorkspace: true,
    },
    ttlMs: QUERY_TTLS.metaConnectorStatus,
    select: (response) => response.data,
  });
  const connectorStatus = connectorStatusQuery.data;
  const {
    error: connectorStatusError,
    isLoading: connectorStatusLoading,
    reload: reloadConnectorStatus,
  } = connectorStatusQuery;
  const error = actionError ?? connectionsError ?? connectorStatusError;

  const onSaveConnection = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSaving(true);
    setActionError(null);
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
      invalidateApiCache("/meta/connections", { requireWorkspace: true });
      invalidateApiCache("/meta/connector-status", { requireWorkspace: true });
      invalidateApiCache("/meta/ad-accounts", { requireWorkspace: true });
      await Promise.all([reloadConnections(), reloadConnectorStatus()]);
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Baglanti kaydedilemedi.");
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
      invalidateApiCache("/meta/connections", { requireWorkspace: true });
      invalidateApiCache("/meta/ad-accounts", { requireWorkspace: true });
      invalidateApiCache("/campaigns", { requireWorkspace: true });
      invalidateApiCache("/dashboard/overview", { requireWorkspace: true });
      await reloadConnections();
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Asset sync basarisiz.");
    }
  };

  const startOAuth = async () => {
    setOauthLoading(true);
    setActionError(null);

    try {
      const response = await apiRequest<{ data: { auth_url: string; state: string } }>("/meta/oauth/start", {
        requireWorkspace: true,
      });

      setMetaOAuthState(response.data.state);
      window.location.assign(response.data.auth_url);
    } catch (err) {
      setActionError(err instanceof Error ? err.message : "Meta OAuth baslatilamadi.");
      setOauthLoading(false);
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
        ) : connectorStatusLoading ? (
          <p className="mt-2 text-sm muted-text">Connector durumu yukleniyor.</p>
        ) : (
          <p className="mt-2 text-sm muted-text">Connector durumu bulunmuyor.</p>
        )}
      </Card>

      <Card>
        <h4 className="text-sm font-bold uppercase tracking-wide">Meta Baglantisi Ekle/Guncelle</h4>
        <p className="mt-2 text-sm muted-text">
          OAuth hazirsa Meta login akisini, degilse manuel user access token akisini kullanin. Canli modda tum tokenlar kayit oncesi dogrulanir.
        </p>
        <div className="mt-3 flex flex-wrap gap-3">
          <Button
            type="button"
            onClick={startOAuth}
            disabled={oauthLoading || !connectorStatus?.oauth_ready}
          >
            {oauthLoading ? "Yonlendiriliyor..." : "Meta ile Baglan"}
          </Button>
          {!connectorStatus?.oauth_ready ? (
            <p className="text-sm muted-text">
              OAuth butonu icin App ID, App Secret ve Redirect URI eksiksiz olmali.
            </p>
          ) : null}
        </div>
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
          {connectionsLoading && connections.length === 0 ? (
            <p className="text-sm muted-text">Meta baglantilari yukleniyor.</p>
          ) : null}
          {!connectionsLoading && connections.length === 0 ? (
            <p className="text-sm muted-text">Bu workspace icin Meta baglantisi bulunmuyor.</p>
          ) : null}
        </div>
      </Card>
    </div>
  );
}
