# سند جامع قوانین، رویه‌ها و جریان‌های HairVision

این سند مرجع فارسی قوانین محصول، معماری، امنیت، داده، دسترسی‌ها و جریان‌های
کاری HairVision است. هدف آن این است که از این به بعد هر تغییر فنی یا محصولی
با یک منبع واحد سنجیده شود.

## هویت محصول

HairVision یک SaaS چندمستاجری برای کلینیک‌های کاشت مو است. هر کلینیک یک
tenant مستقل محسوب می‌شود. مراجعه‌کننده از دامنه اختصاصی یا زیردامنه کلینیک
وارد می‌شود، شماره موبایل خود را با OTP تایید می‌کند، به عنوان lead همان
کلینیک ثبت می‌شود، تصویر چهره/اسکالپ را بارگذاری می‌کند و سه خروجی مرحله‌ای
دریافت می‌کند:

- دو هفته بعد از کاشت: `two_weeks`
- مرحله رشد میانی: `intermediate_regrowth`
- نتیجه نهایی: `final_result`

نسخه هدف فعلی `1.0.0` است و تمرکز آن اثبات جریان کامل MVP با provider نمونه
محلی است، نه اتصال پیش‌فرض به AI واقعی.

## اصول غیرقابل مذاکره

- پروژه باید با conventions لاراول 13 و معماری API-first جلو برود.
- Blade عمومی فقط shell و تجربه کاربری را رندر می‌کند و برای منطق محصول باید
  endpointهای `/api/v1` را صدا بزند.
- UI عمومی فارسی، راست‌به‌چپ و مبتنی بر فونت محلی Vazirmatn است.
- صفحه عمومی کلینیک سه بخش دارد: هدر tenant، بدنه HairVision، فوتر tenant.
- متن‌های تست، دیپلوی، setup یا debugging نباید در صفحه مراجعه‌کننده دیده شود.
- فرانت عمومی نباید به CDN یا resource خارجی وابسته باشد. لوگو و تصاویر theme
  فقط در چارچوب مجاز دامنه اصلی HairVision قابل استفاده‌اند.
- tenant هر درخواست عمومی فقط از host درخواست resolve می‌شود و هیچ ورودی کاربر
  مانند `tenant_id`، slug یا domain نباید مرجع tenant باشد.
- هر رکورد متعلق به کلینیک باید `tenant_id` داشته باشد.
- OTP هرگز نباید plain ذخیره، لاگ، seed، نمایش یا در response ارسال شود.
- تصاویر آپلودی و خروجی‌های تولیدشده نباید در public webroot ذخیره شوند.
- منطق providerهای AI نباید داخل controller باشد.
- در MVP، provider پیش‌فرض `SampleImageProvider` / مدل نمونه HairVision است.
- provider نمونه نباید اینترنت را صدا بزند، تصویر تولید کند یا از URL خارجی
  نمونه بگیرد.
- پردازش‌های سنگین تولید تصویر باید خارج از تست با Redis Queue و Horizon اجرا
  شوند.
- بعد از هر تغییر رفتاری باید تست متناسب اضافه/به‌روزرسانی و کل suite اجرا شود.

## چندمستاجری و دامنه‌ها

tenant همان کلینیک است. تشخیص tenant در مسیرهای عمومی از `Request::getHost()`
انجام می‌شود:

1. host lowercase می‌شود.
2. رکورد فعال در `tenant_domains` پیدا می‌شود.
3. tenant مرتبط باید active باشد.
4. tenant در container و request attributes با کلید `currentTenant` قرار می‌گیرد.
5. host نامعتبر یا tenant غیرفعال باید `404` بدهد.

در محیط local امکان fallback محدود برای `localhost` و `127.0.0.1` وجود دارد،
اما در تست و production باید دامنه tenant صریح باشد.

همه queryهای عمومی tenant-owned باید هم با `tenant_id` و هم در صورت مربوط بودن
با `lead_id` session-scoped محدود شوند. این قانون برای lead، upload،
generation group، generation job، generated asset، theme، AI setting، note و
audit log برقرار است.

در داشبورد، super admin می‌تواند همه کلینیک‌ها را ببیند. کاربران کلینیک فقط
کلینیک‌هایی را می‌بینند که عضویت فعال برای آن دارند. فیلترهای صفحه‌ای مانند
`clinic_id` فقط بعد از اعتبارسنجی عضویت یا super-admin بودن معتبرند.

