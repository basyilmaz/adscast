"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/utils";
import {
  LayoutDashboard,
  Building2,
  Megaphone,
  BarChart3,
  FileText,
  Bell,
  Lightbulb,
  PenSquare,
  ClipboardCheck,
  ScrollText,
  Settings,
  Zap,
} from "lucide-react";

const navItems = [
  { href: "/dashboard", label: "Dashboard", icon: LayoutDashboard },
  { href: "/workspaces", label: "Workspace", icon: Building2 },
  { href: "/ad-accounts", label: "Reklam Hesapları", icon: Megaphone },
  { href: "/campaigns", label: "Kampanyalar", icon: BarChart3 },
  { href: "/reports", label: "Raporlar", icon: FileText },
  { href: "/alerts", label: "Uyarılar", icon: Bell },
  { href: "/recommendations", label: "Öneriler", icon: Lightbulb },
  { href: "/draft-builder", label: "Draft Builder", icon: PenSquare },
  { href: "/approvals", label: "Onay Kuyruğu", icon: ClipboardCheck },
  { href: "/audit-logs", label: "Audit Logları", icon: ScrollText },
  { href: "/settings/meta", label: "Ayarlar", icon: Settings },
];

export function SidebarNav() {
  const pathname = usePathname();

  return (
    <aside className="sticky top-4 flex h-[calc(100vh-2rem)] w-full max-w-[240px] flex-col overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface)] shadow-lg">
      {/* Brand */}
      <div className="flex items-center gap-2.5 border-b border-[var(--border)] px-5 py-4">
        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--accent)]">
          <Zap className="h-4 w-4 text-white" />
        </div>
        <div>
          <p className="text-sm font-extrabold tracking-tight text-[var(--foreground)]">AdsCast</p>
          <p className="text-[10px] font-medium text-[var(--muted)]">Meta Ads Panel</p>
        </div>
      </div>

      {/* Nav */}
      <nav className="flex-1 overflow-y-auto px-3 py-3">
        <p className="mb-2 px-2 text-[10px] font-semibold uppercase tracking-widest text-[var(--muted)]">
          Navigasyon
        </p>
        <ul className="space-y-0.5">
          {navItems.map((item) => {
            const active =
              pathname === item.href ||
              (item.href !== "/dashboard" && pathname.startsWith(item.href));
            const Icon = item.icon;

            return (
              <li key={item.href}>
                <Link
                  href={item.href}
                  className={cn(
                    "group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all duration-150",
                    active
                      ? "bg-[var(--accent)] text-white shadow-sm"
                      : "text-[var(--foreground)] hover:bg-[var(--surface-2)] hover:text-[var(--foreground)]",
                  )}
                >
                  <Icon
                    className={cn(
                      "h-4 w-4 shrink-0 transition-colors",
                      active ? "text-white" : "text-[var(--muted)] group-hover:text-[var(--foreground)]",
                    )}
                  />
                  <span className="truncate">{item.label}</span>
                  {active && (
                    <span className="ml-auto h-1.5 w-1.5 shrink-0 rounded-full bg-white/70" />
                  )}
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>

      {/* Footer */}
      <div className="border-t border-[var(--border)] px-5 py-3">
        <p className="text-[10px] text-[var(--muted)]">v0.2.0 · AdsCast</p>
      </div>
    </aside>
  );
}
