'use client';

import React, { useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { ExternalLink, Maximize2, X, Globe, Copy, Check } from 'lucide-react';
import StructuredDataCard from './StructuredDataCard';

interface ChatMessageContentProps {
  content: string;
  projectId: string;
}

export default function ChatMessageContent({ content, projectId }: ChatMessageContentProps) {
  const [lightboxSrc, setLightboxSrc] = useState<string | null>(null);
  const [copiedCodeIdx, setCopiedCodeIdx] = useState<number | null>(null);
  const apiBase = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

  const projectFileUrl = (path: string): string => {
    const safePath = path.split('/').filter(Boolean).map(encodeURIComponent).join('/');
    return `${apiBase}/projects/${projectId}/files/${safePath}`;
  };

  const projectFilePath = (rawUrl: string): string => {
    const parsed = new URL(rawUrl, 'http://hub.local');
    const queryPath = parsed.searchParams.get('path');
    if (queryPath) return queryPath;
    const marker = `/projects/${projectId}/files/`;
    const index = parsed.pathname.indexOf(marker);
    return index >= 0 ? decodeURIComponent(parsed.pathname.slice(index + marker.length)) : '';
  };

  const resolveMediaUrl = (rawUrl: string): string => {
    if (!rawUrl) return '';
    if (rawUrl.startsWith('http://') || rawUrl.startsWith('https://')) return rawUrl;
    if (rawUrl.startsWith('/api/backend/')) {
      return `${apiBase}${rawUrl.replace('/api/backend', '')}`;
    }
    if (rawUrl.startsWith('sandbox:')) {
      return projectFileUrl(rawUrl.replace(/^sandbox:\/\/?/, ''));
    }
    const clean = rawUrl.replace(/^\.?\//, '');
    return `${apiBase}/projects/${projectId}/files?path=${encodeURIComponent(clean)}`;
  };

  const copyToClipboard = (text: string, idx: number) => {
    navigator.clipboard.writeText(text);
    setCopiedCodeIdx(idx);
    setTimeout(() => setCopiedCodeIdx(null), 2000);
  };

  return (
    <>
      <div className="prose prose-invert max-w-none" style={{ fontSize: '14px', lineHeight: '1.65' }}>
        <ReactMarkdown
          remarkPlugins={[remarkGfm]}
          components={{
            table: ({ ...props }) => (
              <div style={{ overflowX: 'auto', margin: '14px 0' }}>
                <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px', background: 'rgba(0,0,0,0.25)', borderRadius: '8px', overflow: 'hidden' }} {...props} />
              </div>
            ),
            thead: ({ ...props }) => <thead style={{ background: 'rgba(255,255,255,0.06)' }} {...props} />,
            th: ({ ...props }) => (
              <th style={{ padding: '10px 12px', borderBottom: '1px solid var(--border-subtle)', color: 'var(--text-muted)', fontWeight: 600, textAlign: 'left' }} {...props} />
            ),
            td: ({ ...props }) => (
              <td style={{ padding: '10px 12px', borderBottom: '1px solid rgba(255,255,255,0.04)' }} {...props} />
            ),
            h1: ({ ...props }) => <h1 style={{ fontSize: '18px', fontWeight: 800, margin: '16px 0 8px', color: '#fff' }} {...props} />,
            h2: ({ ...props }) => <h2 style={{ fontSize: '16px', fontWeight: 700, margin: '14px 0 6px', color: '#fff' }} {...props} />,
            h3: ({ ...props }) => <h3 style={{ fontSize: '15px', fontWeight: 700, margin: '12px 0 4px', color: '#E2E8F0' }} {...props} />,
            p: ({ ...props }) => <p style={{ margin: '6px 0' }} {...props} />,
            ul: ({ ...props }) => <ul style={{ paddingLeft: '20px', margin: '6px 0' }} {...props} />,
            ol: ({ ...props }) => <ol style={{ paddingLeft: '20px', margin: '6px 0' }} {...props} />,
            li: ({ ...props }) => <li style={{ margin: '2px 0' }} {...props} />,
            a: ({ href, children, ...props }) => {
              const url = href || '';
              const filePath = projectFilePath(url);
              const fileUrl = (filePath || url.split('?')[0]).toLowerCase();
              if (
                url.startsWith('/api/backend/projects/') &&
                (fileUrl.endsWith('.zip') || fileUrl.endsWith('.html'))
              ) {
                const downloadUrl = fileUrl.endsWith('.html')
                  ? projectFileUrl(filePath)
                  : `${apiBase}${url.replace('/api/backend', '')}`;
                return (
                  <a href={downloadUrl} target="_blank" rel="noreferrer" style={{ color: 'var(--accent-primary)', textDecoration: 'underline' }} {...props}>
                    {children || (fileUrl.endsWith('.zip') ? 'Tải file ZIP' : 'Mở file HTML')} <ExternalLink size={11} style={{ display: 'inline' }} />
                  </a>
                );
              }
              if (url.startsWith('sandbox:') || url.endsWith('.html')) {
                const filePath = projectFilePath(url) || url.replace(/^sandbox:\/\/?/, '') || 'index.html';
                const liveUrl = projectFileUrl(filePath);
                return (
                  <a
                    href={liveUrl}
                    target="_blank"
                    rel="noreferrer"
                    style={{
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '5px',
                      padding: '2px 8px',
                      borderRadius: '6px',
                      background: 'rgba(99, 102, 241, 0.15)',
                      border: '1px solid rgba(99, 102, 241, 0.4)',
                      color: '#818CF8',
                      fontSize: '12px',
                      fontWeight: 600,
                      textDecoration: 'none',
                    }}
                    {...props}
                  >
                    <Globe size={13} /> {children || 'Mở xem trang HTML'} <ExternalLink size={11} />
                  </a>
                );
              }
              return (
                <a href={url} target="_blank" rel="noreferrer" style={{ color: 'var(--accent-primary)', textDecoration: 'underline' }} {...props}>
                  {children} <ExternalLink size={11} style={{ display: 'inline' }} />
                </a>
              );
            },
            img: ({ src, alt, ...props }) => {
              const resolved = resolveMediaUrl(src || '');
              return (
                <div style={{ margin: '12px 0', borderRadius: '12px', overflow: 'hidden', border: '1px solid var(--border-subtle)', background: 'rgba(0, 0, 0, 0.3)', maxWidth: '560px' }}>
                  <div style={{ position: 'relative', cursor: 'pointer' }} onClick={() => setLightboxSrc(resolved)}>
                    <img src={resolved} alt={alt || 'Image'} style={{ width: '100%', maxHeight: '420px', objectFit: 'contain', display: 'block', background: '#0d1117' }} {...props} />
                    <button type="button" style={{ position: 'absolute', top: '8px', right: '8px', background: 'rgba(0,0,0,0.6)', border: 'none', borderRadius: '6px', padding: '6px', color: '#fff', cursor: 'pointer' }} title="Toàn màn hình">
                      <Maximize2 size={14} />
                    </button>
                  </div>
                  {alt && <div style={{ padding: '8px 12px', fontSize: '12px', color: 'var(--text-dim)', borderTop: '1px solid var(--border-subtle)' }}>🖼️ {alt}</div>}
                </div>
              );
            },
            code: ({ className, children, ...props }) => {
              const match = /language-(\w+)/.exec(className || '');
              const lang = match ? match[1].toLowerCase() : '';
              const codeStr = String(children).replace(/\n$/, '');

              if (lang === 'json' && (codeStr.trim().startsWith('{') || codeStr.trim().startsWith('['))) {
                return <StructuredDataCard rawJson={codeStr} projectId={projectId} />;
              }

              if (!className) {
                return (
                  <code style={{ background: 'rgba(255, 255, 255, 0.08)', padding: '2px 6px', borderRadius: '4px', fontSize: '12px', color: '#F1F5F9' }} {...props}>
                    {children}
                  </code>
                );
              }

              const codeId = Math.floor(Math.random() * 100000);
              return (
                <div style={{ position: 'relative', margin: '10px 0' }}>
                  <button
                    onClick={() => copyToClipboard(codeStr, codeId)}
                    style={{ position: 'absolute', top: '8px', right: '8px', background: 'rgba(255,255,255,0.1)', border: '1px solid var(--border-subtle)', borderRadius: '6px', padding: '4px 8px', color: '#cbd5e1', fontSize: '11px', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '4px' }}
                  >
                    {copiedCodeIdx === codeId ? <><Check size={12} color="#10b981" /> Đã sao chép</> : <><Copy size={12} /> Sao chép</>}
                  </button>
                  <pre style={{ padding: '14px', borderRadius: '8px', background: 'rgba(0, 0, 0, 0.45)', border: '1px solid var(--border-subtle)', fontSize: '12px', color: '#E2E8F0', overflowX: 'auto' }}>
                    <code className={className} {...props}>{children}</code>
                  </pre>
                </div>
              );
            },
          }}
        >
          {content}
        </ReactMarkdown>
      </div>

      {lightboxSrc && (
        <div onClick={() => setLightboxSrc(null)} style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.85)', zIndex: 9999, display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '24px' }}>
          <div style={{ position: 'relative', maxWidth: '90vw', maxHeight: '90vh' }} onClick={(e) => e.stopPropagation()}>
            <img src={lightboxSrc} alt="Preview" style={{ maxWidth: '100%', maxHeight: '85vh', borderRadius: '8px', display: 'block', boxShadow: '0 20px 40px rgba(0,0,0,0.8)' }} />
            <button onClick={() => setLightboxSrc(null)} style={{ position: 'absolute', top: '-40px', right: '0', background: 'rgba(255,255,255,0.2)', border: 'none', color: '#fff', borderRadius: '50%', width: '32px', height: '32px', display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer' }}>
              <X size={18} />
            </button>
          </div>
        </div>
      )}
    </>
  );
}