## مدل هویت و کاربران

### قانون هدف برای اصلاح معماری

کاربران سطح کل سیستم و کاربران سطح کلینیک دو دامنه هویتی جدا هستند و باید از
نظر جدول، guard/session، role و permission مستقل باشند.

- هر کلینیک می‌تواند بی‌نهایت کاربر کلینیکی داشته باشد.
- داشبورد/پنل مدیریت پلتفرم نیز می‌تواند بی‌نهایت کاربر سیستم داشته باشد.
- کاربر کلینیک فقط در محدوده همان کلینیک یا کلینیک‌های مجاز خود دسترسی دارد.
- کاربر سیستم فقط بر اساس نقش و permission سیستم به مدیریت کل پلتفرم دسترسی
  دارد.
- کاربران کلینیک نباید بتوانند سطح دسترسی دلخواه بسازند یا permissionها را
  تغییر دهند.
- مدیر کلینیک فقط می‌تواند از سطوح دسترسی از پیش تعریف‌شده کلینیکی انتخاب و
  به کاربران کلینیک تخصیص دهد.
- کاربر سیستم با نقش مدیرکل یا permission مناسب می‌تواند سطوح دسترسی سیستم را
  مدیریت کند.
- عملیات‌هایی که نیازمند انتخاب کاربر هستند باید بسته به context فقط کاربر
  همان دامنه را بپذیرند: در بخش کلینیک، کاربر کلینیکی مجاز؛ در بخش سیستم،
  کاربر سیستمی مجاز.
- تنظیماتی که ذاتا متعلق به کلینیک‌اند، مانند درگاه پیامک اختصاصی کلینیک، نباید
  برای مالکیت به یک کاربر کلینیک وابسته شوند. این موارد تنظیمات/metadata خود
  کلینیک‌اند و با اجازه مدیر کلینیک ویرایش می‌شوند.

### وضعیت فعلی کد

وضعیت فعلی هنوز از یک جدول مشترک `users` برای کاربران داشبورد استفاده می‌کند.
سطح دسترسی global/default در `users.role` و نقش در context کلینیک در
`tenant_user.role` ذخیره شده است. نقش‌های seed شده فعلی:

- `super_admin`
- `clinic_admin`
- `editor`
- `viewer`

برای شروع اصلاح، foundation جدول‌های جدا اضافه شده است:

- `platform_users` برای کاربران سطح کل سیستم
- `clinic_users` برای کاربران سطح کلینیک

همچنین roleها دارای `scope` شده‌اند:

- `platform` برای نقش‌های قابل مدیریت در سطح سیستم
- `clinic` برای نقش‌های از پیش تعریف‌شده کلینیکی

نکته مهاجرتی: مسیر auth و بخش‌هایی از dashboard هنوز برای سازگاری با کد موجود از
`users` و `tenant_user` استفاده می‌کنند. بنابراین این مرحله، مرز دیتابیس و
مجوزها را ایجاد کرده و باید در مرحله بعد login، انتخاب کاربر و foreign keyهای
عملیاتی به مدل‌های جداگانه مهاجرت کنند.

## نقش‌ها و دسترسی‌ها

دسترسی backend اصل است؛ نمایش یا مخفی‌کردن آیتم در UI فقط کمک کاربردی است.

در وضعیت فعلی، permissionها در `permissions`، سطح‌ها در `roles` و اتصال آن‌ها
در `permission_role` نگهداری می‌شود. `PermissionService` نقش موثر کاربر را از
`users.role` یا `tenant_user.role` در context tenant محاسبه می‌کند.

قوانین کلیدی فعلی:

- `super_admin` همه permissionهای seed شده را دارد.
- `clinic_admin` می‌تواند لیدها و کاربران کلینیک‌های مجاز خود را مدیریت کند.
- `editor` می‌تواند لیدها را ببیند و فیلدهای عملیاتی محدود مثل source،
  `crm_stage` و note را تغییر دهد.
- `viewer` فقط دسترسی مشاهده دارد.
- نقش‌های کلینیکی `clinic_admin`، `editor` و `viewer` از پیش تعریف‌شده و
  `scope = clinic` هستند؛ permissionهای آن‌ها از catalog ثابت خوانده می‌شود و
  تغییر مستقیم permission در دیتابیس نباید دسترسی سیستم به آن‌ها بدهد.
