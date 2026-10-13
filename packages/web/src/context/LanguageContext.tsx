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
    'projects.cleanup_demos': '🧹 Dọn dẹp demo',
    'projects.cleanup_confirm': 'Bạn có chắc chắn muốn dọn dẹp các dự án thử nghiệm không? Các dự án chính sẽ được bảo lưu.',
    'projects.empty': 'Chưa có dự án nào được tạo',
    'projects.open_chat': 'Mở Chat',
    'projects.library': 'Thư viện',
    'projects.delete': 'Xóa',
    'projects.delete_confirm': 'Bạn có chắc chắn muốn xóa vĩnh viễn dự án này cùng toàn bộ dữ liệu workspace?',

    // Status
    'status.agent_list_title': 'DANH SÁCH AGENTS TRONG HUB',
    'status.agent_list_sub': 'Độc lập • Tương tác qua Thư viện chung',

    // Library
    'library.title': 'Thư viện (Library)',
    'library.subtitle': 'Nơi lưu trữ quy tắc chung, huấn luyện và tài nguyên hình ảnh, artifact của dự án',
    'library.tab_global': '🌐 Thư viện chung (Global Rules & Training)',
    'library.tab_project': '📁 Thư viện theo dự án (Project Assets)',
    'library.upload_btn': 'Tải file lên',
    'library.uploading': 'Đang tải lên...',
    'library.artifacts_section': 'ARTIFACTS CÓ PHIÊN BẢN',
    'library.no_artifacts': 'Chưa có artifact nào được lưu cho dự án này.',
    'library.items_section': 'HÌNH ẢNH & TÀI LIỆU',
    'library.no_items': 'Chưa có hình ảnh hoặc tài liệu nào.',
    'library.preview': 'Xem trước',
    'library.global_rules_title': 'QUY TẮC & TRI THỨC TOÀN CỤC (LEARNED RULES)',
    'library.global_rules_sub': 'Các quy tắc đã học qua lệnh /learn được áp dụng chung cho TẤT CẢ các dự án.',

    // Approvals
    'approvals.title': 'Cổng Phê Duyệt (Approval Gate)',
    'approvals.subtitle': 'Kiểm soát và phê duyệt các hành động có rủi ro hoặc xuất bản tài nguyên từ AI Agent',
    'approvals.telegram_btn': 'Kết nối Telegram Bot',
    'approvals.telegram_code_title': 'Mã kết nối Telegram Bot',
    'approvals.telegram_instruction': 'Mở Telegram và gửi tin nhắn đến bot:',
    'approvals.tab_all': 'TẤT CẢ',
    'approvals.tab_pending': 'CHỜ DUYỆT',
    'approvals.tab_approved': 'ĐÃ DUYỆT',
    'approvals.tab_rejected': 'TỪ CHỐI',
    'approvals.action': 'Hành động:',
    'approvals.package': 'Tệp đóng gói:',

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
    'chat.welcome_title': 'Align AI Assistant (GPT-6 Luna & GPT-5.4 Image)',
    'chat.welcome_desc': 'Natural bilingual chat, visual design ideation, code packaging, and problem-solving like ChatGPT / Claude.',
    'chat.welcome_tip': '💡 Tip: Chat freely with @assistant, tag @design for GPT-5.4 image generation or @code-builder for coding.',
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
    'projects.cleanup_demos': '🧹 Clean up Demos',
    'projects.cleanup_confirm': 'Are you sure you want to clean up disposable test projects? Main projects will be preserved.',
    'projects.empty': 'No projects created yet',
    'projects.open_chat': 'Open Chat',
    'projects.library': 'Library',
    'projects.delete': 'Delete',
    'projects.delete_confirm': 'Are you sure you want to permanently delete this project and its workspace?',

    // Status
    'status.agent_list_title': 'AGENTS IN HUB',
    'status.agent_list_sub': 'Autonomous • Collaborating via Shared Library',

    // Library
    'library.title': 'Library',
    'library.subtitle': 'Manage global rules, training materials, and project image/artifact assets',
    'library.tab_global': '🌐 Global Library (Rules & Training)',
    'library.tab_project': '📁 Project Library (Assets & Artifacts)',
    'library.upload_btn': 'Upload File',
    'library.uploading': 'Uploading...',
    'library.artifacts_section': 'VERSIONED ARTIFACTS',
    'library.no_artifacts': 'No versioned artifacts stored for this project yet.',
    'library.items_section': 'IMAGES & DOCUMENTS',
    'library.no_items': 'No images or documents found.',
    'library.preview': 'Preview',
    'library.global_rules_title': 'GLOBAL LEARNED RULES (ALL PROJECTS)',
    'library.global_rules_sub': 'Rules learned via /learn command applied across ALL projects in the hub.',

    // Approvals
    'approvals.title': 'Approval Gate',
    'approvals.subtitle': 'Control and approve high-risk actions or asset exports from AI Agents',
    'approvals.telegram_btn': 'Connect Telegram Bot',
    'approvals.telegram_code_title': 'Telegram Bot Link Code',
    'approvals.telegram_instruction': 'Open Telegram and send message to the bot:',
    'approvals.tab_all': 'ALL',
    'approvals.tab_pending': 'PENDING',
    'approvals.tab_approved': 'APPROVED',
    'approvals.tab_rejected': 'REJECTED',
    'approvals.action': 'Action:',
    'approvals.package': 'Package:',

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
