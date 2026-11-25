# Domain Glossary

Canonical mapping between English code names, Arabic UI words (verified from `resources/js/locales/ar/translation.json`, `app/Filament/`, and `docs/`), Modern Standard Arabic meanings, and English meanings.

| English code name | Arabic UI word | Arabic meaning | Meaning |
|---|---|---|---|
| `web_order` (`web_delivery`, `web_takeaway`) | طلب الموقع | طلب وارد عبر الإنترنت من متجر المستأجر على الموقع. | Online order placed on the tenant storefront and forwarded to the POS. |
| `tenant` (`Tenant`) | مستأجر / متجر | حساب المتجر أو العلامة التجارية بقاعدة بيانات وملفات مستقلة. | Store instance with its own database connection, domain(s), active plugins, and storage. |
| `branch` (`Branch`) | فرع | فرع فعلي للمتجر مرتبط بمناطق التوصيل وبإعدادات الربط مع نقطة البيع. | Physical store branch mapped to delivery areas and branch POS integration settings. |
| `terminal` (`POS`) | منفذ بيع (never درج) | جهاز كاشير أو نقطة بيع في الفرع تستقبل طلبات الموقع وتحدّث حالتها. | Cashier workstation / POS terminal at a branch receiving web orders and pushing status updates. |
| `product` (`Product`) | منتج | صنف معروض للبيع في المنيو مع السعر والوصف والصور وخيارات التخصيص. | Sellable menu item with pricing, images, categories, variants, weights, and extras. |
| `category` (`Category`) | فئة | تصنيف يجمع المنتجات لتنظيم المنيو والتصفية في الواجهة. | Menu category grouping products for storefront browsing and filtering. |
| `product_variant` (`ProductVariant`) | النوع / تنويع | خيار متغير للمنتج مثل الحجم أو النكهة بسعره الخاص. | Product option variant (such as size or style) carrying its own variant price. |
| `extra_option` (`ExtraOption`, `ExtraOptionItem`) | الإضافات / خيار إضافي | مجموعة إضافات اختيارية للمنتج مع إمكانية تحديد الكمية لبعض الأصناف. | Add-on customization group and items attached to a product, optionally with quantity steppers. |
| `weight_option` (`WeightOption`, `WeightOptionValue`) | الوزن / البيع بالوزن | قالب أوزان بوحدة قياس وقيم محددة لحساب السعر حسب الوزن والمضاعف. | Discrete weight template and values (`sell_by_weight`) multiplying unit price by weight and multiplier. |
| `section` (`Section`) | أقسام الواجهة | قسم منسّق في الصفحة الرئيسية يعرض مجموعة محددة من المنتجات. | Curated homepage showcase shelf displaying selected products. |
| `cart` (`Cart`, `CartItem`) | السلة / سلة التسوق | سلة المشتريات الحالية للعميل أو الزائر قبل إتمام الطلب. | Customer or guest shopping cart holding lines, quantities, variants, weights, extras, and packages. |
| `coupon` (`Coupon`) | كوبون (البروموكود) | رمز خصم يُطبّق في صفحة إتمام الطلب وفق شروط الصلاحية والاستخدام. | Promotional discount code validated at checkout against limits, totals, delivery zones, and schedule rules. |
| `package` (`Package`) | باقات التوفير / باقة | وجبة مجمّعة أو عرض يضم مكونات ثابتة أو اختيارات للعميل بسعر موحد. | Combo meal bundle combining fixed items or selectable component groups (`ComboPackages`). |
| `order` (`Order`) | طلب | طلب عميل من نوع توصيل أو استلام يمر بحالات التحضير والتوصيل حتى الاكتمال. | Customer order (`web_delivery` or `web_takeaway`) tracking status, payment, and POS sync lifecycle. |
| `customer` (`User`) | عميل | مستخدم مسجّل في المتجر يملك حساباً وعناوين محفوظة وسجل طلبات. | Registered storefront user with saved addresses, order history, and optional Expo push tokens. |
| `guest_user` (`GuestUser`) | زائر المتجر / ضيف | عميل يُكمل الطلب دون تسجيل دخول ويُحفظ له سجل مؤقت مع تتبع عبر رمز التحقق. | Unauthenticated checkout customer stored in `guest_users` with phone/HMAC order tracking (`GuestCheckout`). |
| `delivery_zone` (`Area`) | منطقة التوصيل / المنطقة | نطاق جغرافي داخل المحافظة يحدد رسوم التوصيل والفرع المخدّم له. | Delivery area under a governorate carrying `shipping_cost` and assigned `branch_id`. |
| `governorate` (`Governorate`) | المحافظة | التقسيم الإداري الأعلى الذي تندرج تحته مناطق التوصيل. | Top-level geographic grouping containing delivery areas. |
| `payment_method` (`PaymentMethod`) | طريقة الدفع | وسيلة السداد المتاحة عند إتمام الطلب مثل الدفع عند الاستلام أو البطاقة أو المحفظة. | Checkout tender method (`Cash on Delivery` الدفع عند الاستلام, `Credit Card` بطاقة ائتمان, `Mobile Wallet` محفظة إلكترونية, etc.). |
| `plugin` (`Plugin`, `TenantPlugin`) | إضافة | وحدة ميزة مستقلة في سوق الإضافات تُفعّل وتُدار لكل مستأجر على حدة. | Modular microkernel capability in `app/Plugins/*` managed per tenant via `ManagePlugins`. |
| `theme` | سمة / المظهر والألوان | قالب وتصميم واجهة المتجر والألوان المسجل في سجل السمات. | Storefront UI theme implementation and color preset resolved from `themes/registry.ts`. |
| `page` (`Page`) | صفحة ثابتة (صفحة CMS) | صفحة محتوى ثابتة مثل من نحن أو السياسات متاحة عبر رابطها المخصص. | Static CMS storefront page (`CmsPages`) served at `/pages/<slug>`. |
