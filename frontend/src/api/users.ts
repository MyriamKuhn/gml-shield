import { api } from "./client";

export type ManagedUserRole = "ADMIN" | "DISPATCHER" | "DRIVER";

export interface ManagedUser {
  id: number;
  name: string;
  email: string;
  role: ManagedUserRole;
  is_active: boolean;
}

export interface PaginatedUsers {
  current_page: number;
  data: ManagedUser[];
  last_page: number;
  per_page: number;
  total: number;
}

export interface CreateUserData {
  name: string;
  email: string;
  password: string;
  role: "DISPATCHER" | "DRIVER";
}

/**
 * Fetch paginated users.
 * Access is restricted to active administrators by Laravel.
 */
export async function getUsers(page = 1): Promise<PaginatedUsers> {
  const response = await api.get<PaginatedUsers>("/api/users", {
    params: { page },
  });

  return response.data;
}

/**
 * Create a dispatcher or driver account.
 * Password hashing and authorization are handled by Laravel.
 */
export async function createUser(data: CreateUserData): Promise<ManagedUser> {
  const response = await api.post<ManagedUser>("/api/users", data);

  return response.data;
}

/**
 * Activate or deactivate a dispatcher or driver account.
 * Administrator accounts cannot be modified through this endpoint.
 */
export async function updateUserActive(
  userId: number,
  isActive: boolean,
): Promise<ManagedUser> {
  const response = await api.patch<ManagedUser>(`/api/users/${userId}/active`, {
    is_active: isActive,
  });

  return response.data;
}
