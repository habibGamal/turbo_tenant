/**
 * Sanitizes a redirect path to prevent open redirect vulnerabilities.
 *
 * Rules:
 * - Must be a non-empty string.
 * - Must start with '/'.
 * - Must NOT start with '//' or '/\\'.
 * - Must NOT contain any backslash ('\\').
 * - Must NOT contain any control characters (0x00 - 0x1F, 0x7F).
 *
 * Returns the sanitized path if valid, or '/' otherwise.
 */
export function getSafeRedirect(redirectParam: string | null | undefined): string {
    if (!redirectParam || typeof redirectParam !== "string") {
        return "/";
    }

    if (!redirectParam.startsWith("/")) {
        return "/";
    }

    if (redirectParam.startsWith("//") || redirectParam.startsWith("/\\")) {
        return "/";
    }

    if (redirectParam.includes("\\")) {
        return "/";
    }

    // eslint-disable-next-line no-control-regex
    if (/[\x00-\x1F\x7F]/.test(redirectParam)) {
        return "/";
    }

    return redirectParam;
}
