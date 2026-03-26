"use client";

import { useEffect, useRef, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { ReportDecisionSurfaceStatusBar } from "@/components/reports/report-decision-surface-status-bar";
import {
  reportDecisionSurfaceId,
  REPORT_DECISION_SURFACE_FOCUS_EVENT,
  ReportDecisionSurfaceKey,
} from "@/lib/report-failure-focus";
import { ReportDecisionSurfaceStatusItem } from "@/lib/types";

type Props = {
  surfaceKey: ReportDecisionSurfaceKey;
  entityType?: "account" | "campaign";
  entityId?: string;
  statusItem?: ReportDecisionSurfaceStatusItem | null;
  onStatusChanged?: () => Promise<void> | void;
  children: React.ReactNode;
};

const HIGHLIGHT_DURATION_MS = 2400;

export function ReportDecisionSurfaceSection({
  surfaceKey,
  entityType,
  entityId,
  statusItem,
  onStatusChanged,
  children,
}: Props) {
  const [isHighlighted, setIsHighlighted] = useState(false);
  const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const surfaceId = reportDecisionSurfaceId(surfaceKey);

  useEffect(() => {
    const activate = () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }

      setIsHighlighted(true);
      timeoutRef.current = setTimeout(() => {
        setIsHighlighted(false);
      }, HIGHLIGHT_DURATION_MS);
    };

    const checkInitialHash = () => {
      if (window.location.hash === `#${surfaceId}`) {
        activate();
      }
    };

    const onHashChange = () => {
      checkInitialHash();
    };

    const onSurfaceFocus = (event: Event) => {
      const detail = (event as CustomEvent<{ surfaceId?: string }>).detail;

      if (detail?.surfaceId === surfaceId) {
        activate();
      }
    };

    checkInitialHash();
    window.addEventListener("hashchange", onHashChange);
    window.addEventListener(REPORT_DECISION_SURFACE_FOCUS_EVENT, onSurfaceFocus as EventListener);

    return () => {
      window.removeEventListener("hashchange", onHashChange);
      window.removeEventListener(REPORT_DECISION_SURFACE_FOCUS_EVENT, onSurfaceFocus as EventListener);

      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
      }
    };
  }, [surfaceId]);

  return (
    <div
      id={surfaceId}
      className={`scroll-mt-24 transition-all duration-500 ${
        isHighlighted
          ? "rounded-2xl bg-[var(--accent)]/8 ring-2 ring-[var(--accent)]/60 ring-offset-4 ring-offset-transparent"
          : ""
      }`}
    >
      {isHighlighted ? (
        <div className="mb-2 flex justify-end">
          <Badge label="Odaklandi" variant="warning" />
        </div>
      ) : null}
      {entityType && entityId && statusItem ? (
        <ReportDecisionSurfaceStatusBar
          entityType={entityType}
          entityId={entityId}
          surfaceKey={surfaceKey}
          statusItem={statusItem}
          onChanged={onStatusChanged}
        />
      ) : null}
      {children}
    </div>
  );
}
