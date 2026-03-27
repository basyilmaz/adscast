"use client";

import { ReactNode, Suspense } from "react";
import { useRouter } from "next/navigation";
import { SidebarNav } from "./sidebar-nav";
import { AppRoutePrefetcher } from "./app-route-prefetcher";
import { AppGlobalFilters } from "./app-global-filters";
import { WorkspaceSwitcher } from "./workspace-switcher";
import { Button } from "@/components/ui/button";
import { clearSession } from "@/lib/session";
import { LogOut } from "lucide-react";

export function AppShell({
  title,
  subtitle,
  children,
}: {
  title: string;
  subtitle?: string;
  children: ReactNode;
}) {
  const router = useRouter();

  return (
    <div className="mx-auto flex w-full max-w-[1500px] gap-4 p-4">
      <AppRoutePrefetcher />
      <SidebarNav />
      <div className="min-h-[calc(100vh-2rem)] flex-1 min-w-0">
        <header className="mb-4 flex flex-col gap-3 rounded-2xl border border-[var(--border)] bg-[var(--surface)] px-5 py-4 shadow-sm lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 className="text-xl font-extrabold tracking-tight text-[var(--foreground)]">{title}</h2>
            {subtitle ? (
              <p className="mt-0.5 text-sm text-[var(--muted)]">{subtitle}</p>
            ) : null}
          </div>

          <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <WorkspaceSwitcher />
            <Button
              variant="outline"
              size="sm"
              onClick={() => {
                clearSession();
                router.push("/login");
              }}
              className="flex items-center gap-2"
            >
              <LogOut className="h-4 w-4" />
              Çıkış
            </Button>
          </div>
        </header>
        <Suspense fallback={null}>
          <AppGlobalFilters />
        </Suspense>
        {children}
      </div>
    </div>
  );
}
