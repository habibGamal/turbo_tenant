<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Theme;

final class ThemeCssBuilder
{
    private const HEX_COLOR_REGEX = '/^#[0-9a-fA-F]{6}$/';

    private const COLOR_WHITE = '#ffffff';

    private const COLOR_NEAR_BLACK = '#171717';

    /**
     * Build the tenant :root CSS string from a theme configuration array.
     *
     * @param  array<string, mixed>  $theme
     */
    public function build(array $theme): string
    {
        $declarations = [];

        $primary = $this->sanitizeColor($theme['primary'] ?? null);
        if ($primary !== null) {
            $primaryFg = $this->resolveForeground($primary);
            $declarations[] = "--primary: {$primary};";
            $declarations[] = "--primary-foreground: {$primaryFg};";
            $declarations[] = "--ring: {$primary};";
        }

        $secondary = $this->sanitizeColor($theme['secondary'] ?? null);
        if ($secondary !== null) {
            $secondaryFg = $this->resolveForeground($secondary);
            $declarations[] = "--secondary: {$secondary};";
            $declarations[] = "--secondary-foreground: {$secondaryFg};";
            $declarations[] = "--accent: {$secondary};";
            $declarations[] = "--accent-foreground: {$secondaryFg};";
        }

        $radius = $this->sanitizeRadius($theme['radius'] ?? null);
        if ($radius !== null) {
            $declarations[] = "--radius: {$radius};";
        }

        if (empty($declarations)) {
            return '';
        }

        return ":root {\n    ".implode("\n    ", $declarations)."\n}";
    }

    /**
     * Choose white or near-black foreground based on WCAG 2.1 relative luminance contrast ratio.
     */
    public function resolveForeground(string $hexColor): string
    {
        $hex = mb_ltrim($hexColor, '#');
        $r = hexdec(mb_substr($hex, 0, 2)) / 255.0;
        $g = hexdec(mb_substr($hex, 2, 2)) / 255.0;
        $b = hexdec(mb_substr($hex, 4, 2)) / 255.0;

        $rLum = ($r <= 0.04045) ? ($r / 12.92) : pow(($r + 0.055) / 1.055, 2.4);
        $gLum = ($g <= 0.04045) ? ($g / 12.92) : pow(($g + 0.055) / 1.055, 2.4);
        $bLum = ($b <= 0.04045) ? ($b / 12.92) : pow(($b + 0.055) / 1.055, 2.4);

        $bgLum = 0.2126 * $rLum + 0.7152 * $gLum + 0.0722 * $bLum;

        // Relative luminance of #ffffff is 1.0; of #171717 is ~0.0093
        // Contrast ratio = (L1 + 0.05) / (L2 + 0.05)
        $whiteContrast = 1.05 / ($bgLum + 0.05);
        $darkContrast = ($bgLum + 0.05) / 0.0593;

        return $whiteContrast >= $darkContrast ? self::COLOR_WHITE : self::COLOR_NEAR_BLACK;
    }

    /**
     * Validate and normalize a hex color strictly matching /^#[0-9a-fA-F]{6}$/.
     */
    private function sanitizeColor(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);
        if (! preg_match(self::HEX_COLOR_REGEX, $trimmed)) {
            return null;
        }

        return mb_strtolower($trimmed);
    }

    /**
     * Validate and map radius name to fixed CSS rem value.
     */
    private function sanitizeRadius(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return ThemePresets::RADIUS_MAP[$trimmed] ?? null;
    }
}
