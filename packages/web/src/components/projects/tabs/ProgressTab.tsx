'use client';

import React from 'react';
import { BarChart3, CheckCircle2, Clock, HelpCircle } from 'lucide-react';
import { ProjectSettingsData } from '@/lib/api';

interface ProgressTabProps {
  data: ProjectSettingsData | null;
}

export default function ProgressTab({ data }: ProgressTabProps) {
  const completedMilestones = data?.progress.milestones.filter(m => m.status === 'completed').length || 1;
  const progressPercent = Math.round((completedMilestones / 4) * 100);

  return (
    <div>
      {/* Progress guide */}
      <div style={{ padding: '16px', background: 'rgba(56, 189, 248, 0.06)', borderRadius: '12px', border: '1px solid rgba(56, 189, 248, 0.2)', marginBottom: '20px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#38bdf8', fontWeight: 700, fontSize: '13px', marginBottom: '8px' }}>
          <HelpCircle size={16} />
          📖 Cách hệ thống theo dõi và cập nhật tiến độ tự động
        </div>
        <p style={{ fontSize: '12px', color: '#cbd5e1', lineHeight: 1.6, margin: 0 }}>
          Hệ thống theo dõi các lượt thực thi (Runs) của từng chuyên gia AI (Design, Code-Builder, WordPress Builder, SEO Auditor). Khi hoàn thành mỗi giai đoạn quan trọng, mốc tiến độ tương ứng sẽ tự động chuyển sang trạng thái <strong>Đã hoàn thành</strong>.
        </p>
      </div>

      {/* Progress Bar */}
      <div style={{ marginBottom: '20px', padding: '16px', background: '#1e293b', borderRadius: '12px', border: '1px solid #334155' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
          <span style={{ fontSize: '13px', fontWeight: 600, color: '#f8fafc' }}>Tiến độ tổng quan dự án</span>
          <span style={{ fontSize: '13px', fontWeight: 700, color: '#38bdf8' }}>{progressPercent}%</span>
        </div>
        <div style={{ width: '100%', height: '8px', background: '#0b1120', borderRadius: '4px', overflow: 'hidden' }}>
          <div style={{ width: `${progressPercent}%`, height: '100%', background: '#38bdf8', transition: 'width 0.3s' }} />
        </div>
      </div>

      {/* Milestones */}
      <h4 style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0', marginBottom: '12px' }}>Checklist Giai Đoạn (Milestones)</h4>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '24px' }}>
        {data?.progress.milestones.map(m => (
          <div key={m.phase} style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '12px 14px', background: '#1e293b', borderRadius: '8px', border: '1px solid #334155' }}>
            {m.status === 'completed' ? <CheckCircle2 size={18} color="#4ade80" /> : <Clock size={18} color="#94a3b8" />}
            <div>
              <div style={{ fontSize: '13px', color: m.status === 'completed' ? '#f8fafc' : '#94a3b8', fontWeight: 600 }}>
                Giai đoạn {m.phase}: {m.name}
              </div>
              <div style={{ fontSize: '11px', color: m.status === 'completed' ? '#4ade80' : '#64748b' }}>
                {m.status === 'completed' ? '● Đã hoàn thành' : '○ Đang chờ thực hiện'}
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Recent Runs */}
      <h4 style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0', marginBottom: '12px' }}>Các Phiên Chạy Gần Nhất ({data?.progress.total_runs || 0} runs)</h4>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
        {data?.progress.recent_runs.map(r => (
          <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '10px 12px', background: '#0b1120', borderRadius: '6px', fontSize: '12px', border: '1px solid #1e293b' }}>
            <span style={{ color: '#38bdf8', fontWeight: 600 }}>@{r.agent_key}</span>
            <span style={{ color: r.status === 'completed' ? '#4ade80' : '#f59e0b', textTransform: 'capitalize' }}>{r.status}</span>
          </div>
        ))}
        {(!data?.progress.recent_runs || data.progress.recent_runs.length === 0) && (
          <p style={{ fontSize: '12px', color: '#64748b', textAlign: 'center', padding: '12px' }}>Chưa có phiên chạy nào của AI Agent trong dự án này.</p>
        )}
      </div>
    </div>
  );
}
