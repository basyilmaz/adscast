"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { Input } from "@/components/ui/input";
import { Button } from "@/components/ui/button";
import { apiRequest } from "@/lib/api";
import { QUERY_TTLS } from "@/lib/api-query-config";
import { prefetchApiResponse } from "@/lib/api-cache";
import { setToken, setWorkspaceId } from "@/lib/session";
import { Zap, Mail, Lock, AlertCircle, ArrowRight, BarChart3, Bell, Lightbulb } from "lucide-react";

type LoginResponse = {
  token: string;
  workspaces: Array<{ id: string }>;
};

const features = [
  { icon: BarChart3, text: "Canlı kampanya metrikleri ve anomali tespiti" },
  { icon: Bell, text: "Deterministic kural tabanlı uyarı sistemi" },
  { icon: Lightbulb, text: "AI destekli optimizasyon önerileri" },
];

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

      void prefetchApiResponse("/workspaces", {}, { ttlMs: QUERY_TTLS.workspaces });
      void prefetchApiResponse(
        "/dashboard/overview",
        { requireWorkspace: true },
        { ttlMs: QUERY_TTLS.dashboard },
      );

      router.replace("/dashboard");
    } catch (err) {
      const message = err instanceof Error ? err.message : "Giriş başarısız.";
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <main className="flex min-h-[calc(100vh-3.5rem)] w-full items-center justify-center px-4">
      <div className="w-full max-w-4xl overflow-hidden rounded-2xl border border-[var(--border)] bg-[var(--surface)] shadow-2xl md:grid md:grid-cols-2">
        {/* Left panel */}
        <section className="relative flex flex-col justify-between overflow-hidden bg-gradient-to-br from-[var(--accent-2)] to-[#1a4a3c] p-8 text-white">
          <div className="absolute inset-0 opacity-10"
            style={{
              backgroundImage: "radial-gradient(circle at 20% 80%, white 1px, transparent 1px), radial-gradient(circle at 80% 20%, white 1px, transparent 1px)",
              backgroundSize: "40px 40px",
            }}
          />
          <div className="relative">
            <div className="mb-6 flex items-center gap-2.5">
              <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                <Zap className="h-5 w-5 text-white" />
              </div>
              <span className="text-lg font-extrabold tracking-tight">AdsCast</span>
            </div>
            <p className="text-xs font-semibold uppercase tracking-widest text-white/60">
              Meta Ads Operasyon Sistemi
            </p>
            <h1 className="mt-3 text-3xl font-extrabold leading-tight">
              Ajanslar için
              <br />
              güçlü reklam
              <br />
              yönetimi.
            </h1>
            <p className="mt-4 text-sm leading-relaxed text-white/70">
              Çoklu workspace, deterministik alertler, AI destekli öneriler ve approval-gated publish akışı.
            </p>
          </div>

          <div className="relative mt-8 space-y-3">
            {features.map(({ icon: Icon, text }) => (
              <div key={text} className="flex items-center gap-3">
                <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/15">
                  <Icon className="h-3.5 w-3.5 text-white" />
                </div>
                <p className="text-sm text-white/80">{text}</p>
              </div>
            ))}
          </div>
        </section>

        {/* Right panel */}
        <section className="flex flex-col justify-center p-8">
          <div className="mb-8">
            <h2 className="text-2xl font-extrabold text-[var(--foreground)]">Giriş Yap</h2>
            <p className="mt-1 text-sm text-[var(--muted)]">Hesabınızla devam edin.</p>
          </div>

          <form onSubmit={onSubmit} className="space-y-5">
            <div>
              <label className="mb-1.5 block text-sm font-semibold text-[var(--foreground)]">
                E-posta
              </label>
              <div className="relative">
                <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
                <Input
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  type="email"
                  required
                  placeholder="ornek@ajans.com"
                  className="pl-9"
                />
              </div>
            </div>

            <div>
              <label className="mb-1.5 block text-sm font-semibold text-[var(--foreground)]">
                Şifre
              </label>
              <div className="relative">
                <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
                <Input
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  type="password"
                  required
                  placeholder="••••••••"
                  className="pl-9"
                />
              </div>
            </div>

            {error ? (
              <div className="flex items-center gap-2 rounded-lg bg-[var(--danger)]/8 px-3 py-2.5 text-sm text-[var(--danger)]">
                <AlertCircle className="h-4 w-4 shrink-0" />
                {error}
              </div>
            ) : null}

            <Button type="submit" className="group w-full" disabled={loading}>
              {loading ? (
                "Giriş yapılıyor..."
              ) : (
                <span className="flex items-center justify-center gap-2">
                  Panele Gir
                  <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-0.5" />
                </span>
              )}
            </Button>
          </form>

          <p className="mt-6 text-center text-xs text-[var(--muted)]">
            AdsCast · Multi-tenant Meta Ads OS
          </p>
        </section>
      </div>
    </main>
  );
}
