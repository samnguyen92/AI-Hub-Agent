'use client';

import React, { useState } from 'react';
import { CheckCircle2, RotateCcw, XCircle } from 'lucide-react';

interface GateActionsProps {
  busy: boolean;
  approveDisabled: boolean;
  onAction: (action: 'approve' | 'rework' | 'reject', comment?: string) => Promise<void>;
}

/** Render pending gate decisions and collect actionable rework feedback. */
export default function GateActions({ busy, approveDisabled, onAction }: GateActionsProps) {
  const [editing, setEditing] = useState(false);
  const [comment, setComment] = useState('');
  const actionButton: React.CSSProperties = {
    borderRadius: 6, padding: '6px 14px', fontSize: 12, fontWeight: 700,
    cursor: 'pointer', display: 'flex', alignItems: 'center', gap: 6,
  };

  return (
    <div style={{ marginTop: 16, paddingTop: 12, borderTop: '1px solid var(--border-subtle)' }}>
      {!editing ? (
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <button type="button" disabled={busy || approveDisabled} onClick={() => void onAction('approve')} className="btn-primary" style={{ ...actionButton, background: '#10B981', color: '#FFFFFF' }}>
            <CheckCircle2 size={14} /> Duyệt (Approve)
          </button>
          <button type="button" disabled={busy} onClick={() => setEditing(true)} style={{ ...actionButton, background: 'rgba(245,158,11,.15)', border: '1px solid rgba(245,158,11,.4)', color: '#F59E0B' }}>
            <RotateCcw size={14} /> Yêu cầu sửa (Rework)
          </button>
          <button type="button" disabled={busy} onClick={() => void onAction('reject')} style={{ ...actionButton, background: 'rgba(239,68,68,.15)', border: '1px solid rgba(239,68,68,.4)', color: '#EF4444' }}>
            <XCircle size={14} /> Từ chối (Reject)
          </button>
        </div>
      ) : (
        <div style={{ marginTop: 8 }}>
          <textarea value={comment} onChange={(event) => setComment(event.target.value)} placeholder="Nhập yêu cầu chỉnh sửa cụ thể để Agent tiếp thu và khắc phục..." rows={2} style={{ width: '100%', padding: '8px 12px', borderRadius: 8, background: 'rgba(0,0,0,.3)', border: '1px solid rgba(245,158,11,.4)', color: '#FFFFFF', fontSize: 13, resize: 'none', marginBottom: 8 }} />
          <div style={{ display: 'flex', gap: 8 }}>
            <button type="button" disabled={busy || !comment.trim()} onClick={() => void onAction('rework', comment)} className="btn-primary" style={{ ...actionButton, background: '#F59E0B', color: '#000' }}>Gửi yêu cầu sửa</button>
            <button type="button" onClick={() => setEditing(false)} style={{ ...actionButton, background: 'transparent', border: '1px solid var(--border-subtle)', color: 'var(--text-muted)' }}>Hủy</button>
          </div>
        </div>
      )}
    </div>
  );
}
