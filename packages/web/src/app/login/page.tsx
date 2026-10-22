'use client';

import { useEffect, useState } from 'react';
import { Bot, CheckCircle2, Chrome, ShieldAlert } from 'lucide-react';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

export default function LoginPage() {
  const [googleEnabled, setGoogleEnabled] = useState<boolean | null>(null);
  const [error, setError] = useState('');
  const statusText = error === 'account_not_allowed'
    ? 'Tài khoản Google này chưa được cấp quyền truy cập.'
    : error
      ? 'Đăng nhập Google chưa hoàn tất. Vui lòng thử lại.'
      : '';

  useEffect(() => {
    setError(new URLSearchParams(window.location.search).get('error') || '');
    fetch(`${API_BASE}/auth/google/config`, { cache: 'no-store' })
      .then((response) => response.json())
      .then((result: { enabled?: boolean }) => setGoogleEnabled(Boolean(result.enabled)))
      .catch(() => setGoogleEnabled(false));
  }, []);

  return (
    <main style={{ minHeight: 'calc(100vh - 68px)', display: 'grid', placeItems: 'center', padding: 24 }}>
      <section className="glass-panel" style={{ width: 'min(100%, 440px)', padding: 36, textAlign: 'center' }}>
        <div style={{ margin: '0 auto 18px', width: 54, height: 54, borderRadius: 16, display: 'grid', placeItems: 'center', background: 'var(--accent-gradient)' }}>
          <Bot size={28} color="white" />
        </div>
        <h1 style={{ fontSize: 26, marginBottom: 8 }}>Đăng nhập Align AI Hub</h1>
        <p style={{ color: 'var(--text-muted)', lineHeight: 1.6, marginBottom: 24 }}>
          Sử dụng tài khoản Google đã được cấp quyền để tiếp tục.
        </p>

        {statusText && (
          <p role="alert" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8, color: 'var(--danger)', marginBottom: 18 }}>
            <ShieldAlert size={17} />{statusText}
          </p>
        )}

        {googleEnabled ? (
          <a href={`${API_BASE}/auth/google/login`} className="btn-primary" style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: 10, width: '100%', padding: 13, textDecoration: 'none' }}>
            <Chrome size={18} /> Tiếp tục với Google
          </a>
        ) : (
          <div role="status" style={{ padding: 14, borderRadius: 10, background: 'rgba(245, 158, 11, 0.1)', color: 'var(--text-muted)', display: 'flex', gap: 10, textAlign: 'left', lineHeight: 1.5 }}>
            <CheckCircle2 size={18} color="#f59e0b" style={{ flexShrink: 0, marginTop: 2 }} />
            Google OAuth chưa được cấu hình trên máy chủ.
          </div>
        )}
      </section>
    </main>
  );
}
