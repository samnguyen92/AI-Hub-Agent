'use client';

import React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Bot, FolderKanban, MessageSquare, Library, LayoutDashboard, BarChart3, ShieldCheck, Cpu, Globe } from 'lucide-react';
import { useLanguage } from '@/context/LanguageContext';

export default function Navbar() {
  const pathname = usePathname();
  const { language, toggleLanguage, t } = useLanguage();

  const navItems = [
    { key: 'nav.status', href: '/', icon: LayoutDashboard },
    { key: 'nav.projects', href: '/projects', icon: FolderKanban },
    { key: 'nav.chat', href: '/chat', icon: MessageSquare },
    { key: 'nav.agents', href: '/agents', icon: Cpu },
    { key: 'nav.library', href: '/library', icon: Library },
    { key: 'nav.reports', href: '/reports', icon: BarChart3 },
    { key: 'nav.approvals', href: '/approvals', icon: ShieldCheck },
  ];

  return (
    <nav
      style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: '16px 24px',
        borderBottom: '1px solid var(--border-subtle)',
        background: 'rgba(10, 13, 20, 0.8)',
        backdropFilter: 'blur(12px)',
        position: 'sticky',
        top: 0,
        zIndex: 100,
      }}
    >
      <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
        <Link href="/" style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          <div
            style={{
              width: '36px',
              height: '36px',
              borderRadius: '10px',
              background: 'var(--accent-gradient)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              boxShadow: 'var(--accent-glow)',
            }}
          >
            <Bot size={20} color="#ffffff" />
          </div>
          <span style={{ fontWeight: 800, fontSize: '18px', letterSpacing: '-0.02em' }}>
            Align AI Hub
          </span>
        </Link>
      </div>

      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
        {navItems.map((item) => {
          const Icon = item.icon;
          const isActive = pathname === item.href || (item.href !== '/' && pathname.startsWith(item.href));
          return (
            <Link
              key={item.href}
              href={item.href}
              id={`nav-${item.href.replace('/', '') || 'home'}`}
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                padding: '8px 14px',
                borderRadius: '8px',
                fontSize: '13px',
                fontWeight: 600,
                color: isActive ? '#ffffff' : 'var(--text-muted)',
                background: isActive ? 'rgba(99, 102, 241, 0.15)' : 'transparent',
                border: isActive ? '1px solid rgba(99, 102, 241, 0.4)' : '1px solid transparent',
                transition: 'all 0.15s ease',
              }}
            >
              <Icon size={16} color={isActive ? 'var(--accent-primary)' : 'currentColor'} />
              {t(item.key)}
            </Link>
          );
        })}

        <button
          type="button"
          onClick={toggleLanguage}
          id="language-switcher-btn"
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            padding: '7px 12px',
            borderRadius: '8px',
            fontSize: '12px',
            fontWeight: 700,
            background: 'rgba(255, 255, 255, 0.08)',
            border: '1px solid var(--border-subtle)',
            color: '#ffffff',
            cursor: 'pointer',
            marginLeft: '8px',
            transition: 'all 0.2s ease',
          }}
          title={language === 'vi' ? 'Chuyển sang English' : 'Switch to Vietnamese'}
        >
          <Globe size={14} color="var(--accent-primary)" />
          <span>{language === 'vi' ? '🇻🇳 VI' : '🇺🇸 EN'}</span>
        </button>
      </div>
    </nav>
  );
}