- مدیریت roleهای platform مجاز است، اما roleهای clinic فقط قابل تخصیص هستند و
  permissionهایشان قابل ویرایش نیست.
- حذف lead در MVP عملا نباید مسیر عملیاتی آزاد داشته باشد.
- کاربران غیر super admin نمی‌توانند نقش `super_admin` یا نقش‌های سیستمی را به
  کاربران بسپارند.
- clinic manager فقط مجاز است نقش‌های کلینیکی `clinic_admin`، `editor` و
  `viewer` را تخصیص دهد.
- مدیریت نقش‌ها و permissionهای سیستم فقط برای super admin یا کاربران دارای
  permissionهای مناسب مانند `roles.*` و `permissions.*` مجاز است.

نمونه permissionهای مهم:

- کاربران: `users.list`, `users.search`, `users.view`, `users.create`,
  `users.edit`, `users.delete`, `users.manage`
- کلینیک‌ها: `tenants.list`, `tenants.view`, `tenants.create`, `tenants.edit`,
  `tenants.delete`
- لیدها: `leads.list`, `leads.view`, `leads.create`, `leads.edit`,
  `leads.delete`, `leads.manage`
- AI: `ai_models.list`, `ai_models.create`, `ai_models.edit`,
  `ai_models.toggle`, `ai_models.assign_to_clinic`,
  `ai_models.manage_credentials`, `ai_models.manage_prompt`,
  `ai_models.manage_credit`
- مالی: `plans.*`, `billing.*`
- سیستم: `system.dashboard`, `settings.manage`, `sms_gateways.*`,
  `payment_gateways.*`, `reports.management.view`

## نشست‌ها و احراز هویت

HairVision سه سطح session جدا دارد:

- session مراجعه‌کننده عمومی برای lead verification و generation
- session ادمین کلینیک روی host کلینیک
- session ادمین پلتفرم روی host اصلی

هدف از جداسازی cookieها این است که login/logout یا session regeneration در
داشبورد، session مراجعه‌کننده را در همان browser خراب نکند.

### OTP عمومی مراجعه‌کننده

- مسیرها زیر `/api/v1/auth/otp/*` هستند.
- شماره موبایل باید 11 رقم باشد.
- ارقام فارسی، عربی و لاتین پذیرفته و server-side normalize می‌شوند.
- OTP شش‌رقمی تولید می‌شود و فقط hash آن در `otp_codes.code_hash` ذخیره می‌شود.
- purpose جریان عمومی `lead_login` است.
- OTP زمان انقضا، single-use بودن، شمارش attempt و rate limit دارد.
- verify موفق lead را برای tenant جاری create/update می‌کند و `lead_id` را در
  session عمومی می‌گذارد.

### OTP داشبورد

- مسیرها زیر `/admin` هستند.
- purpose در `otp_codes` برابر `admin_login` است.
- user با شماره موبایل normalize شده پیدا می‌شود.
- OTP داشبورد هم hash، expiring، single-use، attempt-limited و rate-limited است.
- کد plain حتی در local debug نباید در صفحه verification نمایش داده شود.

## جریان عمومی مراجعه‌کننده

1. مراجعه‌کننده با دامنه tenant وارد landing کلینیک می‌شود.
2. بازدید landing حتی قبل از ایجاد lead در `tenant_landing_page_visits` ثبت
   می‌شود. IP و user agent به صورت hash ذخیره می‌شوند.
3. frontend از `/api/v1/bootstrap` اطلاعات tenant، theme و featureها را می‌گیرد.
4. کاربر OTP درخواست می‌کند.
5. بعد از verify موفق، lead verified در tenant جاری ساخته یا به‌روزرسانی می‌شود.
6. upload فقط با session lead تاییدشده مجاز است.
7. فایل upload در private storage ذخیره می‌شود.
8. generation فقط برای upload متعلق به همان tenant و همان lead session مجاز است.
9. status و results با polling از `/api/v1/generations/{id}/status` و
   `/results` خوانده می‌شوند.
10. assetها فقط از endpointهای scoped stream می‌شوند و مسیر storage هرگز به
    client داده نمی‌شود.

## لید، CRM و پیگیری

lead متعلق به tenant است و unique بودن شماره با ترکیب `tenant_id +
phone_normalized` اعمال می‌شود.

فیلدهای مهم lead:

