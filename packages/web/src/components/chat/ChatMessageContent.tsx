'use client';

import React, { useState } from 'react';
import { ExternalLink, Maximize2, X, Globe } from 'lucide-react';
import StructuredDataCard from './StructuredDataCard';

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

  // 1. Split content by code blocks: ```json ... ``` or other code blocks
  const codeBlockRegex = /```([a-zA-Z0-9_-]*)\n([\s\S]*?)```/g;
  const blocks: React.ReactNode[] = [];
  let lastIdx = 0;
  let codeMatch: RegExpExecArray | null;

  while ((codeMatch = codeBlockRegex.exec(content)) !== null) {
    if (codeMatch.index > lastIdx) {
      blocks.push(
        renderProseAndImages(
          content.substring(lastIdx, codeMatch.index),
          projectId,
          apiBase,
          resolveMediaUrl,
          setLightboxSrc
        )
      );
    }

    const lang = (codeMatch[1] || '').toLowerCase();
    const rawCode = codeMatch[2].trim();

    if (lang === 'json' && (rawCode.startsWith('{') || rawCode.startsWith('['))) {
      blocks.push(
        <StructuredDataCard key={`json-${codeMatch.index}`} rawJson={rawCode} projectId={projectId} />
      );
    } else {
      blocks.push(
        <pre
          key={`code-${codeMatch.index}`}
          style={{
            margin: '10px 0',
            padding: '12px',
            borderRadius: '8px',
            background: 'rgba(0, 0, 0, 0.45)',
            border: '1px solid var(--border-subtle)',
            fontSize: '12px',
            color: '#E2E8F0',
            overflowX: 'auto',
          }}
        >
          <code>{rawCode}</code>
        </pre>
      );
    }
    lastIdx = codeBlockRegex.lastIndex;
  }

  if (lastIdx < content.length) {
    blocks.push(
      renderProseAndImages(
        content.substring(lastIdx),
        projectId,
        apiBase,
        resolveMediaUrl,
        setLightboxSrc
      )
    );
  }

  return (
    <>
      <div style={{ fontSize: '14px', lineHeight: '1.6' }}>{blocks}</div>

      {/* Lightbox for fullscreen preview */}
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

function renderProseAndImages(
  text: string,
  projectId: string,
  apiBase: string,
  resolveMediaUrl: (url: string) => string,
  setLightboxSrc: (url: string) => void
): React.ReactNode {
  // Split by images: ![alt](url)
  const imageRegex = /!\[([^\]]*)\]\(([^)]+)\)/g;
  const parts: React.ReactNode[] = [];
  let lastIndex = 0;
  let match: RegExpExecArray | null;

  while ((match = imageRegex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      parts.push(renderFormattedText(text.substring(lastIndex, match.index), projectId, apiBase));
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

  if (lastIndex < text.length) {
    parts.push(renderFormattedText(text.substring(lastIndex), projectId, apiBase));
  }

  return <React.Fragment key={`prose-${Math.random()}`}>{parts}</React.Fragment>;
}

function renderFormattedText(text: string, projectId: string, apiBase: string): React.ReactNode {
  const lines = text.split('\n');
  const renderedLines: React.ReactNode[] = [];

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i];

    // Markdown Headers: ### Heading
    if (line.startsWith('### ')) {
      renderedLines.push(
        <div key={`h3-${i}`} style={{ fontWeight: 700, fontSize: '15px', color: '#FFFFFF', margin: '12px 0 4px' }}>
          {renderInlineElements(line.replace(/^###\s+/, ''), projectId, apiBase)}
        </div>
      );
      continue;
    }
    if (line.startsWith('## ')) {
      renderedLines.push(
        <div key={`h2-${i}`} style={{ fontWeight: 800, fontSize: '16px', color: '#FFFFFF', margin: '14px 0 6px' }}>
          {renderInlineElements(line.replace(/^##\s+/, ''), projectId, apiBase)}
        </div>
      );
      continue;
    }

    // Markdown List items: - Item
    if (line.trim().startsWith('- ')) {
      renderedLines.push(
        <div key={`li-${i}`} style={{ display: 'flex', gap: '8px', margin: '4px 0 4px 8px' }}>
          <span style={{ color: 'var(--accent-primary)', fontSize: '14px' }}>•</span>
          <span style={{ flex: 1 }}>{renderInlineElements(line.trim().substring(2), projectId, apiBase)}</span>
        </div>
      );
      continue;
    }

    // Regular line
    if (line.trim() === '') {
      renderedLines.push(<div key={`br-${i}`} style={{ height: '8px' }} />);
    } else {
      renderedLines.push(
        <div key={`line-${i}`} style={{ margin: '2px 0' }}>
          {renderInlineElements(line, projectId, apiBase)}
        </div>
      );
    }
  }

  return <React.Fragment key={`fmt-${Math.random()}`}>{renderedLines}</React.Fragment>;
}

function renderInlineElements(line: string, projectId: string, apiBase: string): React.ReactNode {
  // Parse inline [label](url) and **bold**
  const regex = /\[([^\]]+)\]\(([^)]+)\)|\*\*([^*]+)\*\*/g;
  const elements: React.ReactNode[] = [];
  let lastIdx = 0;
  let match: RegExpExecArray | null;

  while ((match = regex.exec(line)) !== null) {
    if (match.index > lastIdx) {
      elements.push(line.substring(lastIdx, match.index));
    }

    if (match[1] && match[2]) {
      // It's a link: [label](url)
      const label = match[1];
      const url = match[2];

      if (url.startsWith('sandbox:') || url.endsWith('.html')) {
        const parts = url.split('/');
        const filename = parts[parts.length - 1] || 'index.html';
        const liveUrl = `${apiBase}/projects/${projectId}/files?path=${encodeURIComponent(filename)}`;
        elements.push(
          <a
            key={`live-${match.index}`}
            href={liveUrl}
            target="_blank"
            rel="noreferrer"
            style={{
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
              margin: '2px 4px',
              padding: '2px 8px',
              borderRadius: '6px',
              background: 'rgba(99, 102, 241, 0.15)',
              border: '1px solid rgba(99, 102, 241, 0.4)',
              color: '#818CF8',
              fontSize: '12px',
              fontWeight: 600,
              textDecoration: 'none',
            }}
          >
            <Globe size={13} /> 🌐 {label || 'Mở xem trang HTML'} <ExternalLink size={11} />
          </a>
        );
      } else {
        elements.push(
          <a
            key={`link-${match.index}`}
            href={url}
            target="_blank"
            rel="noreferrer"
            style={{ color: 'var(--accent-primary)', textDecoration: 'underline' }}
          >
            {label} <ExternalLink size={11} style={{ display: 'inline' }} />
          </a>
        );
      }
    } else if (match[3]) {
      // It's bold text: **bold**
      elements.push(
        <strong key={`b-${match.index}`} style={{ color: '#F8FAFC', fontWeight: 700 }}>
          {match[3]}
        </strong>
      );
    }

    lastIdx = regex.lastIndex;
  }

  if (lastIdx < line.length) {
    elements.push(line.substring(lastIdx));
  }

  return <React.Fragment key={`inline-${Math.random()}`}>{elements}</React.Fragment>;
}
