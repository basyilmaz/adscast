"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { setToken, setWorkspaceId } from "@/lib/session";

type LoginResponse = {
  token: string;
  workspaces: Array<{ id: string }>;
};

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const onSubmit = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const response = await apiRequest<LoginResponse>("/auth/login", {
        method: "POST",
        body: {
          email,
          password,
          device_name: "web-panel",
        },
      });

      setToken(response.token);
      if (response.workspaces?.[0]?.id) {
        setWorkspaceId(response.workspaces[0].id);
      }

      router.push("/dashboard");
    } catch (err) {
      const message = err instanceof Error ? err.message : "Giris basarisiz.";
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="mx-auto flex min-h-[calc(100vh-3.5rem)] w-full max-w-5xl items-center justify-center px-6">
      <div className="surface-card grid w-full max-w-4xl overflow-hidden md:grid-cols-2">
        <section className="bg-[var(--accent-2)]/10 p-8">
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--accent-2)]">
            ADSCAST
          </p>
          <h1 className="mt-4 text-3xl font-extrabold leading-tight">
            Ajanslar icin
            <br />
            Meta Ads Operasyon Sistemi
          </h1>
          <p className="mt-4 text-sm muted-text">
            Coklu workspace, deterministic alertler, AI destekli oneriler ve approval-gated publish akisi.
          </p>
        </section>

        <section className="p-8">
          <h2 className="text-xl font-bold">Giris Yap</h2>
          <p className="mt-1 text-sm muted-text">Hesabinizla devam edin.</p>

          <form onSubmit={onSubmit} className="mt-6 space-y-4">
            <div>
              <label className="mb-1 block text-sm font-semibold">E-posta</label>
              <Input value={email} onChange={(e) => setEmail(e.target.value)} type="email" required />
            </div>

            <div>
              <label className="mb-1 block text-sm font-semibold">Sifre</label>
              <Input
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                type="password"
                required
              />
            </div>

            {error ? <p className="text-sm text-[var(--danger)]">{error}</p> : null}

            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? "Giris yapiliyor..." : "Panele Gir"}
            </Button>
          </form>
        </section>
      </div>
    </main>
  );
}
