"use client";

import { ReportContactListItem } from "@/lib/types";

type Props = {
  templateKind: string;
  onTemplateKindChange: (value: string) => void;
  targetEntityTypes: string[];
  onTargetEntityTypesChange: (value: string[]) => void;
  matchingCompaniesInput: string;
  onMatchingCompaniesInputChange: (value: string) => void;
  priority: number;
  onPriorityChange: (value: number) => void;
  isRecommendedDefault: boolean;
  onIsRecommendedDefaultChange: (value: boolean) => void;
  contacts: ReportContactListItem[];
};

const TEMPLATE_KIND_OPTIONS = [
  { value: "client_reporting", label: "Musteri Raporlama" },
  { value: "stakeholder_update", label: "Paydas Guncellemesi" },
  { value: "executive_digest", label: "Yonetici Ozeti" },
  { value: "internal_ops", label: "Ic Operasyon" },
];

export function ReportRecipientTemplateProfileFields({
  templateKind,
  onTemplateKindChange,
  targetEntityTypes,
  onTargetEntityTypesChange,
  matchingCompaniesInput,
  onMatchingCompaniesInputChange,
  priority,
  onPriorityChange,
  isRecommendedDefault,
  onIsRecommendedDefaultChange,
  contacts,
}: Props) {
  const availableCompanies = Array.from(
    new Set(
      contacts
        .filter((item) => item.is_active && item.company_name)
        .map((item) => item.company_name?.trim() ?? "")
        .filter(Boolean),
    ),
  ).sort((left, right) => left.localeCompare(right, "tr"));

  const selectedCompanies = parseCompanyTargets(matchingCompaniesInput);

  return (
    <div className="rounded-lg border border-[var(--border)] p-3">
      <p className="text-xs font-semibold uppercase tracking-wide muted-text">Grup Sablon Kurallari</p>
      <div className="mt-3 grid gap-3 md:grid-cols-2">
        <label className="flex flex-col gap-1">
          <span className="text-xs font-semibold uppercase tracking-wide muted-text">Sablon Tipi</span>
          <select
            className="h-10 rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={templateKind}
            onChange={(event) => onTemplateKindChange(event.target.value)}
          >
            {TEMPLATE_KIND_OPTIONS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="flex flex-col gap-1">
          <span className="text-xs font-semibold uppercase tracking-wide muted-text">Oncelik</span>
          <input
            type="number"
            min={1}
            max={100}
            className="h-10 rounded-md border border-[var(--border)] bg-white px-3 text-sm"
            value={priority}
            onChange={(event) => onPriorityChange(Math.max(1, Math.min(100, Number(event.target.value) || 50)))}
          />
        </label>
      </div>

      <div className="mt-3">
        <p className="text-xs font-semibold uppercase tracking-wide muted-text">Hedef Kayit Tipi</p>
        <div className="mt-2 flex flex-wrap gap-2">
          {[
            { value: "account", label: "Reklam Hesabi" },
            { value: "campaign", label: "Kampanya" },
          ].map((option) => {
            const active = targetEntityTypes.includes(option.value);

            return (
              <button
                key={option.value}
                type="button"
                className={`rounded-full border px-3 py-1 text-xs font-medium ${
                  active
                    ? "border-[var(--accent)] bg-[var(--surface-2)] text-[var(--accent)]"
                    : "border-[var(--border)] hover:border-[var(--accent)] hover:text-[var(--accent)]"
                }`}
                onClick={() =>
                  onTargetEntityTypesChange(
                    active
                      ? targetEntityTypes.filter((item) => item !== option.value)
                      : [...targetEntityTypes, option.value],
                  )
                }
              >
                {option.label}
              </button>
            );
          })}
        </div>
      </div>

      <label className="mt-3 flex flex-col gap-1">
        <span className="text-xs font-semibold uppercase tracking-wide muted-text">Eslesen Markalar / Sirketler</span>
        <input
          type="text"
          className="h-10 rounded-md border border-[var(--border)] bg-white px-3 text-sm"
          value={matchingCompaniesInput}
          onChange={(event) => onMatchingCompaniesInputChange(event.target.value)}
          placeholder="Orn. Castintech, Merva KS"
        />
      </label>

      {availableCompanies.length > 0 ? (
        <div className="mt-2 flex flex-wrap gap-2">
          {availableCompanies.slice(0, 8).map((company) => {
            const active = selectedCompanies.some((item) => item.localeCompare(company, "tr", { sensitivity: "accent" }) === 0);

            return (
              <button
                key={company}
                type="button"
                className={`rounded-full border px-3 py-1 text-xs font-medium ${
                  active
                    ? "border-[var(--accent)] bg-[var(--surface-2)] text-[var(--accent)]"
                    : "border-[var(--border)] hover:border-[var(--accent)] hover:text-[var(--accent)]"
                }`}
                onClick={() => onMatchingCompaniesInputChange(toggleCompany(selectedCompanies, company).join(", "))}
              >
                {company}
              </button>
            );
          })}
        </div>
      ) : null}

      <label className="mt-3 flex items-center gap-2 text-sm">
        <input
          type="checkbox"
          checked={isRecommendedDefault}
          onChange={(event) => onIsRecommendedDefaultChange(event.target.checked)}
        />
        Bu sablonu onerilen varsayilan grup olarak one cikar
      </label>
    </div>
  );
}

function parseCompanyTargets(value: string): string[] {
  return Array.from(
    new Set(
      value
        .split(/[,\n;]+/)
        .map((item) => item.trim())
        .filter(Boolean),
    ),
  );
}

function toggleCompany(current: string[], company: string): string[] {
  return current.some((item) => item.localeCompare(company, "tr", { sensitivity: "accent" }) === 0)
    ? current.filter((item) => item.localeCompare(company, "tr", { sensitivity: "accent" }) !== 0)
    : [...current, company];
}
