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
}

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
 *
 * The Laravel API verifies that the user is the assigned driver
 * and that the mission can transition from PENDING to IN_PROGRESS.
 *
 * @param id - The ID of the mission to start.
 * @returns The updated mission returned by the Laravel API.
 */
export async function startMission(id: number): Promise<Mission> {
  const response = await api.patch<Mission>(`/api/missions/${id}/start`);

  return response.data;
}

/**
 * Complete an in-progress mission assigned to the authenticated driver.
 *
 * The Laravel API verifies that the user is the assigned driver
 * and that the mission can transition from IN_PROGRESS to COMPLETED.
 *
 * @param id - The ID of the mission to complete.
 * @returns The updated mission returned by the Laravel API.
 */
export async function completeMission(id: number): Promise<Mission> {
  const response = await api.patch<Mission>(`/api/missions/${id}/complete`);

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
