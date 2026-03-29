"use client";

import Link from "next/link";
import dynamic from "next/dynamic";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardTitle, CardValue } from "@/components/ui/card";
import { NextBestActionsPanel } from "@/components/operations/next-best-actions-panel";
import type { ClientReportPayload } from "@/lib/types";

const SpendResultChart = dynamic(
  () => import("@/components/charts/spend-result-chart").then((mod) => mod.SpendResultChart),
  {
    ssr: false,
    loading: () => <div className="h-[280px] w-full rounded-md bg-[var(--surface-2)]" />,
  },
);

function formatCurrency(value: number | null | undefined) {
  if (value === null || value === undefined) return "-";
  return `$${value.toFixed(2)}`;
}

function formatNumber(value: number | null | undefined) {
  if (value === null || value === undefined) return "-";
  return value.toFixed(value % 1 === 0 ? 0 : 2);
}

function formatPercent(value: number | null | undefined) {
  if (value === null || value === undefined) return "-";
  return `${value.toFixed(2)}%`;
}

type Props = {
  data: ClientReportPayload;
  onSaveSnapshot?: () => void;
  onDownloadCsv?: () => void;
  snapshotLoading?: boolean;
  snapshotMessage?: string | null;
  snapshotActionLabel?: string;
  mode?: "operator" | "client";
};

