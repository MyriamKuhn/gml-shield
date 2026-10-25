import { api } from "./client";
import type { AuthUser } from "./auth";

/**
 * The four allowed mission statuses.
 * Must match the Laravel MissionStatus enum.
 */
export type MissionStatus =
  | "PENDING"
  | "IN_PROGRESS"
  | "COMPLETED"
  | "CANCELLED";

/**
 * Mission data returned by the Laravel API.
 */
export interface Mission {
  id: number;
  reference: string;
  title: string;
  description: string | null;
  pickup_address: string;
  delivery_address: string;
  scheduled_at: string;
  status: MissionStatus;
  driver_id: number | null;
  created_by: number;
  driver: Pick<AuthUser, "id" | "name" | "email"> | null;
  creator: Pick<AuthUser, "id" | "name" | "email">;
  has_delivery_proof?: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Data required to create a new mission.
 *
 * The backend manages the initial status, creator, and driver assignment.
 * These fields must not be submitted during mission creation.
 */
export interface CreateMissionData {
  reference: string;
  title: string;
  description?: string | null;
  pickup_address: string;
  delivery_address: string;
  scheduled_at: string;
  secret_code: string;
}

/**
 * Editable mission fields accepted by the Laravel API.
 *
 * Only pending missions can be updated by authorized users.
 * Status, driver assignment, and creator are managed separately.
 */
export type UpdateMissionData = Partial<Omit<CreateMissionData, "secret_code">>;

/**
 * Laravel's paginated response for GET /api/missions.
 */
export interface PaginatedMissions {
  current_page: number;
  data: Mission[];
  last_page: number;
  per_page: number;
  total: number;
}

/**
 * Fetch missions accessible to the authenticated user.
 *
 * Laravel applies role-based filtering on the server.
 */
export async function getMissions(page = 1): Promise<PaginatedMissions> {
  const response = await api.get<PaginatedMissions>("/api/missions", {
    params: { page },
  });

  return response.data;
}

/**
 * Start a pending mission assigned to the authenticated driver.
 * The backend validates the transition and permissions.
 */
export async function startMission(id: number): Promise<Mission> {
  const response = await api.patch<Mission>(`/api/missions/${id}/status`, {
    status: "IN_PROGRESS",
  });

  return response.data;
}

/**
 * Complete an in-progress mission assigned to the authenticated driver.
 * The backend validates the transition and permissions.
 */
export async function completeMission(id: number): Promise<Mission> {
  const response = await api.patch<Mission>(`/api/missions/${id}/status`, {
    status: "COMPLETED",
  });

  return response.data;
}

/**
 * Create a mission using the authenticated dispatcher or admin session.
 *
 * Laravel validates the submitted data, checks user permissions,
 * and initializes the mission with the PENDING status.
 *
 * @param data - The validated mission creation fields.
 * @returns The mission created by the Laravel API.
 */
export async function createMission(data: CreateMissionData): Promise<Mission> {
  const response = await api.post<Mission>("/api/missions", data);

  return response.data;
}

/**
 * Assign a driver to a pending mission.
 *
 * The Laravel API verifies that the authenticated user can assign
 * the mission and that the selected user has the DRIVER role.
 *
 * @param missionId - The ID of the mission to assign.
 * @param driverId - The ID of the driver receiving the mission.
 * @returns The updated mission returned by the Laravel API.
 */
export async function assignMission(
  missionId: number,
  driverId: number,
): Promise<Mission> {
  const response = await api.patch<Mission>(
    `/api/missions/${missionId}/assign`,
    { driver_id: driverId },
  );

  return response.data;
}

/**
 * Update the editable fields of a pending mission.
 *
 * Laravel validates the submitted fields and verifies that
 * the authenticated user is authorized to modify the mission.
 *
 * @param missionId - The ID of the mission to update.
 * @param data - The mission fields to modify.
 * @returns The updated mission returned by the Laravel API.
 */
export async function updateMission(
  missionId: number,
  data: UpdateMissionData,
): Promise<Mission> {
  const response = await api.patch<Mission>(`/api/missions/${missionId}`, data);

  return response.data;
}

/**
 * Cancel a pending or in-progress mission.
 *
 * The Laravel API verifies that the authenticated user is
 * authorized to cancel the mission and that its current
 * status allows the transition to CANCELLED.
 *
 * The mission remains stored to preserve traceability.
 *
 * @param missionId - The ID of the mission to cancel.
 * @returns The updated mission returned by the Laravel API.
 */
export async function cancelMission(missionId: number): Promise<Mission> {
  const response = await api.patch<Mission>(
    `/api/missions/${missionId}/cancel`,
  );

  return response.data;
}

/**
 * Reveal a warehouse access code for the assigned driver.
 *
 * The secret is requested explicitly and must never be
 * stored in persistent browser storage.
 */
export async function revealMissionSecret(missionId: number): Promise<string> {
  const response = await api.get<{ secret_code: string }>(
    `/api/missions/${missionId}/secret-code`,
  );

  return response.data.secret_code;
}
