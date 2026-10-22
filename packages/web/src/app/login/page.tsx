'use client';

import { FormEvent, useEffect, useState } from 'react';
import { Bot, CheckCircle2, Chrome, ShieldAlert } from 'lucide-react';

const API_BASE = process.env.NEXT_PUBLIC_API_URL || '/api/backend';

export default function LoginPage() {
  const [googleEnabled, setGoogleEnabled] = useState<boolean | null>(null);
  const [localLoginEnabled, setLocalLoginEnabled] = useState(false);
  const [error, setError] = useState('');
  const [loginError, setLoginError] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const statusText = error === 'account_not_allowed'
    ? 'Tài khoản Google này chưa được cấp quyền truy cập.'
    : error
      ? 'Đăng nhập Google chưa hoàn tất. Vui lòng thử lại.'
      : '';

  useEffect(() => {
    setError(new URLSearchParams(window.location.search).get('error') || '');
    Promise.all([
      fetch(`${API_BASE}/auth/google/config`, { cache: 'no-store' }).then((response) => response.json()),
      fetch(`${API_BASE}/auth/local/config`, { cache: 'no-store' }).then((response) => response.json()),
    ]).then(([google, local]: [{ enabled?: boolean }, { enabled?: boolean }]) => {
      setGoogleEnabled(Boolean(google.enabled));
      setLocalLoginEnabled(Boolean(local.enabled));
    }).catch(() => {
      setGoogleEnabled(false);
      setLocalLoginEnabled(false);
    });
  }, []);

  const submitLocalLogin = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setSubmitting(true);
    setLoginError('');
    try {
      const response = await fetch(`${API_BASE}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ username: email, password }),
      });
      if (!response.ok) {
        setLoginError('Email hoặc mật khẩu không đúng.');
        return;
      }
      window.location.assign('/chat');
    } catch {
      setLoginError('Không thể kết nối máy chủ đăng nhập.');
    } finally {
      setSubmitting(false);
    }
  };

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

        {localLoginEnabled && (
          <form onSubmit={submitLocalLogin} style={{ display: 'grid', gap: 12, textAlign: 'left', marginBottom: 22 }}>
            <label style={{ display: 'grid', gap: 6, color: 'var(--text-muted)', fontSize: 13 }}>
              Email
              <input autoComplete="username" type="email" required value={email} onChange={(event) => setEmail(event.target.value)} placeholder="name@company.com" style={loginInputStyle} />
            </label>
            <label style={{ display: 'grid', gap: 6, color: 'var(--text-muted)', fontSize: 13 }}>
              Mật khẩu
              <input autoComplete="current-password" type="password" required value={password} onChange={(event) => setPassword(event.target.value)} style={loginInputStyle} />
            </label>
            {loginError && <p role="alert" style={{ color: 'var(--danger)', fontSize: 13, margin: 0 }}>{loginError}</p>}
            <button className="btn-primary" type="submit" disabled={submitting} style={{ padding: 12 }}>
              {submitting ? 'Đang đăng nhập…' : 'Đăng nhập'}
            </button>
          </form>
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

const loginInputStyle = {
  width: '100%',
  padding: '11px 12px',
  borderRadius: 8,
  border: '1px solid var(--border-subtle)',
  background: 'rgba(8, 11, 18, 0.7)',
  color: 'var(--text-primary)',
  outline: 'none',
};
