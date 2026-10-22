'use client';

import React from 'react';
import { Cpu } from 'lucide-react';
import AgentModelControl from '@/components/agents/AgentModelControl';

export interface AgentInfo {
  key: string;
  model: string;
  rules_path: string;
  config: Record<string, unknown>;
  allowed_tools: string[];
}

interface AgentCardProps {
  agent: AgentInfo;
  saving: boolean;
  onSaveModel: (model: string) => void;
  onSaveImageModel: (model: string) => void;
}

export default function AgentCard({ agent, saving, onSaveModel, onSaveImageModel }: AgentCardProps) {
  const isImageAgent = agent.key === 'image-generator';
  const imageModel = typeof agent.config.image_model === 'string'
    ? agent.config.image_model
    : 'openai/gpt-image-2.5-sunburst';

  return (
    <div style={{ background: 'var(--bg-surface)', borderRadius: '14px', border: '1px solid var(--border-subtle)', padding: '20px' }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '14px' }}>
        <div style={{ width: '36px', height: '36px', borderRadius: '8px', background: 'rgba(59, 130, 246, 0.1)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
          <Cpu size={20} color="#3b82f6" />
        </div>
        <div>
          <h3 style={{ margin: 0, fontSize: '17px', fontWeight: 700 }}>{isImageAgent ? 'Image Generate' : agent.key}</h3>
          <span style={{ fontSize: '12px', color: 'var(--text-muted)' }}>{agent.rules_path}</span>
        </div>
      </div>
      <AgentModelControl agentKey={agent.key} model={agent.model} saving={saving} onSave={onSaveModel} />
      {isImageAgent && (
        <AgentModelControl agentKey={`${agent.key}-image`} mode="image" model={imageModel} saving={saving} onSave={onSaveImageModel} />
      )}
      <div>
        <label style={{ display: 'block', fontSize: '13px', color: 'var(--text-muted)', marginBottom: '6px' }}>
          Công cụ được cấp quyền ({agent.allowed_tools.length})
        </label>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '6px' }}>
          {agent.allowed_tools.map((tool) => (
            <span key={tool} style={{ fontSize: '11px', background: 'rgba(255, 255, 255, 0.05)', padding: '4px 8px', borderRadius: '4px', border: '1px solid var(--border-subtle)', color: 'var(--text-muted)' }}>
              {tool}
            </span>
          ))}
        </div>
      </div>
    </div>
  );
}
