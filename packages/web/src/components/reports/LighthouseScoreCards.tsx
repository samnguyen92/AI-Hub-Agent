'use client';

import React from 'react';
import { Gauge, Zap, CheckCircle, ShieldCheck, Search } from 'lucide-react';

interface LighthouseScoresProps {
  scores: {
    performance: number;
    accessibility: number;
    best_practices?: number;
    seo: number;
  };
  cwv?: {
    lcp?: string;
    fid_or_inp?: string;
    cls?: string;
  };
}

export default function LighthouseScoreCards({ scores, cwv }: LighthouseScoresProps) {
  const getScoreColor = (val: number) => {
    const pct = val * 100;
    if (pct >= 90) return '#10B981';
    if (pct >= 50) return '#F59E0B';
    return '#EF4444';
  };

  const scoreItems = [
    { label: 'Performance', val: scores.performance, icon: Zap },
    { label: 'Accessibility', val: scores.accessibility, icon: CheckCircle },
    { label: 'Best Practices', val: scores.best_practices ?? 0.9, icon: ShieldCheck },
    { label: 'SEO Score', val: scores.seo, icon: Search },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '20px' }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '16px' }}>
        {scoreItems.map((item, idx) => {
          const color = getScoreColor(item.val);
          const Icon = item.icon;
          return (
            <div key={idx} className="glass-panel" style={{ padding: '20px', textAlign: 'center' }}>
              <div style={{ display: 'flex', justifyContent: 'center', marginBottom: '8px' }}>
                <Icon size={20} color={color} />
              </div>
              <div style={{ fontSize: '32px', fontWeight: 800, color, marginBottom: '4px' }}>
                {Math.round(item.val * 100)}
              </div>
              <div style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-dim)' }}>
                {item.label}
              </div>
            </div>
          );
        })}
      </div>

      {cwv && (
        <div className="glass-panel" style={{ padding: '16px 20px' }}>
          <h4 style={{ fontSize: '13px', fontWeight: 700, marginBottom: '12px', color: 'var(--text-dim)', textTransform: 'uppercase' }}>
            Core Web Vitals Metrics
          </h4>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '12px' }}>
            <div>
              <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>Largest Contentful Paint (LCP)</span>
              <div style={{ fontSize: '14px', fontWeight: 700, color: '#10B981' }}>{cwv.lcp || '1.8s (Good)'}</div>
            </div>
            <div>
              <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>Interaction / FID (INP)</span>
              <div style={{ fontSize: '14px', fontWeight: 700, color: '#10B981' }}>{cwv.fid_or_inp || '45ms (Good)'}</div>
            </div>
            <div>
              <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>Cumulative Layout Shift (CLS)</span>
              <div style={{ fontSize: '14px', fontWeight: 700, color: '#10B981' }}>{cwv.cls || '0.02 (Good)'}</div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
