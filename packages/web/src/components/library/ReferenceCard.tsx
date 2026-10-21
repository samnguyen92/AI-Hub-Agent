'use client';

import React, { useState } from 'react';
import { ExternalLink, Pencil, Save, Trash2, X } from 'lucide-react';
import { deleteDesignReference, DesignReference, updateDesignReference } from '@/lib/api';

interface ReferenceCardProps {
  reference: DesignReference;
  projectId: string;
  onChanged: () => void;
}

export default function ReferenceCard({ reference, projectId, onChanged }: ReferenceCardProps) {
  const [editing, setEditing] = useState(false);
  const [title, setTitle] = useState(reference.title);
  const [notes, setNotes] = useState(reference.notes || '');
  const [tags, setTags] = useState(reference.tags.join(', '));
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const fileUrl = (path: string) =>
    `/api/backend/projects/${projectId}/files?path=${encodeURIComponent(path)}`;

  const saveMetadata = async () => {
    setBusy(true);
    setError('');
    try {
      await updateDesignReference(projectId, reference.id, {
        title, notes, tags: tags.split(',').map((tag) => tag.trim()).filter(Boolean),
      });
      setEditing(false);
      onChanged();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Không thể cập nhật reference.');
    } finally {
      setBusy(false);
    }
  };

  const removeReference = async () => {
    if (!window.confirm(`Xóa reference “${reference.title}” khỏi thư viện?`)) return;
    setBusy(true);
    setError('');
    try {
      await deleteDesignReference(projectId, reference.id);
      onChanged();
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Không thể xóa reference.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <article className="glass-panel" style={{ overflow: 'hidden' }}>
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '2px', background: 'var(--border-subtle)' }}>
        <img
          src={fileUrl(reference.desktop_path)}
          alt={`Desktop screenshot of ${reference.title}`}
          style={{ width: '100%', height: '150px', objectFit: 'cover' }}
        />
        <img
          src={fileUrl(reference.mobile_path)}
          alt={`Mobile screenshot of ${reference.title}`}
          style={{ width: '100%', height: '150px', objectFit: 'cover', objectPosition: 'top' }}
        />
      </div>
      <div style={{ padding: '16px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: '12px', alignItems: 'start' }}>
          <h3 style={{ margin: 0, fontSize: '14px', lineHeight: 1.4 }}>{reference.title}</h3>
          <a href={reference.source_url} target="_blank" rel="noreferrer" aria-label={`Open ${reference.title}`}>
            <ExternalLink size={15} />
          </a>
        </div>
        <p style={{ margin: '8px 0', fontSize: '11px', color: 'var(--text-muted)', wordBreak: 'break-all' }}>
          {reference.source_url}
        </p>
        {editing ? (
          <div style={{ display: 'grid', gap: '8px', margin: '10px 0' }}>
            <input aria-label="Reference title" value={title} onChange={(event) => setTitle(event.target.value)} />
            <textarea aria-label="Reference notes" value={notes} onChange={(event) => setNotes(event.target.value)} rows={3} />
            <input aria-label="Tags, separated by commas" value={tags} onChange={(event) => setTags(event.target.value)} />
            <button type="button" onClick={saveMetadata} disabled={busy || !title.trim()}><Save size={14} /> Lưu</button>
          </div>
        ) : reference.notes ? <p style={{ fontSize: '12px', lineHeight: 1.5 }}>{reference.notes}</p> : null}
        {error && <p role="alert" style={{ color: 'var(--color-danger, #f87171)', fontSize: '12px' }}>{error}</p>}
        {reference.page_summary && (
          <details style={{ fontSize: '11px', color: 'var(--text-muted)' }}>
            <summary style={{ cursor: 'pointer' }}>Metadata trang</summary>
            <pre style={{ whiteSpace: 'pre-wrap', font: 'inherit' }}>{reference.page_summary}</pre>
          </details>
        )}
        {!!reference.tags.length && (
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px', marginTop: '10px' }}>
            {reference.tags.map((tag) => (
              <span key={tag} style={{ fontSize: '10px', padding: '3px 7px', borderRadius: '999px', background: 'rgba(99,102,241,0.14)', color: 'var(--accent-primary)' }}>
                {tag}
              </span>
            ))}
          </div>
        )}
        <div style={{ display: 'flex', gap: '8px', marginTop: '12px' }}>
          <button type="button" onClick={() => setEditing((value) => !value)} disabled={busy}>
            {editing ? <X size={14} /> : <Pencil size={14} />} {editing ? 'Đóng' : 'Sửa'}
          </button>
          <button type="button" onClick={removeReference} disabled={busy}><Trash2 size={14} /> Xóa</button>
        </div>
      </div>
    </article>
  );
}
