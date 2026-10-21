'use client';

import React, { useMemo, useState } from 'react';
import { DesignReference } from '@/lib/api';
import ReferenceCard from './ReferenceCard';

interface ReferenceShelfProps {
  references: DesignReference[];
  projectId: string;
  onChanged: () => void;
}

export default function ReferenceShelf({ references, projectId, onChanged }: ReferenceShelfProps) {
  const [query, setQuery] = useState('');
  const filtered = useMemo(() => {
    const normalized = query.trim().toLowerCase();
    if (!normalized) return references;
    return references.filter((reference) =>
      [reference.title, reference.source_url, reference.notes || '', ...reference.tags]
        .some((value) => value.toLowerCase().includes(normalized)),
    );
  }, [query, references]);
  return (
    <section>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '12px', flexWrap: 'wrap' }}>
        <h2 style={{ fontSize: '15px', fontWeight: 600, color: 'var(--text-dim)', letterSpacing: '0.05em', marginBottom: '16px' }}>
        Design references ({references.length})
        </h2>
        {!!references.length && <input aria-label="Tìm reference" placeholder="Tìm theo tên, URL, ghi chú, tag" value={query} onChange={(event) => setQuery(event.target.value)} />}
      </div>
      {!references.length ? (
        <div className="glass-panel" style={{ padding: '24px', color: 'var(--text-muted)' }}>
          Gửi URL trong chat và yêu cầu Design Agent lưu làm reference. Ảnh desktop/mobile và ghi chú sẽ xuất hiện ở đây.
        </div>
      ) : (
        filtered.length ? <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: '16px' }}>
          {filtered.map((reference) => (
            <ReferenceCard key={reference.id} reference={reference} projectId={projectId} onChanged={onChanged} />
          ))}
        </div> : <p style={{ color: 'var(--text-muted)' }}>Không tìm thấy reference phù hợp.</p>
      )}
    </section>
  );
}
