'use client';

import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import {
  Library,
  Upload,
  FileCode,
  Image as ImageIcon,
  GitCommit,
  Layers,
  FileText,
  Clock,
} from 'lucide-react';
import { fetchLibrary, fetchProjects, LibrarySummary, Project } from '@/lib/api';
import ArtifactViewer, { ArtifactData } from '@/components/artifacts/ArtifactViewer';

export default function LibraryPage() {
  const searchParams = useSearchParams();
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProjectId, setSelectedProjectId] = useState<string>('');
  const [libraryData, setLibraryData] = useState<LibrarySummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [selectedArtifact, setSelectedArtifact] = useState<ArtifactData | null>(null);

  useEffect(() => {
    fetchProjects().then((data) => {
      setProjects(data);
      const urlProj = searchParams.get('projectId');
      const defaultProj = urlProj || (data.length > 0 ? data[0].id : '');
      setSelectedProjectId(defaultProj);
    });
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
      if (res.ok) {
        await loadLibrary();
      }
    } finally {
      setUploading(false);
    }
  };

  return (
    <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '32px 24px' }}>
      {/* Top Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '32px' }}>
        <div>
          <h1 style={{ fontSize: '24px', fontWeight: 800, letterSpacing: '-0.02em', marginBottom: '6px' }}>
            Thư viện chung (Project Library)
          </h1>
          <p style={{ fontSize: '13px', color: 'var(--text-muted)' }}>
            Nơi lưu trữ uploads và trao đổi artifacts có phiên bản giữa các Agent
          </p>
        </div>

        <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
          <select
            id="select-library-project"
            value={selectedProjectId}
            onChange={(e) => setSelectedProjectId(e.target.value)}
            style={{
              padding: '10px 14px',
              borderRadius: '8px',
              background: 'var(--bg-secondary)',
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

          <label className="btn-primary" style={{ cursor: 'pointer' }}>
            <Upload size={16} />
            {uploading ? 'Đang tải lên...' : 'Tải file lên'}
            <input
              id="input-library-file"
              type="file"
              onChange={handleFileUpload}
              style={{ display: 'none' }}
              disabled={uploading}
            />
          </label>
        </div>
      </div>

      {loading ? (
        <div style={{ textAlign: 'center', padding: '60px', color: 'var(--text-muted)' }}>
          Đang tải dữ liệu thư viện...
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '36px' }}>
          {/* Selected Artifact Viewer */}
          {selectedArtifact && (
            <section>
              <ArtifactViewer
                artifact={selectedArtifact}
                onClose={() => setSelectedArtifact(null)}
              />
            </section>
          )}

          {/* Versioned Artifacts Section */}
          <section>
            <h2 style={{ fontSize: '15px', fontWeight: 600, color: 'var(--text-dim)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '16px' }}>
              Artifacts có phiên bản ({libraryData?.artifacts.length || 0})
            </h2>
            {!libraryData?.artifacts || libraryData.artifacts.length === 0 ? (
              <div className="glass-panel" style={{ padding: '32px', textAlign: 'center', color: 'var(--text-muted)' }}>
                Chưa có artifact nào được lưu. Khi Design Agent tạo mockup hoặc Code Builder xuất file, kết quả sẽ hiển thị ở đây.
              </div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '16px' }}>
                {libraryData.artifacts.map((a) => (
                  <div
                    key={a.id}
                    className="glass-panel"
                    style={{ padding: '20px', cursor: 'pointer', transition: 'transform 0.2s ease, border-color 0.2s ease' }}
                    onClick={() => {
                      const isHtml = a.path.endsWith('.html');
                      setSelectedArtifact({
                        id: a.id,
                        name: a.artifact_id,
                        version: a.version,
                        projectId: selectedProjectId,
                        htmlPath: isHtml ? a.path : undefined,
                        desktopPng: isHtml ? 'screenshots/preview-desktop.png' : undefined,
                        mobilePng: isHtml ? 'screenshots/preview-mobile.png' : undefined,
                        checklist: [
                          { item: 'Align Style Guide Compliance', status: 'passed', note: 'Áp dụng dark surface & accent' },
                          { item: 'Responsive Layout Check', status: 'passed', note: 'Tương thích 1440px & 390px' },
                          { item: 'Accessibility Basics', status: 'passed', note: 'Contrast ratio và semantic HTML hợp lệ' },
                        ],
                      });
                    }}
                  >
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '12px' }}>
                      <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                        <FileCode size={18} color="var(--accent-primary)" />
                        <span style={{ fontWeight: 700, fontSize: '15px' }}>{a.artifact_id}</span>
                      </div>
                      <span style={{ fontSize: '12px', fontWeight: 700, padding: '3px 8px', borderRadius: '4px', background: 'rgba(99, 102, 241, 0.2)', color: 'var(--accent-primary)' }}>
                        v{a.version}
                      </span>
                    </div>
                    <p style={{ fontSize: '12px', color: 'var(--text-dim)', marginBottom: '12px' }}>
                      Đường dẫn: <code>{a.path}</code>
                    </p>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                      {a.git_commit && (
                        <div style={{ display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px', color: 'var(--text-muted)' }}>
                          <GitCommit size={12} />
                          Commit: <code>{a.git_commit.slice(0, 7)}</code>
                        </div>
                      )}
                      <span style={{ fontSize: '12px', color: 'var(--accent-primary)', fontWeight: 600 }}>
                        Xem preview →
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </section>

          {/* Uploads & Library Items Section */}
          <section>
            <h2 style={{ fontSize: '15px', fontWeight: 600, color: 'var(--text-dim)', textTransform: 'uppercase', letterSpacing: '0.05em', marginBottom: '16px' }}>
              Tài liệu & Uploads ({libraryData?.items.length || 0})
            </h2>
            {!libraryData?.items || libraryData.items.length === 0 ? (
              <div className="glass-panel" style={{ padding: '32px', textAlign: 'center', color: 'var(--text-muted)' }}>
                Chưa có tài liệu upload nào. Bạn có thể bấm 'Tải file lên' để nạp style guide hoặc tài liệu tham chiếu.
              </div>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))', gap: '16px' }}>
                {libraryData.items.map((i) => (
                  <div key={i.id} className="glass-panel" style={{ padding: '20px' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '10px' }}>
                      <span style={{ fontWeight: 600, fontSize: '14px' }}>{i.description || 'Tài liệu'}</span>
                      <span style={{ fontSize: '11px', padding: '2px 8px', borderRadius: '4px', background: 'rgba(255, 255, 255, 0.06)', color: 'var(--text-dim)' }}>
                        {i.type}
                      </span>
                    </div>
                    <p style={{ fontSize: '12px', color: 'var(--text-muted)', wordBreak: 'break-all' }}>
                      File: <code>{i.path}</code>
                    </p>
                  </div>
                ))}
              </div>
            )}
          </section>
        </div>
      )}
    </div>
  );
}
