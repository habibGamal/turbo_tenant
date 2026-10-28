<div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                دليل إعداد المتجر
            </h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                أكمل الخطوات التالية لتجهيز متجرك واستقبال الطلبات بنجاح
            </p>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
                <span>{{ $completed }}</span> / <span>{{ $total }}</span> مكتملة
            </div>
            <div class="h-2 w-32 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                <div class="h-full bg-primary-600 transition-all duration-500 dark:bg-primary-500" style="width: {{ $percentage }}%"></div>
            </div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $item)
            <div class="flex flex-col justify-between rounded-lg border p-4 transition-colors {{ $item['is_completed'] ? 'border-success-200 bg-success-50/50 dark:border-success-800/40 dark:bg-success-950/20' : 'border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-800/40' }}">
                <div class="flex items-start gap-3">
                    <div class="mt-0.5 shrink-0">
                        @if ($item['is_completed'])
                            <svg class="h-5 w-5 text-success-600 dark:text-success-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                            </svg>
                        @else
                            <svg class="h-5 w-5 text-gray-400 dark:text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6.75 9.25a.75.75 0 000 1.5h6.5a.75.75 0 000-1.5h-6.5z" clip-rule="evenodd" />
                            </svg>
                        @endif
                    </div>
                    <div>
                        <h3 class="text-sm font-medium {{ $item['is_completed'] ? 'text-success-950 line-through dark:text-success-200' : 'text-gray-900 dark:text-white' }}">
                            {{ $item['title'] }}
                        </h3>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ $item['description'] }}
                        </p>
                    </div>
                </div>

                @if ($item['url'])
                    <div class="mt-4 flex justify-end">
                        <a href="{{ $item['url'] }}" class="inline-flex items-center gap-1 text-xs font-semibold {{ $item['is_completed'] ? 'text-success-700 hover:text-success-800 dark:text-success-300' : 'text-primary-600 hover:text-primary-700 dark:text-primary-400' }}">
                            <span>{{ $item['is_completed'] ? 'عرض' : 'إعداد الآن' }}</span>
                            <svg class="h-3.5 w-3.5 rtl:rotate-180" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                            </svg>
                        </a>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
