'use client';

import React, { Suspense, useEffect, useRef, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { Sparkles, Loader2 } from 'lucide-react';
import {
  fetchProjects,
  fetchThreads,
  fetchMessages,
  fetchRunStatus,
  sendMessage,
  classifyPrompt,
  uploadChatAttachment,
  Project,
  Thread,
  Message,
} from '@/lib/api';
import ChatSidebar from '@/components/chat/ChatSidebar';
import ChatMessageItem from '@/components/chat/ChatMessageItem';
import ChatComposer from '@/components/chat/ChatComposer';
import { useLanguage } from '@/context/LanguageContext';

function ChatInner() {
  const { t } = useLanguage();
  const searchParams = useSearchParams();
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProjectId, setSelectedProjectId] = useState<string>('');
  const [threads, setThreads] = useState<Thread[]>([]);
  const [activeThreadId, setActiveThreadId] = useState<string>('');
  const [messages, setMessages] = useState<Message[]>([]);
  const [sending, setSending] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const activeThreadIdRef = useRef(activeThreadId);
  activeThreadIdRef.current = activeThreadId;

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

  const handleSend = async (content: string, files: File[]): Promise<boolean> => {
    if (sending || !selectedProjectId) return false;
    const currentInput = content.trim() || 'Please analyze the attached files and help me with them.';
    setSending(true);
    try {
      const attachments = await Promise.all(files.map((file) => uploadChatAttachment(selectedProjectId, file)));
      const recentHistory = messages.slice(-10).map((message) => ({
        role: message.role,
        content: `${message.content} ${message.attachments?.map((attachment) => attachment.path).join(' ') || ''}`,
      }));
      const attachmentHint = attachments.map((attachment) => `${attachment.filename} ${attachment.path}`).join(' ');
      const decision = await classifyPrompt(currentInput, [
        ...recentHistory,
        ...(attachments.length ? [{ role: 'user', content: `Uploaded chat attachments: ${attachmentHint}` }] : []),
      ]);
      const res = await sendMessage({
        projectId: selectedProjectId,
        content: currentInput,
        threadId: activeThreadId || undefined,
        agentOverride: decision.agent,
        runSynchronously: false,
        attachments,
      });

      if (res.thread_id) {
        if (res.thread_id !== activeThreadId) {
          activeThreadIdRef.current = res.thread_id;
          setActiveThreadId(res.thread_id);
          await loadThreads(selectedProjectId);
        }
        const initialMessages = await fetchMessages(res.thread_id);
        if (activeThreadIdRef.current === res.thread_id) setMessages(initialMessages);
        if (res.run_id) {
          let status = res.status;
          let attempt = 0;
          while ((status === 'queued' || status === 'running') && attempt < 1200) {
            await new Promise((resolve) => window.setTimeout(resolve, 1000));
            const run = await fetchRunStatus(res.thread_id, res.run_id);
            status = run.status;
            attempt += 1;
          }
          const updatedMsgs = await fetchMessages(res.thread_id);
          if (res.thread_id === activeThreadIdRef.current) setMessages(updatedMsgs);
          if (status === 'failed' && res.thread_id === activeThreadIdRef.current) {
            setMessages((prev) => [...prev, {
              id: `run-error-${res.run_id}`,
              role: 'assistant',
              content: t('chat.error_run'),
            }]);
          } else if ((status === 'queued' || status === 'running') && res.thread_id === activeThreadIdRef.current) {
            setMessages((prev) => [...prev, {
              id: `run-timeout-${res.run_id}`,
              role: 'assistant',
              content: t('chat.timeout_run'),
            }]);
          }
          await loadThreads(selectedProjectId);
        }
      }
      return true;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'Failed to communicate with agent. Please try again.';
      console.error('Failed to send message:', error);
      setMessages((prev) => [
        ...prev,
        {
          id: 'error-' + Date.now(),
          role: 'assistant',
          content: `⚠️ Error: ${message}`,
        },
      ]);
      return false;
    } finally {
      setSending(false);
    }
  };

  const agentBadges = [
    { tag: '@assistant', color: '#818CF8', desc: t('badge.assistant') },
    { tag: '@wordpress-builder', color: '#06B6D4', desc: t('badge.wordpress_builder') },
    { tag: '@design', color: '#EC4899', desc: t('badge.design') },
    { tag: '@code-builder', color: '#10B981', desc: t('badge.code_builder') },
    { tag: '@site-analyzer', color: '#3B82F6', desc: t('badge.site_analyzer') },
    { tag: '@seo-auditor', color: '#F59E0B', desc: t('badge.seo_auditor') },
    { tag: '@quality-auditor', color: '#A78BFA', desc: t('badge.quality_auditor') },
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
                {t('chat.welcome_title')}
              </h3>
              <p style={{ fontSize: '14px', lineHeight: '1.6', marginBottom: '12px' }}>
                {t('chat.welcome_desc')}
              </p>
              <p style={{ fontSize: '12px', color: 'var(--text-dim)', lineHeight: '1.5' }}>
                {t('chat.welcome_tip')}
              </p>
            </div>
          ) : (
            messages.map((m) => (
              <ChatMessageItem key={m.id} message={m} projectId={selectedProjectId} />
            ))
          )}
          {sending && (
            <div
              style={{
                display: 'inline-flex',
                alignItems: 'center',
                gap: '10px',
                padding: '10px 16px',
                borderRadius: '10px',
                background: 'rgba(99, 102, 241, 0.1)',
                border: '1px solid rgba(99, 102, 241, 0.25)',
                color: 'var(--text-secondary)',
                fontSize: '13px',
                maxWidth: 'fit-content',
              }}
            >
              <Loader2 size={16} className="animate-spin" color="#818CF8" />
              <span>{t('chat.thinking')}</span>
            </div>
          )}
          <div ref={messagesEndRef} />
        </div>

        <ChatComposer busy={sending} enabled={Boolean(selectedProjectId)} agents={agentBadges} onSend={handleSend} />
      </div>
    </div>
  );
}

export default function ChatPage() {
  return (
    <Suspense fallback={<div style={{ textAlign: 'center', padding: '60px', color: 'var(--text-muted)' }}>Loading...</div>}>
      <ChatInner />
    </Suspense>
  );
}
