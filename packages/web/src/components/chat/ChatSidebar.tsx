'use client';

import React from 'react';
import { MessageSquare, Plus } from 'lucide-react';
import { Project, Thread } from '@/lib/api';

interface ChatSidebarProps {
  projects: Project[];
  selectedProjectId: string;
  onSelectProject: (id: string) => void;
  threads: Thread[];
  activeThreadId: string;
  onSelectThread: (id: string) => void;
  onNewThread: () => void;
}

export default function ChatSidebar({
  projects,
  selectedProjectId,
  onSelectProject,
  threads,
  activeThreadId,
  onSelectThread,
  onNewThread,
}: ChatSidebarProps) {
  return (
    <div
      style={{
        width: '280px',
        borderRight: '1px solid var(--border-subtle)',
        background: 'var(--bg-secondary)',
        display: 'flex',
        flexDirection: 'column',
        padding: '16px',
      }}
    >
      <div style={{ marginBottom: '16px' }}>
        <label style={{ fontSize: '11px', fontWeight: 700, color: 'var(--text-dim)', textTransform: 'uppercase' }}>
          Dự án hiện tại
        </label>
        <select
          id="select-active-project"
          value={selectedProjectId}
          onChange={(e) => onSelectProject(e.target.value)}
          style={{
            width: '100%',
            marginTop: '6px',
            padding: '8px 10px',
            borderRadius: '6px',
            background: 'var(--bg-card)',
            color: '#ffffff',
            border: '1px solid var(--border-subtle)',
            fontSize: '13px',
          }}
        >
          {projects.map((p) => (
            <option key={p.id} value={p.id}>
              {p.name}
            </option>
          ))}
        </select>
      </div>

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '12px' }}>
        <span style={{ fontSize: '12px', fontWeight: 600, color: 'var(--text-muted)' }}>Cuộc trò chuyện</span>
        <button
          id="btn-new-thread"
          onClick={onNewThread}
          style={{
            background: 'transparent',
            border: 'none',
            color: 'var(--accent-primary)',
            cursor: 'pointer',
            display: 'flex',
            alignItems: 'center',
            gap: '4px',
            fontSize: '12px',
            fontWeight: 600,
          }}
        >
          <Plus size={14} /> Mới
        </button>
      </div>

      <div style={{ flex: 1, overflowY: 'auto', display: 'flex', flexDirection: 'column', gap: '6px' }}>
        {threads.length === 0 ? (
          <p style={{ fontSize: '12px', color: 'var(--text-dim)', textAlign: 'center', marginTop: '20px' }}>
            Chưa có cuộc trò chuyện nào.
          </p>
        ) : (
          threads.map((t) => (
            <button
              key={t.id}
              onClick={() => onSelectThread(t.id)}
              style={{
                width: '100%',
                textAlign: 'left',
                padding: '10px 12px',
                borderRadius: '8px',
                background: activeThreadId === t.id ? 'var(--bg-tertiary)' : 'transparent',
                border: activeThreadId === t.id ? '1px solid var(--accent-primary)' : '1px solid transparent',
                color: activeThreadId === t.id ? '#ffffff' : 'var(--text-dim)',
                cursor: 'pointer',
                fontSize: '13px',
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                overflow: 'hidden',
                textOverflow: 'ellipsis',
                whiteSpace: 'nowrap',
              }}
            >
              <MessageSquare size={14} />
              <span style={{ overflow: 'hidden', textOverflow: 'ellipsis' }}>
                {t.title || 'Cuộc trò chuyện mới'}
              </span>
            </button>
          ))
        )}
      </div>
    </div>
  );
}
