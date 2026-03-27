import { cn } from "@/lib/utils";

type BadgeVariant = "neutral" | "success" | "warning" | "danger";

const variants: Record<BadgeVariant, string> = {
  neutral: "bg-[var(--surface-2)] text-[var(--foreground)] border border-[var(--border)]",
  success: "bg-[var(--success)]/10 text-[var(--success)] border border-[var(--success)]/20",
  warning: "bg-[var(--warning)]/10 text-[var(--warning)] border border-[var(--warning)]/20",
  danger: "bg-[var(--danger)]/10 text-[var(--danger)] border border-[var(--danger)]/20",
};

export function Badge({
  label,
  variant = "neutral",
}: {
  label: string;
  variant?: BadgeVariant;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wide",
        variants[variant],
      )}
    >
      {label}
    </span>
  );
}
