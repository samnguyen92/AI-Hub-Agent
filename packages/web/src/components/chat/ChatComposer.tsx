'use client';

import React, { useRef, useState } from 'react';
import { FileText, ImagePlus, Paperclip, Send, X } from 'lucide-react';
import { useLanguage } from '@/context/LanguageContext';

const ACCEPTED = '.png,.jpg,.jpeg,.webp,.gif,.pdf,.txt,.md,.csv,.json,.html,.css,.js,.ts,.tsx,.jsx,.py,.yaml,.yml,.xml,.log';
const MAX_FILES = 5;
const MAX_BYTES = 20 * 1024 * 1024;

interface ChatComposerProps {
  busy: boolean;
  enabled: boolean;
  agents: Array<{ tag: string; color: string }>;
  onSend: (content: string, files: File[]) => Promise<boolean>;
}

function AttachmentChip({ file, onRemove }: { file: File; onRemove: () => void }) {
  const [preview, setPreview] = useState('');
  React.useEffect(() => {
    if (!file.type.startsWith('image/')) return;
    const url = URL.createObjectURL(file);
    setPreview(url);
    return () => URL.revokeObjectURL(url);
  }, [file]);
  return (
    <div style={{ display: 'flex', alignItems: 'center', gap: 8, maxWidth: 240, padding: 6, borderRadius: 10, background: 'var(--bg-card)', border: '1px solid var(--border-subtle)' }}>
      {preview ? <img src={preview} alt="" style={{ width: 36, height: 36, objectFit: 'cover', borderRadius: 6 }} /> : <FileText size={20} color="var(--text-muted)" />}
      <span title={file.name} style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: 12, flex: 1 }}>{file.name}</span>
      <button type="button" aria-label={`Remove ${file.name}`} onClick={onRemove} style={iconButton}><X size={14} /></button>
    </div>
  );
}

export default function ChatComposer({ busy, enabled, agents, onSend }: ChatComposerProps) {
  const { t } = useLanguage();
  const picker = useRef<HTMLInputElement>(null);
  const [content, setContent] = useState('');
  const [files, setFiles] = useState<File[]>([]);
  const [error, setError] = useState('');
  const [dragging, setDragging] = useState(false);

  const addFiles = (incoming: FileList | File[]) => {
    const selected = Array.from(incoming);
    const invalid = selected.find((file) => !ACCEPTED.split(',').some((suffix) => file.name.toLowerCase().endsWith(suffix)) || file.size > MAX_BYTES);
    if (invalid) {
      setError(`${invalid.name}: chỉ nhận file ảnh/PDF/văn bản được hỗ trợ, tối đa 20 MB.`);
      return;
    }
    setFiles((current) => {
      if (current.length + selected.length > MAX_FILES) {
        setError(`Mỗi tin nhắn tối đa ${MAX_FILES} file.`);
        return current;
      }
      setError('');
      return [...current, ...selected];
    });
  };

  const submit = async () => {
    if (busy || !enabled || (!content.trim() && files.length === 0)) return;
    const sent = await onSend(content, files);
    if (sent) {
      setContent('');
      setFiles([]);
      setError('');
    }
  };

  return (
    <div
      onDragOver={(event) => { event.preventDefault(); setDragging(true); }}
      onDragLeave={() => setDragging(false)}
      onDrop={(event) => { event.preventDefault(); setDragging(false); addFiles(event.dataTransfer.files); }}
      onPaste={(event) => {
        const images = Array.from(event.clipboardData.items).filter((item) => item.type.startsWith('image/')).map((item) => item.getAsFile()).filter((file): file is File => Boolean(file));
        if (images.length) addFiles(images);
      }}
      style={{ padding: '14px 24px 20px', borderTop: '1px solid var(--border-subtle)', background: 'var(--bg-secondary)', outline: dragging ? '2px dashed var(--accent-primary)' : 'none' }}
    >
      <div style={{ display: 'flex', gap: 8, overflowX: 'auto', alignItems: 'center', marginBottom: 10 }}>
        <span style={{ fontSize: 11, color: 'var(--text-dim)', fontWeight: 600, whiteSpace: 'nowrap' }}>{t('chat.quick_select')}</span>
        {agents.map((agent) => <button key={agent.tag} type="button" onClick={() => setContent((value) => `${agent.tag} ${value}`.trim())} style={{ padding: '4px 8px', borderRadius: 6, fontSize: 11, fontWeight: 600, whiteSpace: 'nowrap', background: 'rgba(255,255,255,.05)', border: '1px solid var(--border-subtle)', color: agent.color, cursor: 'pointer' }}>{agent.tag}</button>)}
      </div>
      {files.length > 0 && <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 10 }}>{files.map((file, index) => <AttachmentChip key={`${file.name}-${file.lastModified}-${index}`} file={file} onRemove={() => setFiles((current) => current.filter((_, i) => i !== index))} />)}</div>}
      {error && <p role="alert" style={{ color: 'var(--danger)', fontSize: 12, marginBottom: 8 }}>{error}</p>}
      <input ref={picker} type="file" accept={ACCEPTED} multiple hidden onChange={(event) => { if (event.target.files) addFiles(event.target.files); event.target.value = ''; }} />
      <div style={{ display: 'flex', gap: 10, alignItems: 'flex-end' }}>
        <button type="button" aria-label="Attach image or file" title={t('chat.attach_title')} onClick={() => picker.current?.click()} disabled={!enabled || busy} style={{ ...iconButton, width: 46, height: 46, border: '1px solid var(--border-subtle)', borderRadius: 9 }}><Paperclip size={18} /></button>
        <textarea
          id="input-chat-message" rows={2} value={content}
          onChange={(event) => setContent(event.target.value)}
          onKeyDown={(event) => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); void submit(); } }}
          placeholder={`${t('chat.placeholder')} · ${t('chat.drag_hint')}`}
          style={{ flex: 1, minWidth: 0, padding: '12px 14px', borderRadius: 8, background: 'var(--bg-card)', color: '#fff', border: '1px solid var(--border-subtle)', fontSize: 14, resize: 'none' }}
          disabled={!enabled || busy}
        />
        <button id="btn-send-message" onClick={() => void submit()} disabled={!enabled || busy || (!content.trim() && files.length === 0)} className="btn-primary" style={{ height: 48, padding: '0 20px', opacity: !enabled || busy || (!content.trim() && files.length === 0) ? 0.5 : 1 }}>
          {busy ? <ImagePlus size={16} /> : <Send size={16} />}{busy ? '...' : t('chat.send')}
        </button>
      </div>
    </div>
  );
}

const iconButton: React.CSSProperties = { display: 'inline-flex', justifyContent: 'center', alignItems: 'center', background: 'var(--bg-card)', color: 'var(--text-muted)', border: 'none', cursor: 'pointer' };
