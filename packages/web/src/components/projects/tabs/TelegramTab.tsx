'use client';

import React, { useState } from 'react';
import { Send, HelpCircle, Check, Copy, ExternalLink, AlertTriangle } from 'lucide-react';
import { ProjectSettingsData } from '@/lib/api';
import { useLanguage } from '@/context/LanguageContext';

interface TelegramTabProps {
  data: ProjectSettingsData | null;
  onGetCode: () => Promise<void>;
  loading: boolean;
  code: string | null;
  botUsername?: string | null;
}

export default function TelegramTab({ data, onGetCode, loading, code, botUsername }: TelegramTabProps) {
  const { t } = useLanguage();
  const [copied, setCopied] = useState(false);

  const effectiveBotUsername = botUsername || data?.telegram?.bot_username;
  const isBotConfigured = data?.telegram?.bot_configured ?? true;

  const handleCopy = () => {
    if (!code) return;
    navigator.clipboard.writeText(code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div>
      {/* Bot Not Configured Warning Banner */}
      {!isBotConfigured && (
        <div style={{
          padding: '12px 16px',
          background: 'rgba(239, 68, 68, 0.1)',
          border: '1px solid rgba(239, 68, 68, 0.25)',
          borderRadius: '10px',
          marginBottom: '16px',
          color: '#f87171',
          fontSize: '12px',
          lineHeight: 1.5,
          display: 'flex',
          gap: '10px',
          alignItems: 'flex-start',
        }}>
          <AlertTriangle size={18} style={{ flexShrink: 0, marginTop: '2px' }} />
          <div>{t('settings.tg.bot_unconfigured')}</div>
        </div>
      )}

      {/* Current Status Box */}
      <div style={{ padding: '16px', background: '#1e293b', borderRadius: '12px', marginBottom: '20px', border: '1px solid #334155' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
          <span style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0' }}>{t('settings.tg.status_label')}</span>
          <span style={{
            fontSize: '12px',
            padding: '3px 8px',
            borderRadius: '12px',
            fontWeight: 600,
            background: data?.telegram.is_linked ? 'rgba(34, 197, 94, 0.2)' : 'rgba(148, 163, 184, 0.2)',
            color: data?.telegram.is_linked ? '#4ade80' : '#94a3b8',
          }}>
            {data?.telegram.is_linked ? `${t('settings.tg.linked')} (Chat ID: ${data.telegram.telegram_id})` : t('settings.tg.unlinked')}
          </span>
        </div>

        {effectiveBotUsername && (
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', fontSize: '12px', color: '#38bdf8', marginTop: '6px' }}>
            <span style={{ color: '#94a3b8' }}>{t('settings.tg.bot_label')}</span>
            <strong>@{effectiveBotUsername}</strong>
          </div>
        )}

        <p style={{ fontSize: '12px', color: '#94a3b8', margin: '8px 0 0 0' }}>
          {t('settings.tg.desc')}
        </p>
      </div>

      {/* Guide Box */}
      <div style={{ padding: '16px', background: 'rgba(56, 189, 248, 0.06)', borderRadius: '12px', border: '1px solid rgba(56, 189, 248, 0.2)', marginBottom: '20px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#38bdf8', fontWeight: 700, fontSize: '13px', marginBottom: '10px' }}>
          <HelpCircle size={16} />
          {t('settings.tg.guide_title')}
        </div>
        <ol style={{ margin: 0, paddingLeft: '18px', fontSize: '12px', color: '#cbd5e1', lineHeight: 1.7 }}>
          <li>{t('settings.tg.step1')}</li>
          <li>{t('settings.tg.step2')}</li>
          <li>
            {effectiveBotUsername ? (
              <span>
                Mở bot <a href={`https://t.me/${effectiveBotUsername}`} target="_blank" rel="noopener noreferrer" style={{ color: '#38bdf8', textDecoration: 'underline', fontWeight: 600 }}>@{effectiveBotUsername}</a> hoặc bấm nút mở bot bên dưới.
              </span>
            ) : (
              t('settings.tg.step3')
            )}
          </li>
          <li>{t('settings.tg.step4')}</li>
          <li>{t('settings.tg.step5')}</li>
        </ol>

        <div style={{ marginTop: '12px', padding: '10px 12px', background: 'rgba(234, 179, 8, 0.1)', border: '1px solid rgba(234, 179, 8, 0.25)', borderRadius: '8px', color: '#fbbf24', fontSize: '11px', lineHeight: 1.5 }}>
          {t('settings.tg.bot_warning')}
        </div>
      </div>

      {/* Action / Code display */}
      <div style={{ display: 'flex', gap: '10px', alignItems: 'center', marginBottom: '16px', flexWrap: 'wrap' }}>
        <button
          onClick={onGetCode}
          disabled={loading}
          style={{
            padding: '10px 18px',
            background: '#38bdf8',
            color: '#0f172a',
            fontWeight: 700,
            borderRadius: '8px',
            border: 'none',
            cursor: 'pointer',
            fontSize: '13px',
            display: 'flex',
            alignItems: 'center',
            gap: '8px',
          }}
        >
          <Send size={15} />
          {loading ? t('settings.tg.btn_generating') : t('settings.tg.btn_generate')}
        </button>

        {effectiveBotUsername && (
          <a
            href={`https://t.me/${effectiveBotUsername}`}
            target="_blank"
            rel="noopener noreferrer"
            style={{
              padding: '10px 18px',
              background: '#1e293b',
              color: '#38bdf8',
              fontWeight: 600,
              borderRadius: '8px',
              border: '1px solid #334155',
              textDecoration: 'none',
              fontSize: '13px',
              display: 'inline-flex',
              alignItems: 'center',
              gap: '6px',
            }}
          >
            <ExternalLink size={14} />
            {t('settings.tg.open_bot')} (@{effectiveBotUsername})
          </a>
        )}
      </div>

      {code && (
        <div style={{ padding: '16px', background: '#0b1120', border: '1px solid #38bdf8', borderRadius: '8px' }}>
          <p style={{ fontSize: '12px', color: '#e2e8f0', margin: '0 0 8px 0' }}>{t('settings.tg.code_instruction')}</p>
          <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <code style={{ flex: 1, padding: '10px 14px', background: '#1e293b', borderRadius: '6px', color: '#38bdf8', fontWeight: 700, fontSize: '14px', letterSpacing: '0.05em' }}>
              {code}
            </code>
            <button
              onClick={handleCopy}
              style={{
                padding: '10px 14px',
                background: copied ? '#22c55e' : '#334155',
                color: '#f8fafc',
                border: 'none',
                borderRadius: '6px',
                cursor: 'pointer',
                display: 'flex',
                alignItems: 'center',
                gap: '6px',
                fontSize: '12px',
                fontWeight: 600,
              }}
            >
              {copied ? <Check size={14} /> : <Copy size={14} />}
              {copied ? t('settings.tg.btn_copied') : t('settings.tg.btn_copy')}
            </button>
          </div>
          <p style={{ fontSize: '11px', color: '#94a3b8', margin: '8px 0 0 0' }}>{t('settings.tg.code_expire')}</p>
        </div>
      )}
    </div>
  );
}
