'use client';

import React, { useState } from 'react';
import { AlertCircle, AlertTriangle, Info, CheckCircle2, ChevronDown, ChevronRight, Code } from 'lucide-react';

export interface AuditIssue {
  id: string;
  title: string;
  severity: 'critical' | 'high' | 'medium' | 'low' | 'info';
  affected_url?: string;
  evidence: string;
  recommendation: string;
  suggested_patch?: string;
}

interface AuditIssuesTableProps {
  issues: AuditIssue[];
}

export default function AuditIssuesTable({ issues }: AuditIssuesTableProps) {
  const [filterSeverity, setFilterSeverity] = useState<string>('all');
  const [expandedId, setExpandedId] = useState<string | null>(null);

  const severityBadge = (sev: string) => {
    switch (sev) {
      case 'critical':
        return <span style={{ padding: '2px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(239, 68, 68, 0.2)', color: '#EF4444' }}>CRITICAL</span>;
      case 'high':
        return <span style={{ padding: '2px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(245, 158, 11, 0.2)', color: '#F59E0B' }}>HIGH</span>;
      case 'medium':
        return <span style={{ padding: '2px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(59, 130, 246, 0.2)', color: '#3B82F6' }}>MEDIUM</span>;
      default:
        return <span style={{ padding: '2px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(156, 163, 175, 0.2)', color: '#9CA3AF' }}>LOW / INFO</span>;
    }
  };

  const filtered = filterSeverity === 'all' ? issues : issues.filter((i) => i.severity === filterSeverity);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
      <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
        <span style={{ fontSize: '12px', color: 'var(--text-muted)', fontWeight: 600 }}>Lọc mức độ:</span>
        {['all', 'critical', 'high', 'medium', 'low'].map((s) => (
          <button
            key={s}
            onClick={() => setFilterSeverity(s)}
            style={{
              padding: '4px 10px',
              borderRadius: '6px',
              fontSize: '11px',
              fontWeight: 600,
              background: filterSeverity === s ? 'var(--accent-primary)' : 'rgba(255, 255, 255, 0.05)',
              color: '#ffffff',
              border: 'none',
              cursor: 'pointer',
              textTransform: 'uppercase',
            }}
          >
            {s}
          </button>
        ))}
      </div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
        {filtered.length === 0 ? (
          <div className="glass-panel" style={{ padding: '24px', textAlign: 'center', color: 'var(--text-muted)' }}>
            Không có vấn đề nào ở mức độ này.
          </div>
        ) : (
          filtered.map((item) => (
            <div key={item.id} className="glass-panel" style={{ padding: '16px' }}>
              <div
                style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', cursor: 'pointer' }}
                onClick={() => setExpandedId(expandedId === item.id ? null : item.id)}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                  {expandedId === item.id ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
                  <span style={{ fontSize: '14px', fontWeight: 700 }}>{item.title}</span>
                </div>
                <div>{severityBadge(item.severity)}</div>
              </div>

              {expandedId === item.id && (
                <div style={{ marginTop: '14px', paddingTop: '12px', borderTop: '1px solid var(--border-subtle)', display: 'flex', flexDirection: 'column', gap: '8px' }}>
                  {item.affected_url && (
                    <div style={{ fontSize: '12px' }}>
                      <span style={{ color: 'var(--text-muted)' }}>URL: </span>
                      <code>{item.affected_url}</code>
                    </div>
                  )}
                  <div style={{ fontSize: '13px', color: 'var(--text-main)' }}>
                    <span style={{ fontWeight: 600, color: 'var(--warning)' }}>Bằng chứng (Evidence): </span>
                    {item.evidence}
                  </div>
                  <div style={{ fontSize: '13px', color: 'var(--text-main)' }}>
                    <span style={{ fontWeight: 600, color: 'var(--success)' }}>Khuyến nghị (Action): </span>
                    {item.recommendation}
                  </div>
                  {item.suggested_patch && (
                    <div style={{ marginTop: '6px' }}>
                      <span style={{ fontSize: '11px', color: 'var(--text-muted)', fontWeight: 600 }}>Suggested Patch:</span>
                      <pre style={{ background: '#000', padding: '10px', borderRadius: '6px', fontSize: '12px', overflowX: 'auto', marginTop: '4px' }}>
                        <code>{item.suggested_patch}</code>
                      </pre>
                    </div>
                  )}
                </div>
              )}
            </div>
          ))
        )}
      </div>
    </div>
  );
}
