import type { Metadata } from "next";
import { AppFooter } from "@/components/layout/app-footer";
import "./globals.css";

export const metadata: Metadata = {
  title: "AdsCast",
  description: "Multi-tenant Meta Ads operating system",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="tr">
      <body className="antialiased">
        <div className="app-background flex min-h-screen flex-col">
          <div className="flex-1">{children}</div>
          <AppFooter />
        </div>
      </body>
    </html>
  );
}
