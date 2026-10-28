'use client';

import React, { useMemo, useState } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { ExternalLink, Maximize2, Globe, Copy, Check, Image as ImageIcon } from 'lucide-react';
import StructuredDataCard from './StructuredDataCard';
import ImageLightboxModal from './ImageLightboxModal';

interface ChatMessageContentProps {
  content: string;
  projectId: string;
}

const WORKSPACE_FOLDER_PREFIXES = ['screenshots/', 'mockups/', 'exports/', 'generated/', 'attachments/', 'assets/'];

export default function ChatMessageContent({ content, projectId }: ChatMessageContentProps) {
  const [lightboxSrc, setLightboxSrc] = useState<string | null>(null);
  const [copiedCodeIdx, setCopiedCodeIdx] = useState<number | null>(null);
  const apiBase = process.env.NEXT_PUBLIC_API_URL || '/api/backend';

  const projectFileUrl = (path: string): string => {
    const safePath = path.split('/').filter(Boolean).map(encodeURIComponent).join('/');
    return `${apiBase}/projects/${projectId}/files/${safePath}`;
  };

  const extractWorkspaceRelPath = (rawUrl: string): string => {
    if (!rawUrl) return '';
    try {
      const parsed = new URL(rawUrl, 'http://hub.local');
      const queryPath = parsed.searchParams.get('path');
      if (queryPath) return queryPath.replace(/^\/+/, '');

      const marker = `/projects/${projectId}/files/`;
      const idx = parsed.pathname.indexOf(marker);
      if (idx >= 0) {
        return decodeURIComponent(parsed.pathname.slice(idx + marker.length)).replace(/^\/+/, '');
      }

      const anyProjMatch = parsed.pathname.match(/\/projects\/[^/]+\/files\/(.+)$/);
      if (anyProjMatch) {
        return decodeURIComponent(anyProjMatch[1]).replace(/^\/+/, '');
      }

      const cleanPath = decodeURIComponent(parsed.pathname).replace(/^\/+/, '');
      if (
        WORKSPACE_FOLDER_PREFIXES.some((prefix) => cleanPath.startsWith(prefix)) ||
        /\.(png|jpe?g|webp|gif|svg|html?|zip|wpress)$/i.test(cleanPath)
      ) {
        // Avoid treating app routes like /chat, /projects, /approvals as workspace files
        const firstSeg = cleanPath.split('/')[0].toLowerCase();
        if (!['chat', 'projects', 'approvals', 'library', 'agents', 'reports', 'login', 'api'].includes(firstSeg)) {
          return cleanPath;
        }
      }
    } catch {
      // Ignore malformed URLs
    }
    return '';
  };

  const resolveMediaUrl = (rawUrl: string): string => {
    if (!rawUrl) return '';
    if (rawUrl.startsWith('/api/backend/')) {
      return `${apiBase}${rawUrl.replace('/api/backend', '')}`;
    }
    if (rawUrl.startsWith('sandbox:')) {
      return projectFileUrl(rawUrl.replace(/^sandbox:\/\/?/, ''));
    }

    const wsRelPath = extractWorkspaceRelPath(rawUrl);
    if (wsRelPath) {
      return `${apiBase}/projects/${projectId}/files?path=${encodeURIComponent(wsRelPath)}`;
    }

    if (rawUrl.startsWith('http://') || rawUrl.startsWith('https://')) {
      return rawUrl;
    }

    const clean = rawUrl.replace(/^\.?\//, '');
    return `${apiBase}/projects/${projectId}/files?path=${encodeURIComponent(clean)}`;
  };

  // Collect actual embedded markdown image URLs in this message so preview links can snap to real rendered screenshots
  const embeddedImages = useMemo(() => {
    const imgs: string[] = [];
    const regex = /!\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g;
    let match: RegExpExecArray | null;
    while ((match = regex.exec(content)) !== null) {
      const resolved = resolveMediaUrl(match[1]);
      if (resolved) imgs.push(resolved);
    }
    return imgs;
  }, [content, projectId, apiBase]);

  const resolveSmartLinkUrl = (rawUrl: string): string => {
    const resolved = resolveMediaUrl(rawUrl);
    const lowerRaw = rawUrl.toLowerCase();
    // If the LLM wrote a raw /screenshots/... link that differs from the actual embedded screenshot in the same message
    if (!lowerRaw.includes('/projects/') && /\.(png|jpe?g|webp|gif)$/i.test(lowerRaw)) {
      if (lowerRaw.includes('desktop')) {
        const desktopImg = embeddedImages.find((u) => u.toLowerCase().includes('desktop'));
        if (desktopImg) return desktopImg;
      } else if (lowerRaw.includes('mobile')) {
        const mobileImg = embeddedImages.find((u) => u.toLowerCase().includes('mobile'));
        if (mobileImg) return mobileImg;
      } else if (embeddedImages.length === 1) {
        return embeddedImages[0];
      }
    }
    return resolved;
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
              const wsPath = extractWorkspaceRelPath(url);
              const lowerTarget = (wsPath || url.split('?')[0]).toLowerCase();

              // 1. Image preview links (.png, .jpg, .webp, .gif, .svg)
              if (/\.(png|jpe?g|webp|gif|svg)$/i.test(lowerTarget)) {
                const imgUrl = resolveSmartLinkUrl(url);
                return (
                  <a
                    href={imgUrl}
                    target="_blank"
                    rel="noreferrer"
                    onClick={(e) => {
                      if (!e.metaKey && !e.ctrlKey) {
                        e.preventDefault();
                        setLightboxSrc(imgUrl);
                      }
                    }}
                    style={{
                      display: 'inline-flex',
                      alignItems: 'center',
                      gap: '5px',
                      color: 'var(--accent-primary)',
                      textDecoration: 'underline',
                      cursor: 'pointer',
                    }}
                    {...props}
                  >
                    <ImageIcon size={13} style={{ display: 'inline' }} /> {children || 'Mở ảnh preview'} <ExternalLink size={11} style={{ display: 'inline' }} />
                  </a>
                );
              }

              // 2. HTML preview links
              if (url.startsWith('sandbox:') || lowerTarget.endsWith('.html') || lowerTarget.endsWith('.htm')) {
                const filePath = wsPath || url.replace(/^sandbox:\/\/?/, '') || 'index.html';
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

              // 3. ZIP / WPRESS / workspace file downloads
              if (wsPath || url.startsWith('/api/backend/projects/')) {
                const downloadUrl = resolveMediaUrl(url);
                return (
                  <a href={downloadUrl} target="_blank" rel="noreferrer" style={{ color: 'var(--accent-primary)', textDecoration: 'underline' }} {...props}>
                    {children || 'Tải file'} <ExternalLink size={11} style={{ display: 'inline' }} />
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
                  <div style={{ position: 'relative', cursor: 'zoom-in' }} onClick={() => setLightboxSrc(resolved)}>
                    <img src={resolved} alt={alt || 'Image'} style={{ width: '100%', maxHeight: '420px', objectFit: 'contain', display: 'block', background: '#0d1117' }} {...props} />
                    <button type="button" style={{ position: 'absolute', top: '8px', right: '8px', background: 'rgba(0,0,0,0.65)', border: '1px solid rgba(255,255,255,0.15)', borderRadius: '6px', padding: '6px 8px', color: '#fff', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '4px', fontSize: '11px' }} title="Phóng to & Zoom">
                      <Maximize2 size={13} /> Zoom
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

      <ImageLightboxModal src={lightboxSrc} onClose={() => setLightboxSrc(null)} />
    </>
  );
}
