'use client';

import React, { Suspense, useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import {
  Upload,
  Layers,
  GitCommit,
  BookOpen,
  Image as ImageIcon,
  Maximize2,
  X,
  ExternalLink,
} from 'lucide-react';
import {
  fetchLibrary,
  fetchProjects,
  fetchProjectNotes,
  LibrarySummary,
  Project,
  ProjectNoteItem,
} from '@/lib/api';
import ArtifactViewer, { ArtifactData } from '@/components/artifacts/ArtifactViewer';
import ReferenceShelf from '@/components/library/ReferenceShelf';
import { useLanguage } from '@/context/LanguageContext';

function LibraryInner() {
  const { t } = useLanguage();
  const searchParams = useSearchParams();
  const [activeTab, setActiveTab] = useState<'global' | 'project'>('project');
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProjectId, setSelectedProjectId] = useState<string>('');
  const [libraryData, setLibraryData] = useState<LibrarySummary | null>(null);
  const [globalRules, setGlobalRules] = useState<ProjectNoteItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [selectedArtifact, setSelectedArtifact] = useState<ArtifactData | null>(null);
  const [lightboxUrl, setLightboxUrl] = useState<string | null>(null);

  useEffect(() => {
    fetchProjects().then((data) => {
      setProjects(data);
      const urlProj = searchParams.get('projectId');
      const defaultProj = urlProj || (data.length > 0 ? data[0].id : '');
      setSelectedProjectId(defaultProj);
    });
    fetchProjectNotes('global').then((rules) => setGlobalRules(rules || []));
  }, [searchParams]);

  const loadLibrary = async () => {
    if (!selectedProjectId) return;
    setLoading(true);
    try {
      const summary = await fetchLibrary(selectedProjectId);
      setLibraryData(summary);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadLibrary();
  }, [selectedProjectId]);

  const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file || !selectedProjectId) return;
    setUploading(true);
    const formData = new FormData();
    formData.append('file', file);
    formData.append('tag', 'manual_upload');
    formData.append('description', file.name);

    try {
      const res = await fetch(`/api/backend/projects/${selectedProjectId}/library/upload`, {
        method: 'POST',
        body: formData,
      });
      if (res.ok) await loadLibrary();
    } finally {
      setUploading(false);
    }
  };

  return (
    <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '32px 24px' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '24px', flexWrap: 'wrap', gap: '16px' }}>
        <div>
          <h1 style={{ fontSize: '24px', fontWeight: 800, letterSpacing: '-0.02em', marginBottom: '6px' }}>
            {t('library.title')}
          </h1>
          <p style={{ fontSize: '13px', color: 'var(--text-muted)' }}>
            {t('library.subtitle')}
          </p>
        </div>

        {activeTab === 'project' && (
          <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
            <select
              id="select-library-project"
              value={selectedProjectId}
              onChange={(e) => setSelectedProjectId(e.target.value)}
              style={{ padding: '9px 14px', borderRadius: '8px', background: 'var(--bg-secondary)', color: '#fff', border: '1px solid var(--border-subtle)', fontSize: '13px' }}
            >
              {projects.map((p) => (
                <option key={p.id} value={p.id}>{p.name}</option>
              ))}
            </select>
            <label className="btn-primary" style={{ cursor: 'pointer', padding: '9px 16px' }}>
              <Upload size={16} />
              {uploading ? t('library.uploading') : t('library.upload_btn')}
              <input id="input-library-file" type="file" onChange={handleFileUpload} style={{ display: 'none' }} disabled={uploading} />
            </label>
          </div>
        )}
      </div>

      {/* Tabs */}
      <div style={{ display: 'flex', gap: '8px', marginBottom: '28px', borderBottom: '1px solid var(--border-subtle)', paddingBottom: '12px' }}>
        <button
          onClick={() => setActiveTab('project')}
          style={{
            padding: '8px 18px',
            borderRadius: '8px',
            fontSize: '13px',
            fontWeight: 600,
            background: activeTab === 'project' ? 'rgba(99, 102, 241, 0.2)' : 'transparent',
            color: activeTab === 'project' ? 'var(--accent-primary)' : 'var(--text-muted)',
            border: activeTab === 'project' ? '1px solid var(--accent-primary)' : '1px solid transparent',
            cursor: 'pointer',
          }}
        >
          {t('library.tab_project')}
        </button>
        <button
          onClick={() => setActiveTab('global')}
          style={{
            padding: '8px 18px',
            borderRadius: '8px',
            fontSize: '13px',
            fontWeight: 600,
            background: activeTab === 'global' ? 'rgba(99, 102, 241, 0.2)' : 'transparent',
            color: activeTab === 'global' ? 'var(--accent-primary)' : 'var(--text-muted)',
            border: activeTab === 'global' ? '1px solid var(--accent-primary)' : '1px solid transparent',
            cursor: 'pointer',
          }}
        >
          {t('library.tab_global')} ({globalRules.length})
        </button>
      </div>

      {/* Global Rules Tab */}
      {activeTab === 'global' && (
        <section>
          <div style={{ marginBottom: '20px' }}>
            <h2 style={{ fontSize: '15px', fontWeight: 700, color: 'var(--text-dim)', letterSpacing: '0.05em', marginBottom: '4px' }}>
              {t('library.global_rules_title')}
            </h2>
            <p style={{ fontSize: '13px', color: 'var(--text-muted)' }}>
              {t('library.global_rules_sub')}
            </p>
          </div>
          {globalRules.length === 0 ? (
            <div className="glass-panel" style={{ padding: '40px', textAlign: 'center', color: 'var(--text-muted)' }}>
              Chưa có quy tắc học toàn cục nào. Sử dụng lệnh <code>/learn &lt;quy tắc&gt;</code> trong chat để lưu.
            </div>
          ) : (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(340px, 1fr))', gap: '16px' }}>
              {globalRules.map((r) => (
                <div key={r.id} className="glass-panel" style={{ padding: '20px', borderLeft: '3px solid var(--accent-primary)' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '8px' }}>
                    <BookOpen size={16} color="var(--accent-primary)" />
                    <span style={{ fontSize: '11px', color: '#10b981', fontWeight: 600, background: 'rgba(16, 185, 129, 0.1)', padding: '2px 8px', borderRadius: '4px' }}>
                      {r.status.toUpperCase()}
                    </span>
                  </div>
                  <p style={{ fontSize: '13px', color: '#e2e8f0', lineHeight: 1.5 }}>
                    {r.content.replace(/^\[LEARNED_RULE:[^\]]+\]\s*/, '')}
                  </p>
                </div>
              ))}
            </div>
          )}
        </section>
      )}

      {/* Project Assets Tab */}
      {activeTab === 'project' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '36px' }}>
          {selectedArtifact && (
            <ArtifactViewer artifact={selectedArtifact} onClose={() => setSelectedArtifact(null)} />
          )}

          <ReferenceShelf references={libraryData?.references || []} projectId={selectedProjectId} onChanged={loadLibrary} />

          {/* Artifacts */}
          <section>
            <h2 style={{ fontSize: '15px', fontWeight: 600, color: 'var(--text-dim)', letterSpacing: '0.05em', marginBottom: '16px' }}>
              {t('library.artifacts_section')} ({libraryData?.artifacts.length || 0})
            </h2>
            {!libraryData?.artifacts?.length ? (
              <div className="glass-panel" style={{ padding: '32px', textAlign: 'center', color: 'var(--text-muted)' }}>
                {t('library.no_artifacts')}
              </div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '16px' }}>
                {libraryData.artifacts.map((a) => (
                  <div key={a.id} className="glass-panel" style={{ padding: '20px', cursor: 'pointer' }} onClick={() => setSelectedArtifact({ id: a.id, name: a.artifact_id, version: a.version, projectId: selectedProjectId, htmlPath: a.path })}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '10px' }}>
                      <span style={{ fontWeight: 600, fontSize: '14px' }}>{a.artifact_id}</span>
                      <span style={{ fontSize: '11px', padding: '2px 8px', borderRadius: '4px', background: 'rgba(99,102,241,0.15)', color: 'var(--accent-primary)' }}>v{a.version}</span>
                    </div>
                    <p style={{ fontSize: '12px', color: 'var(--text-dim)', marginBottom: '12px' }}>Đường dẫn: <code>{a.path}</code></p>
                    <span style={{ fontSize: '12px', color: 'var(--accent-primary)', fontWeight: 600 }}>Xem preview →</span>
                  </div>
                ))}
              </div>
            )}
          </section>

          {/* Uploads & Images */}
          <section>
            <h2 style={{ fontSize: '15px', fontWeight: 600, color: 'var(--text-dim)', letterSpacing: '0.05em', marginBottom: '16px' }}>
              {t('library.items_section')} ({libraryData?.items.length || 0})
            </h2>
            {!libraryData?.items?.length ? (
              <div className="glass-panel" style={{ padding: '32px', textAlign: 'center', color: 'var(--text-muted)' }}>
                {t('library.no_items')}
              </div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '16px' }}>
                {libraryData.items.map((i) => {
                  const isImage = i.type === 'generated_image' || /\.(png|jpe?g|webp|gif)$/i.test(i.path);
                  const imgUrl = `/api/backend/projects/${selectedProjectId}/files?path=${i.path}`;
                  return (
                    <div key={i.id} className="glass-panel" style={{ padding: '16px', display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
                      {isImage && (
                        <div
                          style={{ position: 'relative', width: '100%', height: '170px', borderRadius: '8px', overflow: 'hidden', background: '#0a0d14', marginBottom: '12px', cursor: 'pointer' }}
                          onClick={() => setLightboxUrl(imgUrl)}
                        >
                          <img src={imgUrl} alt={i.description || 'Preview'} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
                          <div style={{ position: 'absolute', bottom: '8px', right: '8px', background: 'rgba(0,0,0,0.65)', backdropFilter: 'blur(4px)', padding: '4px 8px', borderRadius: '4px', fontSize: '11px', color: '#fff', display: 'flex', alignItems: 'center', gap: '4px' }}>
                            <Maximize2 size={12} /> {t('library.preview')}
                          </div>
                        </div>
                      )}
                      <div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '8px' }}>
                          <span style={{ fontWeight: 600, fontSize: '13px', lineHeight: 1.4, flex: 1 }}>{i.description || 'Item'}</span>
                          <span style={{ fontSize: '11px', padding: '2px 8px', borderRadius: '4px', background: 'rgba(255,255,255,0.06)', color: 'var(--text-dim)', marginLeft: '8px' }}>{i.type}</span>
                        </div>
                        <p style={{ fontSize: '11px', color: 'var(--text-muted)', wordBreak: 'break-all' }}>
                          File: <code>{i.path}</code>
                        </p>
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </section>
        </div>
      )}

      {/* Lightbox Modal */}
      {lightboxUrl && (
        <div style={{ position: 'fixed', inset: 0, zIndex: 9999, background: 'rgba(0,0,0,0.85)', backdropFilter: 'blur(8px)', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '24px' }} onClick={() => setLightboxUrl(null)}>
          <div style={{ position: 'relative', maxWidth: '90vw', maxHeight: '90vh' }} onClick={(e) => e.stopPropagation()}>
            <button onClick={() => setLightboxUrl(null)} style={{ position: 'absolute', top: '-40px', right: '0', background: 'none', border: 'none', color: '#fff', cursor: 'pointer' }}>
              <X size={24} />
            </button>
            <img src={lightboxUrl} alt="High resolution" style={{ maxWidth: '100%', maxHeight: '85vh', borderRadius: '8px', objectFit: 'contain', boxShadow: '0 20px 50px rgba(0,0,0,0.5)' }} />
          </div>
        </div>
      )}
    </div>
  );
}

export default function LibraryPage() {
  return (
    <Suspense fallback={<div style={{ textAlign: 'center', padding: '60px', color: 'var(--text-muted)' }}>Loading...</div>}>
      <LibraryInner />
    </Suspense>
  );
}
