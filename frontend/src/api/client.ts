import axios from "axios";

// Shared HTTP client for the Laravel API.
export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || "http://localhost:8000",
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: "application/json",
  },
});

// Initialize Laravel Sanctum's CSRF cookie before login.
export async function initializeCsrf(): Promise<void> {
  await api.get("/sanctum/csrf-cookie");
}
