# سند جامع قوانین محصول HairVision

نسخه: `1.0.0`

این سند مرجع فارسی قوانین حاکم بر HairVision است. هر تصمیم محصولی، فنی،
امنیتی، مالی یا عملیاتی باید با این سند سازگار باشد. اگر قانون جدیدی برای
محصول لازم شد، ابتدا همین سند به‌روزرسانی می‌شود و سپس کد تغییر می‌کند.

## هویت محصول

HairVision یک SaaS چندمستاجری کامل برای کلینیک‌های کاشت مو است. هر کلینیک یک
tenant مستقل دارد. مراجعه‌کننده از دامنه اختصاصی یا زیردامنه کلینیک وارد
می‌شود، شماره موبایل را با OTP تایید می‌کند، به عنوان lead همان کلینیک ثبت
می‌شود، تصویر چهره/اسکالپ را به صورت خصوصی بارگذاری می‌کند و سه خروجی
مرحله‌ای دریافت می‌کند:

- دو هفته بعد از کاشت: `two_weeks`
- مرحله رشد میانی: `intermediate_regrowth`
- نتیجه نهایی: `final_result`

نسخه `1.0.0` نخستین نسخه کامل محصول است و شامل جریان عمومی، داشبورد مدیریت،
مدیریت مدل‌های AI، پلن‌ها، پرداخت، entitlement سرویس، اعتبار مصرفی، گزارش‌ها،
تنظیمات سیستم و قوانین امنیتی مرتبط است.

## اصول غیرقابل مذاکره

- پروژه با Laravel 13 و معماری API-first توسعه می‌یابد.
- Blade عمومی فقط shell و تجربه کاربری را رندر می‌کند و منطق محصول را از
  endpointهای `/api/v1` می‌گیرد.
- UI عمومی و داشبورد فارسی و راست‌به‌چپ هستند.
- UI عمومی از فونت محلی Vazirmatn استفاده می‌کند.
- صفحه عمومی کلینیک سه بخش دارد: هدر tenant، بدنه HairVision، فوتر tenant.
- متن‌های تست، setup، deployment و debugging نباید در صفحه مراجعه‌کننده دیده
  شوند.
- frontend عمومی نباید به CDN یا resource خارجی وابسته باشد. لوگو و تصاویر
  theme فقط از دامنه اصلی HairVision مجازند.
- tenant هر درخواست عمومی فقط از host درخواست resolve می‌شود.
- هیچ ورودی کاربر مانند `tenant_id`، slug یا domain مرجع tenant نیست.
- هر رکورد متعلق به کلینیک باید `tenant_id` داشته باشد.
- داده‌های متعلق به مراجعه‌کننده باید علاوه بر `tenant_id` با `lead_id` session
  جاری scope شوند.
- OTP هرگز plain ذخیره، لاگ، seed، نمایش یا در response ارسال نمی‌شود.
- تصاویر آپلودی و خروجی‌های تولیدشده هرگز در public webroot ذخیره نمی‌شوند.
- منطق providerهای AI داخل controller قرار نمی‌گیرد.
- provider پیش‌فرض نسخه `1.0.0` مدل نمونه HairVision / `SampleImageProvider`
  است.
- provider نمونه فقط فایل نمونه محلی را برمی‌گرداند و نباید اینترنت، AI خارجی،
  تولید تصویر، ویرایش تصویر یا URL خارجی را صدا بزند.
- provider واقعی فقط از مسیر AI model access فعال، credential رمزنگاری‌شده،
  entitlement سرویس، اعتبار کافی و job اجرا می‌شود.
- کارهای سنگین تولید تصویر خارج از تست با Redis Queue و Horizon اجرا می‌شوند.
- هر تغییر رفتاری باید تست داشته باشد و کل test suite بعد از تغییر اجرا شود.

## چندمستاجری

tenant همان کلینیک است. resolve در مسیرهای عمومی با `Request::getHost()` انجام
می‌شود:

1. host به lowercase تبدیل می‌شود.
2. رکورد فعال در `tenant_domains` پیدا می‌شود.
3. tenant مرتبط باید active باشد.
4. tenant در container و request attributes با کلید `currentTenant` قرار
   می‌گیرد.
