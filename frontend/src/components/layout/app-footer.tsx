import { APP_INFO } from "@/lib/app-info";

export function AppFooter() {
  const year = new Date().getFullYear();

  return (
    <footer className="border-t border-[var(--border)]/70 bg-[var(--surface)]/70 px-4 py-3 backdrop-blur">
      <div className="mx-auto flex w-full max-w-[1500px] flex-col gap-1 text-xs muted-text sm:flex-row sm:items-center sm:justify-between">
        <p>
          {APP_INFO.name} | Surum {APP_INFO.version}
        </p>
        <p>{APP_INFO.vendor} | {year}</p>
      </div>
    </footer>
  );
}