- `tenant_id`
- `full_name`
- `phone`
- `phone_normalized`
- `source`
- `phone_verified_at`
- `status`
- `crm_stage`
- `crm_last_note_at`
- `assigned_user_id`

یادداشت‌ها و follow-upها در `lead_notes` ذخیره می‌شوند و tenant-owned هستند.
هر note باید `tenant_id` و `lead_id` داشته باشد. تاریخ‌های business مثل
`next_follow_up_at` و `completed_at` با فرمت جلالی `YYYY/MM/DD HH:mm:ss` ذخیره
می‌شوند.

کاربر viewer نمی‌تواند lead را ویرایش کند. editor فقط فیلدهای عملیاتی محدود را
ویرایش می‌کند. clinic admin می‌تواند leadهای مجاز کلینیک را create/update کند.

## آپلود و assetهای خصوصی

- upload فقط بعد از verify شماره و وجود `lead_id` معتبر در session مجاز است.
- فایل باید image معتبر باشد و طبق FormRequest محدودیت نوع/حجم را رعایت کند.
- upload در disk خصوصی `local` و زیر مسیرهای داخلی ذخیره می‌شود.
- metadata در `uploads` شامل `tenant_id`, `lead_id`, disk, path, mime, size و
  status است.
- original upload هرگز نباید public شود.
- generated assetها نیز private هستند و فقط از مسیرهای scoped stream می‌شوند.
- access به asset عمومی باید هم tenant و هم lead session را چک کند.
- access به asset در داشبورد باید policy، tenant context، مالکیت lead/upload و
  private storage را چک کند.

## تولید تصویر و AI

تولید تصویر در public API از `/api/v1/generations` انجام می‌شود و payload فقط
`upload_id` می‌گیرد. client نباید provider، tenant، credential یا مدل ارسال کند.

پیش‌شرط‌های generation:

1. tenant از host resolve شده باشد.
2. lead session تاییدشده وجود داشته باشد.
3. upload متعلق به همان tenant و همان lead باشد.
4. کلینیک اشتراک پایه فعال داشته باشد.
5. entitlement فعال برای سرویس `hair_transplant` داشته باشد.
6. اعتبار service برای سه مرحله کافی باشد.
7. AI model access فعال و assign شده به کلینیک برای `hair_transplant` وجود داشته
   باشد.

رفتار generation:

- برای هر upload فقط یک generation group ساخته می‌شود؛ درخواست تکراری همان group
  را reuse می‌کند.
- سه job جدا برای `two_weeks`, `intermediate_regrowth`, `final_result` ساخته
  می‌شود.
- در flow پیش‌فرض فقط اولین مرحله dispatch می‌شود و بعد از تکمیل هر مرحله،
  مرحله بعدی dispatch می‌شود.
- برای GapGPT هر سه مرحله هم‌زمان dispatch می‌شوند، چون درخواست‌های image edit
  مستقل‌اند.
- هر job log، retry/failure، asset و credit accounting مستقل دارد.
- اگر یک job failed شود، group failed می‌شود.
- اگر همه jobها completed شوند، group completed می‌شود و SMS نهایی ارسال می‌شود.

## Providerها و مدل‌های AI

مدل‌های قابل استفاده در `config/ai-models.php` تعریف می‌شوند و accessهای واقعی
در `ai_model_accesses` ذخیره می‌شوند.

دسته‌های استفاده فعلی:

- `hair_transplant`
- `eyebrow_transplant` برای add-on و توسعه آینده

مدل‌های فعلی:

- `sample_image`: مدل نمونه محافظت‌شده HairVision، local، بدون credential،
  غیرقابل حذف، قابل toggle، بدون تماس خارجی.
- `gapgpt`: مدل واقعی image edit، نیازمند credential رمزنگاری‌شده، قابل ایجاد و
  ویرایش از داشبورد برای usageهای مجاز.

قوانین امنیت AI:

- credentialها در `AiModelAccess.credentials` با encrypted cast ذخیره و از
  serialization مخفی می‌شوند.
- credential نباید در config، seed، log، response یا Blade plain دیده شود.
- update با credential خالی باید secret قبلی را حفظ کند.
- usage log فقط metadata امن، status، referenceهای opaque و errorهای safe نگه
  می‌دارد.
