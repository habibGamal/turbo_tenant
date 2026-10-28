import axios, { AxiosError, AxiosInstance, InternalAxiosRequestConfig } from 'axios';

/**
 * Core API Client for Turbo Tenant Storefront.
 * Communicates with Laravel backend using Sanctum stateful cookies.
 */
export const apiClient: AxiosInstance = axios.create({
  baseURL: '/',
  withCredentials: true,
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Accept': 'application/json',
    'Content-Type': 'application/json',
  },
});

let csrfPromise: Promise<void> | null = null;

/**
 * Ensures CSRF cookie is initialized before making non-GET requests.
 */
export async function ensureCsrfToken(): Promise<void> {
  // If XSRF-TOKEN cookie already exists, no need to re-fetch
  if (document.cookie.includes('XSRF-TOKEN=')) {
    return;
  }

  if (!csrfPromise) {
    csrfPromise = axios.get('/sanctum/csrf-cookie', { withCredentials: true })
      .then(() => {
        csrfPromise = null;
      })
      .catch((err) => {
        csrfPromise = null;
        console.warn('Failed to initialize CSRF token:', err);
      });
  }

  return csrfPromise;
}

// Request interceptor: ensure CSRF cookie before mutating requests
apiClient.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  const method = config.method?.toUpperCase();
  if (method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    await ensureCsrfToken();
  }
  return config;
}, (error) => {
  return Promise.reject(error);
});

// Response interceptor: handle token mismatch and global auth states
apiClient.interceptors.response.use(
  (response) => response,
  async (error: AxiosError) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };

    // Retry once if CSRF expired (HTTP 419)
    if (error.response?.status === 419 && originalRequest && !originalRequest._retry) {
      originalRequest._retry = true;
      document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/;';
      await ensureCsrfToken();
      return apiClient(originalRequest);
    }

    return Promise.reject(error);
  }
);
