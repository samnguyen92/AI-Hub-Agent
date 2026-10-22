/** Shared API payload types used by chat, library, projects, and approvals. */

export interface HealthResponse { status: string; service?: string; database?: string }
export interface Project { id: string; name: string; source_url?: string | null; workspace_path: string }
export interface Thread { id: string; project_id: string; agent_key: string; channel: string; title: string }
export interface ChatAttachment {
  id: string;
  filename: string;
  content_type: string;
  size: number;
  path: string;
  url?: string;
}
export interface Message {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  attachments?: ChatAttachment[];
}
export interface RouterDecision {
  agent: string;
  skill_hint?: string | null;
  confidence: number;
  missing_info: string[];
  reason: string;
}
export interface LibraryItem { id: string; type: string; tag?: string | null; description?: string | null; path: string }
export interface Artifact {
  id: string; artifact_id: string; version: number; parent_version?: number | null;
  path: string; git_commit?: string | null;
}
export interface LibrarySummary { items: LibraryItem[]; artifacts: Artifact[]; references: DesignReference[] }
export interface DesignReference {
  id: string; source_url: string; title: string; notes?: string | null; tags: string[];
  desktop_path: string; mobile_path: string; snapshot_path: string;
  page_summary?: string | null; captured_at: string;
}
export interface DesignReferenceUpdate { title?: string; notes?: string | null; tags?: string[] }
export interface ApprovalItem {
  id: string;
  run_id: string;
  action_type: string;
  status: 'pending' | 'approved' | 'rework' | 'rejected';
  payload: {
    archive_name?: string;
    result?: { download_url?: string };
    gate_type?: string;
    title?: string;
    decision_needed?: string;
    recommendation?: string;
    example?: { reference_candidates?: ReferenceCandidate[] };
    try_it?: { url?: string; instruction?: string; expected_result?: string };
    how_to_test?: string[];
  };
  comment?: string | null;
  created_at: string;
}
export interface ReferenceCandidate {
  id: string; url: string; title: string; rationale: string; study_focus: string;
}
export interface ProjectNoteItem {
  id: string; project_id: string; content: string; status: string;
  source_run_id?: string | null; created_at: string;
}
