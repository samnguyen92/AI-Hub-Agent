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
  mode?: 'llm' | 'image';
  onSave: (model: string) => void;
}

const IMAGE_MODEL_OPTIONS = [
  'openai/gpt-image-2.5-sunburst',
  'openai/gpt-5.4-image-2',
  'bytedance-seed/seedream-4.5',
];

export default function AgentModelControl({ agentKey, model, saving, mode = 'llm', onSave }: AgentModelControlProps) {
  const [customModel, setCustomModel] = useState('');
  const options = mode === 'image' ? IMAGE_MODEL_OPTIONS : MODEL_OPTIONS;
  const inputValue = customModel || (options.includes(model) ? '' : model);

  return (
    <div style={{ marginBottom: '16px' }}>
      <label htmlFor={`agent-model-${agentKey}`} style={{ display: 'block', fontSize: '13px', color: 'var(--text-muted)', marginBottom: '6px' }}>
        {mode === 'image' ? 'Image Generation Model' : 'LLM Model'}
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
        {!options.includes(model) && <option value={model}>{model} (custom)</option>}
        {options.map((option) => <option key={option} value={option}>{option}</option>)}
      </select>
      <div style={{ display: 'flex', gap: '8px', marginTop: '8px' }}>
        <input
          aria-label={`Model ID tùy chỉnh cho ${agentKey}`}
          value={inputValue}
          onChange={(event) => setCustomModel(event.target.value)}
          placeholder={mode === 'image' ? 'Image model ID, ví dụ: provider/model-name' : 'LLM model ID, ví dụ: provider/model-name'}
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
