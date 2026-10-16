'use client';

import React, { useEffect, useState } from 'react';
import {
  ShieldCheck,
  CheckCircle,
  XCircle,
  RotateCcw,
  Clock,
  Send,
  FileArchive,
  Download,
  KeyRound,
  Bot,
} from 'lucide-react';
import { fetchApprovals, respondApproval, generateTelegramLinkCode, ApprovalItem } from '@/lib/api';
import { useLanguage } from '@/context/LanguageContext';
import GateCard from '@/components/GateCard';

export default function ApprovalsPage() {
  const { t } = useLanguage();
  const [approvals, setApprovals] = useState<ApprovalItem[]>([]);
  const [filter, setFilter] = useState<string>('all');
  const [loading, setLoading] = useState(true);
  const [telegramInfo, setTelegramInfo] = useState<{ link_code: string; instructions: string } | null>(null);

  const loadApprovals = async () => {
    setLoading(true);
    try {
      const data = await fetchApprovals(filter);
      setApprovals(data);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadApprovals();
  }, [filter]);

  const handleDecision = async (id: string, action: 'approve' | 'rework' | 'reject', comment?: string) => {
    await respondApproval(id, action, comment);
    await loadApprovals();
  };

  const handleGetTelegramCode = async () => {
    const res = await generateTelegramLinkCode();
    setTelegramInfo(res);
  };

  const renderBadge = (status: string) => {
    if (status === 'approved') {
      return <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '3px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(16, 185, 129, 0.2)', color: '#10B981' }}><CheckCircle size={12} /> {t('approvals.tab_approved')}</span>;
    }
    if (status === 'rework') {
      return <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '3px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(245, 158, 11, 0.2)', color: '#F59E0B' }}><RotateCcw size={12} /> Yêu cầu sửa</span>;
    }
    if (status === 'rejected') {
      return <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '3px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(239, 68, 68, 0.2)', color: '#EF4444' }}><XCircle size={12} /> {t('approvals.tab_rejected')}</span>;
    }
    return <span style={{ display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '3px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(56, 189, 248, 0.2)', color: '#38BDF8' }}><Clock size={12} /> {t('approvals.tab_pending')}</span>;
  };

  return (
    <div style={{ maxWidth: '1100px', margin: '0 auto', padding: '32px 24px' }}>
      {/* Top Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '28px' }}>
        <div>
          <h1 style={{ fontSize: '24px', fontWeight: 800, letterSpacing: '-0.02em', marginBottom: '6px' }}>
            {t('approvals.title')}
          </h1>
          <p style={{ fontSize: '13px', color: 'var(--text-muted)' }}>
            {t('approvals.subtitle')}
          </p>
        </div>

        <button
          onClick={handleGetTelegramCode}
          className="btn-primary"
          style={{ background: 'rgba(99, 102, 241, 0.2)', color: 'var(--accent-primary)', border: '1px solid rgba(99, 102, 241, 0.4)' }}
        >
          <Bot size={16} /> {t('approvals.telegram_btn')}
        </button>
      </div>

      {/* Telegram Link Box if requested */}
      {telegramInfo && (
        <div className="glass-panel" style={{ padding: '20px', marginBottom: '24px', borderColor: 'var(--accent-primary)', background: 'rgba(99, 102, 241, 0.08)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: '8px', fontWeight: 700, fontSize: '14px', marginBottom: '4px' }}>
                <KeyRound size={16} color="var(--accent-primary)" /> {t('approvals.telegram_code_title')}
              </div>
              <p style={{ fontSize: '13px', color: 'var(--text-dim)' }}>{telegramInfo.instructions}</p>
            </div>
            <div style={{ fontSize: '22px', fontWeight: 800, letterSpacing: '0.1em', color: 'var(--accent-primary)', padding: '6px 16px', background: '#000', borderRadius: '8px' }}>
              {telegramInfo.link_code}
            </div>
          </div>
        </div>
      )}

      {/* Filter Tabs */}
      <div style={{ display: 'flex', gap: '8px', marginBottom: '20px', flexWrap: 'wrap' }}>
        {[
          { key: 'all', label: t('approvals.tab_all') },
          { key: 'pending', label: t('approvals.tab_pending') },
          { key: 'approved', label: t('approvals.tab_approved') },
          { key: 'rework', label: t('approvals.tab_rework') },
          { key: 'rejected', label: t('approvals.tab_rejected') },
        ].map((f) => (
          <button
            key={f.key}
            onClick={() => setFilter(f.key)}
            style={{
              padding: '6px 14px',
              borderRadius: '6px',
              fontSize: '12px',
              fontWeight: 600,
              background: filter === f.key ? 'var(--accent-primary)' : 'rgba(255, 255, 255, 0.05)',
              color: '#ffffff',
              border: 'none',
              cursor: 'pointer',
              textTransform: 'uppercase',
            }}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* Approvals List */}
      {loading ? (
        <div style={{ textAlign: 'center', padding: '60px', color: 'var(--text-muted)' }}>Đang tải danh sách...</div>
      ) : approvals.length === 0 ? (
        <div className="glass-panel" style={{ padding: '48px', textAlign: 'center', color: 'var(--text-muted)' }}>
          Không có yêu cầu phê duyệt nào trong danh mục này.
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '14px' }}>
          {approvals.map((appr) => {
            if (appr.action_type.startsWith('gate_') || appr.payload?.gate_type) {
              return (
                <GateCard
                  key={appr.id}
                  gate={appr}
                  onRespond={handleDecision}
                />
              );
            }

            return (
              <div key={appr.id} className="glass-panel" style={{ padding: '20px' }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '12px' }}>
                <div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '4px' }}>
                    <FileArchive size={18} color="var(--accent-primary)" />
                    <span style={{ fontWeight: 700, fontSize: '15px' }}>
                      Hành động: <code>{appr.action_type}</code>
                    </span>
                  </div>
                  <span style={{ fontSize: '11px', color: 'var(--text-muted)' }}>ID: {appr.id}</span>
                </div>
                <div>{renderBadge(appr.status)}</div>
              </div>

              {/* Payload info */}
              <div style={{ fontSize: '13px', color: 'var(--text-dim)', marginBottom: '14px', background: 'rgba(0,0,0,0.3)', padding: '10px 14px', borderRadius: '8px' }}>
                <div>Tệp đóng gói: <strong>{appr.payload.archive_name || 'project_export.zip'}</strong></div>
                {appr.payload.result?.download_url && (
                  <div style={{ marginTop: '6px' }}>
                    <a
                      href={`/api/backend${appr.payload.result.download_url}`}
                      target="_blank"
                      rel="noreferrer"
                      className="btn-primary"
                      style={{ fontSize: '12px', padding: '4px 10px', display: 'inline-flex', alignItems: 'center', gap: '6px' }}
                    >
                      <Download size={14} /> Tải file ZIP đã duyệt
                    </a>
                  </div>
                )}
              </div>

              {/* Action Buttons if pending */}
              {appr.status === 'pending' && (
                <div style={{ display: 'flex', gap: '10px', justifyContent: 'flex-end' }}>
                  <button
                    onClick={() => handleDecision(appr.id, 'reject')}
                    style={{
                      padding: '8px 16px',
                      borderRadius: '8px',
                      background: 'rgba(239, 68, 68, 0.15)',
                      border: '1px solid rgba(239, 68, 68, 0.4)',
                      color: '#EF4444',
                      fontWeight: 600,
                      fontSize: '13px',
                      cursor: 'pointer',
                    }}
                  >
                    Từ chối
                  </button>
                  <button
                    onClick={() => handleDecision(appr.id, 'approve')}
                    className="btn-primary"
                    style={{ padding: '8px 20px', fontSize: '13px' }}
                  >
                    Duyệt & Thực thi
                  </button>
                </div>
              )}
            </div>
          );
        })}
        </div>
      )}
    </div>
  );
}
