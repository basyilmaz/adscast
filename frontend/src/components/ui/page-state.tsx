"use client";

import { Card, CardTitle } from "./card";

export function PageLoadingState({ title, detail }: { title: string; detail?: string }) {
  return (
    <Card className="animate-pulse">
      <CardTitle>{title}</CardTitle>
      <p className="mt-2 text-sm muted-text">{detail ?? "Icerik yukleniyor."}</p>
    </Card>
  );
}

export function PageErrorState({ title, detail }: { title: string; detail: string }) {
  return (
    <Card>
      <CardTitle>{title}</CardTitle>
      <p className="mt-2 text-sm text-[var(--danger)]">{detail}</p>
    </Card>
  );
}

export function PageEmptyState({ title, detail }: { title: string; detail: string }) {
  return (
    <Card>
      <CardTitle>{title}</CardTitle>
      <p className="mt-2 text-sm muted-text">{detail}</p>
    </Card>
  );
}
