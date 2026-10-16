'use client';

import React, { useState } from 'react';
import { MapPin, ChevronDown, ChevronUp, Code2, Layers, CheckCircle2 } from 'lucide-react';

interface PageItem {
  path: string;
  title: string;
  description?: string;
  status?: string;
}

interface StructuredDataCardProps {
  rawJson: string;
  projectId?: string;
}

export default function StructuredDataCard({ rawJson }: StructuredDataCardProps) {
  const [showRaw, setShowRaw] = useState(false);
  const [isExpanded, setIsExpanded] = useState(true);

  let data: any = null;
  try {
    data = JSON.parse(rawJson);
  } catch {
    return <pre className="code-block" style={{ fontSize: '12px' }}>{rawJson}</pre>;
  }

  // Check if it's a sitemap format
  const pages: PageItem[] = data.pages || data.sitemap || (Array.isArray(data) ? data : null);

  if (pages && Array.isArray(pages) && pages.length > 0 && pages[0].path) {
    return (
      <div
        style={{
          margin: '14px 0',
          borderRadius: '12px',
          border: '1px solid rgba(99, 102, 241, 0.3)',
          background: 'linear-gradient(180deg, rgba(99, 102, 241, 0.08) 0%, rgba(15, 23, 42, 0.6) 100%)',
          overflow: 'hidden',
          backdropFilter: 'blur(8px)',
        }}
      >
        {/* Header */}
        <div
          style={{
            display: 'flex',
            justifyContent: 'space-between',
            alignItems: 'center',
            padding: '12px 16px',
            borderBottom: '1px solid rgba(255, 255, 255, 0.08)',
            background: 'rgba(255, 255, 255, 0.02)',
          }}
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
            <div
              style={{
                width: '28px',
                height: '28px',
                borderRadius: '6px',
                background: 'rgba(99, 102, 241, 0.2)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                color: '#818CF8',
              }}
            >
              <Layers size={16} />
            </div>
            <div>
              <div style={{ fontWeight: 700, fontSize: '13px', color: '#ffffff' }}>
                🗺️ Cấu trúc Trang & Sitemap Đề xuất
              </div>
              <div style={{ fontSize: '11px', color: 'var(--text-dim)' }}>
                Tổng cộng {pages.length} trang được định hình
              </div>
            </div>
          </div>

          <div style={{ display: 'flex', alignItems: 'center', gap: '6px' }}>
            <button
              type="button"
              onClick={() => setShowRaw(!showRaw)}
              style={{
                padding: '4px 8px',
                fontSize: '11px',
                borderRadius: '6px',
                background: 'rgba(255, 255, 255, 0.06)',
                border: '1px solid var(--border-subtle)',
                color: 'var(--text-secondary)',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '4px',
              }}
            >
              <Code2 size={12} /> {showRaw ? 'Ẩn JSON' : 'Xem JSON'}
            </button>
            <button
              type="button"
              onClick={() => setIsExpanded(!isExpanded)}
              style={{
                background: 'transparent',
                border: 'none',
                color: 'var(--text-dim)',
                cursor: 'pointer',
                padding: '4px',
              }}
            >
              {isExpanded ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
            </button>
          </div>
        </div>

        {/* Content */}
        {isExpanded && (
          <div style={{ padding: '12px 16px' }}>
            {showRaw ? (
              <pre
                style={{
                  fontSize: '12px',
                  background: 'rgba(0, 0, 0, 0.4)',
                  padding: '12px',
                  borderRadius: '8px',
                  overflowX: 'auto',
                  color: '#94A3B8',
                }}
              >
                {JSON.stringify(data, null, 2)}
              </pre>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '10px' }}>
                {pages.map((p, idx) => (
                  <div
                    key={p.path || idx}
                    style={{
                      padding: '10px 12px',
                      borderRadius: '8px',
                      background: 'rgba(255, 255, 255, 0.03)',
                      border: '1px solid rgba(255, 255, 255, 0.06)',
                      display: 'flex',
                      flexDirection: 'column',
                      gap: '4px',
                    }}
                  >
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      <span style={{ fontWeight: 700, fontSize: '13px', color: '#E2E8F0' }}>
                        {p.title}
                      </span>
                      <code
                        style={{
                          fontSize: '10px',
                          color: '#818CF8',
                          background: 'rgba(99, 102, 241, 0.15)',
                          padding: '2px 6px',
                          borderRadius: '4px',
                        }}
                      >
                        {p.path}
                      </code>
                    </div>
                    {p.description && (
                      <p style={{ fontSize: '12px', color: 'var(--text-dim)', margin: 0, lineHeight: '1.4' }}>
                        {p.description}
                      </p>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    );
  }

  // Fallback for generic JSON
  return (
    <pre
      style={{
        margin: '10px 0',
        padding: '12px',
        borderRadius: '8px',
        background: 'rgba(0, 0, 0, 0.4)',
        fontSize: '12px',
        color: '#CBD5E1',
        overflowX: 'auto',
      }}
    >
      {JSON.stringify(data, null, 2)}
    </pre>
  );
}
