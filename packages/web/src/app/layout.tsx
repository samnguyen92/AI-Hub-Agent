import type { Metadata } from 'next';
import './globals.css';

import { LanguageProvider } from '@/context/LanguageContext';
import Navbar from '@/components/layout/Navbar';

export const metadata: Metadata = {
  title: 'Align AI Hub | Multi-Agent Platform',
  description: 'Internal multi-agent orchestration hub for design, engineering, SEO and site analytics.',
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="vi">
      <body>
        <LanguageProvider>
          <Navbar />
          <main>{children}</main>
        </LanguageProvider>
      </body>
    </html>
  );
}
