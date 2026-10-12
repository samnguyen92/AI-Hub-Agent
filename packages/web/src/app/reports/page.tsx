'use client';

import React, { useEffect, useState } from 'react';
import { useSearchParams } from 'next/navigation';
import { BarChart3, Globe, ShieldAlert, FileSpreadsheet, RefreshCw } from 'lucide-react';
import { fetchProjects, Project } from '@/lib/api';
import LighthouseScoreCards from '@/components/reports/LighthouseScoreCards';
import AuditIssuesTable, { AuditIssue } from '@/components/reports/AuditIssuesTable';

export default function ReportsPage() {
  const searchParams = useSearchParams();
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProjectId, setSelectedProjectId] = useState<string>('');
  const [activeTab, setActiveTab] = useState<'site' | 'seo' | 'analytics'>('site');

  useEffect(() => {
    fetchProjects().then((data) => {
      setProjects(data);
      const urlProj = searchParams.get('projectId');
      const defaultProj = urlProj || (data.length > 0 ? data[0].id : '');
      setSelectedProjectId(defaultProj);
    });
  }, [searchParams]);

  // Sample data demonstrating Site Report & SEO Audit
  const sampleLighthouse = {
    performance: 0.88,
    accessibility: 0.95,
    best_practices: 0.92,
    seo: 0.90,
  };

  const sampleCwv = {
    lcp: '1.9s (Good)',
    fid_or_inp: '38ms (Good)',
    cls: '0.01 (Good)',
  };

  const sampleIssues: AuditIssue[] = [
    {
      id: 'SEO-01',
      title: 'Thiếu thuộc tính alt trên 3 ảnh sản phẩm trang chủ',
      severity: 'medium',
      affected_url: 'https://align.vn/',
      evidence: '<img src="/assets/hero-bg.png"> không có alt attribute.',
      recommendation: 'Bổ sung thuộc tính alt mô tả rõ ngữ cảnh của hình ảnh để tăng khả năng tiếp cận và SEO hình ảnh.',
      suggested_patch: '<img src="/assets/hero-bg.png" alt="Align AI Hub Platform Showcase">',
    },
    {
      id: 'SEO-02',
      title: 'Thẻ Meta Description quá ngắn (< 70 ký tự)',
      severity: 'high',
      affected_url: 'https://align.vn/about',
      evidence: 'Độ dài hiện tại: 52 ký tự ("Align - Giải pháp thiết kế và phát triển website.").',
      recommendation: 'Viết lại thẻ meta description độ dài 130 - 155 ký tự kèm kêu gọi hành động (CTA) để cải thiện tỷ lệ CTR.',
      suggested_patch: '<meta name="description" content="Align cung cấp giải pháp thiết kế website AI hiện đại, tối ưu SEO kỹ thuật và chuyển đổi vượt trội cho doanh nghiệp. Khám phá ngay!">'
    },
    {
      id: 'SEO-03',
      title: 'Trang có 2 thẻ H1 đồng cấp',
      severity: 'low',
      affected_url: 'https://align.vn/pricing',
      evidence: 'Tìm thấy cả <h1>Bảng giá dịch vụ</h1> và <h1>Các gói cao cấp</h1> trên cùng một trang.',
      recommendation: 'Chuyển tiêu đề thứ hai thành thẻ <h2> để giữ nguyên phân cấp Heading duy nhất.',
      suggested_patch: '<h2>Các gói cao cấp</h2>',
    },
  ];

  const sampleQueries = [
    { query: 'thiết kế web ai', clicks: 420, impressions: 5800, ctr: '7.2%', pos: 3.2 },
    { query: 'hub ai agent', clicks: 280, impressions: 4100, ctr: '6.8%', pos: 2.1 },
    { query: 'tối ưu seo website', clicks: 190, impressions: 6200, ctr: '3.1%', pos: 5.4 },
    { query: 'align platform demo', clicks: 95, impressions: 980, ctr: '9.7%', pos: 1.2 },
  ];

  return (
    <div style={{ maxWidth: '1200px', margin: '0 auto', padding: '32px 24px' }}>
      {/* Header */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '28px' }}>
        <div>
          <h1 style={{ fontSize: '24px', fontWeight: 800, letterSpacing: '-0.02em', marginBottom: '6px' }}>
            Báo cáo Site & SEO Audit
          </h1>
          <p style={{ fontSize: '13px', color: 'var(--text-muted)' }}>
            Dữ liệu tổng hợp từ Site Analyzer, SEO Auditor và Google Search Console
          </p>
        </div>

        <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
          <select
            id="select-reports-project"
            value={selectedProjectId}
            onChange={(e) => setSelectedProjectId(e.target.value)}
            style={{
              padding: '10px 14px',
              borderRadius: '8px',
              background: 'var(--bg-secondary)',
              color: '#ffffff',
              border: '1px solid var(--border-subtle)',
              fontSize: '13px',
            }}
          >
            {projects.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div style={{ display: 'flex', gap: '10px', marginBottom: '24px' }}>
        <button
          onClick={() => setActiveTab('site')}
          className="btn-primary"
          style={{ background: activeTab === 'site' ? 'var(--accent-primary)' : 'rgba(255,255,255,0.05)', color: '#fff' }}
        >
          <Globe size={16} /> Site Analysis & Lighthouse
        </button>
        <button
          onClick={() => setActiveTab('seo')}
          className="btn-primary"
          style={{ background: activeTab === 'seo' ? 'var(--accent-primary)' : 'rgba(255,255,255,0.05)', color: '#fff' }}
        >
          <ShieldAlert size={16} /> SEO Audit Issues ({sampleIssues.length})
        </button>
        <button
          onClick={() => setActiveTab('analytics')}
          className="btn-primary"
          style={{ background: activeTab === 'analytics' ? 'var(--accent-primary)' : 'rgba(255,255,255,0.05)', color: '#fff' }}
        >
          <FileSpreadsheet size={16} /> Search Console Queries
        </button>
      </div>

      {/* Tab Contents */}
      {activeTab === 'site' && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
          <LighthouseScoreCards scores={sampleLighthouse} cwv={sampleCwv} />
        </div>
      )}

      {activeTab === 'seo' && (
        <div>
          <AuditIssuesTable issues={sampleIssues} />
        </div>
      )}

      {activeTab === 'analytics' && (
        <div className="glass-panel" style={{ padding: '20px' }}>
          <h3 style={{ fontSize: '15px', fontWeight: 700, marginBottom: '16px' }}>
            Top Queries & Hiệu suất Tìm kiếm (Search Console)
          </h3>
          <div style={{ overflowX: 'auto' }}>
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '13px' }}>
              <thead>
                <tr style={{ borderBottom: '1px solid var(--border-subtle)', textAlign: 'left', color: 'var(--text-muted)' }}>
                  <th style={{ padding: '10px' }}>Từ khóa / Truy vấn</th>
                  <th style={{ padding: '10px' }}>Clicks</th>
                  <th style={{ padding: '10px' }}>Impressions</th>
                  <th style={{ padding: '10px' }}>CTR</th>
                  <th style={{ padding: '10px' }}>Vị trí TB</th>
                </tr>
              </thead>
              <tbody>
                {sampleQueries.map((q, idx) => (
                  <tr key={idx} style={{ borderBottom: '1px solid rgba(255,255,255,0.03)' }}>
                    <td style={{ padding: '12px 10px', fontWeight: 600 }}>{q.query}</td>
                    <td style={{ padding: '12px 10px', color: 'var(--accent-primary)', fontWeight: 700 }}>{q.clicks}</td>
                    <td style={{ padding: '12px 10px' }}>{q.impressions.toLocaleString()}</td>
                    <td style={{ padding: '12px 10px', color: '#10B981', fontWeight: 600 }}>{q.ctr}</td>
                    <td style={{ padding: '12px 10px' }}>{q.pos}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
}
