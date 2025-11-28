import React from 'react';
import { Head } from '@inertiajs/react';

interface PageProps {
    page: {
        title: string;
        content: string;
    };
}

import MainLayout from '@/themes/default/layouts/MainLayout';

export default function Page({ page }: PageProps) {
    return (
        <MainLayout>
            <Head title={page.title} />

            <div className="container mx-auto px-4 py-12">
                <div className="max-w-4xl mx-auto bg-card rounded-lg shadow-sm border p-6 md:p-10">
                    <h1 className="text-3xl font-bold mb-8 text-center">{page.title}</h1>

                    <div
                        className="prose prose-slate dark:prose-invert max-w-none"
                        dangerouslySetInnerHTML={{ __html: page.content }}
                    />
                </div>
            </div>
        </MainLayout>
    );
}
