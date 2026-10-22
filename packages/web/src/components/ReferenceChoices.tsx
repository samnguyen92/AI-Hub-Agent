'use client';

import React from 'react';
import type { ReferenceCandidate } from '@/lib/types';

interface ReferenceChoicesProps {
  candidates: ReferenceCandidate[];
  selected: string[];
  onChange: (ids: string[]) => void;
}

export default function ReferenceChoices({ candidates, selected, onChange }: ReferenceChoicesProps) {
  return (
    <div style={{ display: 'grid', gap: 8, margin: '12px 0' }}>
      <strong style={{ fontSize: 12, color: '#E2E8F0' }}>Chọn website tham khảo muốn lưu vào Library:</strong>
      {candidates.map((candidate) => {
        const checked = selected.includes(candidate.id);
        return (
          <label key={candidate.id} style={{ display: 'grid', gridTemplateColumns: '18px 1fr', gap: 9, padding: 10, border: `1px solid ${checked ? 'var(--accent-primary)' : 'var(--border-subtle)'}`, borderRadius: 8, cursor: 'pointer' }}>
            <input type="checkbox" checked={checked} onChange={() => onChange(checked ? selected.filter((id) => id !== candidate.id) : [...selected, candidate.id])} />
            <span style={{ display: 'grid', gap: 4 }}>
              <a href={candidate.url} target="_blank" rel="noreferrer" onClick={(event) => event.stopPropagation()} style={{ color: '#A5B4FC', fontSize: 12, fontWeight: 700 }}>{candidate.title} ↗</a>
              <span style={{ color: 'var(--text-muted)', fontSize: 11 }}>{candidate.rationale}</span>
              <span style={{ color: 'var(--text-dim)', fontSize: 11 }}>Nên tham khảo: {candidate.study_focus}</span>
            </span>
          </label>
        );
      })}
    </div>
  );
}
