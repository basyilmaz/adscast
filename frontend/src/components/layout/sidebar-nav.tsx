"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";

const navItems = [
  { href: "/dashboard", label: "Dashboard" },
  { href: "/workspaces", label: "Workspace Switcher" },
  { href: "/ad-accounts", label: "Reklam Hesaplari" },
  { href: "/campaigns", label: "Kampanyalar" },
  { href: "/reports", label: "Raporlar" },
  { href: "/alerts", label: "Uyarilar" },
  { href: "/recommendations", label: "Oneriler" },
  { href: "/draft-builder", label: "Draft Builder" },
  { href: "/approvals", label: "Onay Kuyrugu" },
  { href: "/audit-logs", label: "Audit Loglari" },
  { href: "/settings/meta", label: "Ayarlar / Meta" },
];

export function SidebarNav() {
  const pathname = usePathname();

  return (
    <aside className="surface-card sticky top-4 h-[calc(100vh-2rem)] w-full max-w-[260px] p-4">
      <div className="mb-6 border-b border-[var(--border)] pb-4">
        <h1 className="text-xl font-extrabold tracking-tight">AdsCast</h1>
        <p className="text-sm muted-text">Meta Ads Operasyon Paneli</p>
      </div>

      <nav className="space-y-1">
        {navItems.map((item) => {
          const active =
            pathname === item.href || (item.href !== "/dashboard" && pathname.startsWith(item.href));

          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                "block rounded-md px-3 py-2 text-sm font-semibold transition",
                active
                  ? "bg-[var(--accent)] text-white"
                  : "text-[var(--foreground)] hover:bg-[var(--surface-2)]",
              )}
            >
              {item.label}
            </Link>
          );
        })}
      </nav>
    </aside>
  );
}
