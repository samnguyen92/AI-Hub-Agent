'use client';

import React, { useState } from 'react';
import { ExternalLink, Maximize2, X, Globe } from 'lucide-react';

interface ChatMessageContentProps {
  content: string;
  projectId: string;
}

export default function ChatMessageContent({ content, projectId }: ChatMessageContentProps) {
  const [lightboxSrc, setLightboxSrc] = useState<string | null>(null);
  const apiBase = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

  const resolveMediaUrl = (rawUrl: string): string => {
    if (rawUrl.startsWith('http://') || rawUrl.startsWith('https://')) return rawUrl;
    if (rawUrl.startsWith('/api/backend/')) {
      return `${apiBase}${rawUrl.replace('/api/backend', '')}`;
    }
    if (rawUrl.startsWith('sandbox:')) {
      const parts = rawUrl.split('/');
      const filename = parts[parts.length - 1];
      return `${apiBase}/projects/${projectId}/files?path=${encodeURIComponent(filename)}`;
    }
    const clean = rawUrl.replace(/^\.?\//, '');
    return `${apiBase}/projects/${projectId}/files?path=${encodeURIComponent(clean)}`;
  };

  // Split content by images: ![alt](url)
  const imageRegex = /!\[([^\]]*)\]\(([^)]+)\)/g;
  const parts: React.ReactNode[] = [];
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = imageRegex.exec(content)) !== null) {
    if (match.index > lastIndex) {
      parts.push(renderTextAndLinks(content.substring(lastIndex, match.index), projectId, apiBase));
    }
    const alt = match[1] || 'Hình ảnh';
    const rawUrl = match[2];
    const resolvedUrl = resolveMediaUrl(rawUrl);

    parts.push(
      <div
        key={`img-${match.index}`}
        style={{
          margin: '12px 0',
          borderRadius: '12px',
          overflow: 'hidden',
          border: '1px solid var(--border-subtle)',
          background: 'rgba(0, 0, 0, 0.3)',
          maxWidth: '560px',
        }}
      >
        <div style={{ position: 'relative', cursor: 'pointer' }} onClick={() => setLightboxSrc(resolvedUrl)}>
          <img
            src={resolvedUrl}
            alt={alt}
            style={{
              width: '100%',
              maxHeight: '420px',
              objectFit: 'contain',
              display: 'block',
              background: '#0d1117',
            }}
          />
          <button
            type="button"
            style={{
              position: 'absolute',
              top: '8px',
              right: '8px',
              background: 'rgba(0,0,0,0.6)',
              border: 'none',
              borderRadius: '6px',
              padding: '6px',
              color: '#fff',
              cursor: 'pointer',
            }}
            title="Xem toàn màn hình"
          >
            <Maximize2 size={14} />
          </button>
        </div>
        {alt && (
          <div style={{ padding: '8px 12px', fontSize: '12px', color: 'var(--text-dim)', borderTop: '1px solid var(--border-subtle)' }}>
            🖼️ {alt}
          </div>
        )}
      </div>
    );
    lastIndex = imageRegex.lastIndex;
  }

  if (lastIndex < content.length) {
    parts.push(renderTextAndLinks(content.substring(lastIndex), projectId, apiBase));
  }

  return (
    <>
      <div style={{ fontSize: '14px', lineHeight: '1.6' }}>{parts}</div>
      {lightboxSrc && (
        <div
          onClick={() => setLightboxSrc(null)}
          style={{
            position: 'fixed',
            inset: 0,
            background: 'rgba(0,0,0,0.85)',
            zIndex: 9999,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            padding: '24px',
          }}
        >
          <div style={{ position: 'relative', maxWidth: '90vw', maxHeight: '90vh' }} onClick={(e) => e.stopPropagation()}>
            <img
              src={lightboxSrc}
              alt="Fullscreen Preview"
              style={{ maxWidth: '100%', maxHeight: '85vh', borderRadius: '8px', display: 'block', boxShadow: '0 20px 40px rgba(0,0,0,0.8)' }}
            />
            <button
              onClick={() => setLightboxSrc(null)}
              style={{
                position: 'absolute',
                top: '-40px',
                right: '0',
                background: 'rgba(255,255,255,0.2)',
                border: 'none',
                color: '#fff',
                borderRadius: '50%',
                width: '32px',
                height: '32px',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                cursor: 'pointer',
              }}
            >
              <X size={18} />
            </button>
          </div>
        </div>
      )}
    </>
  );
}

function renderTextAndLinks(text: string, projectId: string, apiBase: string): React.ReactNode {
  // Check for sandbox preview links: [Title](sandbox:...)
  const linkRegex = /\[([^\]]+)\]\(([^)]+)\)/g;
  const elements: React.ReactNode[] = [];
  let curIndex = 0;
  let linkMatch: RegExpExecArray | null;

  while ((linkMatch = linkRegex.exec(text)) !== null) {
    if (linkMatch.index > curIndex) {
      elements.push(
        <span key={`txt-${curIndex}`} style={{ whiteSpace: 'pre-wrap' }}>
          {text.substring(curIndex, linkMatch.index)}
        </span>
      );
    }
    const label = linkMatch[1];
    const url = linkMatch[2];

    if (url.startsWith('sandbox:') || url.endsWith('.html')) {
      const parts = url.split('/');
      const filename = parts[parts.length - 1] || 'index.html';
      const liveUrl = `${apiBase}/projects/${projectId}/files?path=${encodeURIComponent(filename)}`;
      elements.push(
        <a
          key={`live-${linkMatch.index}`}
          href={liveUrl}
          target="_blank"
          rel="noreferrer"
          style={{
            display: 'inline-flex',
            alignItems: 'center',
            gap: '6px',
            margin: '6px 4px',
            padding: '4px 10px',
            borderRadius: '6px',
            background: 'rgba(99, 102, 241, 0.15)',
            border: '1px solid rgba(99, 102, 241, 0.4)',
            color: '#818CF8',
            fontSize: '13px',
            fontWeight: 600,
            textDecoration: 'none',
          }}
        >
          <Globe size={14} /> 🌐 {label || 'Mở xem trang HTML'} <ExternalLink size={12} />
        </a>
      );
    } else {
      elements.push(
        <a
          key={`link-${linkMatch.index}`}
          href={url}
          target="_blank"
          rel="noreferrer"
          style={{ color: 'var(--accent-primary)', textDecoration: 'underline' }}
        >
          {label} <ExternalLink size={11} style={{ display: 'inline' }} />
        </a>
      );
    }
    curIndex = linkRegex.lastIndex;
  }

  if (curIndex < text.length) {
    elements.push(
      <span key={`txt-${curIndex}`} style={{ whiteSpace: 'pre-wrap' }}>
        {text.substring(curIndex)}
      </span>
    );
  }

  return <React.Fragment key={`block-${Math.random()}`}>{elements}</React.Fragment>;
}
