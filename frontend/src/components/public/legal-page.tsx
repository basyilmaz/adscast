import Link from "next/link";

type LegalSection = {
  title: string;
  paragraphs: string[];
};

export function LegalPage({
  eyebrow,
  title,
  summary,
  sections,
}: {
  eyebrow: string;
  title: string;
  summary: string;
  sections: LegalSection[];
}) {
  return (
    <main className="mx-auto min-h-screen max-w-5xl px-4 py-10">
      <div className="rounded-[28px] border border-[var(--border)] bg-[linear-gradient(135deg,#f7f2e7_0%,#eef4e8_55%,#ffffff_100%)] p-6 shadow-[0_24px_80px_rgba(35,42,32,0.08)] md:p-10">
        <div className="max-w-3xl">
          <p className="text-xs font-bold uppercase tracking-[0.24em] text-[#6d7765]">{eyebrow}</p>
          <h1 className="mt-3 text-3xl font-black tracking-tight text-[#1e241d] md:text-5xl">{title}</h1>
          <p className="mt-4 text-base leading-7 text-[#455042] md:text-lg">{summary}</p>
        </div>

        <div className="mt-8 grid gap-4">
          {sections.map((section) => (
            <section
              key={section.title}
              className="rounded-2xl border border-[var(--border)] bg-white/90 p-5"
            >
              <h2 className="text-lg font-bold text-[#1e241d]">{section.title}</h2>
              <div className="mt-3 grid gap-3">
                {section.paragraphs.map((paragraph) => (
                  <p key={paragraph} className="text-sm leading-7 text-[#455042] md:text-base">
                    {paragraph}
                  </p>
                ))}
              </div>
            </section>
          ))}
        </div>

        <div className="mt-8 flex flex-wrap gap-3 text-sm font-semibold">
          <Link className="rounded-full border border-[var(--border)] bg-white px-4 py-2" href="/">
            Anasayfa
          </Link>
          <Link className="rounded-full border border-[var(--border)] bg-white px-4 py-2" href="/login">
            Giris
          </Link>
          <Link className="rounded-full border border-[var(--border)] bg-white px-4 py-2" href="/privacy">
            Gizlilik
          </Link>
          <Link className="rounded-full border border-[var(--border)] bg-white px-4 py-2" href="/terms">
            Hizmet Sartlari
          </Link>
          <Link className="rounded-full border border-[var(--border)] bg-white px-4 py-2" href="/data-deletion">
            Veri Silme
          </Link>
        </div>
      </div>
    </main>
  );
}