5. host نامعتبر یا tenant غیرفعال باید `404` بدهد.

در local ممکن است fallback محدود برای `localhost` و `127.0.0.1` وجود داشته
باشد، اما تست و production باید host صریح tenant داشته باشند.

همه queryهای tenant-owned باید با `tenant_id` محدود شوند. همه منابع
visitor-owned، مانند upload، generation group، generation job و generated asset،
باید با `tenant_id` و `lead_id` session جاری محدود شوند. فیلترهای dashboard مثل
`clinic_id` فقط وقتی معتبرند که کاربر super admin باشد یا عضویت فعال همان
کلینیک را داشته باشد.

## هویت، نشست و احراز هویت

HairVision سه سطح session جدا دارد:

- session مراجعه‌کننده عمومی برای lead verification و generation
- session ادمین کلینیک روی host کلینیک
- session ادمین پلتفرم روی host اصلی

جداسازی cookieها مانع می‌شود login/logout یا session regeneration در داشبورد،
session مراجعه‌کننده را در همان مرورگر خراب کند.

### OTP مراجعه‌کننده

- مسیرها زیر `/api/v1/auth/otp/*` هستند.
- شماره موبایل باید 11 رقم باشد.
- ارقام فارسی، عربی و لاتین پذیرفته و server-side normalize می‌شوند.
- OTP شش رقمی فقط به صورت hash در `otp_codes.code_hash` ذخیره می‌شود.
- purpose عمومی `lead_login` است.
- OTP expiring، single-use، attempt-limited و rate-limited است.
- verify موفق lead را در tenant جاری create/update می‌کند و `lead_id` را در
  session عمومی می‌گذارد.

### OTP داشبورد

- ورود داشبورد زیر `/admin` انجام می‌شود.
- purpose در `otp_codes` برابر `admin_login` است.
- user با شماره normalize شده پیدا می‌شود.
- OTP داشبورد هم hash، expiring، single-use، attempt-limited و rate-limited است.
- کد plain حتی در local debug نباید در صفحه verification دیده شود.
- seed اولیه نسخه `1.0.0` درگاه پیامک `local` را به عنوان تنها درگاه سیستم
  فعال و پیش‌فرض می‌سازد. درگاه سیستم `ippanel` seed می‌شود، اما غیرفعال و
  غیرپیش‌فرض می‌ماند تا فقط بعد از پیکربندی و فعال‌سازی مدیریتی استفاده شود.
- OTP عمومی و داشبورد باید از gateway سیستم فعال و پیش‌فرض ارسال شوند؛ فعال
  کردن IPPanel عملیاتی یک تصمیم مدیریتی بعد از تنظیم credential معتبر است.

## کاربران، نقش‌ها و permissionها

وضعیت عملیاتی نسخه `1.0.0` از جدول `users` برای کاربران داشبورد استفاده می‌کند
و مرز access را با `users.access_scope` مشخص می‌کند:

- `system`: کاربر سطح سیستم است و فقط roleهای `scope = platform` را می‌پذیرد.
- `clinic`: کاربر سطح کلینیک است و فقط roleهای ثابت `clinic_admin`, `editor`,
  `viewer` را می‌پذیرد.

foundation جدول‌های `platform_users` و `clinic_users` برای تفکیک آینده وجود
دارد، اما مسیر auth فعلی و بخش‌هایی از dashboard برای سازگاری هنوز از `users`
و `tenant_user` استفاده می‌کنند. هر توسعه جدید باید دامنه کاربر را روشن کند:
کاربر سیستم برای عملیات پلتفرم و کاربر کلینیکی برای عملیات کلینیک.

نقش‌های پایه:

- `super_admin`: دسترسی سیستمی و عبور صریح از مرز tenant در policy/service.
- `clinic_admin`: مدیریت lead و کاربران کلینیک‌های مجاز.
- `editor`: مشاهده lead و تغییر فیلدهای عملیاتی محدود مانند source،
  `crm_stage` و note.
