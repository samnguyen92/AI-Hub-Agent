'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import { FolderKanban, Plus, ExternalLink, MessageSquare, Library, Trash2, Settings } from 'lucide-react';
import { createProject, deleteProject, fetchProjects, Project } from '@/lib/api';
import { useLanguage } from '@/context/LanguageContext';
import ProjectSettingsModal from '@/components/projects/ProjectSettingsModal';

export default function ProjectsPage() {
  const { t } = useLanguage();
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [showModal, setShowModal] = useState(false);
  const [settingsProject, setSettingsProject] = useState<Project | null>(null);
  const [name, setName] = useState('');
  const [sourceUrl, setSourceUrl] = useState('');
  const [creating, setCreating] = useState(false);
  const [deletingId, setDeletingId] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState('');

  const loadProjects = async () => {
    setLoading(true);
    try {
      const data = await fetchProjects();
      setProjects(data);
      setErrorMessage('');
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Không thể tải danh sách dự án.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadProjects();
  }, []);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    setCreating(true);
    try {
      await createProject(name.trim(), sourceUrl.trim() || undefined);
      setName('');
      setSourceUrl('');
      setShowModal(false);
      await loadProjects();
    } catch (err) {
      setErrorMessage(err instanceof Error ? err.message : 'Không thể tạo dự án.');
    } finally {
      setCreating(false);
    }
  };

  const handleDelete = async (projectId: string, projectName: string) => {
    if (!window.confirm(`${t('projects.delete_confirm')}\n\nProject: ${projectName}`)) return;
    setDeletingId(projectId);
    try {
      await deleteProject(projectId);
      await loadProjects();
    } catch (err) {
      alert(`Error deleting project: ${err instanceof Error ? err.message : String(err)}`);
    } finally {
      setDeletingId(null);
    }
  };

  return (
    <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '32px 24px' }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '24px', fontWeight: 800, letterSpacing: '-0.02em', marginBottom: '6px' }}>
            {t('projects.title')}
          </h1>
          <p style={{ fontSize: '13px', color: 'var(--text-muted)' }}>
            {t('projects.subtitle')}
          </p>
        </div>
        <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
          <button
            id="btn-create-project"
            className="btn-primary"
            onClick={() => setShowModal(true)}
          >
            <Plus size={16} />
            {t('projects.new')}
          </button>
        </div>
      </div>

      {loading ? (
        <div style={{ textAlign: 'center', padding: '60px', color: 'var(--text-muted)' }}>
          Đang tải danh sách dự án...
        </div>
      ) : projects.length === 0 ? (
        <div className="glass-panel" style={{ padding: '60px', textAlign: 'center' }}>
          {errorMessage && <p role="alert" style={{ color: '#f87171', marginBottom: '16px' }}>{errorMessage}</p>}
          <FolderKanban size={48} color="var(--accent-primary)" style={{ margin: '0 auto 16px', opacity: 0.6 }} />
          <h3 style={{ fontSize: '18px', fontWeight: 700, marginBottom: '8px' }}>Chưa có dự án nào</h3>
          <p style={{ fontSize: '14px', color: 'var(--text-muted)', marginBottom: '24px' }}>
            Tạo dự án đầu tiên để bắt đầu chat và làm việc cùng các agent của Align.
          </p>
          <button className="btn-primary" onClick={() => setShowModal(true)}>
            <Plus size={16} />
            Tạo dự án ngay
          </button>
        </div>
      ) : (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(340px, 1fr))', gap: '20px' }}>
          {projects.map((p) => (
            <div key={p.id} className="glass-panel" style={{ padding: '24px', display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
              <div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '12px' }}>
                  <h3 style={{ fontSize: '17px', fontWeight: 700 }}>{p.name}</h3>
                  <span style={{ fontSize: '11px', color: 'var(--text-dim)', background: 'rgba(255,255,255,0.06)', padding: '2px 8px', borderRadius: '4px' }}>
                    ID: {p.id.slice(0, 8)}...
                  </span>
                </div>
                {p.source_url && (
                  <p style={{ fontSize: '12px', color: 'var(--text-muted)', marginBottom: '12px', display: 'flex', alignItems: 'center', gap: '4px' }}>
                    <ExternalLink size={12} /> {p.source_url}
                  </p>
                )}
                <p style={{ fontSize: '12px', color: 'var(--text-dim)', marginBottom: '20px', wordBreak: 'break-all' }}>
                  Workspace: <code>{p.workspace_path}</code>
                </p>
              </div>

              <div style={{ display: 'flex', gap: '8px', borderTop: '1px solid var(--border-subtle)', paddingTop: '16px' }}>
                <Link
                  href={`/chat?projectId=${p.id}`}
                  style={{
                    flex: 1,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                    padding: '8px',
                    borderRadius: '6px',
                    fontSize: '13px',
                    fontWeight: 600,
                    background: 'rgba(99, 102, 241, 0.15)',
                    color: 'var(--text-main)',
                  }}
                >
                  <MessageSquare size={14} color="var(--accent-primary)" />
                  {t('projects.open_chat')}
                </Link>
                <Link
                  href={`/library?projectId=${p.id}`}
                  style={{
                    flex: 1,
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                    padding: '8px',
                    borderRadius: '6px',
                    fontSize: '13px',
                    fontWeight: 600,
                    background: 'rgba(255, 255, 255, 0.05)',
                    color: 'var(--text-muted)',
                  }}
                >
                  <Library size={14} />
                  {t('projects.library')}
                </Link>
                <button
                  onClick={() => setSettingsProject(p)}
                  title={t('settings.title')}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    gap: '6px',
                    padding: '8px 12px',
                    borderRadius: '6px',
                    fontSize: '13px',
                    fontWeight: 600,
                    background: 'rgba(56, 189, 248, 0.1)',
                    color: '#38bdf8',
                    border: '1px solid rgba(56, 189, 248, 0.2)',
                    cursor: 'pointer',
                  }}
                >
                  <Settings size={14} />
                  {t('projects.settings')}
                </button>
                <button
                  onClick={() => handleDelete(p.id, p.name)}
                  disabled={deletingId === p.id}
                  title={t('projects.delete')}
                  style={{
                    padding: '8px 12px',
                    borderRadius: '6px',
                    background: 'rgba(239, 68, 68, 0.1)',
                    color: '#f87171',
                    border: '1px solid rgba(239, 68, 68, 0.2)',
                    cursor: 'pointer',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                  }}
                >
                  <Trash2 size={14} />
                </button>
              </div>
            </div>
          ))}
        </div>
      )}
      {errorMessage && projects.length > 0 && <p role="alert" style={{ color: '#f87171', marginTop: '16px' }}>{errorMessage}</p>}

      {/* Project Settings Modal */}
      {settingsProject && (
        <ProjectSettingsModal
          projectId={settingsProject.id}
          projectName={settingsProject.name}
          onClose={() => setSettingsProject(null)}
        />
      )}

      {/* Create Modal */}
      {showModal && (
        <div
          style={{
            position: 'fixed',
            inset: 0,
            background: 'rgba(0,0,0,0.7)',
            backdropFilter: 'blur(4px)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            zIndex: 1000,
            padding: '20px',
          }}
        >
          <div className="glass-panel" style={{ width: '100%', maxWidth: '440px', padding: '28px', background: '#101623' }}>
            <h2 style={{ fontSize: '18px', fontWeight: 700, marginBottom: '20px' }}>{t('projects.modal.create_title')}</h2>
            <form onSubmit={handleCreate}>
              <div style={{ marginBottom: '16px' }}>
                <label style={{ display: 'block', fontSize: '13px', fontWeight: 600, marginBottom: '6px', color: 'var(--text-muted)' }}>{t('projects.modal.name_label')}</label>
                <input id="input-project-name" type="text" required value={name} onChange={(e) => setName(e.target.value)} placeholder={t('projects.modal.name_placeholder')} style={{ width: '100%', padding: '10px 14px', borderRadius: '8px', border: '1px solid var(--border-subtle)', background: 'rgba(255,255,255,0.03)', color: '#fff', fontSize: '14px', outline: 'none' }} />
              </div>
              <div style={{ marginBottom: '24px' }}>
                <label style={{ display: 'block', fontSize: '13px', fontWeight: 600, marginBottom: '6px', color: 'var(--text-muted)' }}>{t('projects.modal.url_label')}</label>
                <input id="input-project-url" type="url" value={sourceUrl} onChange={(e) => setSourceUrl(e.target.value)} placeholder="https://example.com" style={{ width: '100%', padding: '10px 14px', borderRadius: '8px', border: '1px solid var(--border-subtle)', background: 'rgba(255,255,255,0.03)', color: '#fff', fontSize: '14px', outline: 'none' }} />
              </div>
              <div style={{ display: 'flex', justifyContent: 'flex-end', gap: '10px' }}>
                <button type="button" onClick={() => setShowModal(false)} style={{ padding: '8px 16px', borderRadius: '6px', background: 'transparent', border: '1px solid var(--border-subtle)', color: 'var(--text-muted)', cursor: 'pointer' }}>{t('projects.modal.btn_cancel')}</button>
                <button id="btn-submit-project" type="submit" className="btn-primary" disabled={creating}>{creating ? t('projects.modal.btn_creating') : t('projects.modal.btn_create')}</button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
