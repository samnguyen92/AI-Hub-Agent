'use client';

import React, { useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { Send, HelpCircle, Sparkles } from 'lucide-react';
import {
  fetchProjects,
  fetchThreads,
  fetchMessages,
  sendMessage,
  classifyPrompt,
  Project,
  Thread,
  Message,
} from '@/lib/api';
import ChatSidebar from '@/components/chat/ChatSidebar';
import ChatMessageItem from '@/components/chat/ChatMessageItem';

export default function ChatPage() {
  const searchParams = useSearchParams();
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProjectId, setSelectedProjectId] = useState<string>('');
  const [threads, setThreads] = useState<Thread[]>([]);
  const [activeThreadId, setActiveThreadId] = useState<string>('');
  const [messages, setMessages] = useState<Message[]>([]);
  const [inputContent, setInputContent] = useState('');
  const [sending, setSending] = useState(false);
  const [routerHint, setRouterHint] = useState<any>(null);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    fetchProjects().then((data) => {
      setProjects(data);
      const urlProj = searchParams.get('projectId');
      const defaultProj = urlProj || (data.length > 0 ? data[0].id : '');
      setSelectedProjectId(defaultProj);
    });
  }, [searchParams]);

  const loadThreads = async (projId: string) => {
    if (!projId) return;
    const th = await fetchThreads(projId);
    setThreads(th);
    if (th.length > 0 && !activeThreadId) {
      setActiveThreadId(th[0].id);
    }
  };

  useEffect(() => {
    if (selectedProjectId) {
      loadThreads(selectedProjectId);
    }
  }, [selectedProjectId]);

  useEffect(() => {
    if (!activeThreadId) {
      setMessages([]);
      return;
    }
    fetchMessages(activeThreadId).then((msgs) => setMessages(msgs));
  }, [activeThreadId]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const handleSend = async (overrideAgent?: string) => {
    if (!inputContent.trim() || sending || !selectedProjectId) return;
    setSending(true);
    const currentInput = inputContent;
    setInputContent('');

    try {
      let resolvedAgent = overrideAgent;
      if (!resolvedAgent && !currentInput.startsWith('@')) {
        try {
          const decision = await classifyPrompt(currentInput);
          resolvedAgent = decision.agent;
        } catch {
          resolvedAgent = 'assistant';
        }
      }

      setRouterHint(null);
      const res = await sendMessage({
        projectId: selectedProjectId,
        content: currentInput,
        threadId: activeThreadId || undefined,
        agentOverride: resolvedAgent,
        runSynchronously: true,
      });

      if (res.thread_id) {
        if (res.thread_id !== activeThreadId) {
          setActiveThreadId(res.thread_id);
          await loadThreads(selectedProjectId);
        }
        const updatedMsgs = await fetchMessages(res.thread_id);
        setMessages(updatedMsgs);
      }
    } finally {
      setSending(false);
    }
  };

  const agentBadges = [
    { tag: '@assistant', color: '#818CF8', desc: 'Trợ lý Hội thoại' },
    { tag: '@design', color: '#EC4899', desc: 'Thiết kế Mockup' },
    { tag: '@code-builder', color: '#10B981', desc: 'Lập trình Code' },
    { tag: '@site-analyzer', color: '#3B82F6', desc: 'Phân tích Website' },
    { tag: '@seo-auditor', color: '#F59E0B', desc: 'Kiểm toán SEO' },
  ];

  return (
    <div style={{ display: 'flex', height: 'calc(100vh - 65px)', overflow: 'hidden' }}>
      <ChatSidebar
        projects={projects}
        selectedProjectId={selectedProjectId}
        onSelectProject={(id) => { setSelectedProjectId(id); setActiveThreadId(''); }}
        threads={threads}
        activeThreadId={activeThreadId}
        onSelectThread={setActiveThreadId}
        onNewThread={() => setActiveThreadId('')}
      />

      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', background: 'var(--bg-primary)' }}>
        <div style={{ flex: 1, overflowY: 'auto', padding: '24px', display: 'flex', flexDirection: 'column', gap: '16px' }}>
          {messages.length === 0 ? (
            <div style={{ margin: 'auto', textAlign: 'center', maxWidth: '480px', color: 'var(--text-muted)' }}>
              <Sparkles size={36} color="var(--accent-primary)" style={{ margin: '0 auto 16px' }} />
              <h3 style={{ fontSize: '20px', fontWeight: 800, color: '#ffffff', marginBottom: '8px' }}>
                Trợ lý Align AI (GPT-6 Luna)
              </h3>
              <p style={{ fontSize: '14px', lineHeight: '1.6', marginBottom: '12px' }}>
                Hội thoại tự nhiên, tư vấn ý tưởng và giải đáp như <b>ChatGPT / Claude</b>.
              </p>
              <p style={{ fontSize: '12px', color: 'var(--text-dim)', lineHeight: '1.5' }}>
                💡 Mẹo: Nhập tự do để chat cùng <b>@assistant</b>, tag chuyên sâu <code>@design</code>, <code>@code-builder</code>, hoặc gõ <code>/learn &lt;quy tắc&gt;</code> để dạy AI.
              </p>
            </div>
          ) : (
            messages.map((m) => (
              <ChatMessageItem key={m.id} message={m} projectId={selectedProjectId} />
            ))
          )}
          <div ref={messagesEndRef} />
        </div>

        <div style={{ padding: '16px 24px 24px', borderTop: '1px solid var(--border-subtle)', background: 'var(--bg-secondary)' }}>
          <div style={{ display: 'flex', gap: '8px', marginBottom: '10px', alignItems: 'center' }}>
            <span style={{ fontSize: '11px', color: 'var(--text-dim)', fontWeight: 600 }}>Chọn nhanh:</span>
            {agentBadges.map((badge) => (
              <button
                key={badge.tag}
                type="button"
                onClick={() => setInputContent((prev) => `${badge.tag} ${prev}`.trim())}
                style={{
                  display: 'inline-flex',
                  alignItems: 'center',
                  gap: '4px',
                  padding: '4px 8px',
                  borderRadius: '6px',
                  fontSize: '11px',
                  fontWeight: 600,
                  background: 'rgba(255, 255, 255, 0.05)',
                  border: '1px solid var(--border-subtle)',
                  color: badge.color,
                  cursor: 'pointer',
                }}
              >
                {badge.tag}
              </button>
            ))}
          </div>

          <div style={{ display: 'flex', gap: '10px', alignItems: 'flex-end' }}>
            <textarea
              id="input-chat-message"
              rows={2}
              value={inputContent}
              onChange={(e) => setInputContent(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                  e.preventDefault();
                  handleSend();
                }
              }}
              placeholder="Trò chuyện tự nhiên cùng Trợ lý Align AI (hoặc tag @design, @code... hoặc gõ /learn <quy tắc>)..."
              style={{
                flex: 1,
                padding: '12px 14px',
                borderRadius: '8px',
                background: 'var(--bg-card)',
                color: '#ffffff',
                border: '1px solid var(--border-subtle)',
                fontSize: '14px',
                resize: 'none',
              }}
            />
            <button
              id="btn-send-message"
              onClick={() => handleSend()}
              disabled={sending || !inputContent.trim()}
              className="btn-primary"
              style={{ height: '48px', padding: '0 20px', opacity: sending || !inputContent.trim() ? 0.5 : 1 }}
            >
              <Send size={16} />
              {sending ? 'Đang xử lý...' : 'Gửi'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
