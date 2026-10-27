'use client';

import React, { useState } from 'react';
import { Globe, ExternalLink, HelpCircle, CheckCircle2 } from 'lucide-react';
import { ProjectSettingsData } from '@/lib/api';

interface WordPressTabProps {
  data: ProjectSettingsData | null;
  onConnect: (url: string, user: string, pass: string) => Promise<void>;
  connecting: boolean;
}

export default function WordPressTab({ data, onConnect, connecting }: WordPressTabProps) {
  const [wpUrl, setWpUrl] = useState(data?.wordpress.site_url || '');
  const [wpUser, setWpUser] = useState(data?.wordpress.admin_user || 'admin');
  const [wpPass, setWpPass] = useState('');
  const [showGuide, setShowGuide] = useState(true);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!wpUrl.trim() || !wpUser.trim() || !wpPass.trim()) return;
    onConnect(wpUrl.trim(), wpUser.trim(), wpPass.trim());
  };

  return (
    <div>
      {/* Current Status Box */}
      <div style={{ padding: '16px', background: '#1e293b', borderRadius: '12px', marginBottom: '20px', border: '1px solid #334155' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
          <span style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0' }}>Trạng thái kết nối WordPress:</span>
          <span style={{ fontSize: '12px', padding: '3px 8px', borderRadius: '12px', fontWeight: 600, background: data?.wordpress.connected ? 'rgba(34, 197, 94, 0.2)' : 'rgba(148, 163, 184, 0.2)', color: data?.wordpress.connected ? '#4ade80' : '#94a3b8' }}>
            {data?.wordpress.connected ? '● Đã kết nối' : '○ Chưa kết nối'}
          </span>
        </div>
        {data?.wordpress.site_url ? (
          <div style={{ fontSize: '12px', color: '#94a3b8', lineHeight: 1.6 }}>
            <div>URL: <a href={data.wordpress.site_url} target="_blank" rel="noreferrer" style={{ color: '#38bdf8', textDecoration: 'none' }}>{data.wordpress.site_url} <ExternalLink size={12} style={{ display: 'inline' }} /></a></div>
            {data.wordpress.admin_url && <div>Admin: <a href={data.wordpress.admin_url} target="_blank" rel="noreferrer" style={{ color: '#94a3b8', textDecoration: 'underline' }}>{data.wordpress.admin_url}</a></div>}
            <div>Loại môi trường: <strong style={{ color: '#e2e8f0' }}>{data.wordpress.site_type === 'external' ? 'Website WordPress bên ngoài' : 'Docker Container nội bộ'}</strong></div>
          </div>
        ) : (
          <p style={{ fontSize: '12px', color: '#64748b', margin: 0 }}>Chưa có website WordPress nào được liên kết với dự án này.</p>
        )}
      </div>

      {/* Guide Banner */}
      <div style={{ padding: '16px', background: 'rgba(56, 189, 248, 0.06)', borderRadius: '12px', border: '1px solid rgba(56, 189, 248, 0.2)', marginBottom: '20px' }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: showGuide ? '12px' : '0' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '8px', color: '#38bdf8', fontWeight: 700, fontSize: '13px' }}>
            <HelpCircle size={16} />
            📖 Hướng dẫn lấy thông tin kết nối WordPress qua Application Password
          </div>
          <button type="button" onClick={() => setShowGuide(!showGuide)} style={{ background: 'transparent', border: 'none', color: '#38bdf8', fontSize: '12px', cursor: 'pointer', textDecoration: 'underline' }}>
            {showGuide ? 'Thu gọn' : 'Xem chi tiết'}
          </button>
        </div>
        {showGuide && (
          <ol style={{ margin: 0, paddingLeft: '18px', fontSize: '12px', color: '#cbd5e1', lineHeight: 1.7 }}>
            <li>Đăng nhập vào trang quản trị WordPress của bạn (ví dụ: <code>your-domain.com/wp-admin</code>).</li>
            <li>Vào menu <strong>Users (Thành viên)</strong> &rarr; <strong>Profile (Hồ sơ của bạn)</strong>.</li>
            <li>Kéo xuống mục <strong>Application Passwords (Mật khẩu ứng dụng)</strong>.</li>
            <li>Nhập tên định danh (ví dụ: <code>Align AI Hub</code>) rồi bấm nút <strong>Add New Application Password</strong>.</li>
            <li>Sao chép chuỗi mật khẩu 16-24 ký tự (dạng <code>xxxx xxxx xxxx xxxx</code>), dán vào ô mật khẩu bên dưới cùng URL và Username rồi bấm <strong>Kiểm tra &amp; Kết nối</strong>.</li>
          </ol>
        )}
      </div>

      {/* Connect Form */}
      <h4 style={{ fontSize: '14px', fontWeight: 600, color: '#f8fafc', marginBottom: '8px' }}>Kết nối Website WordPress Bên Ngoài</h4>
      <p style={{ fontSize: '12px', color: '#94a3b8', marginBottom: '16px' }}>Sau khi kết nối, AI Agent có thể trực tiếp viết bài, tạo trang, tối ưu SEO và upload media vào WordPress của bạn.</p>

      <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
        <div>
          <label style={{ fontSize: '12px', color: '#cbd5e1', fontWeight: 600, display: 'block', marginBottom: '4px' }}>Website URL</label>
          <input type="text" placeholder="https://vietthanhcrocodile.vn" value={wpUrl} onChange={e => setWpUrl(e.target.value)} required style={{ width: '100%', padding: '10px 12px', background: '#0b1120', border: '1px solid #334155', borderRadius: '8px', color: '#f8fafc', fontSize: '13px' }} />
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
          <div>
            <label style={{ fontSize: '12px', color: '#cbd5e1', fontWeight: 600, display: 'block', marginBottom: '4px' }}>Username Admin</label>
            <input type="text" placeholder="admin" value={wpUser} onChange={e => setWpUser(e.target.value)} required style={{ width: '100%', padding: '10px 12px', background: '#0b1120', border: '1px solid #334155', borderRadius: '8px', color: '#f8fafc', fontSize: '13px' }} />
          </div>
          <div>
            <label style={{ fontSize: '12px', color: '#cbd5e1', fontWeight: 600, display: 'block', marginBottom: '4px' }}>Application Password</label>
            <input type="password" placeholder="xxxx xxxx xxxx xxxx" value={wpPass} onChange={e => setWpPass(e.target.value)} required style={{ width: '100%', padding: '10px 12px', background: '#0b1120', border: '1px solid #334155', borderRadius: '8px', color: '#f8fafc', fontSize: '13px' }} />
          </div>
        </div>
        <button type="submit" disabled={connecting} style={{ alignSelf: 'flex-start', padding: '10px 20px', background: '#38bdf8', color: '#0f172a', fontWeight: 700, borderRadius: '8px', border: 'none', cursor: 'pointer', fontSize: '13px' }}>
          {connecting ? 'Đang kiểm tra kết nối...' : 'Kiểm tra & Kết nối WordPress'}
        </button>
      </form>
    </div>
  );
}
