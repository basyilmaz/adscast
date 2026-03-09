import type { Metadata } from "next";
import { IBM_Plex_Mono, Manrope } from "next/font/google";
import { AppFooter } from "@/components/layout/app-footer";
import "./globals.css";

const manrope = Manrope({
  variable: "--font-manrope",
  subsets: ["latin"],
});

const ibmPlexMono = IBM_Plex_Mono({
  variable: "--font-ibm-plex-mono",
  weight: ["400", "500"],
  subsets: ["latin"],
});

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
      <body className={`${manrope.variable} ${ibmPlexMono.variable} antialiased`}>
        <div className="app-background flex min-h-screen flex-col">
          <div className="flex-1">{children}</div>
          <AppFooter />
        </div>
      </body>
    </html>
  );
}
