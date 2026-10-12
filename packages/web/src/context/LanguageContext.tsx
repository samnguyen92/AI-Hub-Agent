'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';

export type Language = 'vi' | 'en';

interface LanguageContextType {
  language: Language;
  setLanguage: (lang: Language) => void;
  toggleLanguage: () => void;
  t: (key: string) => string;
}

const translations: Record<Language, Record<string, string>> = {
  vi: {
    // Nav
    'nav.status': 'Trạng thái',
    'nav.projects': 'Dự án',
    'nav.chat': 'Chat Agent',
    'nav.agents': 'Agents & Memory',
    'nav.library': 'Thư viện',
    'nav.reports': 'Báo cáo',
    'nav.approvals': 'Phê duyệt',

    // Chat Sidebar & Interface
    'chat.current_project': 'DỰ ÁN HIỆN TẠI',
    'chat.select_project': 'Chọn dự án...',
    'chat.conversations': 'Cuộc trò chuyện',
    'chat.new_chat': '+ Mới',
    'chat.empty_threads': 'Chưa có cuộc trò chuyện nào',
    'chat.quick_select': 'Chọn nhanh:',
    'chat.placeholder': 'Trò chuyện tự nhiên cùng Trợ lý Align AI (tag @design, @code... hoặc /learn <quy tắc>)...',
    'chat.send': 'Gửi',
    'chat.thinking': 'Agent đang suy nghĩ & xử lý tác vụ...',
    'chat.teach_ai': 'Dạy AI',
    'chat.teach_placeholder': 'Nhập quy tắc/bài học cần AI ghi nhớ (vd: "Nút CTA luôn dùng chữ in hoa")...',
    'chat.save_rule': 'Lưu quy tắc',
    'chat.teach_success': 'Đã dạy AI thành công! Quy tắc đã được nạp vào bộ nhớ vĩnh viễn của dự án.',
    'chat.welcome_title': 'Trợ lý Align AI (GPT-6 Luna & Seedream 4.5)',
    'chat.welcome_desc': 'Hội thoại song ngữ tự nhiên, tư vấn ý tưởng, thiết kế mockup và giải đáp như ChatGPT / Claude.',
    'chat.welcome_tip': '💡 Mẹo: Chat tự do cùng @assistant, tag @design để sinh ảnh xe/banner với Seedream 4.5 hoặc @code-builder để code.',
    'chat.user': 'Bạn',
    'chat.assistant': 'Align Assistant',

    // Badges
    'badge.assistant': 'Trợ lý Hội thoại',
    'badge.design': 'Thiết kế Mockup & Ảnh',
    'badge.code_builder': 'Lập trình Code',
    'badge.site_analyzer': 'Phân tích Website',
    'badge.seo_auditor': 'Kiểm toán SEO',

    // Home / Status
    'home.subtitle': 'Hệ thống điều phối Multi-Agent nội bộ',
    'home.refresh': 'Làm mới trạng thái',
    'home.refreshing': 'Đang kiểm tra...',
    'home.foundation_status': 'Trạng thái nền tảng',
    'home.agents_section': 'Mạng lưới Agent chuyên trách',
    'home.last_checked': 'Cập nhật lúc:',

    // Projects
    'projects.title': 'Quản lý Dự án (Projects)',
    'projects.subtitle': 'Không gian làm việc biệt lập cho từng khách hàng hoặc nhiệm vụ',
    'projects.new': '+ Tạo dự án mới',
    'projects.empty': 'Chưa có dự án nào được tạo',
    'projects.open_chat': 'Mở Chat',
    'projects.library': 'Thư viện',

    // Common
    'common.lang': 'Tiếng Việt',
    'common.switch_lang': 'Chuyển sang English',
  },
  en: {
    // Nav
    'nav.status': 'Status',
    'nav.projects': 'Projects',
    'nav.chat': 'Agent Chat',
    'nav.agents': 'Agents & Memory',
    'nav.library': 'Library',
    'nav.reports': 'Reports',
    'nav.approvals': 'Approvals',

    // Chat Sidebar & Interface
    'chat.current_project': 'CURRENT PROJECT',
    'chat.select_project': 'Select project...',
    'chat.conversations': 'Conversations',
    'chat.new_chat': '+ New Chat',
    'chat.empty_threads': 'No conversations yet',
    'chat.quick_select': 'Quick Tags:',
    'chat.placeholder': 'Chat naturally with Align AI (tag @design, @code... or /learn <rule>)...',
    'chat.send': 'Send',
    'chat.thinking': 'Agent is thinking & executing task...',
    'chat.teach_ai': 'Teach AI',
    'chat.teach_placeholder': 'Enter rule/guideline for AI to memorize (e.g. "Always use uppercase for CTA buttons")...',
    'chat.save_rule': 'Save Rule',
    'chat.teach_success': 'Rule saved successfully! Added to permanent project memory.',
    'chat.welcome_title': 'Align AI Assistant (GPT-6 Luna & Seedream 4.5)',
    'chat.welcome_desc': 'Natural bilingual chat, visual design ideation, code packaging, and problem-solving like ChatGPT / Claude.',
    'chat.welcome_tip': '💡 Tip: Chat freely with @assistant, tag @design for Seedream 4.5 image generation or @code-builder for coding.',
    'chat.user': 'You',
    'chat.assistant': 'Align Assistant',

    // Badges
    'badge.assistant': 'Chat Assistant',
    'badge.design': 'Design & Images',
    'badge.code_builder': 'Code Builder',
    'badge.site_analyzer': 'Site Analyzer',
    'badge.seo_auditor': 'SEO Auditor',

    // Home / Status
    'home.subtitle': 'Internal Multi-Agent Orchestration Hub',
    'home.refresh': 'Refresh Status',
    'home.refreshing': 'Checking...',
    'home.foundation_status': 'Platform Foundation Status',
    'home.agents_section': 'Specialized Agent Network',
    'home.last_checked': 'Last checked at:',

    // Projects
    'projects.title': 'Project Management',
    'projects.subtitle': 'Isolated workspace for each client or task',
    'projects.new': '+ Create New Project',
    'projects.empty': 'No projects created yet',
    'projects.open_chat': 'Open Chat',
    'projects.library': 'Library',

    // Common
    'common.lang': 'English',
    'common.switch_lang': 'Switch to Vietnamese',
  },
};

const LanguageContext = createContext<LanguageContextType>({
  language: 'vi',
  setLanguage: () => {},
  toggleLanguage: () => {},
  t: (key: string) => key,
});

export function LanguageProvider({ children }: { children: React.ReactNode }) {
  const [language, setLanguageState] = useState<Language>('vi');

  useEffect(() => {
    const saved = localStorage.getItem('align_lang') as Language;
    if (saved && (saved === 'vi' || saved === 'en')) {
      setLanguageState(saved);
    }
  }, []);

  const setLanguage = (lang: Language) => {
    setLanguageState(lang);
    localStorage.setItem('align_lang', lang);
  };

  const toggleLanguage = () => {
    const nextLang = language === 'vi' ? 'en' : 'vi';
    setLanguage(nextLang);
  };

  const t = (key: string): string => {
    return translations[language][key] || translations.en[key] || key;
  };

  return (
    <LanguageContext.Provider value={{ language, setLanguage, toggleLanguage, t }}>
      {children}
    </LanguageContext.Provider>
  );
}

export function useLanguage() {
  return useContext(LanguageContext);
}
