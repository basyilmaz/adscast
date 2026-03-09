"use client";

import { ReactNode } from "react";
import { useRouter } from "next/navigation";
import { SidebarNav } from "./sidebar-nav";
import { WorkspaceSwitcher } from "./workspace-switcher";
import { Button } from "@/components/ui/button";
import { clearSession } from "@/lib/session";

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
      <SidebarNav />
      <div className="min-h-[calc(100vh-2rem)] flex-1">
        <header className="surface-card mb-4 flex flex-col gap-3 p-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 className="text-2xl font-extrabold">{title}</h2>
            {subtitle ? <p className="text-sm muted-text">{subtitle}</p> : null}
          </div>

          <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
            <WorkspaceSwitcher />
            <Button
              variant="outline"
              onClick={() => {
                clearSession();
                router.push("/login");
              }}
            >
              Cikis
            </Button>
          </div>
        </header>
        {children}
      </div>
    </div>
  );
}