- `viewer`: فقط مشاهده.

قوانین:

- backend authority است؛ پنهان‌کردن دکمه در UI امنیت نیست.
- permissionها در `permissions`، roleها در `roles` و اتصال در
  `permission_role` ذخیره می‌شوند.
- roleهای clinic ثابت و `scope = clinic` هستند و در مدیریت roleهای سیستمی
  ویرایش یا حذف نمی‌شوند.
- roleهای platform در مدیریت سطوح دسترسی سیستم قابل مدیریت‌اند.
- کاربران غیر super admin نمی‌توانند `super_admin` یا roleهای سیستمی را به
  کاربران بدهند.
- clinic manager فقط می‌تواند roleهای `clinic_admin`, `editor`, `viewer` را در
  کلینیک مجاز تخصیص دهد.
- حذف lead مسیر عملیاتی آزاد ندارد و باید فقط با permission/policy صریح ممکن
  شود.

permissionهای مهم شامل `users.*`, `tenants.*`, `leads.*`, `ai_models.*`,
`plans.*`, `billing.*`, `roles.manage`, `permissions.manage`,
`settings.manage`, `sms_gateways.*`, `payment_gateways.*`,
`reports.management.view` و `reports.user.view` هستند.

## جریان عمومی مراجعه‌کننده

1. مراجعه‌کننده با دامنه tenant وارد landing کلینیک می‌شود.
2. بازدید landing حتی قبل از lead در `tenant_landing_page_visits` ثبت می‌شود.
   IP و user agent به صورت hash ذخیره می‌شوند.
3. frontend از `/api/v1/bootstrap` اطلاعات tenant، theme و featureها را می‌گیرد.
4. کاربر OTP درخواست می‌کند.
5. بعد از verify موفق، lead verified در tenant جاری ساخته یا به‌روزرسانی می‌شود.
6. upload فقط با session lead تاییدشده مجاز است.
7. فایل upload در private storage ذخیره می‌شود.
8. generation فقط برای upload متعلق به همان tenant و همان lead session مجاز است.
9. status و results با polling خوانده می‌شوند.
10. assetها فقط از endpointهای scoped stream می‌شوند و path storage هرگز به
    client داده نمی‌شود.

## لید، CRM و پیگیری

