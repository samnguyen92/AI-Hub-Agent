'use client';

import React, { useEffect, useState } from 'react';
import {
  Activity,
  Bot,
  CheckCircle2,
  Cpu,
  Database,
  ExternalLink,
  Layers,
  Palette,
  RefreshCw,
  Search,
  Server,
  Sparkles,
} from 'lucide-react';
import { fetchDbHealth, fetchHealth, HealthResponse } from '@/lib/api';

export default function HomePage() {
  const [apiHealth, setApiHealth] = useState<HealthResponse | null>(null);
  const [dbHealth, setDbHealth] = useState<HealthResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [lastChecked, setLastChecked] = useState<string>('');

  const checkStatus = async () => {
    setLoading(true);
    const [api, db] = await Promise.all([fetchHealth(), fetchDbHealth()]);
    setApiHealth(api);
    setDbHealth(db);
    setLastChecked(new Date().toLocaleTimeString());
    setLoading(false);
  };

  useEffect(() => {
    checkStatus();
  }, []);

  const agents = [
    {
      key: 'design',
      name: 'Design Agent',
      desc: 'Phân tích brief, xuất design tokens, sinh mockup HTML/CSS & render PNG.',
      icon: Palette,
      badge: 'Active',
      color: '#ec4899',
    },
    {
      key: 'code-builder',
      name: 'Code Builder',
      desc: 'Biến mockup thành HTML/CSS/JS thuần theo chuẩn align.vn, kiểm thử lint.',
      icon: Layers,
      badge: 'Active',
      color: '#6366f1',
    },
    {
      key: 'site-analyzer',
      name: 'Site Analyzer',
      desc: 'Crawl cấu trúc trang, đo Lighthouse Core Web Vitals và chụp ảnh viewport.',
      icon: Cpu,
      badge: 'Active',
      color: '#3b82f6',
    },
    {
      key: 'seo-auditor',
      name: 'SEO Auditor',
      desc: 'Đối chiếu GSC/GA4, rà soát noindex, canonical và xếp ưu tiên tối ưu.',
      icon: Search,
      badge: 'Active',
      color: '#10b981',
    },
  ];

  return (
    <div style={{ minHeight: '100vh', padding: '32px 24px', maxWidth: '1200px', margin: '0 auto' }}>
      {/* Top Header */}
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '40px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '14px' }}>
          <div
            style={{
              width: '44px',
              height: '44px',
              borderRadius: '12px',
              background: 'var(--accent-gradient)',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              boxShadow: 'var(--accent-glow)',
            }}
          >
            <Bot size={24} color="#ffffff" />
          </div>
          <div>
            <h1 style={{ fontSize: '24px', fontWeight: 800, letterSpacing: '-0.02em' }}>
              Align AI Hub
            </h1>
            <p style={{ fontSize: '13px', color: 'var(--text-muted)' }}>
              Hệ thống điều phối Multi-Agent nội bộ
            </p>
          </div>
        </div>

        <button
          id="btn-refresh-status"
          className="btn-primary"
          onClick={checkStatus}
          disabled={loading}
        >
          <RefreshCw size={15} className={loading ? 'pulse' : ''} />
          {loading ? 'Đang kiểm tra...' : 'Làm mới trạng thái'}
        </button>
      </header>

      {/* System Status Section */}
      <section style={{ marginBottom: '48px' }}>
        <h2 style={{ fontSize: '15px', fontWeight: 600, color: 'var(--text-dim)', marginBottom: '16px', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
          Trạng thái nền tảng (Foundation Status)
        </h2>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '20px' }}>
          {/* API Status Card */}
          <div className="glass-panel" style={{ padding: '24px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                <Server size={20} color="var(--accent-primary)" />
                <span style={{ fontWeight: 600, fontSize: '15px' }}>FastAPI Backend</span>
              </div>
              <span
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '6px',
                  fontSize: '12px',
                  fontWeight: 600,
                  padding: '4px 10px',
                  borderRadius: '20px',
                  background: apiHealth?.status === 'ok' ? 'var(--success-bg)' : 'rgba(239, 68, 68, 0.15)',
                  color: apiHealth?.status === 'ok' ? 'var(--success)' : 'var(--danger)',
                }}
              >
                <span style={{ width: '6px', height: '6px', borderRadius: '50%', backgroundColor: 'currentColor' }} />
                {apiHealth?.status === 'ok' ? 'Online' : 'Offline'}
              </span>
            </div>
            <p style={{ fontSize: '13px', color: 'var(--text-muted)', marginBottom: '12px' }}>
              Dịch vụ: <code>{apiHealth?.service || 'Đang kết nối...'}</code>
            </p>
            <span style={{ fontSize: '11px', color: 'var(--text-dim)' }}>
              Cổng: :8000 &bull; Cập nhật: {lastChecked || 'Chưa kiểm tra'}
            </span>
          </div>

          {/* Database Status Card */}
          <div className="glass-panel" style={{ padding: '24px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                <Database size={20} color="#38bdf8" />
                <span style={{ fontWeight: 600, fontSize: '15px' }}>PostgreSQL Database</span>
              </div>
              <span
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '6px',
                  fontSize: '12px',
                  fontWeight: 600,
                  padding: '4px 10px',
                  borderRadius: '20px',
                  background: dbHealth?.status === 'ok' ? 'var(--success-bg)' : 'rgba(239, 68, 68, 0.15)',
                  color: dbHealth?.status === 'ok' ? 'var(--success)' : 'var(--danger)',
                }}
              >
                <span style={{ width: '6px', height: '6px', borderRadius: '50%', backgroundColor: 'currentColor' }} />
                {dbHealth?.status === 'ok' ? 'Connected' : 'Disconnected'}
              </span>
            </div>
            <p style={{ fontSize: '13px', color: 'var(--text-muted)', marginBottom: '12px' }}>
              Kết nối: <code>{dbHealth?.database || 'Đang kiểm tra...'}</code>
            </p>
            <span style={{ fontSize: '11px', color: 'var(--text-dim)' }}>
              Cổng: :5432 &bull; 13 bảng schema sẵn sàng
            </span>
          </div>

          {/* Web Frontend Card */}
          <div className="glass-panel" style={{ padding: '24px' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '16px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                <Activity size={20} color="#a855f7" />
                <span style={{ fontWeight: 600, fontSize: '15px' }}>Next.js Frontend</span>
              </div>
              <span
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '6px',
                  fontSize: '12px',
                  fontWeight: 600,
                  padding: '4px 10px',
                  borderRadius: '20px',
                  background: 'var(--success-bg)',
                  color: 'var(--success)',
                }}
              >
                <span style={{ width: '6px', height: '6px', borderRadius: '50%', backgroundColor: 'currentColor' }} />
                Ready
              </span>
            </div>
            <p style={{ fontSize: '13px', color: 'var(--text-muted)', marginBottom: '12px' }}>
              Cổng: <code>:3000</code> &bull; Chế độ: <code>Development</code>
            </p>
            <span style={{ fontSize: '11px', color: 'var(--text-dim)' }}>
              Giai đoạn 1 hoàn thành &bull; Sẵn sàng cho Giai đoạn 2
            </span>
          </div>
        </div>
      </section>

      {/* Agents Overview Section */}
      <section>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginBottom: '16px' }}>
          <h2 style={{ fontSize: '15px', fontWeight: 600, color: 'var(--text-dim)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
            Danh sách Agents trong Hub
          </h2>
          <span style={{ fontSize: '12px', color: 'var(--accent-primary)', fontWeight: 500 }}>
            Độc lập &bull; Tương tác qua Thư viện chung
          </span>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(260px, 1fr))', gap: '20px' }}>
          {agents.map((ag) => {
            const Icon = ag.icon;
            return (
              <div key={ag.key} className="glass-panel" style={{ padding: '24px' }}>
                <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: '16px' }}>
                  <div
                    style={{
                      width: '40px',
                      height: '40px',
                      borderRadius: '10px',
                      backgroundColor: `${ag.color}20`,
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}
                  >
                    <Icon size={20} color={ag.color} />
                  </div>
                  <span
                    style={{
                      fontSize: '11px',
                      fontWeight: 600,
                      padding: '3px 8px',
                      borderRadius: '6px',
                      background: 'rgba(255, 255, 255, 0.06)',
                      color: 'var(--text-muted)',
                    }}
                  >
                    @{ag.key}
                  </span>
                </div>
                <h3 style={{ fontSize: '16px', fontWeight: 700, marginBottom: '8px' }}>{ag.name}</h3>
                <p style={{ fontSize: '13px', color: 'var(--text-muted)', lineHeight: '1.5' }}>{ag.desc}</p>
              </div>
            );
          })}
        </div>
      </section>
    </div>
  );
}
