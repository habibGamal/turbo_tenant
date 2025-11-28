<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'title' => 'About Us',
                'title_ar' => 'من نحن',
                'slug' => 'about-us',
                'content' => '<h1>About Us</h1><p>About us content...</p>',
                'content_ar' => '<h1>من نحن</h1><p>محتوى صفحة من نحن...</p>',
            ],
            [
                'title' => 'Privacy Policy',
                'title_ar' => 'سياسة الخصوصية',
                'slug' => 'privacy-policy',
                'content' => '<h1>Privacy Policy</h1><p>Privacy policy content...</p>',
                'content_ar' => '<h1>سياسة الخصوصية</h1><p>محتوى سياسة الخصوصية...</p>',
            ],
            [
                'title' => 'Return Policy',
                'title_ar' => 'سياسة الإرجاع',
                'slug' => 'return-policy',
                'content' => '<h1>Return Policy</h1><p>Return policy content...</p>',
                'content_ar' => '<h1>سياسة الإرجاع</h1><p>محتوى سياسة الإرجاع...</p>',
            ],
            [
                'title' => 'Replacement Policy',
                'title_ar' => 'سياسة الاستبدال',
                'slug' => 'replacement-policy',
                'content' => '<h1>Replacement Policy</h1><p>Replacement policy content...</p>',
                'content_ar' => '<h1>سياسة الاستبدال</h1><p>محتوى سياسة الاستبدال...</p>',
            ],
            [
                'title' => 'Delivery Policy',
                'title_ar' => 'سياسة التسليم',
                'slug' => 'delivery-policy',
                'content' => '<h1>Delivery Policy</h1><p>Delivery policy content...</p>',
                'content_ar' => '<h1>سياسة التسليم</h1><p>محتوى سياسة التسليم...</p>',
            ],
            [
                'title' => 'Shipping Policy',
                'title_ar' => 'سياسة الشحن',
                'slug' => 'shipping-policy',
                'content' => '<h1>Shipping Policy</h1><p>Shipping policy content...</p>',
                'content_ar' => '<h1>سياسة الشحن</h1><p>محتوى سياسة الشحن...</p>',
            ],
            [
                'title' => 'Terms of Service',
                'title_ar' => 'شروط الخدمة',
                'slug' => 'terms-of-service',
                'content' => '<h1>Terms of Service</h1><p>Terms of service content...</p>',
                'content_ar' => '<h1>شروط الخدمة</h1><p>محتوى شروط الخدمة...</p>',
            ],
            [
                'title' => 'Contact Us',
                'title_ar' => 'اتصل بنا',
                'slug' => 'contact-us',
                'content' => '<h1>Contact Us</h1><p>Contact us content...</p>',
                'content_ar' => '<h1>اتصل بنا</h1><p>محتوى اتصل بنا...</p>',
            ],
        ];

        foreach ($pages as $page) {
            Page::firstOrCreate(
                ['slug' => $page['slug']],
                $page
            );
        }
    }
}