- provider failure نباید اعتبار مدل یا اعتبار سرویس را مصرف کند.
- protected demo access قابل حذف نیست.
- کلینیک فقط می‌تواند accessهای active و assign شده خود را استفاده کند.
- برای هر کلینیک و هر usage category فقط یک assignment فعال مجاز است؛ فعال‌کردن
  یکی، assignmentهای فعال دیگر همان category را غیرفعال می‌کند.

قانون GapGPT:

- درخواست باید multipart باشد، نه JSON.
- endpoint پیش‌فرض: `https://api.gapgpt.app/v1/images/edits`
- field فایل: `image`
- fieldها: `model = gpt-image-2`, `n = 1`, `response_format = url`,
  `size = 1024x1024`, و prompt مرحله‌ای.
- Laravel HTTP client باید boundary multipart را بسازد و نباید دستی
  `Content-Type: application/json` تنظیم شود.

## SampleImageProvider

provider نمونه فقط sample local را برمی‌گرداند:

- `sample-results/two-weeks.jpg`
- `sample-results/intermediate_regrowth.jpg`
- `sample-results/final_result.jpg`

این provider نباید اینترنت، AI خارجی، تولید تصویر، random URL یا mutation تصویر
انجام دهد. خروجی نهایی باید به مسیر private tenant/lead/group کپی شود:

`storage/app/private/tenants/{tenant_uuid}/leads/{lead_uuid}/results/{generation_group_uuid}/{period}.jpg`

## اشتراک، پلن و اعتبار

پلن‌ها سه نوع دارند:

- `base_subscription`: اشتراک اصلی کلینیک
- `service_add_on`: افزودن سرویس جدید به اشتراک پایه فعال
- `credit_top_up`: افزایش اعتبار سرویس فعال

قوانین پلن:

- هر کلینیک فقط یک base subscription فعال دارد.
- خرید پلن پایه متفاوت، upgrade/change محسوب می‌شود و subscription قبلی را
  `replaced` می‌کند.
- زمان و اعتبار باقی‌مانده از پلن قبلی به پلن جدید منتقل نمی‌شود.
- خرید همان پلن فعال تا وقتی subscription هنوز زمان و اعتبار دارد، ممنوع است.
- service add-on فقط با base subscription فعال قابل خرید است.
- add-on تکراری برای سرویس فعال ممنوع است.
- credit top-up زمان subscription را تغییر نمی‌دهد و سرویس جدید unlock نمی‌کند.
- top-up فقط برای سرویسی مجاز است که از قبل در subscription فعال وجود دارد.

اعتبار سرویس در `subscription_service_entitlements` نگهداری و هر grant/top-up/use
در `credit_transactions` ثبت می‌شود. مصرف اعتبار باید داخل transaction و با
row lock انجام شود تا balance منفی و double-spend رخ ندهد.

اتمام اعتبار یک سرویس فقط همان سرویس را block می‌کند؛ اشتراک پایه را expire
نمی‌کند و گزارش‌های تاریخی را نمی‌بندد. انقضای زمانی subscription همه سرویس‌ها
را متوقف می‌کند حتی اگر اعتبار باقی مانده باشد.

## پرداخت و فاکتور

جریان پرداخت:

1. کاربر کلینیک یا مدیر مجاز plan را انتخاب می‌کند.
2. `BillingService::invoiceForPlan` قوانین خرید را validate می‌کند.
3. invoice pending برای tenant ساخته می‌شود.
4. payment با gateway پیش‌فرض شروع می‌شود.
5. کاربر به gateway هدایت می‌شود.
6. callback به مسیر verify برمی‌گردد.
7. payment و invoice paid می‌شوند.
8. activation دقیقا یک بار انجام می‌شود.

قوانین:

- invoice و payment مربوط به کلینیک باید `tenant_id` داشته باشند.
- `invoices.activated_at` جلوی activation تکراری callback را می‌گیرد.
- خطاهای gateway باید با context امن log شوند و به کاربر پیام generic فارسی
  داده شود.
- authority، credential، raw payload و stack trace نباید در UI/JSON دیده شود.
- کاربران عادی فقط invoiceهای کلینیک خود را می‌بینند، مگر permission مدیریت مالی
  داشته باشند.

## درگاه‌های پیامک

درگاه‌های پیامک در `sms_gateways` ذخیره می‌شوند و سه scope دارند:

