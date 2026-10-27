'use client';

import React, { useEffect, useState } from 'react';
import {
  X, Globe, Users, BarChart3, Send, CheckCircle2, Clock,
  AlertCircle, Trash2, ExternalLink, Key, UserCheck, Shield
} from 'lucide-react';
import {
  fetchProjectSettings, connectProjectWordPress, addProjectMember,
  removeProjectMember, generateProjectTelegramCode, ProjectSettingsData
} from '@/lib/api';

interface ProjectSettingsModalProps {
  projectId: string;
  projectName: string;
  onClose: () => void;
}

export default function ProjectSettingsModal({ projectId, projectName, onClose }: ProjectSettingsModalProps) {
  const [activeTab, setActiveTab] = useState<'wordpress' | 'members' | 'progress' | 'telegram'>('wordpress');
  const [data, setData] = useState<ProjectSettingsData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  // WordPress form state
  const [wpUrl, setWpUrl] = useState('');
  const [wpUser, setWpUser] = useState('');
  const [wpPass, setWpPass] = useState('');
  const [wpConnecting, setWpConnecting] = useState(false);

  // Member form state
  const [memberEmail, setMemberEmail] = useState('');
  const [memberRole, setMemberRole] = useState('editor');
  const [memberAdding, setMemberAdding] = useState(false);

  // Telegram code state
  const [tgCode, setTgCode] = useState<string | null>(null);
  const [tgLoading, setTgLoading] = useState(false);

  const loadSettings = async () => {
    setLoading(true);
    try {
      const res = await fetchProjectSettings(projectId);
      setData(res);
      if (res.wordpress.site_url) {
        setWpUrl(res.wordpress.site_url);
        setWpUser(res.wordpress.admin_user || 'admin');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không thể tải cài đặt dự án.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSettings();
  }, [projectId]);

  const handleConnectWp = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!wpUrl.trim() || !wpUser.trim() || !wpPass.trim()) return;
    setWpConnecting(true);
    setError('');
    setSuccess('');
    try {
      const res = await connectProjectWordPress(projectId, {
        site_type: 'external',
        site_url: wpUrl.trim(),
        admin_user: wpUser.trim(),
        admin_password: wpPass.trim(),
      });
      setSuccess(res.message || 'Kết nối WordPress thành công!');
      await loadSettings();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Kết nối thất bại.');
    } finally {
      setWpConnecting(false);
    }
  };

  const handleAddMember = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!memberEmail.trim()) return;
    setMemberAdding(true);
    setError('');
    try {
      await addProjectMember(projectId, { email: memberEmail.trim(), role: memberRole });
      setMemberEmail('');
      setSuccess('Đã thêm thành viên vào dự án!');
      await loadSettings();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không thể thêm thành viên.');
    } finally {
      setMemberAdding(false);
    }
  };

  const handleRemoveMember = async (memberId: string) => {
    if (!window.confirm('Bạn có chắc muốn xóa thành viên này khỏi dự án?')) return;
    try {
      await removeProjectMember(projectId, memberId);
      await loadSettings();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không thể xóa thành viên.');
    }
  };

  const handleGetTelegramCode = async () => {
    setTgLoading(true);
    try {
      const res = await generateProjectTelegramCode(projectId);
      setTgCode(res.command);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không thể tạo mã Telegram.');
    } finally {
      setTgLoading(false);
    }
  };

  const completedMilestones = data?.progress.milestones.filter(m => m.status === 'completed').length || 1;
  const progressPercent = Math.round((completedMilestones / 4) * 100);

  return (
    <div style={{ position: 'fixed', inset: 0, background: 'rgba(0,0,0,0.75)', backdropFilter: 'blur(6px)', display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 1100, padding: '20px' }}>
      <div style={{ background: '#0f172a', border: '1px solid #1e293b', borderRadius: '16px', width: '100%', maxWidth: '720px', maxHeight: '90vh', display: 'flex', flexDirection: 'column', boxShadow: '0 25px 50px -12px rgba(0,0,0,0.5)', overflow: 'hidden' }}>
        
        {/* Header */}
        <div style={{ padding: '20px 24px', borderBottom: '1px solid #1e293b', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div>
            <h2 style={{ fontSize: '18px', fontWeight: 700, color: '#f8fafc', margin: 0 }}>Cài đặt Dự án: {projectName}</h2>
            <p style={{ fontSize: '12px', color: '#94a3b8', margin: '4px 0 0 0' }}>ID: {projectId}</p>
          </div>
          <button onClick={onClose} style={{ background: 'transparent', border: 'none', color: '#94a3b8', cursor: 'pointer', padding: '4px' }}>
            <X size={20} />
          </button>
        </div>

        {/* Tab Nav */}
        <div style={{ display: 'flex', borderBottom: '1px solid #1e293b', background: '#0b1120', padding: '0 16px' }}>
          {[
            { id: 'wordpress', label: 'WordPress', icon: Globe },
            { id: 'members', label: 'Thành viên', icon: Users },
            { id: 'progress', label: 'Tiến độ', icon: BarChart3 },
            { id: 'telegram', label: 'Telegram', icon: Send },
          ].map(tab => {
            const Icon = tab.icon;
            const active = activeTab === tab.id;
            return (
              <button key={tab.id} onClick={() => { setActiveTab(tab.id as any); setError(''); setSuccess(''); }} style={{ display: 'flex', alignItems: 'center', gap: '8px', padding: '14px 16px', fontSize: '13px', fontWeight: 600, border: 'none', background: 'transparent', color: active ? '#38bdf8' : '#94a3b8', borderBottom: active ? '2px solid #38bdf8' : '2px solid transparent', cursor: 'pointer' }}>
                <Icon size={16} />
                {tab.label}
              </button>
            );
          })}
        </div>

        {/* Body Content */}
        <div style={{ padding: '24px', overflowY: 'auto', flex: 1 }}>
          {error && <div style={{ padding: '12px 16px', background: 'rgba(239, 68, 68, 0.1)', border: '1px solid rgba(239, 68, 68, 0.2)', color: '#f87171', borderRadius: '8px', marginBottom: '16px', fontSize: '13px', display: 'flex', gap: '8px', alignItems: 'center' }}><AlertCircle size={16} />{error}</div>}
          {success && <div style={{ padding: '12px 16px', background: 'rgba(34, 197, 94, 0.1)', border: '1px solid rgba(34, 197, 94, 0.2)', color: '#4ade80', borderRadius: '8px', marginBottom: '16px', fontSize: '13px', display: 'flex', gap: '8px', alignItems: 'center' }}><CheckCircle2 size={16} />{success}</div>}

          {/* TAB 1: WORDPRESS */}
          {activeTab === 'wordpress' && (
            <div>
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

              <h4 style={{ fontSize: '14px', fontWeight: 600, color: '#f8fafc', marginBottom: '8px' }}>Kết nối Website WordPress Khác (Bên ngoài)</h4>
              <p style={{ fontSize: '12px', color: '#94a3b8', marginBottom: '16px' }}>Cho phép AI Agent truy cập, đăng bài và chỉnh sửa trực tiếp trên website WordPress thực tế của bạn qua REST API.</p>

              <form onSubmit={handleConnectWp} style={{ display: 'flex', flexDirection: 'column', gap: '12px' }}>
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
                <div style={{ padding: '10px', background: 'rgba(56, 189, 248, 0.08)', borderRadius: '6px', fontSize: '11px', color: '#7dd3fc', lineHeight: 1.5 }}>
                  💡 <strong>Cách lấy Application Password:</strong> Vào WordPress Admin &rarr; Users &rarr; Profile &rarr; kéo xuống mục <em>Application Passwords</em> &rarr; Đặt tên &quot;Align Agent&quot; rồi bấm <em>Add New Application Password</em>.
                </div>
                <button type="submit" disabled={wpConnecting} style={{ alignSelf: 'flex-start', padding: '10px 20px', background: '#38bdf8', color: '#0f172a', fontWeight: 700, borderRadius: '8px', border: 'none', cursor: 'pointer', fontSize: '13px' }}>
                  {wpConnecting ? 'Đang kiểm tra kết nối...' : 'Kiểm tra & Kết nối WordPress'}
                </button>
              </form>
            </div>
          )}

          {/* TAB 2: MEMBERS */}
          {activeTab === 'members' && (
            <div>
              <form onSubmit={handleAddMember} style={{ display: 'flex', gap: '8px', marginBottom: '20px' }}>
                <input type="email" placeholder="Email thành viên muốn mời..." value={memberEmail} onChange={e => setMemberEmail(e.target.value)} required style={{ flex: 1, padding: '10px 12px', background: '#0b1120', border: '1px solid #334155', borderRadius: '8px', color: '#f8fafc', fontSize: '13px' }} />
                <select value={memberRole} onChange={e => setMemberRole(e.target.value)} style={{ padding: '10px 12px', background: '#0b1120', border: '1px solid #334155', borderRadius: '8px', color: '#f8fafc', fontSize: '13px' }}>
                  <option value="editor">Editor (Có thể chat & sửa web)</option>
                  <option value="viewer">Viewer (Chỉ xem báo cáo)</option>
                </select>
                <button type="submit" disabled={memberAdding} style={{ padding: '10px 16px', background: '#38bdf8', color: '#0f172a', fontWeight: 700, borderRadius: '8px', border: 'none', cursor: 'pointer', fontSize: '13px', whiteSpace: 'nowrap' }}>
                  {memberAdding ? 'Đang thêm...' : '+ Thêm'}
                </button>
              </form>

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
                        <div style={{ fontSize: '11px', color: '#94a3b8' }}>Vai trò: <span style={{ textTransform: 'capitalize', color: '#38bdf8' }}>{m.role}</span></div>
                      </div>
                    </div>
                    <button onClick={() => handleRemoveMember(m.id)} style={{ background: 'transparent', border: 'none', color: '#f87171', cursor: 'pointer', padding: '4px' }} title="Xóa thành viên">
                      <Trash2 size={16} />
                    </button>
                  </div>
                ))}
                {(!data?.members || data.members.length === 0) && (
                  <p style={{ fontSize: '12px', color: '#64748b', textAlign: 'center', padding: '16px' }}>Chưa có thành viên nào khác. Bạn đang là chủ dự án.</p>
                )}
              </div>
            </div>
          )}

          {/* TAB 3: PROGRESS */}
          {activeTab === 'progress' && (
            <div>
              <div style={{ marginBottom: '20px', padding: '16px', background: '#1e293b', borderRadius: '12px', border: '1px solid #334155' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '8px' }}>
                  <span style={{ fontSize: '13px', fontWeight: 600, color: '#f8fafc' }}>Tiến độ tổng quan dự án</span>
                  <span style={{ fontSize: '13px', fontWeight: 700, color: '#38bdf8' }}>{progressPercent}%</span>
                </div>
                <div style={{ width: '100%', height: '8px', background: '#0b1120', borderRadius: '4px', overflow: 'hidden' }}>
                  <div style={{ width: `${progressPercent}%`, height: '100%', background: '#38bdf8', transition: 'width 0.3s' }} />
                </div>
              </div>

              <h4 style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0', marginBottom: '12px' }}>Checklist Giai Đoạn (Milestones)</h4>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '8px', marginBottom: '24px' }}>
                {data?.progress.milestones.map(m => (
                  <div key={m.phase} style={{ display: 'flex', alignItems: 'center', gap: '12px', padding: '10px 14px', background: '#1e293b', borderRadius: '8px', border: '1px solid #334155' }}>
                    {m.status === 'completed' ? <CheckCircle2 size={18} color="#4ade80" /> : <Clock size={18} color="#94a3b8" />}
                    <span style={{ fontSize: '13px', color: m.status === 'completed' ? '#f8fafc' : '#94a3b8' }}>
                      Giai đoạn {m.phase}: {m.name}
                    </span>
                  </div>
                ))}
              </div>

              <h4 style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0', marginBottom: '12px' }}>Các Phiên Chạy Gần Nhất ({data?.progress.total_runs || 0} runs)</h4>
              <div style={{ display: 'flex', flexDirection: 'column', gap: '6px' }}>
                {data?.progress.recent_runs.map(r => (
                  <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 12px', background: '#0b1120', borderRadius: '6px', fontSize: '12px' }}>
                    <span style={{ color: '#38bdf8', fontWeight: 600 }}>@{r.agent_key}</span>
                    <span style={{ color: r.status === 'completed' ? '#4ade80' : '#f59e0b', textTransform: 'capitalize' }}>{r.status}</span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* TAB 4: TELEGRAM */}
          {activeTab === 'telegram' && (
            <div>
              <div style={{ padding: '16px', background: '#1e293b', borderRadius: '12px', marginBottom: '20px', border: '1px solid #334155' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' }}>
                  <span style={{ fontSize: '13px', fontWeight: 600, color: '#e2e8f0' }}>Trạng thái kết nối Telegram:</span>
                  <span style={{ fontSize: '12px', padding: '3px 8px', borderRadius: '12px', fontWeight: 600, background: data?.telegram.is_linked ? 'rgba(34, 197, 94, 0.2)' : 'rgba(148, 163, 184, 0.2)', color: data?.telegram.is_linked ? '#4ade80' : '#94a3b8' }}>
                    {data?.telegram.is_linked ? `● Đã liên kết (ID: ${data.telegram.telegram_id})` : '○ Chưa liên kết'}
                  </span>
                </div>
                <p style={{ fontSize: '12px', color: '#94a3b8', margin: 0 }}>
                  Nhận thông báo khi Agent hoàn thành tác vụ, nhận đường link website WordPress và duyệt các thao tác xuất file trực tiếp trên Telegram.
                </p>
              </div>

              <button onClick={handleGetTelegramCode} disabled={tgLoading} style={{ padding: '10px 18px', background: '#38bdf8', color: '#0f172a', fontWeight: 700, borderRadius: '8px', border: 'none', cursor: 'pointer', fontSize: '13px', display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '16px' }}>
                <Send size={15} />
                {tgLoading ? 'Đang tạo mã...' : 'Lấy mã kết nối Telegram'}
              </button>

              {tgCode && (
                <div style={{ padding: '16px', background: '#0b1120', border: '1px solid #38bdf8', borderRadius: '8px' }}>
                  <p style={{ fontSize: '12px', color: '#e2e8f0', margin: '0 0 8px 0' }}>Mở Telegram và gửi tin nhắn sau cho bot của bạn:</p>
                  <code style={{ display: 'block', padding: '10px', background: '#1e293b', borderRadius: '6px', color: '#38bdf8', fontWeight: 700, fontSize: '14px', letterSpacing: '0.05em' }}>
                    {tgCode}
                  </code>
                  <p style={{ fontSize: '11px', color: '#94a3b8', margin: '8px 0 0 0' }}>Mã có hiệu lực trong vòng 15 phút.</p>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
