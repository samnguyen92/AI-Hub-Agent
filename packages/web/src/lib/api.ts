/**
 * API client helper communicating with the FastAPI backend.
 */
import type {
  ApprovalItem, ChatAttachment, DesignReference, DesignReferenceUpdate, HealthResponse,
  LibrarySummary, Message, Project, ProjectNoteItem, RouterDecision, Thread,
} from './types';
export type {
  ApprovalItem, Artifact, ChatAttachment, DesignReference, DesignReferenceUpdate, HealthResponse,
  LibraryItem, LibrarySummary, Message, Project, ProjectNoteItem, RouterDecision, Thread,
} from './types';

const isBrowser = typeof window !== 'undefined';
const isRemoteHost = isBrowser && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1';

export const API_BASE = isBrowser
  ? (isRemoteHost && (!process.env.NEXT_PUBLIC_API_URL || process.env.NEXT_PUBLIC_API_URL.includes('localhost'))
      ? '/api/backend'
      : (process.env.NEXT_PUBLIC_API_URL || '/api/backend'))
  : process.env.INTERNAL_API_URL || process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

const DEV_SESSION_KEY = 'hub_dev_session_token_v2';
const DEV_AUTH_ENABLED = process.env.NEXT_PUBLIC_AUTH_MODE === 'dev';
let sessionRequest: Promise<string> | null = null;

async function createDevSession(): Promise<string> {
  const response = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: 'developer' }),
    credentials: 'include',
  });
  const result = await handleResponse<{ token: string }>(response);
  window.localStorage.setItem(DEV_SESSION_KEY, result.token);
  return result.token;
}

async function getDevSessionToken(): Promise<string> {
  const savedToken = window.localStorage.getItem(DEV_SESSION_KEY);
  if (savedToken && sessionExpiresAt(savedToken) > Date.now() / 1000 + 3 * 24 * 60 * 60) {
    return savedToken;
  }
  sessionRequest ??= createDevSession().finally(() => { sessionRequest = null; });
  return sessionRequest;
}

function sessionExpiresAt(token: string): number {
  try {
    const encoded = token.split('.')[0].replace(/-/g, '+').replace(/_/g, '/');
    const payload = JSON.parse(window.atob(encoded.padEnd(Math.ceil(encoded.length / 4) * 4, '='))) as { exp?: number };
    return typeof payload.exp === 'number' ? payload.exp : 0;
  } catch {
    return 0;
  }
}

export async function fetchWithAuth(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
  if (typeof window === 'undefined') return fetch(input, init);
  if (!DEV_AUTH_ENABLED) {
    const response = await fetch(input, { ...init, credentials: init.credentials ?? 'include' });
    if (response.status === 401 && window.location.pathname !== '/login') {
      window.location.assign(`/login?next=${encodeURIComponent(window.location.pathname)}`);
    }
    return response;
  }
  const headers = new Headers(init.headers);
  headers.set('Authorization', `Bearer ${await getDevSessionToken()}`);
  const response = await fetch(input, { ...init, headers, credentials: 'include' });
  if (response.status !== 401) return response;

  window.localStorage.removeItem(DEV_SESSION_KEY);
  headers.set('Authorization', `Bearer ${await createDevSession()}`);
  return fetch(input, { ...init, headers, credentials: 'include' });
}

async function handleResponse<T>(res: Response): Promise<T> {
  if (!res.ok) {
    const errorText = await res.text();
    let message = errorText;
    try {
      const errJson = JSON.parse(errorText);
      message = errJson.detail || errJson.error || errJson.message || errorText;
    } catch {
      // not JSON format
    }
    throw new Error(message || `HTTP Error ${res.status}`);
  }
  return res.json();
}

export async function fetchHealth(): Promise<HealthResponse> {
  try {
    const res = await fetchWithAuth(`${API_BASE}/health`, { cache: 'no-store' });
    return await handleResponse<HealthResponse>(res);
  } catch (err) {
    return { status: 'error', service: err instanceof Error ? err.message : 'Error' };
  }
}

export async function fetchDbHealth(): Promise<HealthResponse> {
  try {
    const res = await fetchWithAuth(`${API_BASE}/health/db`, { cache: 'no-store' });
    return await handleResponse<HealthResponse>(res);
  } catch (err) {
    return { status: 'error', database: err instanceof Error ? err.message : 'Error' };
  }
}

export async function fetchProjects(): Promise<Project[]> {
  const res = await fetchWithAuth(`${API_BASE}/projects`, { cache: 'no-store' });
  return handleResponse<Project[]>(res);
}

export async function createProject(name: string, sourceUrl?: string): Promise<Project> {
  const res = await fetchWithAuth(`${API_BASE}/projects`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, source_url: sourceUrl }),
  });
  return handleResponse<Project>(res);
}

export async function deleteProject(projectId: string): Promise<{ status: string; id: string }> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}`, {
    method: 'DELETE',
  });
  return handleResponse<{ status: string; id: string }>(res);
}

export async function cleanupDemoProjects(): Promise<{ deleted_count: number; deleted_ids: string[] }> {
  const res = await fetchWithAuth(`${API_BASE}/projects/cleanup-demos`, {
    method: 'POST',
  });
  return handleResponse<{ deleted_count: number; deleted_ids: string[] }>(res);
}

export async function fetchThreads(projectId: string): Promise<Thread[]> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/threads`, { cache: 'no-store' });
  return handleResponse<Thread[]>(res);
}

