'use client';

import React, { useState } from 'react';
import {
  ShieldCheck,
  CheckCircle2,
  XCircle,
  RotateCcw,
  ExternalLink,
  Globe,
  ChevronDown,
  ChevronUp,
} from 'lucide-react';
import { ApprovalItem } from '@/lib/api';
import { useLanguage } from '@/context/LanguageContext';
import ReferenceChoices from '@/components/ReferenceChoices';
import GateActions from '@/components/GateActions';

interface GateCardProps {
  gate: ApprovalItem;
  onRespond: (id: string, action: 'approve' | 'rework' | 'reject', comment?: string) => Promise<void>;
  isCompact?: boolean;
}

export default function GateCard({ gate, onRespond, isCompact = false }: GateCardProps) {
  const { t } = useLanguage();
  const [submitting, setSubmitting] = useState(false);
  const [showDetails, setShowDetails] = useState(!isCompact);
  const [selectedReferences, setSelectedReferences] = useState<string[]>([]);

  const payload = gate.payload || {};
  const gateType = (payload.gate_type || gate.action_type.replace('gate_', '')).toLowerCase();
  const isPending = gate.status === 'pending';
  const isReferenceGate = gateType === 'reference';
  const apiBase = process.env.NEXT_PUBLIC_API_URL || '/api/backend';

  const getGateBadge = () => {
    switch (gateType) {
      case 'intake':
        return { label: 'INTAKE GATE', bg: 'rgba(56, 189, 248, 0.15)', color: '#38BDF8', border: 'rgba(56, 189, 248, 0.4)' };
      case 'plan':
        return { label: 'PLAN GATE', bg: 'rgba(129, 140, 248, 0.15)', color: '#818CF8', border: 'rgba(129, 140, 248, 0.4)' };
      case 'result':
        return { label: 'RESULT GATE', bg: 'rgba(52, 211, 153, 0.15)', color: '#34D399', border: 'rgba(52, 211, 153, 0.4)' };
      default:
        return { label: 'APPROVAL GATE', bg: 'rgba(245, 158, 11, 0.15)', color: '#F59E0B', border: 'rgba(245, 158, 11, 0.4)' };
    }
  };

  const badge = getGateBadge();

  const handleAction = async (action: 'approve' | 'rework' | 'reject', comment?: string) => {
    setSubmitting(true);
    try {
      const decisionComment = action === 'approve' && isReferenceGate
        ? `reference:${selectedReferences.join(',')}` : comment;
      await onRespond(gate.id, action, decisionComment);
    } finally {
      setSubmitting(false);
    }
  };

  const resolveTryItUrl = (url: string): string => {
    if (url.startsWith('http://') || url.startsWith('https://')) return url;
    if (url.startsWith('/api/backend/')) return `${apiBase}${url.replace('/api/backend', '')}`;
    return `${apiBase}${url.startsWith('/') ? '' : '/'}${url}`;
  };

  return (
    <div
      style={{
        borderRadius: '12px',
        border: `1px solid ${isPending ? badge.border : 'var(--border-subtle)'}`,
        background: isPending
          ? 'linear-gradient(180deg, rgba(30, 41, 59, 0.7) 0%, rgba(15, 23, 42, 0.8) 100%)'
          : 'var(--bg-card)',
        padding: '18px 20px',
        marginBottom: '16px',
        boxShadow: isPending ? '0 10px 30px rgba(0, 0, 0, 0.3)' : 'none',
      }}
    >
      {/* Top Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '12px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flexWrap: 'wrap' }}>
          <span
            style={{
              padding: '3px 8px',
              borderRadius: '6px',
              fontSize: '11px',
              fontWeight: 800,
              letterSpacing: '0.05em',
              background: badge.bg,
              color: badge.color,
              border: `1px solid ${badge.border}`,
            }}
          >
            {badge.label}
          </span>
          <h3 style={{ fontSize: '15px', fontWeight: 700, color: '#FFFFFF', margin: 0 }}>
            {payload.title || 'Cổng kiểm định chất lượng'}
          </h3>
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          {gate.status === 'approved' && (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#10B981', fontSize: '12px', fontWeight: 700 }}>
              <CheckCircle2 size={14} /> {t('approvals.tab_approved')}
            </span>
          )}
          {gate.status === 'rework' && (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#F59E0B', fontSize: '12px', fontWeight: 700 }}>
              <RotateCcw size={14} /> Yêu cầu sửa
            </span>
          )}
          {gate.status === 'rejected' && (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', color: '#EF4444', fontSize: '12px', fontWeight: 700 }}>
              <XCircle size={14} /> {t('approvals.tab_rejected')}
            </span>
          )}
          <span style={{ fontSize: '11px', color: 'var(--text-dim)' }}>
            {new Date(gate.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
          </span>
        </div>
      </div>

      {/* Decision Needed */}
      {payload.decision_needed && (
        <div
          style={{
            padding: '10px 14px',
            borderRadius: '8px',
            background: 'rgba(255, 255, 255, 0.04)',
            borderLeft: `3px solid ${badge.color}`,
            marginBottom: '12px',
          }}
        >
          <div style={{ fontSize: '11px', color: 'var(--text-dim)', fontWeight: 600, marginBottom: '2px' }}>
            Quyết định cần đưa ra:
          </div>
          <div style={{ fontSize: '13px', color: '#E2E8F0', fontWeight: 600 }}>
            {payload.decision_needed}
          </div>
        </div>
      )}

      {/* Recommendation */}
      {payload.recommendation && (
        <div style={{ fontSize: '12px', color: 'var(--text-muted)', marginBottom: '12px', lineHeight: '1.5' }}>
          <strong style={{ color: '#F1F5F9' }}>Đề xuất của Agent:</strong> {payload.recommendation}
        </div>
      )}
      {isReferenceGate && payload.example?.reference_candidates && (
        <ReferenceChoices candidates={payload.example.reference_candidates} selected={selectedReferences} onChange={setSelectedReferences} />
      )}

      {/* Try It Section for Result Gates */}
      {payload.try_it && payload.try_it.url && (
        <div
          style={{
            padding: '12px 14px',
            borderRadius: '8px',
            background: 'rgba(16, 185, 129, 0.08)',
            border: '1px solid rgba(16, 185, 129, 0.25)',
            marginBottom: '12px',
          }}
        >
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '6px' }}>
            <span style={{ fontSize: '12px', fontWeight: 700, color: '#34D399', display: 'flex', alignItems: 'center', gap: '6px' }}>
              <Globe size={14} /> Chạy thử nghiệm trực tiếp (Try It)
            </span>
            <a
              href={resolveTryItUrl(payload.try_it.url)}
              target="_blank"
              rel="noreferrer"
              style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '4px',
                fontSize: '12px',
                fontWeight: 700,
                color: '#10B981',
                textDecoration: 'none',
                padding: '4px 8px',
                borderRadius: '6px',
                background: 'rgba(16, 185, 129, 0.15)',
              }}
            >
              Mở link <ExternalLink size={12} />
            </a>
          </div>
          {payload.try_it.instruction && (
            <p style={{ fontSize: '12px', color: 'var(--text-dim)', margin: '2px 0' }}>
              • {payload.try_it.instruction}
            </p>
          )}
          {payload.try_it.expected_result && (
            <p style={{ fontSize: '12px', color: 'var(--text-dim)', margin: '2px 0' }}>
              • Kỳ vọng: {payload.try_it.expected_result}
            </p>
          )}
        </div>
      )}

      {/* How to test steps (collapsible) */}
      {payload.how_to_test && payload.how_to_test.length > 0 && (
        <div style={{ marginBottom: '12px' }}>
          <button
            type="button"
            onClick={() => setShowDetails(!showDetails)}
            style={{
              background: 'transparent',
              border: 'none',
              color: 'var(--text-dim)',
              fontSize: '11px',
              fontWeight: 600,
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '4px',
              padding: '2px 0',
            }}
          >
            {showDetails ? <ChevronUp size={12} /> : <ChevronDown size={12} />}
            {showDetails ? 'Thu gọn các bước kiểm tra' : `Xem ${payload.how_to_test.length} bước kiểm tra (How to test)`}
          </button>

          {showDetails && (
            <div style={{ marginTop: '8px', paddingLeft: '8px', borderLeft: '2px solid rgba(255, 255, 255, 0.1)' }}>
              {payload.how_to_test.map((step: string, idx: number) => (
                <div key={idx} style={{ fontSize: '12px', color: 'var(--text-muted)', margin: '4px 0' }}>
                  {step}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {isPending && <GateActions
        busy={submitting}
        approveDisabled={isReferenceGate && selectedReferences.length === 0}
        onAction={handleAction}
      />}

      {/* Comment note if already decided */}
      {gate.comment && (
        <div style={{ marginTop: '10px', fontSize: '12px', color: 'var(--text-dim)', borderTop: '1px solid var(--border-subtle)', paddingTop: '8px' }}>
          💬 <strong>Ghi chú:</strong> {gate.comment}
        </div>
      )}
    </div>
  );
}
