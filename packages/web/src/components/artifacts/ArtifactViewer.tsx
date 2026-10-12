'use client';

import React, { useState } from 'react';
import {
  Monitor,
  Smartphone,
  Code2,
  CheckCircle2,
  AlertCircle,
  ExternalLink,
  Layers,
  Image as ImageIcon,
} from 'lucide-react';

export interface ArtifactData {
  id?: string;
  name: string;
  version?: number;
  projectId: string;
  htmlPath?: string;
  desktopPng?: string;
  mobilePng?: string;
  checklist?: Array<{ item: string; status: 'passed' | 'failed' | 'warning'; note?: string }>;
  lintResults?: { html_valid?: boolean; css_lint_passed?: boolean; a11y_violations?: number; details?: string };
}

interface ArtifactViewerProps {
  artifact: ArtifactData;
  onClose?: () => void;
}

export default function ArtifactViewer({ artifact, onClose }: ArtifactViewerProps) {
  const [activeTab, setActiveTab] = useState<'preview' | 'screenshots' | 'checklist'>('preview');
  const [viewport, setViewport] = useState<'desktop' | 'mobile'>('desktop');

  const getFileUrl = (path?: string) => {
    if (!path) return '';
    return `/api/backend/projects/${artifact.projectId}/files?path=${encodeURIComponent(path)}`;
  };

  const renderPreviewTab = () => (
    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', width: '100%' }}>
      <div style={{ display: 'flex', gap: '8px', marginBottom: '16px', background: 'rgba(255,255,255,0.05)', padding: '4px', borderRadius: '8px' }}>
        <button
          onClick={() => setViewport('desktop')}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            padding: '6px 14px',
            borderRadius: '6px',
            fontSize: '12px',
            fontWeight: 600,
            background: viewport === 'desktop' ? 'var(--accent-primary)' : 'transparent',
            color: '#fff',
            border: 'none',
            cursor: 'pointer',
          }}
        >
          <Monitor size={14} /> Desktop (1440px)
        </button>
        <button
          onClick={() => setViewport('mobile')}
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
            padding: '6px 14px',
            borderRadius: '6px',
            fontSize: '12px',
            fontWeight: 600,
            background: viewport === 'mobile' ? 'var(--accent-primary)' : 'transparent',
            color: '#fff',
            border: 'none',
            cursor: 'pointer',
          }}
        >
          <Smartphone size={14} /> Mobile (390px)
        </button>
      </div>

      <div
        style={{
          width: viewport === 'desktop' ? '100%' : '390px',
          height: '620px',
          border: '1px solid var(--border-subtle)',
          borderRadius: viewport === 'mobile' ? '32px' : '12px',
          overflow: 'hidden',
          background: '#0B0F19',
          boxShadow: '0 20px 40px rgba(0,0,0,0.5)',
          transition: 'width 0.3s ease',
        }}
      >
        {artifact.htmlPath ? (
          <iframe
            src={getFileUrl(artifact.htmlPath)}
            title="Artifact Preview"
            style={{ width: '100%', height: '100%', border: 'none' }}
          />
        ) : (
          <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '100%', color: 'var(--text-muted)' }}>
            Không có file HTML preview
          </div>
        )}
      </div>
    </div>
  );

  const renderScreenshotsTab = () => (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(360px, 1fr))', gap: '20px' }}>
      <div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', fontWeight: 600, marginBottom: '8px' }}>
          <Monitor size={15} color="var(--accent-primary)" /> Desktop Render (1440px)
        </div>
        <div style={{ borderRadius: '10px', overflow: 'hidden', border: '1px solid var(--border-subtle)', background: '#000' }}>
          {artifact.desktopPng ? (
            <img src={getFileUrl(artifact.desktopPng)} alt="Desktop Screenshot" style={{ width: '100%', display: 'block' }} />
          ) : (
            <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-muted)', fontSize: '12px' }}>Chưa có screenshot Desktop</div>
          )}
        </div>
      </div>

      <div>
        <div style={{ display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', fontWeight: 600, marginBottom: '8px' }}>
          <Smartphone size={15} color="var(--accent-primary)" /> Mobile Render (390px)
        </div>
        <div style={{ borderRadius: '10px', overflow: 'hidden', border: '1px solid var(--border-subtle)', background: '#000', maxWidth: '320px', margin: '0 auto' }}>
          {artifact.mobilePng ? (
            <img src={getFileUrl(artifact.mobilePng)} alt="Mobile Screenshot" style={{ width: '100%', display: 'block' }} />
          ) : (
            <div style={{ padding: '40px', textAlign: 'center', color: 'var(--text-muted)', fontSize: '12px' }}>Chưa có screenshot Mobile</div>
          )}
        </div>
      </div>
    </div>
  );

  const renderChecklistTab = () => (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
      <div className="glass-panel" style={{ padding: '16px' }}>
        <h4 style={{ fontSize: '14px', fontWeight: 700, marginBottom: '12px' }}>Design & Code Review Checklist</h4>
        {!artifact.checklist || artifact.checklist.length === 0 ? (
          <p style={{ fontSize: '12px', color: 'var(--text-muted)' }}>Chưa có checklist đánh giá cho artifact này.</p>
        ) : (
          <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
            {artifact.checklist.map((c, i) => (
              <div key={i} style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: '10px', borderRadius: '8px', background: 'rgba(255,255,255,0.03)' }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                  {c.status === 'passed' ? <CheckCircle2 size={16} color="#10B981" /> : <AlertCircle size={16} color="#EF4444" />}
                  <span style={{ fontSize: '13px', fontWeight: 600 }}>{c.item}</span>
                </div>
                <span style={{ fontSize: '11px', color: 'var(--text-dim)' }}>{c.note || c.status}</span>
              </div>
            ))}
          </div>
        )}
      </div>

      {artifact.lintResults && (
        <div className="glass-panel" style={{ padding: '16px' }}>
          <h4 style={{ fontSize: '14px', fontWeight: 700, marginBottom: '12px' }}>Automated Lint Results</h4>
          <div style={{ display: 'flex', gap: '16px', fontSize: '13px' }}>
            <div>HTML Valid: <strong>{artifact.lintResults.html_valid ? '✅ Passed' : '❌ Failed'}</strong></div>
            <div>CSS Lint: <strong>{artifact.lintResults.css_lint_passed ? '✅ Passed' : '❌ Failed'}</strong></div>
            <div>A11y Violations: <strong>{artifact.lintResults.a11y_violations ?? 0}</strong></div>
          </div>
        </div>
      )}
    </div>
  );

  return (
    <div className="glass-panel" style={{ padding: '24px', borderRadius: '16px', position: 'relative', marginTop: '16px' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
          <Layers size={20} color="var(--accent-primary)" />
          <h3 style={{ fontSize: '16px', fontWeight: 800 }}>{artifact.name}</h3>
          {artifact.version && (
            <span style={{ fontSize: '11px', fontWeight: 700, padding: '2px 8px', borderRadius: '4px', background: 'rgba(99, 102, 241, 0.2)', color: 'var(--accent-primary)' }}>
              v{artifact.version}
            </span>
          )}
        </div>

        <div style={{ display: 'flex', gap: '8px' }}>
          <button
            onClick={() => setActiveTab('preview')}
            style={{
              padding: '6px 12px',
              borderRadius: '6px',
              fontSize: '12px',
              fontWeight: 600,
              background: activeTab === 'preview' ? 'var(--bg-tertiary)' : 'transparent',
              color: '#fff',
              border: 'none',
              cursor: 'pointer',
            }}
          >
            Live Preview
          </button>
          <button
            onClick={() => setActiveTab('screenshots')}
            style={{
              padding: '6px 12px',
              borderRadius: '6px',
              fontSize: '12px',
              fontWeight: 600,
              background: activeTab === 'screenshots' ? 'var(--bg-tertiary)' : 'transparent',
              color: '#fff',
              border: 'none',
              cursor: 'pointer',
            }}
          >
            Screenshots
          </button>
          <button
            onClick={() => setActiveTab('checklist')}
            style={{
              padding: '6px 12px',
              borderRadius: '6px',
              fontSize: '12px',
              fontWeight: 600,
              background: activeTab === 'checklist' ? 'var(--bg-tertiary)' : 'transparent',
              color: '#fff',
              border: 'none',
              cursor: 'pointer',
            }}
          >
            Checklist & Audit
          </button>
          {onClose && (
            <button onClick={onClose} style={{ marginLeft: '12px', background: 'transparent', border: 'none', color: 'var(--text-muted)', cursor: 'pointer', fontSize: '13px' }}>
              Đóng
            </button>
          )}
        </div>
      </div>

      {/* Body */}
      {activeTab === 'preview' && renderPreviewTab()}
      {activeTab === 'screenshots' && renderScreenshotsTab()}
      {activeTab === 'checklist' && renderChecklistTab()}
    </div>
  );
}
