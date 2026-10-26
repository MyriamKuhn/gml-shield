import { api } from "./client";

export interface AuditLog {
  id: number;
  user_id: number | null;
  action: string;
  ip_address: string | null;
  timestamp: string;
  event_id: string | null;
}

export interface AuditLogPage {
  current_page: number;
  data: AuditLog[];
  last_page: number;
  per_page: number;
  total: number;
}

export async function getAuditLogs(page: number = 1): Promise<AuditLogPage> {
  const response = await api.get<AuditLogPage>("/api/audit-logs", {
    params: { page },
  });

  return response.data;
}
