/**
 * API client helper communicating with the FastAPI backend.
 */

export interface HealthResponse {
  status: string;
  service?: string;
  database?: string;
}

export interface Project {
  id: string;
  name: string;
  source_url?: string | null;
  workspace_path: string;
}

export interface Thread {
  id: string;
  project_id: string;
  agent_key: string;
  channel: string;
  title: string;
}

export interface Message {
  id: string;
  role: 'user' | 'assistant' | 'system';
  content: string;
  attachments?: Array<Record<string, unknown>>;
}

export interface RouterDecision {
  agent: string;
  skill_hint?: string | null;
  confidence: number;
  missing_info: string[];
  reason: string;
}

export interface LibraryItem {
  id: string;
  type: string;
  tag?: string | null;
  description?: string | null;
  path: string;
}

export interface Artifact {
  id: string;
  artifact_id: string;
  version: number;
  parent_version?: number | null;
  path: string;
  git_commit?: string | null;
}

export interface LibrarySummary {
  items: LibraryItem[];
  artifacts: Artifact[];
  references: DesignReference[];
}

export interface DesignReference {
  id: string;
  source_url: string;
  title: string;
  notes?: string | null;
  tags: string[];
  desktop_path: string;
  mobile_path: string;
  snapshot_path: string;
  page_summary?: string | null;
  captured_at: string;
}

export interface DesignReferenceUpdate {
  title?: string;
  notes?: string | null;
  tags?: string[];
}

const API_BASE =
  typeof window !== 'undefined'
    ? process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'
    : process.env.INTERNAL_API_URL || process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

const DEV_SESSION_KEY = 'hub_dev_session_token';
const DEV_AUTH_ENABLED = process.env.NEXT_PUBLIC_AUTH_MODE === 'dev';
let sessionRequest: Promise<string> | null = null;

async function createDevSession(): Promise<string> {
  const response = await fetch(`${API_BASE}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ username: 'local-developer' }),
    credentials: 'include',
  });
  const result = await handleResponse<{ token: string }>(response);
  window.localStorage.setItem(DEV_SESSION_KEY, result.token);
  return result.token;
}

async function getDevSessionToken(): Promise<string> {
  const savedToken = window.localStorage.getItem(DEV_SESSION_KEY);
  if (savedToken) return savedToken;
  sessionRequest ??= createDevSession().finally(() => { sessionRequest = null; });
  return sessionRequest;
}

async function fetchWithAuth(input: RequestInfo | URL, init: RequestInit = {}): Promise<Response> {
  if (!DEV_AUTH_ENABLED || typeof window === 'undefined') return fetch(input, init);
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
    throw new Error(errorText || `HTTP Error ${res.status}`);
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

export async function sendMessage(params: {
  projectId: string;
  content: string;
  threadId?: string;
  agentOverride?: string;
  runSynchronously?: boolean;
}): Promise<{ thread_id: string; run_id: string; agent_key: string }> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${params.projectId}/messages`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      content: params.content,
      thread_id: params.threadId,
      agent_override: params.agentOverride,
      run_synchronously: params.runSynchronously ?? true,
    }),
  });
  return handleResponse(res);
}

export async function classifyPrompt(prompt: string): Promise<RouterDecision> {
  const res = await fetchWithAuth(`${API_BASE}/router/classify`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ prompt }),
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

export interface ApprovalItem {
  id: string;
  run_id: string;
  action_type: string;
  status: 'pending' | 'approved' | 'rework' | 'rejected';
  payload: Record<string, unknown>;
  comment?: string | null;
  created_at: string;
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

export interface ProjectNoteItem {
  id: string;
  project_id: string;
  content: string;
  status: string;
  source_run_id?: string | null;
  created_at: string;
}

export async function fetchProjectNotes(projectId: string = 'global'): Promise<ProjectNoteItem[]> {
  const res = await fetchWithAuth(`${API_BASE}/projects/${projectId}/notes`, { cache: 'no-store' });
  return handleResponse<ProjectNoteItem[]>(res);
}