lead متعلق به tenant است و uniqueness شماره با ترکیب `tenant_id +
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

یادداشت‌ها و follow-upها در `lead_notes` ذخیره می‌شوند و باید `tenant_id` و
`lead_id` داشته باشند. تغییر CRM follow-up باید داخل transaction فیلدهای
`leads.crm_stage` و `leads.crm_last_note_at` را هم سازگار نگه دارد. تاریخ‌های
business مانند `next_follow_up_at` و `completed_at` با فرمت جلالی
`YYYY/MM/DD HH:mm:ss` ذخیره می‌شوند.

## آپلود و assetهای خصوصی

- upload فقط بعد از verify شماره و وجود `lead_id` معتبر مجاز است.
- فایل باید image معتبر باشد و محدودیت نوع/حجم FormRequest را رعایت کند.
- upload و generated asset در disk خصوصی ذخیره می‌شوند.
- metadata شامل `tenant_id`, `lead_id`, disk, path, mime, size و status است.
- original upload و resultها public نمی‌شوند.
- public asset access باید tenant و lead session را چک کند.
- dashboard asset access باید policy، tenant context، مالکیت lead/upload و
  private storage را چک کند.

## تولید تصویر و AI

payload public generation فقط `upload_id` است. client نباید provider، tenant،
credential یا model ارسال کند.

پیش‌شرط‌ها:

1. tenant از host resolve شده باشد.
2. lead session تاییدشده وجود داشته باشد.
3. upload متعلق به همان tenant و lead باشد.
4. کلینیک اشتراک پایه فعال داشته باشد.
5. entitlement فعال برای سرویس `hair_transplant` داشته باشد.
6. اعتبار سرویس برای سه مرحله کافی باشد.
7. AI model access فعال و assign شده به کلینیک برای `hair_transplant` وجود
   داشته باشد.

رفتار generation:

- برای هر upload فقط یک generation group ساخته می‌شود؛ درخواست تکراری همان group
  را reuse می‌کند.
- سه job جدا برای `two_weeks`, `intermediate_regrowth`, `final_result` ساخته
  می‌شود.
- flow پیش‌فرض فقط اولین مرحله را dispatch می‌کند و بعد از تکمیل هر مرحله،
  مرحله بعد dispatch می‌شود.
- برای providerهای واقعی هزینه‌دار مانند GapGPT، stageها هم‌زمان به provider
  ارسال نمی‌شوند تا ترتیب نمایش حفظ شود و در صورت خطا، ادامه مراحل بدون مصرف
  هزینه متوقف شود.
- هر job log، failure، asset و credit accounting مستقل دارد.
- provider call واقعی نباید retry خودکار HTTP یا queue داشته باشد؛ timeoutها
  باید به اندازه کافی بلند و با `retry_after` صف هماهنگ باشند تا درخواست
  تکراری به مدل ارسال نشود.
- اگر همه jobها completed شوند، group completed می‌شود و SMS نهایی ارسال
  می‌شود.

## مدل‌های AI و providerها

مدل‌های قابل استفاده در `config/ai-models.php` تعریف می‌شوند و accessهای واقعی
در `ai_model_accesses` ذخیره می‌شوند.

usage categoryهای فعلی:

- `hair_transplant`
- `eyebrow_transplant`

مدل‌های فعلی:

- `sample_image`: مدل نمونه محافظت‌شده HairVision، local، بدون credential،
  غیرقابل حذف، قابل toggle، بدون تماس خارجی.
- `gapgpt`: مدل واقعی image edit، نیازمند credential رمزنگاری‌شده، قابل ایجاد و
  ویرایش از داشبورد برای usageهای مجاز.

قوانین امنیت AI:

- credentialها در `AiModelAccess.credentials` با encrypted cast ذخیره می‌شوند.
- credential نباید در config، seed، log، response یا Blade plain دیده شود.
- update با credential خالی باید secret قبلی را حفظ کند.
- usage log فقط metadata امن، status، referenceهای opaque و errorهای safe نگه
  می‌دارد.
- provider failure نباید اعتبار مدل یا اعتبار سرویس را مصرف کند.
- protected demo access قابل حذف نیست.
- کلینیک فقط می‌تواند accessهای active و assign شده خود را استفاده کند.
- برای هر کلینیک و usage category فقط یک assignment فعال مجاز است.

قانون GapGPT:

- درخواست باید multipart باشد، نه JSON.
- endpoint پیش‌فرض: `https://api.gapgpt.app/v1/images/edits`
- field فایل: `image`
- fieldها: `model = gpt-image-2`, `prompt`, `n = 1`,
  `response_format = url`, `size = 1024x1024`
- Laravel HTTP client باید multipart boundary را بسازد؛ نباید دستی
  `Content-Type: application/json` تنظیم شود.
- کلید واقعی GapGPT هرگز seed، commit، log، display یا document نمی‌شود.

## SampleImageProvider

provider نمونه فقط sample local را برمی‌گرداند:

- `sample-results/two-weeks.jpg`
- `sample-results/intermediate_regrowth.jpg`
- `sample-results/final_result.jpg`

خروجی نهایی باید به مسیر private زیر کپی شود:

```text
storage/app/private/tenants/{tenant_uuid}/leads/{lead_uuid}/results/{generation_group_uuid}/{period}.jpg
```

## اشتراک، پلن و اعتبار

پلن‌ها سه نوع دارند:

- `base_subscription`: اشتراک اصلی کلینیک
- `service_add_on`: افزودن سرویس جدید به اشتراک پایه فعال
- `credit_top_up`: افزایش اعتبار سرویس فعال

قوانین:

