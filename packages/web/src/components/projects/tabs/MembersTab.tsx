'use client';

import React, { useState } from 'react';
import { Users, HelpCircle, Trash2, ShieldCheck, Eye } from 'lucide-react';
import { ProjectSettingsData } from '@/lib/api';

interface MembersTabProps {
  data: ProjectSettingsData | null;
  onAddMember: (email: string, role: string) => Promise<void>;
  onRemoveMember: (id: string) => Promise<void>;
  adding: boolean;
}

export default function MembersTab({ data, onAddMember, onRemoveMember, adding }: MembersTabProps) {
  const [email, setEmail] = useState('');
  const [role, setRole] = useState('editor');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!email.trim()) return;
    onAddMember(email.trim(), role);
    setEmail('');
  };

  return (
    <div>
      {/* Role explanation guide */}
      <div style={{ padding: '16px', background: 'rgba(56, 189, 248, 0.06)', borderRadius: '12px', border: '1px solid rgba(56, 189, 248, 0.2)', marginBottom: '20px' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#38bdf8', fontWeight: 700, fontSize: '13px', marginBottom: '8px' }}>
          <HelpCircle size={16} />
          📖 Hướng dẫn phân quyền thành viên dự án
        </div>
        <div style={{ fontSize: '12px', color: '#cbd5e1', lineHeight: 1.6, display: 'flex', flexDirection: 'column', gap: '6px' }}>
          <div><strong style={{ color: '#38bdf8' }}>Editor (Cộng tác viên)</strong>: Có quyền nhắn tin điều khiển AI Agent, tạo và chỉnh sửa file trong Workspace, kích hoạt triển khai WordPress và xuất file ZIP.</div>
          <div><strong style={{ color: '#94a3b8' }}>Viewer (Khách hàng / Quan sát viên)</strong>: Chỉ có quyền xem sản phẩm hoàn thiện, xem tiến độ và đọc báo cáo SEO/Lighthouse mà không can thiệp vào mã nguồn.</div>
        </div>
      </div>

      {/* Invite Form */}
      <form onSubmit={handleSubmit} style={{ display: 'flex', gap: '8px', marginBottom: '20px' }}>
        <input type="email" placeholder="Nhập email thành viên cần mời..." value={email} onChange={e => setEmail(e.target.value)} required style={{ flex: 1, padding: '10px 12px', background: '#0b1120', border: '1px solid #334155', borderRadius: '8px', color: '#f8fafc', fontSize: '13px' }} />
        <select value={role} onChange={e => setRole(e.target.value)} style={{ padding: '10px 12px', background: '#0b1120', border: '1px solid #334155', borderRadius: '8px', color: '#f8fafc', fontSize: '13px' }}>
          <option value="editor">Editor (Cộng tác viên)</option>
          <option value="viewer">Viewer (Chỉ xem)</option>
        </select>
        <button type="submit" disabled={adding} style={{ padding: '10px 18px', background: '#38bdf8', color: '#0f172a', fontWeight: 700, borderRadius: '8px', border: 'none', cursor: 'pointer', fontSize: '13px', whiteSpace: 'nowrap' }}>
          {adding ? 'Đang thêm...' : '+ Thêm'}
        </button>
      </form>

      {/* Members List */}
      <h4 style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0', marginBottom: '12px' }}>Thành viên trong dự án ({data?.members.length || 0})</h4>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '8px' }}>
        {data?.members.map(m => (
          <div key={m.id} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '12px 16px', background: '#1e293b', borderRadius: '8px', border: '1px solid #334155' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
              <div style={{ width: '32px', height: '32px', borderRadius: '50%', background: '#38bdf8', color: '#0f172a', display: 'flex', alignItems: 'center', justifyContent: 'center', fontWeight: 700, fontSize: '13px' }}>
                {m.email[0].toUpperCase()}
              </div>
              <div>
                <div style={{ fontSize: '13px', color: '#f8fafc', fontWeight: 600 }}>{m.email}</div>
                <div style={{ fontSize: '11px', color: '#94a3b8' }}>Vai trò: <span style={{ textTransform: 'capitalize', color: m.role === 'editor' ? '#38bdf8' : '#94a3b8' }}>{m.role}</span></div>
              </div>
            </div>
            <button onClick={() => onRemoveMember(m.id)} style={{ background: 'transparent', border: 'none', color: '#f87171', cursor: 'pointer', padding: '4px' }} title="Xóa thành viên">
              <Trash2 size={16} />
            </button>
          </div>
        ))}
        {(!data?.members || data.members.length === 0) && (
          <p style={{ fontSize: '12px', color: '#64748b', textAlign: 'center', padding: '16px' }}>Chưa có thành viên nào khác. Bạn đang là chủ dự án.</p>
        )}
      </div>
    </div>
  );
}
