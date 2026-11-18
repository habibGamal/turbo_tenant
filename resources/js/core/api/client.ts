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
 * Reads the CSRF token embedded in <meta name="csrf-token">.
 */
export function getCsrfToken(): string | null {
  if (typeof document === 'undefined') {
    return null;
  }

  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')?.trim() || null;
}

function setCsrfMetaToken(token: string | null): void {
  if (typeof document === 'undefined') {
    return;
  }

  const meta = document.querySelector('meta[name="csrf-token"]');
  if (meta) {
    meta.setAttribute('content', token ?? '');
  }
}

/**
 * Forces a fresh tenant/central CSRF token fetch and syncs <meta name="csrf-token">.
 */
export async function forceRefreshCsrfToken(): Promise<string | null> {
  if (typeof document !== 'undefined') {
    document.cookie = 'XSRF-TOKEN=; Max-Age=0; path=/;';
  }
  setCsrfMetaToken(null);

  try {
    const res = await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    const headerToken = (res.headers?.['x-csrf-token'] as string | undefined)?.trim() || null;
    if (headerToken) {
      setCsrfMetaToken(headerToken);
    }
    return headerToken;
  } catch (err) {
    console.warn('Failed to initialize CSRF token:', err);
    return null;
  }
}

/**
 * Ensures CSRF token/cookie is initialized before making non-GET requests.
 */
export async function ensureCsrfToken(): Promise<void> {
  if (typeof document !== 'undefined' && (getCsrfToken() || document.cookie.includes('XSRF-TOKEN='))) {
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

// Request interceptor: ensure CSRF token/cookie before mutating requests
apiClient.interceptors.request.use(async (config: InternalAxiosRequestConfig) => {
  const method = config.method?.toUpperCase();
  if (method && ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
    await ensureCsrfToken();
    const csrfToken = getCsrfToken();
    if (csrfToken) {
      config.headers['X-CSRF-TOKEN'] = csrfToken;
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
        originalRequest.headers['X-CSRF-TOKEN'] = fresh;
        delete originalRequest.headers['X-XSRF-TOKEN'];
      }
      return apiClient(originalRequest);
    }

    return Promise.reject(error);
  }
);
