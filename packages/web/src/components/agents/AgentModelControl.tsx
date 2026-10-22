'use client';

import React, { useState } from 'react';

const MODEL_OPTIONS = [
  'openai/gpt-6-luna',
  'openai/gpt-4o',
  'openai/gpt-4o-mini',
  'anthropic/claude-3-5-sonnet',
  'deepseek/deepseek-chat',
];

interface AgentModelControlProps {
  agentKey: string;
  model: string;
  saving: boolean;
  onSave: (model: string) => void;
}

export default function AgentModelControl({ agentKey, model, saving, onSave }: AgentModelControlProps) {
  const [customModel, setCustomModel] = useState('');
  const inputValue = customModel || (MODEL_OPTIONS.includes(model) ? '' : model);

  return (
    <div style={{ marginBottom: '16px' }}>
      <label htmlFor={`agent-model-${agentKey}`} style={{ display: 'block', fontSize: '13px', color: 'var(--text-muted)', marginBottom: '6px' }}>
        LLM Model
      </label>
      <select
        id={`agent-model-${agentKey}`}
        value={model}
        disabled={saving}
        onChange={(event) => {
          setCustomModel('');
          onSave(event.target.value);
        }}
        style={{ width: '100%', padding: '10px 12px', borderRadius: '8px', background: 'var(--bg-main)', border: '1px solid var(--border-subtle)', color: '#fff', outline: 'none' }}
      >
        {!MODEL_OPTIONS.includes(model) && <option value={model}>{model} (custom)</option>}
        {MODEL_OPTIONS.map((option) => <option key={option} value={option}>{option}</option>)}
      </select>
      <div style={{ display: 'flex', gap: '8px', marginTop: '8px' }}>
        <input
          aria-label={`Model ID tùy chỉnh cho ${agentKey}`}
          value={inputValue}
          onChange={(event) => setCustomModel(event.target.value)}
          placeholder="Nhập model ID, ví dụ: provider/model-name"
          disabled={saving}
          style={{ flex: 1, minWidth: 0, padding: '10px 12px', borderRadius: '8px', background: 'var(--bg-main)', border: '1px solid var(--border-subtle)', color: '#fff', outline: 'none' }}
        />
        <button
          type="button"
          onClick={() => onSave(inputValue)}
          disabled={saving || !inputValue.trim()}
          style={{ padding: '0 12px', borderRadius: '8px', background: 'var(--accent-primary)', color: '#fff', border: 'none', cursor: 'pointer', opacity: saving ? 0.6 : 1 }}
        >
          {saving ? 'Đang lưu…' : 'Lưu model'}
        </button>
      </div>
    </div>
  );
}
