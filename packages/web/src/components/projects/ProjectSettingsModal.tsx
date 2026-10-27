'use client';

import React, { useEffect, useState } from 'react';
import { X, Globe, Users, BarChart3, Send, AlertCircle, CheckCircle2 } from 'lucide-react';
import {
  fetchProjectSettings, connectProjectWordPress, addProjectMember,
  removeProjectMember, generateProjectTelegramCode, ProjectSettingsData
} from '@/lib/api';
import WordPressTab from './tabs/WordPressTab';
import TelegramTab from './tabs/TelegramTab';
import MembersTab from './tabs/MembersTab';
import ProgressTab from './tabs/ProgressTab';

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

  const [wpConnecting, setWpConnecting] = useState(false);
  const [memberAdding, setMemberAdding] = useState(false);
  const [tgCode, setTgCode] = useState<string | null>(null);
  const [tgLoading, setTgLoading] = useState(false);

  const loadSettings = async () => {
    setLoading(true);
    try {
      const res = await fetchProjectSettings(projectId);
      setData(res);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Không thể tải cài đặt dự án.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSettings();
  }, [projectId]);

  const handleConnectWp = async (url: string, user: string, pass: string) => {
    setWpConnecting(true);
    setError('');
    setSuccess('');
    try {
      const res = await connectProjectWordPress(projectId, {
        site_type: 'external',
        site_url: url,
        admin_user: user,
        admin_password: pass,
      });
      setSuccess(res.message || 'Kết nối WordPress thành công!');
      await loadSettings();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Kết nối thất bại.');
    } finally {
      setWpConnecting(false);
    }
  };

  const handleAddMember = async (email: string, role: string) => {
    setMemberAdding(true);
    setError('');
    try {
      await addProjectMember(projectId, { email, role });
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
      setSuccess('Đã xóa thành viên.');
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

          {activeTab === 'wordpress' && <WordPressTab data={data} onConnect={handleConnectWp} connecting={wpConnecting} />}
          {activeTab === 'members' && <MembersTab data={data} onAddMember={handleAddMember} onRemoveMember={handleRemoveMember} adding={memberAdding} />}
          {activeTab === 'progress' && <ProgressTab data={data} />}
          {activeTab === 'telegram' && <TelegramTab data={data} onGetCode={handleGetTelegramCode} loading={tgLoading} code={tgCode} />}
        </div>
      </div>
    </div>
  );
}
