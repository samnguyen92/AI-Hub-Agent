'use client';

import React, { useState } from 'react';
import ArtifactViewer, { ArtifactData } from '@/components/artifacts/ArtifactViewer';
import { Eye, Layers, Lightbulb, Check, Sparkles } from 'lucide-react';

interface ChatMessageItemProps {
  message: {
    id: string;
    role: string;
    content: string;
  };
  projectId: string;
}

export function parseArtifactFromMessage(content: string, projectId: string): ArtifactData | null {
  try {
    const jsonMatch = content.match(/```json\s*([\s\S]*?)\s*```/);
    if (!jsonMatch) return null;
    const data = JSON.parse(jsonMatch[1]);

    if (data.artifact_name && (data.html_file_path || data.screenshots)) {
      return {
        name: data.artifact_name,
        version: data.version || 1,
        projectId,
        htmlPath: data.html_file_path,
        desktopPng: data.screenshots?.desktop_png,
        mobilePng: data.screenshots?.mobile_png,
        checklist: data.review_checklist,
      };
    }
    if (data.project_name && data.entry_file) {
      return {
        name: data.project_name,
        projectId,
        htmlPath: data.entry_file,
        desktopPng: data.visual_diff?.diff_image_path,
        lintResults: data.lint_results,
      };
    }
  } catch {
    // Không phải JSON hợp lệ
  }
  return null;
}

export default function ChatMessageItem({ message, projectId }: ChatMessageItemProps) {
  const [showViewer, setShowViewer] = useState(false);
  const [showTeachInput, setShowTeachInput] = useState(false);
  const [teachRule, setTeachRule] = useState('');
  const [taughtSuccess, setTaughtSuccess] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const isUser = message.role === 'user';
  const artifact = !isUser ? parseArtifactFromMessage(message.content, projectId) : null;
  const apiBase = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

  const handleTeachRule = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!teachRule.trim() || !projectId) return;
    setSubmitting(true);
    try {
      const res = await fetch(`${apiBase}/projects/${projectId}/learn`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ rule: teachRule.trim(), category: 'user_teach' }),
      });
      if (res.ok) {
        setTaughtSuccess(true);
        setShowTeachInput(false);
        setTeachRule('');
        setTimeout(() => setTaughtSuccess(false), 4000);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div
      style={{
        alignSelf: isUser ? 'flex-end' : 'flex-start',
        maxWidth: isUser ? '75%' : '85%',
        background: isUser ? 'rgba(99, 102, 241, 0.2)' : 'var(--bg-card)',
        border: isUser ? '1px solid rgba(99, 102, 241, 0.4)' : '1px solid var(--border-subtle)',
        borderRadius: '12px',
        padding: '14px 18px',
      }}
    >
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          marginBottom: '6px',
        }}
      >
        <span
          style={{
            fontSize: '11px',
            fontWeight: 700,
            color: isUser ? 'var(--accent-primary)' : 'var(--text-muted)',
            display: 'flex',
            alignItems: 'center',
            gap: '6px',
          }}
        >
          {!isUser && <Sparkles size={12} color="#818CF8" />}
          {isUser ? 'Bạn' : 'Align Assistant'}
        </span>

        {!isUser && (
          <button
            onClick={() => setShowTeachInput(!showTeachInput)}
            style={{
              background: 'transparent',
              border: 'none',
              color: 'var(--text-dim)',
              cursor: 'pointer',
              display: 'flex',
              alignItems: 'center',
              gap: '4px',
              fontSize: '11px',
              padding: '2px 6px',
              borderRadius: '4px',
            }}
            title="Dạy AI quy tắc/bài học kinh nghiệm từ câu trả lời này"
          >
            <Lightbulb size={12} color="#FBBF24" /> Dạy AI
          </button>
        )}
      </div>

      <div style={{ fontSize: '14px', lineHeight: '1.6', whiteSpace: 'pre-wrap' }}>
        {message.content}
      </div>

      {showTeachInput && (
        <form onSubmit={handleTeachRule} style={{ marginTop: '10px', paddingTop: '10px', borderTop: '1px solid var(--border-subtle)', display: 'flex', gap: '8px' }}>
          <input
            type="text"
            placeholder="Nhập quy tắc/bài học cần AI ghi nhớ (vd: 'Nút CTA luôn dùng chữ in hoa')..."
            value={teachRule}
            onChange={(e) => setTeachRule(e.target.value)}
            style={{ flex: 1, padding: '6px 10px', borderRadius: '6px', background: 'var(--bg-main)', border: '1px solid var(--border-subtle)', color: '#fff', fontSize: '12px' }}
          />
          <button
            type="submit"
            disabled={submitting || !teachRule.trim()}
            style={{ padding: '6px 12px', borderRadius: '6px', background: 'var(--accent-primary)', color: '#fff', border: 'none', fontSize: '12px', fontWeight: 600, cursor: 'pointer' }}
          >
            {submitting ? '...' : 'Lưu quy tắc'}
          </button>
        </form>
      )}

      {taughtSuccess && (
        <div style={{ marginTop: '8px', fontSize: '12px', color: '#10B981', display: 'flex', alignItems: 'center', gap: '4px' }}>
          <Check size={12} /> Đã dạy AI thành công! Quy tắc đã được nạp vào bộ nhớ vĩnh viễn của dự án.
        </div>
      )}

      {artifact && (
        <div style={{ marginTop: '12px', paddingTop: '10px', borderTop: '1px solid var(--border-subtle)' }}>
          <button
            onClick={() => setShowViewer(!showViewer)}
            className="btn-primary"
            style={{ fontSize: '12px', padding: '6px 12px', display: 'inline-flex', alignItems: 'center', gap: '6px' }}
          >
            {showViewer ? <Eye size={14} /> : <Layers size={14} />}
            {showViewer ? 'Ẩn Preview Artifact' : `Xem Preview: ${artifact.name}`}
          </button>

          {showViewer && (
            <div style={{ marginTop: '12px' }}>
              <ArtifactViewer artifact={artifact} onClose={() => setShowViewer(false)} />
            </div>
          )}
        </div>
      )}
    </div>
  );
}
