"use client";

import { useEffect } from "react";
import { useRouter } from "next/navigation";

export default function HomePage() {
  const router = useRouter();

  useEffect(() => {
    void router.prefetch("/login");
    router.replace("/login");
  }, [router]);

  return <main className="p-6 text-sm muted-text">Giris ekranina yonlendiriliyorsunuz.</main>;
}