- هر کلینیک فقط یک base subscription فعال دارد.
- خرید پلن پایه متفاوت، subscription قبلی را `replaced` می‌کند.
- زمان و اعتبار باقی‌مانده به پلن جدید منتقل نمی‌شود.
- خرید همان پلن فعال تا وقتی زمان و اعتبار دارد ممنوع است.
- service add-on فقط با base subscription فعال قابل خرید است.
- add-on تکراری برای سرویس فعال ممنوع است.
- credit top-up زمان subscription را تغییر نمی‌دهد و سرویس جدید unlock نمی‌کند.
- top-up فقط برای سرویس فعال موجود مجاز است.

اعتبار سرویس در `subscription_service_entitlements` و ledger تغییرات در
`credit_transactions` ذخیره می‌شود. مصرف اعتبار باید داخل transaction و با row
lock انجام شود تا balance منفی و double-spend رخ ندهد. اتمام اعتبار یک سرویس
فقط همان سرویس را block می‌کند و base subscription را expire نمی‌کند.

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

`invoices.activated_at` جلوی activation تکراری callback را می‌گیرد. خطاهای
gateway با context امن log می‌شوند و به کاربر فقط پیام generic فارسی داده
می‌شود. authority، credential، raw payload، stack trace و provider diagnostics
نباید در UI یا JSON دیده شوند.

## درگاه‌های پیامک

- SMS gatewayها scopeهای `system` و `tenant` دارند.
- gateway سیستم برای OTP و پیام‌های پلتفرم استفاده می‌شود.
- gateway tenant تنظیمات پیامک یک کلینیک است و با `tenant_id` همان کلینیک ذخیره
  می‌شود.
- هر کلینیک حداکثر یک gateway پیامک کلینیکی دارد.
- gateway کلینیک دارایی کاربر نیست؛ metadata خود کلینیک است.
- credentialها encrypted هستند و فقط masked نمایش داده می‌شوند.
- default کردن gateway باید defaultهای دیگر همان scope و owner را unset کند.

## داشبورد مدیریت

داشبورد زیر `/admin` است و با Blade، session auth، policy و route middleware
کار می‌کند.

قابلیت‌های نسخه `1.0.0`:

- mobile OTP login
- dashboard overview برای system و clinic context
- lead list/detail/create/edit و CRM follow-up
- private upload preview از route مجاز
- user management
- clinic management
- AI model access management و clinic assignment
- roles, permissions, settings
- SMS و payment gateways
- plan definitions, billing, invoices, payments, subscriptions
- user و management reports
- active-plan gating برای کاربران عادی

رفتار UI:

- super admin با context global شروع می‌کند.
- clinic filter داخل صفحات مرتبط است، نه header جهانی.
- light theme پیش‌فرض است؛ dark mode فقط با انتخاب persisted فعال می‌شود.
- زیر `1024px` حالت mobile/tablet و از `1024px` حالت desktop است.
- همه صفحه‌های داشبورد سیستم و کلینیک باید بالای محتوای اصلی مسیر سایت
  فارسی، RTL و قابل دسترس داشته باشند؛ این مسیر از component مشترک layout
  رندر می‌شود و نباید در صفحه‌ها تکرار شود.
- لیست‌های مهم search/filter/sort/pagination backend دارند.
- جدول‌های مهم در mobile/tablet به card تبدیل می‌شوند.
- actionهای ردیفی از منوی سه‌نقطه shared استفاده می‌کنند.
- اگر ردیف جدول در منوی عملیات اکشن مشاهده داشته باشد، کلیک روی خود ردیف به
  صفحه مشاهده همان رکورد می‌رود و نشانگر ماوس روی آن ردیف به شکل دست است.
- متن اکشن‌های ردیفی برای صفحه show باید «مشاهده» باشد، نه «جزئیات».
- create-capable list pageها فرم create را در modal بالای لیست ارائه می‌کنند.
- iconهای dashboard از `x-admin.icon` و UIcons محلی هستند، نه CDN یا SVG
  پراکنده.

کاربران عادی بدون plan فعال هنوز می‌توانند overview، plans، billing/invoices،
tenant switch/logout و settings مجاز خود را ببینند؛ routeهای operational با
middleware `active_plan` block می‌شوند. super admin و کاربران system-management
با permission مناسب از این gating معاف‌اند.

## تاریخ، عدد و زبان

