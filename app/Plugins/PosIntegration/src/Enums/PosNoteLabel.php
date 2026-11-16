<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Enums;

enum PosNoteLabel: string
{
    case NOTES = 'ملاحظات';
    case WEIGHT = 'الوزن';
    case EXTRAS = 'اضافات';
    case EXTRA_DEFAULT = 'إضافة';
    case TYPE = 'النوع';
    case COMPONENTS = 'مكونات';
    case ADDON_FOR = 'إضافة على';
}
