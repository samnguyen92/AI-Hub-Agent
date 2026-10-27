'use client';

import React, { useState } from 'react';
import { Send, HelpCircle, Check, Copy } from 'lucide-react';
import { ProjectSettingsData } from '@/lib/api';
import { useLanguage } from '@/context/LanguageContext';

interface TelegramTabProps {
  data: ProjectSettingsData | null;
  onGetCode: () => Promise<void>;
  loading: boolean;
  code: string | null;
}

export default function TelegramTab({ data, onGetCode, loading, code }: TelegramTabProps) {
  const { t } = useLanguage();
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    if (!code) return;
    navigator.clipboard.writeText(code);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div>
      {/* Current Status Box */}
      <div style={{ padding: '16px', background: '#1e293b', borderRadius: '12px', marginBottom: '20px', border: '1px solid #334155' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
          <span style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0' }}>{t('settings.tg.status_label')}</span>
          <span style={{ fontSize: '12px', padding: '3px 8px', borderRadius: '12px', fontWeight: 600, background: data?.telegram.is_linked ? 'rgba(34, 197, 94, 0.2)' : 'rgba(148, 163, 184, 0.2)', color: data?.telegram.is_linked ? '#4ade80' : '#94a3b8' }}>
            {data?.telegram.is_linked ? `${t('settings.tg.linked')} (ID: ${data.telegram.telegram_id})` : t('settings.tg.unlinked')}
          </span>
        </div>
        <p style={{ fontSize: '12px', color: '#94a3b8', margin: 0 }}>
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
          <li>{t('settings.tg.step3')}</li>
          <li>{t('settings.tg.step4')}</li>
          <li>{t('settings.tg.step5')}</li>
        </ol>
      </div>

      {/* Action / Code display */}
      <button onClick={onGetCode} disabled={loading} style={{ padding: '10px 18px', background: '#38bdf8', color: '#0f172a', fontWeight: 700, borderRadius: '8px', border: 'none', cursor: 'pointer', fontSize: '13px', display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '16px' }}>
        <Send size={15} />
        {loading ? t('settings.tg.btn_generating') : t('settings.tg.btn_generate')}
      </button>

      {code && (
        <div style={{ padding: '16px', background: '#0b1120', border: '1px solid #38bdf8', borderRadius: '8px' }}>
          <p style={{ fontSize: '12px', color: '#e2e8f0', margin: '0 0 8px 0' }}>{t('settings.tg.code_instruction')}</p>
          <div style={{ display: 'flex', gap: '8px', alignItems: 'center' }}>
            <code style={{ flex: 1, padding: '10px 14px', background: '#1e293b', borderRadius: '6px', color: '#38bdf8', fontWeight: 700, fontSize: '14px', letterSpacing: '0.05em' }}>
              {code}
            </code>
            <button onClick={handleCopy} style={{ padding: '10px 14px', background: copied ? '#22c55e' : '#334155', color: '#f8fafc', border: 'none', borderRadius: '6px', cursor: 'pointer', display: 'flex', alignItems: 'center', gap: '6px', fontSize: '12px', fontWeight: 600 }}>
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
