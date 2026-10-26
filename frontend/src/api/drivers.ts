import { api } from "./client";

/**
 * Driver information returned by the Laravel API.
 *
 * Only the fields required for mission assignment are exposed.
 */
export interface Driver {
  id: number;
  name: string;
  email: string;
}

/**
 * Fetch the list of drivers available for mission assignment.
 *
 * The Laravel API restricts access to authenticated admins
 * and dispatchers and returns drivers sorted by name.
 *
 * @returns The list of drivers returned by the Laravel API.
 */
export async function getDrivers(): Promise<Driver[]> {
  const response = await api.get<Driver[]>("/api/drivers");

  return response.data;
}
