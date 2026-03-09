"use client";

import Link from "next/link";
import { FormEvent, useEffect, useState } from "react";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";

type DraftResponse = {
  data: {
    id: string;
  };
};

type AdAccountResponse = {
  data: {
    data: Array<{
      id: string;
      name: string;
      account_id: string;
    }>;
  };
};

export default function DraftBuilderPage() {
  const [accounts, setAccounts] = useState<Array<{ id: string; label: string }>>([]);
  const [form, setForm] = useState({
    meta_ad_account_id: "",
    objective: "LEADS",
    product_service: "",
    target_audience: "",
    location: "TR",
    budget_min: "50",
    budget_max: "150",
    offer: "",
    landing_page_url: "",
    tone_style: "performans",
    existing_creative_availability: "var",
    notes: "",
  });
  const [createdId, setCreatedId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const loadAccounts = async () => {
      try {
        const response = await apiRequest<AdAccountResponse>("/meta/ad-accounts", {
          requireWorkspace: true,
        });
        const mapped = (response.data.data ?? []).map((item) => ({
          id: item.id,
          label: `${item.name} (${item.account_id})`,
        }));
        setAccounts(mapped);
        if (mapped[0]?.id) {
          setForm((prev) =>
            prev.meta_ad_account_id ? prev : { ...prev, meta_ad_account_id: mapped[0].id },
          );
        }
      } catch {
        // Hesap listesi yoksa manuel giris fallback'i korunur.
      }
    };

    loadAccounts();
  }, []);

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setLoading(true);
    setError(null);
    setCreatedId(null);

    try {
      const payload = {
        ...form,
        budget_min: Number(form.budget_min),
        budget_max: Number(form.budget_max),
      };

      const response = await apiRequest<DraftResponse>("/drafts", {
        method: "POST",
        body: payload,
        requireWorkspace: true,
      });

      setCreatedId(response.data.id);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Draft olusturulamadi.");
    } finally {
      setLoading(false);
    }
  };

  return (
    <Card>
      <form className="grid grid-cols-1 gap-4 md:grid-cols-2" onSubmit={onSubmit}>
        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-semibold">Meta Reklam Hesabi</label>
          {accounts.length > 0 ? (
            <select
              className="h-10 w-full rounded-md border border-[var(--border)] bg-white px-3 text-sm"
              value={form.meta_ad_account_id}
              onChange={(e) => setForm((prev) => ({ ...prev, meta_ad_account_id: e.target.value }))}
              required
            >
              {accounts.map((account) => (
                <option key={account.id} value={account.id}>
                  {account.label}
                </option>
              ))}
            </select>
          ) : (
            <Input
              value={form.meta_ad_account_id}
              onChange={(e) => setForm((prev) => ({ ...prev, meta_ad_account_id: e.target.value }))}
              placeholder="meta_ad_accounts tablosundan bir UUID"
              required
            />
          )}
        </div>

        <div>
          <label className="mb-1 block text-sm font-semibold">Objective</label>
          <Input
            value={form.objective}
            onChange={(e) => setForm((prev) => ({ ...prev, objective: e.target.value }))}
            required
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-semibold">Lokasyon</label>
          <Input
            value={form.location}
            onChange={(e) => setForm((prev) => ({ ...prev, location: e.target.value }))}
          />
        </div>

        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-semibold">Urun/Hizmet</label>
          <Input
            value={form.product_service}
            onChange={(e) => setForm((prev) => ({ ...prev, product_service: e.target.value }))}
            required
          />
        </div>

        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-semibold">Hedef Kitle</label>
          <Input
            value={form.target_audience}
            onChange={(e) => setForm((prev) => ({ ...prev, target_audience: e.target.value }))}
            required
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-semibold">Butce Min</label>
          <Input
            value={form.budget_min}
            onChange={(e) => setForm((prev) => ({ ...prev, budget_min: e.target.value }))}
            type="number"
            required
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-semibold">Butce Max</label>
          <Input
            value={form.budget_max}
            onChange={(e) => setForm((prev) => ({ ...prev, budget_max: e.target.value }))}
            type="number"
            required
          />
        </div>

        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-semibold">Teklif</label>
          <Input
            value={form.offer}
            onChange={(e) => setForm((prev) => ({ ...prev, offer: e.target.value }))}
          />
        </div>

        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-semibold">Landing URL</label>
          <Input
            value={form.landing_page_url}
            onChange={(e) => setForm((prev) => ({ ...prev, landing_page_url: e.target.value }))}
            type="url"
          />
        </div>

        <div className="md:col-span-2">
          <label className="mb-1 block text-sm font-semibold">Notlar</label>
          <Input value={form.notes} onChange={(e) => setForm((prev) => ({ ...prev, notes: e.target.value }))} />
        </div>

        <div className="md:col-span-2 flex items-center gap-3">
          <Button type="submit" disabled={loading}>
            {loading ? "Olusturuluyor..." : "Draft Olustur"}
          </Button>
          {createdId ? (
            <Link href={`/drafts/${createdId}`} className="text-sm font-semibold text-[var(--accent)] hover:underline">
              Drafti Gor
            </Link>
          ) : null}
        </div>

        {error ? <p className="md:col-span-2 text-sm text-[var(--danger)]">{error}</p> : null}
      </form>
    </Card>
  );
}
