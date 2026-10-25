import { api, initializeCsrf } from "./client";

export type UserRole = "ADMIN" | "DISPATCHER" | "DRIVER";

export interface AuthUser {
  id: number;
  name: string;
  email: string;
  role: UserRole;
}

export interface LoginCredentials {
  email: string;
  password: string;
}

export async function login(credentials: LoginCredentials): Promise<AuthUser> {
  // Obtain the CSRF cookie before submitting credentials.
  await initializeCsrf();

  const response = await api.post<{ user: AuthUser }>(
    "/api/auth/login",
    credentials,
  );

  return response.data.user;
}

export async function getCurrentUser(): Promise<AuthUser> {
  const response = await api.get<AuthUser>("/api/user");

  return response.data;
}

export async function logout(): Promise<void> {
  await api.post("/api/logout");
}