- زبان کاربر فارسی است.
- اعداد فارسی، عربی و لاتین در ورودی‌های پشتیبانی‌شده پذیرفته و normalize
  می‌شوند.
- normalization فقط با `App\Support\NumberNormalizer` و validationهای مرتبط مثل
  `SupportedDigits` انجام شود.
- تاریخ‌های business/user-facing با تقویم جلالی ذخیره و نمایش داده می‌شوند.
- فرمت datetime جلالی: `YYYY/MM/DD HH:mm:ss`
- ستون‌های business datetime از `char(19)` استفاده می‌کنند تا sort/filter
  رشته‌ای درست بماند.
- timestampهای سیستمی لاراول مانند `created_at`, `updated_at`, queue timestamp و
  OTP expiration در storage میلادی می‌مانند و فقط برای نمایش تبدیل می‌شوند.
- تبدیل تاریخ فقط از `App\Support\JalaliDate`، ruleهای `JalaliDate` و
  `JalaliDateTime` و componentهای Blade مربوط انجام شود.
- datepicker باید محلی و از Vite bundle باشد، نه CDN.
- copy کاربر در `lang/fa` نگهداری می‌شود؛ keyهای فنی مانند permission code،
  route name، provider code و status داخلی انگلیسی و پایدار می‌مانند.

## امنیت، خطا و لاگ

اولویت‌های امنیتی:

- جلوگیری از نشت داده بین tenantها.
- محافظت از OTP، session و credentialها.
- خصوصی‌ماندن تصاویر مراجعه‌کننده و خروجی‌ها.
- جلوگیری از UUID guessing برای assetها.
- جلوگیری از external resource injection در theme.
- جلوگیری از تماس accidental با provider واقعی.
- جلوگیری از نشت CRM note، invoice، gateway و payloadهای حساس.

قوانین خطا:

- failureهای operational، gateway، provider، queue handoff و write flowهای حساس
  باید در مرز controller/service/job/adapter catch شوند.
- exception واقعی با context غیرحساس log شود.
- کاربر فقط پیام generic و localized ببیند.
- OTP، credential، bearer token، private path، full gateway authority، payload
  upload، raw provider response و stack trace نباید log یا نمایش داده شود.
- debug traceهای چندمرحله‌ای از instrumentation مرکزی عبور کنند، در فایل‌های
  عادی import دستی لاگر اضافه نشود، و وقتی `APP_DEBUG=false` خاموش باشند.

## تست و کیفیت

هر تغییر رفتاری باید تست داشته باشد. suite باید موارد زیر را پوشش دهد یا بعد
از تغییر مرتبط به‌روزرسانی شود:

- tenant resolution و unknown host
- landing visit tracking
- OTP hash/rate-limit/digit normalization
- lead verification
- upload authorization و validation
- session cookie separation
- generation با sample provider و GapGPT
- tenant isolation برای assetها و CRM
- admin OTP و dashboard scoping
- role/permission enforcement
- user, clinic, AI model و system management
- SMS gateway scoping و credential masking
- billing, invoice, payment, subscription و credit ledger
- AI service access blocking
- Jalali date و number normalization
- responsive dashboard component contracts

دستور اصلی:

```bash
php artisan test
```

در صورت تغییر CSS، JavaScript، Blade، font یا Vite:

```bash
npm run build
```

## قانون تصمیم‌گیری برای توسعه بعدی

قبل از هر تغییر بپرسید:

1. داده متعلق به کل سیستم است یا یک کلینیک؟
2. اگر user لازم دارد، کاربر سیستمی است یا کلینیکی؟
3. آیا `tenant_id` لازم دارد؟
4. tenant از host یا context مجاز resolve می‌شود؟
5. role و permission در سطح system است یا clinic؟
6. آیا credential، OTP، private path یا payload حساس نشت نمی‌کند؟
7. آیا public Blade از `/api/v1` عبور می‌کند؟
8. آیا رفتار جدید تست دارد؟
9. آیا داکیومنت موجود باید به‌روزرسانی شود یا واقعاً فایل جدید لازم است؟