export async function fetchMessages(threadId: string): Promise<Message[]> {
  const res = await fetchWithAuth(`${API_BASE}/threads/${threadId}/messages`, { cache: 'no-store' });
  return handleResponse<Message[]>(res);
}

export async function fetchRunStatus(
  threadId: string,
  runId: string,
): Promise<{ status: string }> {
  const res = await fetchWithAuth(`${API_BASE}/threads/${threadId}/runs/${runId}`, { cache: 'no-store' });
  return handleResponse<{ status: string }>(res);
}

export async function uploadChatAttachment(projectId: string, file: File): Promise<ChatAttachment> {
  const form = new FormData();
  form.append('file', file);
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/attachments`, {
    method: 'POST', body: form,
  });
  return handleResponse<ChatAttachment>(res);
}

export async function sendMessage(params: {
  projectId: string;
  content: string;
  threadId?: string;
  agentOverride?: string;
  runSynchronously?: boolean;
  attachments?: ChatAttachment[];
}): Promise<{ thread_id: string; run_id: string; agent_key: string; status: string }> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${params.projectId}/messages`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      content: params.content,
      thread_id: params.threadId,
      agent_override: params.agentOverride,
      run_synchronously: params.runSynchronously ?? false,
      attachments: params.attachments ?? [],
    }),
  });
  return handleResponse(res);
}

export async function classifyPrompt(
  prompt: string,
  recentHistory: Array<{ role: string; content: string }> = [],
): Promise<RouterDecision> {
  const res = await fetchWithAuth(`${API_BASE}/router/classify`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ prompt, recent_history: recentHistory }),
  });
  return handleResponse<RouterDecision>(res);
}

export async function fetchLibrary(projectId: string): Promise<LibrarySummary> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/library`, { cache: 'no-store' });
  return handleResponse<LibrarySummary>(res);
}

export async function updateDesignReference(
  projectId: string,
  referenceId: string,
  update: DesignReferenceUpdate,
): Promise<{ id: string; status: string }> {
  const res = await fetchWithAuth(
    `${API_BASE}/projects/${projectId}/library/references/${referenceId}`,
    { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(update) },
  );
  return handleResponse<{ id: string; status: string }>(res);
}

export async function deleteDesignReference(
  projectId: string,
  referenceId: string,
): Promise<{ id: string; status: string }> {
  const res = await fetchWithAuth(
    `${API_BASE}/projects/${projectId}/library/references/${referenceId}`,
    { method: 'DELETE' },
  );
  return handleResponse<{ id: string; status: string }>(res);
}

export async function fetchApprovals(status?: string): Promise<ApprovalItem[]> {
  const query = status && status !== 'all' ? `?status=${status}` : '';
  const res = await fetchWithAuth(`${API_BASE}/approvals${query}`, { cache: 'no-store' });
  return handleResponse<ApprovalItem[]>(res);
}

export async function respondApproval(id: string, action: 'approve' | 'rework' | 'reject', comment?: string): Promise<ApprovalItem> {
  const res = await fetchWithAuth(`${API_BASE}/approvals/${id}/respond`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, comment }),
  });
  return handleResponse<ApprovalItem>(res);
}

export async function generateTelegramLinkCode(): Promise<{ link_code: string; instructions: string }> {
  const res = await fetchWithAuth(`${API_BASE}/telegram/link-code`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
  });
  return handleResponse<{ link_code: string; instructions: string }>(res);
}

export async function fetchProjectNotes(projectId: string = 'global'): Promise<ProjectNoteItem[]> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/notes`, { cache: 'no-store' });
  return handleResponse<ProjectNoteItem[]>(res);
}

export interface ProjectSettingsData {
  project: { id: string; name: string; created_at: string };
  wordpress: {
    connected: boolean;
    site_type: string;
    site_url: string;
    admin_url: string;
    admin_user: string;
    status: string;
    port?: number;
  };
  telegram: {
    is_linked: boolean;
    telegram_id?: string | null;
    bot_configured?: boolean;
    bot_username?: string | null;
  };
  members: Array<{ id: string; email: string; role: string; status: string; created_at: string }>;
  progress: {
    total_runs: number;
    milestones: Array<{ phase: number; name: string; status: 'completed' | 'pending' }>;
    recent_runs: Array<{ id: string; agent_key: string; status: string; created_at: string }>;
  };
}

export async function fetchProjectSettings(projectId: string): Promise<ProjectSettingsData> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/settings`, { cache: 'no-store' });
  return handleResponse<ProjectSettingsData>(res);
}

export async function connectProjectWordPress(
  projectId: string,
  data: { site_type: string; site_url: string; admin_user: string; admin_password: string }
): Promise<{ success: boolean; message: string; site_url?: string }> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/settings/wordpress`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return handleResponse<{ success: boolean; message: string; site_url?: string }>(res);
}

export async function addProjectMember(
  projectId: string,
  data: { email: string; role: string }
): Promise<{ success: boolean; member: any }> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/settings/members`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(data),
  });
  return handleResponse<{ success: boolean; member: any }>(res);
}

export async function removeProjectMember(
  projectId: string,
  memberId: string
): Promise<{ success: boolean; message: string }> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/settings/members/${memberId}`, {
    method: 'DELETE',
  });
  return handleResponse<{ success: boolean; message: string }>(res);
}

export async function generateProjectTelegramCode(
  projectId: string
): Promise<{ link_code: string; command: string; instructions: string; bot_configured?: boolean; bot_username?: string | null }> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/settings/telegram/link-code`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
  });
  return handleResponse<{ link_code: string; command: string; instructions: string; bot_configured?: boolean; bot_username?: string | null }>(res);
}
