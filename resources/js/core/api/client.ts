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
let refreshPromise: Promise<string | null> | null = null;
let csrfVerifiedForDocument = false;

/**
 * Reads the most specific/latest XSRF-TOKEN cookie from document.cookie.
 */
export function getXsrfToken(): string | null {
  if (typeof document === 'undefined' || !document.cookie) {
    return null;
  }

  let latest: string | null = null;
  for (const part of document.cookie.split(';')) {
    const trimmed = part.trim();
    if (!trimmed.startsWith('XSRF-TOKEN=')) {
      continue;
    }
    const rawValue = trimmed.slice('XSRF-TOKEN='.length);
    if (!rawValue) {
      continue;
    }
    try {
      latest = decodeURIComponent(rawValue);
    } catch {
      latest = rawValue;
    }
  }

  return latest;
}

function clearXsrfCookie(): void {
  if (typeof document === 'undefined') {
    return;
  }

  document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/;';
  const hostname = typeof window !== 'undefined' ? window.location.hostname : '';
  if (hostname) {
    document.cookie = `XSRF-TOKEN=; Max-Age=0; path=/; domain=${hostname};`;
    const dotIndex = hostname.indexOf('.');
    if (dotIndex !== -1) {
      document.cookie = `XSRF-TOKEN=; Max-Age=0; path=/; domain=${hostname.slice(dotIndex)};`;
    }
  }
}

/**
 * Forces a fresh tenant-scoped CSRF cookie via /sanctum/csrf-cookie (deduplicated).
 */
export async function forceRefreshCsrfToken(): Promise<string | null> {
  if (refreshPromise) {
    return refreshPromise;
  }

  refreshPromise = (async () => {
    csrfVerifiedForDocument = false;
    clearXsrfCookie();
    try {
      try {
        await axios.get('/api/tenant/auth/user', { withCredentials: true });
      } catch {
        await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
      }
      const token = getXsrfToken();
      if (token) {
        csrfVerifiedForDocument = true;
      }
      return token;
    } catch (err) {
      console.warn('Failed to initialize CSRF token:', err);
      return null;
    } finally {
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}

/**
 * Ensures CSRF cookie is verified for the active document before making non-GET requests.
 */
export async function ensureCsrfToken(): Promise<void> {
  if (csrfVerifiedForDocument && getXsrfToken()) {
    return;
  }

  if (!csrfPromise) {
    csrfPromise = forceRefreshCsrfToken()
      .then(() => {
        csrfPromise = null;
      })
      .catch(() => {
        csrfPromise = null;
      });
  }

  return csrfPromise;
}

// Request interceptor: ensure CSRF cookie before mutating requests
apiClient.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  const method = config.method?.toUpperCase();
  if (method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    await ensureCsrfToken();
    const xsrfToken = getXsrfToken();
    if (xsrfToken) {
      config.headers['X-XSRF-TOKEN'] = xsrfToken;
    }
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
      const fresh = await forceRefreshCsrfToken();
      if (fresh) {
        originalRequest.headers['X-XSRF-TOKEN'] = fresh;
      }
      return apiClient(originalRequest);
    }

    return Promise.reject(error);
  }
);
