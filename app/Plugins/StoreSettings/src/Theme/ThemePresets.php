<?php

declare(strict_types=1);

namespace App\Plugins\StoreSettings\src\Theme;

final class ThemePresets
{
    /**
     * Radius map from semantic name to CSS rem value.
     *
     * @var array<string, string>
     */
    public const RADIUS_MAP = [
        'none' => '0rem',
        'sm' => '0.375rem',
        'md' => '0.5rem',
        'lg' => '0.75rem',
        'xl' => '1rem',
    ];

    /**
     * @return array<string, array{name: string, label: string, primary: string, secondary: string, radius: string}>
     */
    public static function all(): array
    {
        return [
            'default' => [
                'name' => 'default',
                'label' => 'افتراضي (محايد)',
                'primary' => '#171717',
                'secondary' => '#f5f5f5',
                'radius' => 'lg',
            ],
            'terracotta' => [
                'name' => 'terracotta',
                'label' => 'تيراكوتا (طوبي)',
                'primary' => '#893c25',
                'secondary' => '#fdf4f0',
                'radius' => 'lg',
            ],
            'emerald' => [
                'name' => 'emerald',
                'label' => 'زمردي (أخضر)',
                'primary' => '#059669',
                'secondary' => '#ecfdf5',
                'radius' => 'md',
            ],
            'ocean' => [
                'name' => 'ocean',
                'label' => 'محيطي (أزرق)',
                'primary' => '#0284c7',
                'secondary' => '#f0f9ff',
                'radius' => 'md',
            ],
            'amber' => [
                'name' => 'amber',
                'label' => 'كهرماني (ذهبي)',
                'primary' => '#d97706',
                'secondary' => '#fffbeb',
                'radius' => 'md',
            ],
            'burgundy' => [
                'name' => 'burgundy',
                'label' => 'عنابي (بورجوندي)',
                'primary' => '#9f1239',
                'secondary' => '#fff1f2',
                'radius' => 'md',
            ],
        ];
    }

    /**
     * @return array{name: string, label: string, primary: string, secondary: string, radius: string}|null
     */
    public static function get(string $name): ?array
    {
        return self::all()[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::all() as $key => $preset) {
            $options[$key] = $preset['label'];
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function radiusOptions(): array
    {
        return [
            'none' => 'بدون حواف (0)',
            'sm' => 'صغير (0.375rem)',
            'md' => 'متوسط (0.5rem)',
            'lg' => 'كبير (0.75rem)',
            'xl' => 'كبير جداً (1rem)',
        ];
    }
}