- `system`: پیام‌های پلتفرم و OTPهای عمومی/ادمین
- `user`: درگاه خصوصی کاربر برای پیام به مشتریان خودش
- `tenant`: تنظیم اختصاصی کلینیک

قانون مهم کلینیک: درگاه پیامک اختصاصی کلینیک یک تنظیم کلینیک است، نه دارایی یک
کاربر کلینیک. بنابراین باید با `owner_scope = tenant` و `tenant_id` ذخیره شود.
مدیر مجاز کلینیک می‌تواند از providerهای مجاز انتخاب کند، credential و sender
را تنظیم کند و فعال/غیرفعال کند.

قوانین SMS:

- OTPهای سیستم باید از system IPPanel فعال استفاده کنند یا در شرایط local به
  provider local fallback کنند.
- credentialها encrypted هستند و فقط masked نمایش داده می‌شوند.
- user-private gateway فقط توسط مالک خودش قابل استفاده است.
- tenant gateway فقط برای همان tenant است.
- default کردن یک gateway باید defaultهای دیگر همان scope و همان owner را unset
  کند.
- adapter محلی MVP نباید سرویس خارجی را صدا بزند.

## داشبورد مدیریت

داشبورد زیر `/admin` است و با Blade، session auth، policy و route middleware
کار می‌کند. مسیرهای مهم:

- `/admin/dashboard`
- `/admin/leads`
- `/admin/users`
- `/admin/reports`
- `/admin/settings`
- `/admin/plans`
- `/admin/billing`
- `/admin/ai-models`
- `/admin/clinics`
- `/admin/my-sms-gateways`
- `/admin/system/*`

رفتار داشبورد:

- super admin با context global شروع می‌کند و لازم نیست clinic پیش‌فرض انتخاب
  شود.
- کاربران کلینیک با tenant context فعال کار می‌کنند.
- فیلتر clinic در صفحات مرتبط است، نه در header جهانی.
- UI فارسی و RTL است.
- light theme پیش‌فرض است و dark mode فقط با انتخاب persisted user/browser فعال
  می‌شود.
- زیر 1024px حالت mobile/tablet و از 1024px حالت desktop استفاده می‌شود.
- list pageهای مهم باید search/filter/sort/pagination backend داشته باشند.
- جدول‌های مهم در mobile/tablet به card تبدیل می‌شوند.
- actionهای ردیفی باید از منوی سه‌نقطه shared استفاده کنند.
- صفحات create-capable بهتر است فرم create را در modal بالای لیست داشته باشند.
- iconهای داشبورد باید از icon surface محلی `x-admin.icon` باشند، نه CDN یا SVG
  پراکنده.

## محدودیت دسترسی با پلن فعال

کاربران عادی بدون پلن فعال هنوز می‌توانند وارد داشبورد شوند، وضعیت را ببینند،
پلن‌ها را مشاهده/خرید کنند، billing/invoice را ببینند، logout یا tenant switch
انجام دهند و settings مجاز خود را ببینند.

اما routeهای operational مثل leads، CRM، users، SMS خصوصی و گزارش‌های عملیاتی
با middleware `active_plan` محدود می‌شوند. super admin و کاربران دارای
permissionهای مدیریتی سیستم از این gating معاف‌اند.

## تنظیمات سیستم

تنظیمات سیستم در `system_settings` ذخیره و توسط `SystemSettingService` cache
می‌شود. نمونه تنظیمات:

- `system.name`
- `support.phone`
- `notifications.global_enabled`
- `billing.invoice_due_days`
- `api.pagination.default_per_page`
- `api.pagination.max_per_page`

بعد از update تنظیمات، cache باید پاک شود.

## تاریخ، عدد و زبان

- زبان کاربر فارسی است.
- اعداد فارسی، عربی و لاتین در ورودی‌های عددی پشتیبانی‌شده پذیرفته و normalize
  می‌شوند.
- همه normalizationها باید از `App\Support\NumberNormalizer` و validationهای
  مربوط مثل `SupportedDigits` عبور کنند.
- تاریخ‌های business/user-facing با تقویم جلالی ذخیره و نمایش داده می‌شوند.
- فرمت datetime جلالی: `YYYY/MM/DD HH:mm:ss`
- ستون‌های business datetime از `char(19)` استفاده می‌کنند تا sort/filter رشته‌ای
  درست بماند.
