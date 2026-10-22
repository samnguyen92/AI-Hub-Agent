'use client';

import React, { useState, useEffect } from 'react';
import { CheckCircle2, XCircle, Plus } from 'lucide-react';
import { fetchWithAuth } from '@/lib/api';
import AgentCard, { AgentInfo } from '@/components/agents/AgentCard';

interface ProjectNote {
  id: string;
  project_id: string;
  content: string;
  status: 'pending' | 'approved' | 'rejected';
  source_run_id?: string;
  created_at: string;
}

interface Project {
  id: string;
  name: string;
}

export default function AgentsPage() {
  const [activeTab, setActiveTab] = useState<'agents' | 'memory'>('agents');
  const [agents, setAgents] = useState<AgentInfo[]>([]);
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProjectId, setSelectedProjectId] = useState<string>('');
  const [notes, setNotes] = useState<ProjectNote[]>([]);
  const [newNoteContent, setNewNoteContent] = useState('');
  const [loading, setLoading] = useState(false);
  const [updatingKey, setUpdatingKey] = useState<string | null>(null);
  const [modelError, setModelError] = useState('');
  const apiBase = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';
  const fetchAgents = async () => {
    try {
      const res = await fetchWithAuth(`${apiBase}/agents`);
      if (res.ok) setAgents(await res.json());
    } catch (e) {
      console.error(e);
    }
  };
  const fetchProjects = async () => {
    try {
      const res = await fetchWithAuth(`${apiBase}/projects`);
      if (res.ok) {
        const data = await res.json();
        setProjects(data);
        if (data.length > 0 && !selectedProjectId) {
          setSelectedProjectId(data[0].id);
        }
      }
    } catch (e) {
      console.error(e);
    }
  };
  const fetchNotes = async (projId: string) => {
    if (!projId) return;
    try {
      const res = await fetchWithAuth(`${apiBase}/projects/${projId}/notes`);
      if (res.ok) setNotes(await res.json());
    } catch (e) {
      console.error(e);
    }
  };
  useEffect(() => {
    fetchAgents();
    fetchProjects();
  }, []);
  useEffect(() => {
    if (selectedProjectId) fetchNotes(selectedProjectId);
  }, [selectedProjectId]);
  const handleUpdateModel = async (key: string, newModel: string) => {
    const model = newModel.trim();
    if (!model) return;
    setUpdatingKey(key);
    setModelError('');
    try {
      const res = await fetchWithAuth(`${apiBase}/agents/${key}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ model }),
      });
      if (!res.ok) {
        const payload: unknown = await res.json().catch((): unknown => null);
        const detail = typeof payload === 'object' && payload !== null && 'detail' in payload
          ? payload.detail
          : null;
        throw new Error(typeof detail === 'string' ? detail : `Không thể lưu model (HTTP ${res.status}).`);
      }
      await fetchAgents();
    } catch (error) {
      setModelError(error instanceof Error ? error.message : 'Không thể lưu model.');
    } finally {
      setUpdatingKey(null);
    }
  };

  const handleUpdateAgentConfig = async (key: string, config: Record<string, unknown>) => {
    setUpdatingKey(key);
    setModelError('');
    try {
      const res = await fetchWithAuth(`${apiBase}/agents/${key}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ config }),
      });
      if (!res.ok) throw new Error(`Không thể lưu cấu hình ảnh (HTTP ${res.status}).`);
      await fetchAgents();
    } catch (error) {
      setModelError(error instanceof Error ? error.message : 'Không thể lưu cấu hình ảnh.');
    } finally {
      setUpdatingKey(null);
    }
  };
  const handleNoteRespond = async (noteId: string, action: 'approve' | 'reject') => {
    if (!selectedProjectId) return;
    try {
      const res = await fetchWithAuth(`${apiBase}/projects/${selectedProjectId}/notes/${noteId}/respond`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action }),
      });
      if (res.ok) fetchNotes(selectedProjectId);
    } catch (e) {
      console.error(e);
    }
  };
  const handleAddNote = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedProjectId || !newNoteContent.trim()) return;
    setLoading(true);
    try {
      const res = await fetchWithAuth(`${apiBase}/projects/${selectedProjectId}/notes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ content: newNoteContent.trim() }),
      });
      if (res.ok) {
        setNewNoteContent('');
        fetchNotes(selectedProjectId);
      }
    } finally {
      setLoading(false);
    }
  };
  return (
    <div style={{ minHeight: '100vh', background: 'var(--bg-main)', color: 'var(--text-main)' }}>
      <main style={{ maxWidth: '1200px', margin: '0 auto', padding: '32px 24px' }}>
        <header style={{ marginBottom: '28px', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <h1 style={{ fontSize: '28px', fontWeight: 800, margin: '0 0 6px 0' }}>Quản lý Agents & Bộ nhớ</h1>
            <p style={{ margin: 0, color: 'var(--text-muted)' }}>Cấu hình mô hình LLM chuyên biệt và kiểm soát ký ức dài hạn của dự án.</p>
          </div>
          <div style={{ display: 'flex', gap: '8px', background: 'var(--bg-surface)', padding: '4px', borderRadius: '10px' }}>
            <button
              onClick={() => setActiveTab('agents')}
              style={{
                padding: '8px 16px',
                borderRadius: '8px',
                border: 'none',
                background: activeTab === 'agents' ? 'var(--accent-primary)' : 'transparent',
                color: '#fff',
                cursor: 'pointer',
                fontWeight: 600,
              }}
            >
              Agents ({agents.length})
            </button>
            <button
              onClick={() => setActiveTab('memory')}
              style={{
                padding: '8px 16px',
                borderRadius: '8px',
                border: 'none',
                background: activeTab === 'memory' ? 'var(--accent-primary)' : 'transparent',
                color: '#fff',
                cursor: 'pointer',
                fontWeight: 600,
              }}
            >
              Bộ nhớ Dự án
            </button>
          </div>
        </header>

        {activeTab === 'agents' ? (
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(360px, 1fr))', gap: '20px' }}>
            {modelError && <p role="alert" style={{ color: '#f87171', gridColumn: '1 / -1' }}>{modelError}</p>}
            {agents.map((ag) => (
              <AgentCard
                key={ag.key}
                agent={ag}
                saving={updatingKey === ag.key}
                onSaveModel={(model) => handleUpdateModel(ag.key, model)}
                onSaveImageModel={(model) => handleUpdateAgentConfig(ag.key, { image_model: model })}
              />
            ))}
          </div>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '12px', background: 'var(--bg-surface)', padding: '16px 20px', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
              <span style={{ fontWeight: 600 }}>Dự án mục tiêu:</span>
              <select
                value={selectedProjectId}
                onChange={(e) => setSelectedProjectId(e.target.value)}
                style={{ padding: '8px 12px', borderRadius: '8px', background: 'var(--bg-main)', border: '1px solid var(--border-subtle)', color: '#fff', outline: 'none', minWidth: '240px' }}
              >
                {projects.map((p) => (
                  <option key={p.id} value={p.id}>{p.name}</option>
                ))}
              </select>
            </div>

            <form onSubmit={handleAddNote} style={{ display: 'flex', gap: '12px', background: 'var(--bg-surface)', padding: '16px', borderRadius: '12px', border: '1px solid var(--border-subtle)' }}>
              <input
                type="text"
                placeholder="Thêm ghi chú quy tắc/bộ nhớ mới cho dự án này..."
                value={newNoteContent}
                onChange={(e) => setNewNoteContent(e.target.value)}
                style={{ flex: 1, padding: '10px 14px', borderRadius: '8px', background: 'var(--bg-main)', border: '1px solid var(--border-subtle)', color: '#fff', outline: 'none' }}
              />
              <button
                type="submit"
                disabled={loading || !newNoteContent.trim()}
                style={{ padding: '0 20px', borderRadius: '8px', background: 'var(--accent-primary)', color: '#fff', border: 'none', fontWeight: 600, display: 'flex', alignItems: 'center', gap: '8px', cursor: 'pointer' }}
              >
                <Plus size={16} /> Thêm ghi chú
              </button>
            </form>

            <div style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
              <h3 style={{ fontSize: '18px', fontWeight: 700, margin: '8px 0' }}>Danh sách Ghi nhớ ({notes.length})</h3>
              {notes.length === 0 ? (
                <div style={{ padding: '40px', textAlign: 'center', background: 'var(--bg-surface)', borderRadius: '12px', color: 'var(--text-muted)' }}>
                  Chưa có ghi nhớ nào cho dự án này.
                </div>
              ) : (
                notes.map((n) => (
                  <div key={n.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '16px 20px', background: 'var(--bg-surface)', borderRadius: '10px', border: '1px solid var(--border-subtle)' }}>
                    <div>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '6px' }}>
                        {n.content.includes('[LEARNED_RULE') ? (
                          <span style={{ padding: '2px 8px', borderRadius: '4px', fontSize: '11px', fontWeight: 700, background: 'rgba(129, 140, 248, 0.2)', color: '#818CF8' }}>
                            💡 TỰ HỌC (LEARNED)
                          </span>
                        ) : (
                          <span style={{
                            padding: '2px 8px',
                            borderRadius: '4px',
                            fontSize: '11px',
                            fontWeight: 700,
                            background: n.status === 'approved' ? 'rgba(34, 197, 94, 0.15)' : n.status === 'rejected' ? 'rgba(239, 68, 68, 0.15)' : 'rgba(234, 179, 8, 0.15)',
                            color: n.status === 'approved' ? '#22c55e' : n.status === 'rejected' ? '#ef4444' : '#eab308'
                          }}>
                            {n.status.toUpperCase()}
                          </span>
                        )}
                        <span style={{ fontSize: '12px', color: 'var(--text-muted)' }}>{new Date(n.created_at).toLocaleString('vi-VN')}</span>
                      </div>
                      <p style={{ margin: 0, fontSize: '15px', color: 'var(--text-main)' }}>{n.content}</p>
                    </div>

                    {n.status === 'pending' && (
                      <div style={{ display: 'flex', gap: '8px' }}>
                        <button
                          onClick={() => handleNoteRespond(n.id, 'approve')}
                          style={{ padding: '6px 12px', borderRadius: '6px', background: '#22c55e', color: '#fff', border: 'none', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '6px', fontWeight: 600, fontSize: '13px' }}
                        >
                          <CheckCircle2 size={14} /> Duyệt
                        </button>
                        <button
                          onClick={() => handleNoteRespond(n.id, 'reject')}
                          style={{ padding: '6px 12px', borderRadius: '6px', background: 'rgba(239, 68, 68, 0.2)', color: '#ef4444', border: '1px solid rgba(239, 68, 68, 0.3)', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '6px', fontWeight: 600, fontSize: '13px' }}
                        >
                          <XCircle size={14} /> Từ chối
                        </button>
                      </div>
                    )}
                  </div>
                ))
              )}
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