export function ClientReportCanvas({
  data,
  onSaveSnapshot,
  onDownloadCsv,
  snapshotLoading = false,
  snapshotMessage,
  snapshotActionLabel = "Snapshot Kaydet",
  mode = "operator",
}: Props) {
  return (
    <div className="space-y-4">
      <Card>
        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <p className="text-sm muted-text">
              {data.entity.type === "account" ? "Reklam Hesabi" : "Kampanya"} / {data.entity.name}
            </p>
            <h2 className="text-2xl font-bold">{data.report.title}</h2>
            <p className="mt-2 text-sm muted-text">{data.report.headline}</p>
          </div>
          <div className="flex flex-wrap gap-2">
            <Badge label={data.entity.type === "account" ? "Account Report" : "Campaign Report"} variant="neutral" />
            <Badge label={`${data.range.start_date} / ${data.range.end_date}`} variant="neutral" />
            {data.share_link ? <Badge label="Musteri Paylasimi" variant="success" /> : null}
            <div className="no-print flex flex-wrap gap-2">
              {onDownloadCsv ? (
                <Button type="button" variant="secondary" onClick={onDownloadCsv}>
                  CSV Indir
                </Button>
              ) : null}
              <Button type="button" variant="secondary" onClick={() => window.print()}>
                Yazdir / PDF
              </Button>
              {onSaveSnapshot ? (
                <Button type="button" onClick={onSaveSnapshot} disabled={snapshotLoading}>
                  {snapshotLoading ? "Kaydediliyor..." : snapshotActionLabel}
                </Button>
              ) : null}
            </div>
          </div>
        </div>
        {snapshotMessage ? <p className="mt-3 text-sm text-[var(--accent)]">{snapshotMessage}</p> : null}
      </Card>

      <section className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <Card>
          <CardTitle>Toplam Harcama</CardTitle>
          <CardValue>{formatCurrency(data.summary.spend)}</CardValue>
        </Card>
        <Card>
          <CardTitle>Toplam Sonuc</CardTitle>
          <CardValue>{formatNumber(data.summary.results)}</CardValue>
        </Card>
        <Card>
          <CardTitle>CPA / CPL</CardTitle>
          <CardValue>{formatCurrency(data.summary.cpa_cpl)}</CardValue>
        </Card>
        <Card>
          <CardTitle>Acik Uyari</CardTitle>
          <CardValue>{formatNumber(data.summary.open_alerts)}</CardValue>
        </Card>
        <Card>
          <CardTitle>Acik Oneri</CardTitle>
          <CardValue>{formatNumber(data.summary.open_recommendations)}</CardValue>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1.35fr_1fr]">
        <Card>
          <CardTitle>Performans Trendi</CardTitle>
          <div className="mt-3">
            <SpendResultChart data={data.trend} />
          </div>
        </Card>

        <Card>
          <CardTitle>Rapor Ozetleri</CardTitle>
          <div className="mt-3 space-y-3 text-sm">
            <div>
              <p className="font-semibold">Musteri Ozeti</p>
              <p className="muted-text">{data.report.client_summary}</p>
            </div>
            {mode === "operator" ? (
              <div>
                <p className="font-semibold">Operator Ozeti</p>
                <p className="muted-text">{data.report.operator_summary}</p>
              </div>
            ) : null}
            <div>
              <p className="font-semibold">PDF Foundation</p>
              <p className="muted-text">{data.export_options.pdf_foundation.note}</p>
            </div>
            {data.share_link ? (
              <div>
                <p className="font-semibold">Paylasim Durumu</p>
                <p className="muted-text">
                  Bu link {data.share_link.expires_at ?? "belirsiz tarihe kadar"} gecerlidir.
                </p>
              </div>
            ) : null}
          </div>
        </Card>
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr]">
        <Card>
          <CardTitle>Odak Alanlari</CardTitle>
          <div className="mt-3 space-y-3">
            {data.focus_areas.map((item) => (
              <div key={item.label} className="rounded-lg border border-[var(--border)] p-3">
                <p className="text-sm font-semibold">{item.label}</p>
                <p className="mt-1 text-sm muted-text">{item.detail ?? "-"}</p>
              </div>
            ))}
          </div>
        </Card>

        {mode === "operator" ? (
          <NextBestActionsPanel
            title="Raporun Onerdigi Sonraki Adimlar"
            items={data.next_best_actions}
            emptyText="Bu rapor icin kayitli sonraki adim bulunmuyor."
          />
        ) : (
          <Card>
            <CardTitle>Paylasim Notu</CardTitle>
            <p className="mt-3 text-sm muted-text">
              Bu gorunum, kaydedilmis snapshot&apos;in musteri paylasimi icin hazirlanmis sabit kopyasidir.
            </p>
          </Card>
        )}
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr]">
        <Card>
          <CardTitle>Bu Donemde Ne Denendi?</CardTitle>
          <div className="mt-3 space-y-3">
            {data.what_we_tested.map((item) => (
              <div key={`${item.type}-${item.title}`} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label={item.type} variant="neutral" />
                  <Badge label={item.status} variant="neutral" />
                </div>
                <p className="mt-2 font-semibold">{item.title}</p>
                <p className="mt-1 text-sm muted-text">{item.subtitle}</p>
                {item.spend != null ? (
                  <div className="mt-2 flex flex-wrap gap-3 text-xs">
                    <span>Harcama: <strong>{formatCurrency(item.spend)}</strong></span>
                    <span>Sonuc: <strong>{formatNumber(item.results)}</strong></span>
                    <span>CPA: <strong>{formatCurrency(item.cpa_cpl)}</strong></span>
                    <span>CTR: <strong>{formatPercent(item.ctr)}</strong></span>
                    <span>CPM: <strong>{formatCurrency(item.cpm)}</strong></span>
                  </div>
                ) : null}
                <p className="mt-2 text-sm">{item.note}</p>
                {mode === "operator" && item.route ? (
                  <Link href={item.route} className="mt-3 inline-flex text-sm font-semibold text-[var(--accent)] hover:underline">
                    Ilgili kaydi ac
                  </Link>
                ) : null}
              </div>
            ))}
            {data.what_we_tested.length === 0 ? <p className="text-sm muted-text">Bu aralikta listelenecek test bulunmuyor.</p> : null}
          </div>
        </Card>

        {mode === "operator" ? (
          <Card>
            <CardTitle>Snapshot Gecmisi</CardTitle>
            <div className="mt-3 space-y-3">
              {(data.snapshot_history ?? []).map((item) => (
                <div key={item.id} className="rounded-lg border border-[var(--border)] p-3">
                  <p className="font-semibold">{item.title}</p>
                  <p className="mt-1 text-xs muted-text">
                    {item.start_date} / {item.end_date} • {item.created_at ?? "-"}
                  </p>
                  <div className="mt-3 flex flex-wrap gap-3 text-sm">
                    <Link href={item.snapshot_url} className="font-semibold text-[var(--accent)] hover:underline">
                      Snapshot Ac
                    </Link>
                  </div>
                </div>
              ))}
              {(data.snapshot_history ?? []).length === 0 ? (
                <p className="text-sm muted-text">Bu entity icin kayitli snapshot bulunmuyor.</p>
              ) : null}
            </div>
          </Card>
        ) : (
          <Card>
            <CardTitle>Rapor Bilgisi</CardTitle>
            <p className="mt-3 text-sm muted-text">
              Hazirlanma zamani: {data.snapshot?.created_at ?? data.report.generated_at}
            </p>
          </Card>
        )}
      </section>

      <section className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_1fr]">
        <Card>
          <CardTitle>Riskler</CardTitle>
          <div className="mt-3 space-y-3">
            {data.risks.map((item) => (
              <div key={item.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label={item.severity} variant={item.severity === "high" ? "danger" : "warning"} />
                  <span className="text-xs muted-text">{item.date_detected ?? "-"}</span>
                </div>
                <p className="mt-2 font-semibold">{item.summary}</p>
                <p className="mt-1 text-sm muted-text">{item.impact_summary}</p>
              </div>
            ))}
            {data.risks.length === 0 ? <p className="text-sm muted-text">Bu aralikta kayitli risk bulunmuyor.</p> : null}
          </div>
        </Card>

        <Card>
          <CardTitle>{mode === "operator" ? "Oneriler" : "Test ve Gelisim Notlari"}</CardTitle>
          <div className="mt-3 space-y-3">
            {data.recommendations.map((item) => (
              <div key={item.id} className="rounded-lg border border-[var(--border)] p-3">
                <div className="flex flex-wrap items-center gap-2">
                  <Badge label={item.priority} variant={item.priority === "high" ? "danger" : "warning"} />
                  {mode === "operator" ? <Badge label={item.action_status.label} variant="neutral" /> : null}
                </div>
                <p className="mt-2 font-semibold">{item.summary}</p>
                <p className="mt-1 text-sm muted-text">{item.client_view.summary}</p>
                {mode === "operator" ? <p className="mt-2 text-sm">Sonraki test: {item.operator_view.next_test ?? "-"}</p> : null}
              </div>
            ))}
            {data.recommendations.length === 0 ? <p className="text-sm muted-text">Bu aralikta kayitli oneri bulunmuyor.</p> : null}
          </div>
        </Card>
      </section>

      {(data.creative_performance ?? []).length > 0 ? (
        <Card>
          <CardTitle>Kreatif Performans Siralamasi</CardTitle>
          <p className="mt-1 text-sm muted-text">Reklamlar CPA/CPL sirasiyla listelenmistir. Dusuk CPA = daha iyi performans.</p>
          <div className="mt-3 overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-[var(--border)] text-xs uppercase tracking-wider text-[var(--muted)]">
                  <th className="px-3 py-2">#</th>
                  <th className="px-3 py-2">Reklam</th>
                  <th className="px-3 py-2">Baslik</th>
                  <th className="px-3 py-2">CTA</th>
                  <th className="px-3 py-2">Tur</th>
                  <th className="px-3 py-2 text-right">Harcama</th>
                  <th className="px-3 py-2 text-right">Sonuc</th>
                  <th className="px-3 py-2 text-right">CPA/CPL</th>
                  <th className="px-3 py-2 text-right">CTR</th>
                  <th className="px-3 py-2 text-right">CPM</th>
                  <th className="px-3 py-2">Etiket</th>
                </tr>
              </thead>
              <tbody>
                {(data.creative_performance ?? []).map((cp, idx) => (
                  <tr key={cp.ad_id} className="border-b border-[var(--border)] last:border-0 hover:bg-[var(--surface-2)]">
                    <td className="px-3 py-2 text-[var(--muted)]">{idx + 1}</td>
                    <td className="px-3 py-2 max-w-[180px] truncate">{cp.ad_name}</td>
                    <td className="px-3 py-2 max-w-[160px] truncate">{cp.headline ?? "-"}</td>
                    <td className="px-3 py-2">{cp.call_to_action ?? "-"}</td>
                    <td className="px-3 py-2">{cp.asset_type ?? "-"}</td>
                    <td className="px-3 py-2 text-right">{formatCurrency(cp.spend)}</td>
                    <td className="px-3 py-2 text-right">{formatNumber(cp.results)}</td>
                    <td className="px-3 py-2 text-right">{formatCurrency(cp.cpa_cpl)}</td>
                    <td className="px-3 py-2 text-right">{formatPercent(cp.ctr)}</td>
                    <td className="px-3 py-2 text-right">{formatCurrency(cp.cpm)}</td>
                    <td className="px-3 py-2">
                      {cp.rank_label ? (
                        <Badge label={cp.rank_label} variant={cp.rank_label === "En Iyi Performans" ? "success" : "danger"} />
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      ) : null}
    </div>
  );
}
