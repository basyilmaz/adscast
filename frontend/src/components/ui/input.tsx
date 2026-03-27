import * as React from "react";
import { cn } from "@/lib/utils";

export const Input = React.forwardRef<
  HTMLInputElement,
  React.InputHTMLAttributes<HTMLInputElement>
>(({ className, ...props }, ref) => {
  return (
    <input
      ref={ref}
      className={cn(
        "h-10 w-full rounded-xl border border-[var(--border)] bg-[var(--surface-2)] px-3 text-sm outline-none transition-all duration-150 placeholder:text-[var(--muted)] focus:border-[var(--accent)] focus:bg-white focus:ring-2 focus:ring-[var(--accent)]/10",
        className,
      )}
      {...props}
    />
  );
});

Input.displayName = "Input";
