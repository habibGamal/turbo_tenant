/**
 * Arabic Grammatical Pluralization Helpers
 */

export function formatItemsCount(count: number, isAr: boolean): string {
    if (!isAr) return `${count} ${count === 1 ? 'item' : 'items'}`;
    if (count === 0) return 'لا توجد عناصر';
    if (count === 1) return 'عنصر واحد';
    if (count === 2) return 'عنصران';
    if (count >= 3 && count <= 10) return `${count} عناصر`;
    return `${count} عنصراً`;
}

export function formatReviewsCount(count: number, isAr: boolean): string {
    if (!isAr) return `${count} ${count === 1 ? 'review' : 'reviews'}`;
    if (count === 0) return 'لا توجد مراجعات';
    if (count === 1) return 'مراجعة واحدة';
    if (count === 2) return 'مراجعتان';
    if (count >= 3 && count <= 10) return `${count} مراجعات`;
    return `${count} مراجعة`;
}

export function formatDaysCount(count: number, isAr: boolean): string {
    if (!isAr) return `${count} ${count === 1 ? 'business day' : 'business days'}`;
    if (count === 1) return 'يوم عمل واحد';
    if (count === 2) return 'يوما عمل';
    if (count >= 3 && count <= 10) return `${count} أيام عمل`;
    return `${count} يوم عمل`;
}