- timestampهای سیستمی لاراول مثل `created_at`, `updated_at`, queue timestamp و
  OTP expiration در storage میلادی می‌مانند و فقط برای نمایش تبدیل می‌شوند.
- تبدیل تاریخ فقط از `App\Support\JalaliDate`، ruleهای `JalaliDate` و
  `JalaliDateTime` و componentهای Blade مربوط انجام شود.
- datepicker باید محلی و از Vite bundle باشد، نه CDN.

## امنیت و لاگ

اولویت‌های امنیتی:

- جلوگیری از نشت داده بین tenantها.
- محافظت از OTP و sessionها.
- خصوصی‌ماندن تصاویر مراجعه‌کننده و خروجی‌ها.
- جلوگیری از دسترسی UUID-guessing به assetها.
- جلوگیری از external resource injection در theme.
- جلوگیری از تماس accidental با provider واقعی در MVP.
- جلوگیری از نشت CRM note، invoice، gateway و credentialها.

قوانین خطا:

- failureهای عملیاتی، gatewayها، providerها، queue handoffها و write flowهای
  حساس باید در مرز controller/service/job/adapter catch شوند.
- exception واقعی با context غیرحساس log شود.
- به کاربر فقط پیام generic و localized داده شود.
- OTP، credential، bearer token، private path، full gateway authority، payload
  آپلود، raw provider response و stack trace نباید log یا نمایش داده شود.

## تست و کیفیت

هر تغییر رفتاری باید تست داشته باشد. suite فعلی موارد زیر را پوشش می‌دهد:

- tenant resolution و unknown host
- landing visit tracking
- OTP hash/rate-limit/digit normalization
- lead verification
- upload authorization و validation
- session cookie separation
- generation با sample provider و GapGPT
- tenant isolation برای assetها
- admin OTP و dashboard scoping
- role/permission enforcement
- user management
- SMS gateway scoping و credential masking
- billing, invoice, payment, subscription و credit ledger
- AI model access assignment، credential encryption، credit/log behavior
- Jalali date و number normalization
- responsive/dashboard component contracts

دستور اصلی تست:

```bash
php artisan test
```

در صورت تغییر CSS، JavaScript، Blade، font یا ورودی‌های Vite باید build هم اجرا
شود:

```bash
npm run build
```

## وضعیت فعلی و اصلاحات لازم

موارد زیر از بررسی پروژه به عنوان شکاف‌های باقی‌مانده نسبت به قانون هدف ثبت
می‌شود:

- جدول‌های `platform_users` و `clinic_users` اضافه شده‌اند، اما auth و بسیاری از
  روابط عملیاتی هنوز از `users` legacy استفاده می‌کنند.
- نقش global و نقش tenant-context در یک مدل کاربر مشترک قرار گرفته‌اند.
- برخی رکوردها هنوز به `users` مشترک foreign key دارند؛ در طراحی هدف باید مشخص
  شود هر association به کاربر سیستمی است یا کاربر کلینیکی.
- permissionهای کلینیک از roleهای platform جدا و قفل شده‌اند، اما migration کامل
  همه routeها و associationها به مدل‌های جدا هنوز باقی است.
- عملیات انتخاب user باید domain-aware شود: system user برای عملیات پلتفرم،
  clinic user برای عملیات کلینیک.
- تنظیمات tenant-level مانند SMS gateway اختصاصی کلینیک نباید به user مالک
  وابسته شود و باید روی tenant ذخیره شود؛ این بخش در کد فعلی با
  `owner_scope = tenant` شروع شده و باید همین مسیر حفظ شود.

## قانون تصمیم‌گیری برای توسعه بعدی

هر تغییر جدید باید اول به این پرسش‌ها پاسخ دهد:

1. این داده متعلق به کل سیستم است یا به یک کلینیک؟
2. آیا نیاز به user دارد؟ اگر بله، user سیستمی است یا user کلینیکی؟
3. آیا باید `tenant_id` داشته باشد؟
4. tenant از کجا resolve می‌شود و آیا ورودی کاربر در آن دخیل نیست؟
5. نقش و permission در سطح سیستم است یا کلینیک؟
6. آیا credential، OTP، private path یا اطلاعات حساس در log/response/UI نشت
   نمی‌کند؟
7. آیا رفتار public Blade از `/api/v1` عبور می‌کند؟
8. آیا برای تغییر رفتاری تست اضافه یا به‌روزرسانی شده است؟
