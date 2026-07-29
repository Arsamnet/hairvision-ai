# سند جامع قوانین محصول HairVision

نسخه: [`2.0.2`](../CHANGELOG.md#202)

این سند مرجع فارسی قوانین حاکم بر HairVision است. هر تصمیم محصولی، فنی،
امنیتی، مالی یا عملیاتی باید با این سند سازگار باشد. اگر قانون جدیدی برای
محصول لازم شد، ابتدا همین سند به‌روزرسانی و سپس کد، تست‌ها و مستندات فنی مطابق
آن تغییر داده می‌شوند.

نسخه `2.0.0` ساختار ثابت «سه تصویر کاشت مو» را به یک پلتفرم عمومی،
دسته‌بندی‌شده و قابل توسعه برای سرویس‌های تولید تصویر تبدیل می‌کند. در این
نسخه دسته‌بندی مدل، نوع مدل، نمونه اجرایی مدل، خروجی قابل درخواست، دسترسی
کلینیک و صف اجرای مدل مفاهیم مستقل هستند.

## تغییرات نسخه 2.0.2

- نوع مدل code-owned جدید `openai` با adapter مستقل Images Edit اضافه شد.
- API key رمزنگاری می‌شود، پارامترهای provider از تنظیمات مدل و prompt از
  output کاربرد خوانده می‌شود.
- seed برای هر دو کاربرد اولیه مدل OpenAI و تنظیمات پیش‌فرض آن را می‌سازد، ولی
  assignment کلینیک را تا فعال‌سازی صریح مدیر غیرفعال نگه می‌دارد.

## خلاصه تغییرات نسخه 2.0.0

- دسته‌بندی‌های مدل هوش مصنوعی به منابع سیستمی قابل مدیریت با API و CRUD تبدیل
  می‌شوند.
- دسته‌بندی‌هایی مانند `hair_transplant` و `eyebrow_transplant` مبنای خرید،
  entitlement و دسترسی کلینیک هستند.
- نوع مدل مانند `sample_image`، `gapgpt` و `openai` در فایل کانفیگ تعریف می‌شود و از طریق
  دیتابیس ساخته یا حذف نمی‌شود.
- هر رکورد مدل در دیتابیس یک نمونه اجرایی و قابل صف‌بندی از یک نوع مدل است.
- فرم تنظیم مدل بر اساس schema تعریف‌شده در کانفیگ نوع مدل به صورت پویا ساخته
  می‌شود.
- خروجی‌ها دیگر به سه مرحله ثابت محدود نیستند و هر خروجی به صورت مستقل و به
  درخواست مراجعه‌کننده تولید می‌شود.
- در هر دسته‌بندی می‌توان چند مدل فعال داشت و درخواست‌ها بین مدل‌های واجد شرایط
  با صف منطقی مستقل توزیع می‌شوند.
- پروفایل مراجعه‌کننده در هر کلینیک یک تصویر اصلی جاری دارد؛ تصاویر قبلی و
  خروجی‌های جایگزین‌شده برای آرشیو کلینیک نگهداری می‌شوند.
- یک شماره موبایل می‌تواند در چند کلینیک lead مستقل داشته باشد، بدون اینکه
  اطلاعات یا تصاویر بین tenantها به اشتراک گذاشته شود.

### قالب‌های لندینگ مدیریت‌شده

- قالب لندینگ به یک منبع مدیریت‌شده در سطح سیستم تبدیل شده و مدیر سیستم
  می‌تواند کد HTML هدر، بدنه و فوتر هر قالب را ویرایش کند.
- هر کلینیک دقیقاً یک قالب فعال دریافت می‌کند و یک قالب فعال به عنوان پیش‌فرض
  کلینیک‌های جدید تعریف می‌شود.
- CSS و JavaScript قالب باید داخل همان HTML باشد و دریافت asset اجرایی از
  دامنه خارجی مجاز نیست.
- شخصی‌سازی‌های legacy کلینیک تا زمان نخستین تخصیص صریح قالب حفظ می‌شوند.
- ویرایشگر کد با تم روشن و تاریک داشبورد هماهنگ است و باید متن، caret،
  selection، border و focus پرکنتراست و خوانا داشته باشد.
- قرارداد نسخه‌گذاری و وضعیت `Unreleased` برای تغییرات فاقد نسخه رسمی ثبت شد.

## هویت محصول

HairVision یک SaaS چندمستاجری برای کلینیک‌هایی است که سرویس‌های مبتنی بر تولید
یا ویرایش تصویر، از جمله کاشت مو و کاشت ابرو، ارائه می‌کنند. هر کلینیک یک
`tenant` مستقل دارد. مراجعه‌کننده از دامنه اختصاصی یا زیردامنه کلینیک وارد
می‌شود، شماره موبایل خود را با OTP تایید می‌کند، به عنوان lead همان کلینیک ثبت
می‌شود، یک تصویر اصلی خصوصی بارگذاری می‌کند و سپس هر یک از خروجی‌های مجاز
دسته‌بندی‌های خریداری‌شده توسط کلینیک را به صورت مستقل درخواست می‌کند.

نمونه دسته‌بندی‌ها:

- کاشت مو: `hair_transplant`
- کاشت ابرو: `eyebrow_transplant`

نمونه خروجی‌های دسته‌بندی کاشت مو:

- یک روز بعد از کاشت مو: `two_weeks`
- ۶ ماه بعد از کاشت مو: `intermediate_regrowth`
- یک سال بعد از کاشت مو: `final_result`

خروجی اولیه کاشت ابرو با کلید `eyebrow_final_result` نتیجه یک سال
بعد از کاشت ابرو را نمایش می‌دهد.

این خروجی‌ها فقط seed اولیه هستند. تعداد، عنوان و ترتیب خروجی‌های هر دسته‌بندی
باید از داشبورد سیستم قابل مدیریت باشد و منطق عمومی نباید به این سه کلید hard
code شود.

## واژگان و مرز مفاهیم

### دسته‌بندی مدل

`AI Model Category` یک قابلیت محصولی قابل خرید و قابل ارائه به کلینیک است؛
برای مثال کاشت مو یا کاشت ابرو. دسته‌بندی مبنای entitlement، اعتبار، نمایش در
رابط مراجعه‌کننده و انتخاب pool مدل‌ها است.

### خروجی دسته‌بندی

`Category Output` یک نتیجه مستقل و قابل درخواست در یک دسته‌بندی است؛ برای مثال
`final_result`. خروجی دارای کلید فنی پایدار، عنوان فارسی، توضیح، ترتیب نمایش،
وضعیت فعال و هزینه اعتباری است.

### نوع مدل

`AI Model Type` یک adapter یا provider فنی است که فقط در کانفیگ برنامه تعریف
می‌شود؛ برای مثال `sample_image`، `gapgpt` و `openai`. نوع مدل مشخص می‌کند چه
credentialها، تنظیمات عمومی و تنظیمات تکرارشونده برای هر خروجی لازم است.

### نمونه مدل

`AI Model` یک رکورد اجرایی در دیتابیس است که به یک دسته‌بندی و یک نوع مدل تعلق
دارد. برای مثال می‌توان سه مدل فعال از نوع `gapgpt` در دسته‌بندی کاشت مو داشت.
هر مدل credential، تنظیمات، محدودیت هم‌زمانی، وضعیت سلامت و تنظیمات خروجی مخصوص
خود را دارد.

### درخواست تولید

`Generation Request` درخواست مراجعه‌کننده برای تولید یک خروجی مشخص از یک تصویر
اصلی مشخص در یک دسته‌بندی است. مراجعه‌کننده مدل یا provider را انتخاب نمی‌کند.

### تلاش اجرا

`Generation Attempt` اجرای واقعی یک درخواست روی یک مدل مشخص است. retry دستی یا
جابجایی کنترل‌شده به مدل دیگر باید تلاش جدید بسازد و تاریخچه تلاش قبلی را حفظ
کند.

## اصول غیرقابل مذاکره

- پروژه با Laravel 13 و معماری API-first توسعه می‌یابد.
- Blade عمومی فقط shell و تجربه کاربری را رندر می‌کند و منطق محصول را از
  endpointهای `/api/v1` می‌گیرد.
- UI عمومی و داشبورد فارسی و راست‌به‌چپ هستند.
- UI عمومی از فونت محلی Vazirmatn استفاده می‌کند.
- frontend عمومی نباید به CDN یا resource خارجی وابسته باشد.
- tenant هر درخواست عمومی فقط از host درخواست resolve می‌شود.
- هیچ ورودی کاربر مانند `tenant_id`، slug یا domain مرجع tenant نیست.
- هر رکورد متعلق به کلینیک باید `tenant_id` داشته باشد.
- داده مراجعه‌کننده علاوه بر `tenant_id` باید با `lead_id` session جاری scope
  شود.
- یک شماره موبایل می‌تواند در چند tenant lead مستقل داشته باشد، اما هیچ
  endpoint کلینیکی حق کشف یا نمایش ارتباط آن شماره با کلینیک‌های دیگر را ندارد.
- OTP هرگز plain ذخیره، لاگ، seed، نمایش یا در response ارسال نمی‌شود.
- تصاویر آپلودی، نمونه‌های مدل و خروجی‌های تولیدشده هرگز در public webroot
  ذخیره نمی‌شوند.
- منطق provider، انتخاب مدل، رزرو اعتبار و مدیریت صف داخل controller قرار
  نمی‌گیرد.
- client عمومی هرگز provider، model، credential، prompt یا tenant را تعیین
  نمی‌کند.
- credential واقعی فقط به صورت رمزنگاری‌شده ذخیره و فقط در adapter مربوط مصرف
  می‌شود.
- provider نمونه نباید اینترنت، AI خارجی، ویرایش تصویر یا URL خارجی را صدا
  بزند.
- تماس provider واقعی فقط از طریق job و پس از entitlement معتبر، اعتبار کافی،
  انتخاب مدل و رزرو ظرفیت انجام می‌شود.
- Redis Queue و Horizon مسیر اصلی اجرای کارهای سنگین در محیط غیرتست هستند.
  در هاست اشتراکی cPanel که worker دائمی در دسترس نیست، fallback کرون باید به
  صورت صریح فعال شود، با Redis یا database queue کار کند و هم‌زمان با Horizon
  اجرا نشود.
- دیتابیس source of truth وضعیت درخواست، صف، تلاش‌ها، اعتبار و assetها است؛ Redis
  فقط برای اجرای queue، lock، semaphore و cache استفاده می‌شود.
- هیچ تماس خارجی provider داخل transaction دیتابیس انجام نمی‌شود.
- هر تغییر رفتاری باید تست داشته باشد و کل test suite پس از تغییر اجرا شود.

## چندمستاجری

tenant همان کلینیک است. resolve در مسیرهای عمومی با `Request::getHost()` انجام
می‌شود:

1. host به lowercase تبدیل می‌شود.
2. رکورد فعال در `tenant_domains` پیدا می‌شود.
3. tenant مرتبط باید active باشد.
4. tenant در container و request attributes با کلید `currentTenant` قرار
   می‌گیرد.
5. host نامعتبر یا tenant غیرفعال باید `404` بدهد.

همه queryهای tenant-owned باید با `tenant_id` محدود شوند. همه منابع
visitor-owned، از جمله lead profile، source image، generation request، attempt،
generated asset و archive، باید با `tenant_id` و `lead_id` session جاری محدود
شوند.

وجود یک شماره موبایل در چند کلینیک مجاز است. uniqueness lead با ترکیب زیر
اعمال می‌شود:

```text
unique(tenant_id, phone_normalized)
```

هر کلینیک فقط رکورد lead، تصاویر، درخواست‌ها و آرشیو tenant خود را می‌بیند.
ورود در کلینیک دیگر session، تصویر اصلی یا خروجی‌های کلینیک فعلی را منتقل یا
قابل مشاهده نمی‌کند.

## هویت، نشست و احراز هویت

HairVision سه سطح session جدا دارد:

- session مراجعه‌کننده عمومی برای lead verification، profile و generation
- session ادمین کلینیک روی host کلینیک
- session ادمین پلتفرم روی host اصلی

session عمومی باید به host/tenant جاری وابسته باشد. داشتن `lead_id` متعلق به
tenant دیگر حتی با UUID معتبر، مجوز دسترسی ایجاد نمی‌کند.

### OTP مراجعه‌کننده

- مسیرها زیر `/api/v1/auth/otp/*` هستند.
- شماره موبایل باید 11 رقم باشد.
- ارقام فارسی، عربی و لاتین پذیرفته و server-side normalize می‌شوند.
- OTP شش رقمی فقط به صورت hash در `otp_codes.code_hash` ذخیره می‌شود.
- purpose عمومی `lead_login` است.
- OTP expiring، single-use، attempt-limited و rate-limited است.
- ارسال به provider پیامک در صورت timeout، خطای ارتباطی یا پاسخ ناموفق
  باید حداقل سه تلاش محدود داشته باشد.
- پس از شکست هر سه تلاش، کاربر فقط پیام عمومی دریافت می‌کند و یک
  warning با context ایمن، بدون OTP، شماره موبایل، credential یا raw response،
  در لاگ لاراول ثبت می‌شود.
- verify موفق lead همان tenant را create/update می‌کند و `lead_id` را در session
  عمومی tenant جاری می‌گذارد.
- ورود همان شماره در tenant دیگر lead و session مستقل ایجاد می‌کند.

### OTP داشبورد

- ورود داشبورد زیر `/admin` انجام می‌شود.
- purpose در `otp_codes` برابر `admin_login` است.
- OTP داشبورد hash، expiring، single-use، attempt-limited و rate-limited است.
- کد plain حتی در local debug نباید در صفحه verification دیده شود.
- OTP عمومی و داشبورد باید از gateway سیستم فعال و پیش‌فرض ارسال شوند.

## کاربران، نقش‌ها و permissionها

مرز access کاربران داشبورد با `users.access_scope` مشخص می‌شود:

- `system`: عملیات سطح پلتفرم
- `clinic`: عملیات tenantهای مجاز

نقش‌های پایه:

- `super_admin`
- `clinic_admin`
- `editor`
- `viewer`

backend authority است و پنهان‌کردن دکمه در UI امنیت محسوب نمی‌شود.

### پیام‌ها و اعلان‌های کلینیک

مدیر سیستم می‌تواند پیام‌هایی با نوع و سطح اهمیت مشخص برای کلینیک‌ها ارسال کند.
پیام‌ها می‌توانند برای همه کلینیک‌ها، یک کلینیک مشخص، یا چند کلینیک انتخابی
هدف‌گذاری شوند. شرط‌های عملیاتی مثل داشتن/نداشتن پلن فعال، دسترسی به یک کاربرد
AI یا اتصال یک مدل AI مشخص، سطح ارسال جداگانه نیستند و فقط روی همان دامنه
انتخاب‌شده اعمال می‌شوند. ارسال فوری پیام بدون بازه نمایش مجاز است؛ در ارسال
زمان‌بندی‌شده، زمان شروع الزامی و زمان پایان اختیاری است.

ارسال پیام باید recipientهای کلینیک را در زمان ایجاد پیام snapshot کند تا
تاریخچه تحویل مستقل از تغییرات بعدی پلن، مدل یا entitlement باقی بماند. شمارنده
هدر کلینیک فقط پیام‌های منتشرشده، قابل نمایش در زمان فعلی و خوانده‌نشده همان
tenant را نشان می‌دهد. باز کردن پیام در داشبورد کلینیک، همان پیام را برای همان
tenant خوانده‌شده می‌کند و شناسه، نام کاربر خواننده و زمان خواندن ثبت می‌شود.
متن پیام در لیست پیام‌های کلینیک نمایش داده نمی‌شود و فقط بعد از باز شدن پیام
قابل مشاهده است. اطلاعات کاربر خواننده و گزارش خوانده‌شده/خوانده‌نشده فقط در
داشبورد مدیریت سیستم و صفحه وضعیت همان پیام قابل مشاهده است؛ در داشبورد کلینیک
فقط زمان خواندن همان پیام در صفحه مشاهده پیام نمایش داده می‌شود.

permissionهای جدید و مهم نسخه 2.0.0:

- `ai_model_categories.view`
- `ai_model_categories.create`
- `ai_model_categories.update`
- `ai_model_categories.delete`
- `ai_model_category_outputs.manage`
- `ai_model_types.view`
- `ai_models.view`
- `ai_models.create`
- `ai_models.update`
- `ai_models.delete`
- `ai_models.activate`
- `ai_models.test_connection`
- `ai_model_jobs.view`
- `ai_model_jobs.retry`
- `ai_model_jobs.cancel`
- `lead_archives.view`
- `lead_archives.download`

permissionهای مدیریت قالب لندینگ در نسخه 2.0.0:

- `landing_templates.view`
- `landing_templates.create`
- `landing_templates.update`
- `landing_templates.delete`
- `landing_templates.assign`

مدیریت دسته‌بندی، نوع‌های قابل استفاده، نمونه‌های مدل و وضعیت عملیاتی pool فقط
در سطح سیستم انجام می‌شود. کاربران کلینیک مجاز نیستند prompt، credential، نوع
مدل، تعداد مدل‌ها، ظرفیت مدل یا مدل منتخب هر job را تغییر دهند.

## جریان عمومی مراجعه‌کننده

1. مراجعه‌کننده با دامنه tenant وارد landing کلینیک می‌شود.
2. بازدید landing ثبت می‌شود.
3. frontend از `/api/v1/bootstrap` اطلاعات tenant، theme، دسته‌بندی‌های قابل
   استفاده و وضعیت session را می‌گیرد.
4. کاربر OTP درخواست و تایید می‌کند.
5. lead tenant جاری create/update می‌شود.
6. پروفایل جاری از `/api/v1/profile` خوانده می‌شود.
7. کاربر در صورت نیاز تصویر اصلی جدید بارگذاری می‌کند.
8. سیستم تصویر قبلی را از حالت جاری خارج می‌کند و نسخه جدید را current می‌کند.
9. frontend فقط خروجی‌های فعال دسته‌بندی‌هایی را نمایش می‌دهد که هم category
   فعال، هم entitlement فعال، هم پلن پایه فعال، هم مدل فعال متصل به کلینیک، و
   هم مدل/خروجی اجرایی فعال داشته باشند.
10. کاربر هر خروجی دلخواه را جداگانه درخواست می‌کند.
11. درخواست persisted می‌شود و ترک صفحه باعث لغو آن نمی‌شود.
12. کاربر در مراجعه بعدی وضعیت `queued`, `processing`, `completed` یا `failed`
    و نتیجه موجود را در پروفایل مشاهده می‌کند.
13. asset فقط از endpoint خصوصی و scoped stream می‌شود.

## قالب لندینگ کلینیک

قالب‌های لندینگ منبع system-owned هستند و `tenant_id` ندارند. ارتباط
`tenant_theme_settings` قالب فعال هر کلینیک را نگهداری می‌کند و هر tenant فقط
یک ردیف تنظیمات لندینگ دارد.

قوانین:

- مدیر سیستم می‌تواند قالب را ایجاد، ویرایش، فعال یا غیرفعال کند و به هر
  کلینیک یک قالب فعال تخصیص دهد.
- همواره باید حداقل یک قالب فعال به عنوان پیش‌فرض وجود داشته باشد. ساخت کلینیک
  جدید و تخصیص قالب پیش‌فرض در یک transaction انجام می‌شود.
- اگر قالب تخصیص‌یافته بعداً غیرفعال شود، لندینگ از قالب فعال پیش‌فرض استفاده
  می‌کند و ارتباط تاریخی حذف نمی‌شود.
- قالب پیش‌فرض و قالبی که به کلینیکی تخصیص دارد قابل حذف نیست.
- کدهای `header_html`، `body_html` و `footer_html` فقط برای مدیر دارای
  permission و به صورت raw HTML رندر می‌شوند؛ بنابراین این سطح دسترسی معادل
  دسترسی انتشار کد در لندینگ عمومی است.
- `<style>` و `<script>` باید inline باشند. `script src`، تگ `link`،
  `@import`، سند کامل HTML، frame/object/embed، scheme ناامن و asset خارجی رد
  می‌شوند.
- تصویر و favicon فقط می‌توانند local، inline image data یا روی دامنه اصلی
  HairVision باشند.
- مقادیر legacy هر کلینیک تا وقتی مدیر سیستم یک قالب را صریحاً تخصیص نداده
  است خوانده می‌شوند. نخستین تخصیص، منبع محتوا را به قالب مرکزی منتقل می‌کند
  و داده legacy را برای rollback یا بررسی تاریخی حذف نمی‌کند.
- tenant فقط از host درخواست resolve می‌شود و انتخاب قالب عمومی نباید
  `tenant_id`، slug یا domain ارسالی کاربر را بپذیرد.

## دسته‌بندی مدل‌های هوش مصنوعی

دسته‌بندی‌ها در جدول `ai_model_categories` نگهداری می‌شوند و منابع سیستمی
هستند؛ بنابراین `tenant_id` ندارند.

فیلدهای پیشنهادی:

- `id` یا UUID عمومی
- `code`: کلید فنی unique، lowercase و پایدار
- `name`: عنوان فارسی
- `description`
- `status`: `active`, `inactive`, `archived`
- `sort_order`
- `icon` یا شناسه icon داخلی اختیاری
- `created_by`
- `updated_by`
- timestamps سیستمی

قوانین:

- `code` پس از استفاده در entitlement، invoice، generation یا ledger تغییر
  نمی‌کند.
- تغییر عنوان و توضیح مجاز است.
- حذف hard فقط وقتی مجاز است که هیچ output، model، entitlement، invoice،
  generation یا ledger مرتبطی وجود نداشته باشد.
- در غیر این صورت API باید `409 Conflict` برگرداند و مدیر از `inactive` یا
  `archived` استفاده کند.
- دسته‌بندی غیرفعال برای خرید و درخواست جدید نمایش داده نمی‌شود، اما تاریخچه
  آن قابل مشاهده می‌ماند.
- APIها باید pagination، search، filter و sort backend داشته باشند.

### خروجی‌های دسته‌بندی

خروجی‌های قابل درخواست در `ai_model_category_outputs` نگهداری می‌شوند.

فیلدهای پیشنهادی:

- `id`
- `ai_model_category_id`
- `code`: کلید پایدار در محدوده دسته‌بندی
- `name`
- `description`
- `prompt`: پرامپت اجباری و محرمانه همان خروجی برای تولید تصویر
- `sort_order`
- `credit_cost`: هزینه هر تولید موفق یا واحد رزرو اعتبار
- `is_active`
- timestamps

قوانین:

- unique روی `(ai_model_category_id, code)` اعمال می‌شود.
- خروجی با generation history حذف hard نمی‌شود و فقط غیرفعال می‌شود.
- تغییر ترتیب یا عنوان نباید تاریخچه را خراب کند.
- هر خروجی باید prompt مستقل خود را داشته باشد. در generation مستقل، snapshot
  همین prompt روی تلاش اجرا ذخیره و همان متن برای اجرای provider استفاده می‌شود.
- prompt خروجی در API عمومی، پروفایل مراجعه‌کننده یا پاسخ‌های visitor نمایش داده
  نمی‌شود و فقط برای مدیران مجاز سیستم قابل مدیریت است.
- تعداد خروجی قابل نمایش دسته‌بندی از خروجی‌های فعال آن به دست می‌آید و در
  frontend hard code نمی‌شود.
- هر مدل می‌تواند همه یا بخشی از خروجی‌های دسته‌بندی را پشتیبانی کند.
- برای هر درخواست فقط مدل‌هایی eligible هستند که output config فعال برای همان
  خروجی داشته باشند.

## نوع مدل و کانفیگ پویا

نوع‌های مدل در `config/ai-models.php` تعریف می‌شوند. نوع مدل رکورد CRUD دیتابیس
نیست و deploy کد برای افزودن نوع جدید لازم است. API فقط registry امن و schema
قابل نمایش نوع‌ها را برمی‌گرداند.

هر type حداقل مشخصات زیر را تعریف می‌کند:

- کلید type
- عنوان نمایشی
- provider class
- قابلیت‌ها
- schema credentialها
- schema تنظیمات عمومی
- schema تنظیمات هر خروجی
- validation rules
- defaultهای غیرحساس
- قابلیت تست اتصال
- timeout و سیاست اجرای امن

نمونه ساختار مورد انتظار:

```php
<?php

use App\AI\Providers\GapGptImageEditProvider;
use App\AI\Providers\SampleImageProvider;

return [
    'types' => [
        'sample_image' => [
            'label' => 'مدل نمونه',
            'provider' => SampleImageProvider::class,
            'credentials' => [],
            'settings' => [],
            'output_fields' => [
                [
                    'key' => 'sample_asset_id',
                    'label' => 'تصویر نمونه',
                    'type' => 'private_image',
                    'required' => true,
                    'rules' => ['required', 'uuid'],
                    'secret' => false,
                ],
            ],
            'defaults' => [
                'concurrency_limit' => 10,
            ],
        ],
        'gapgpt' => [
            'label' => 'GapGPT',
            'provider' => GapGptImageEditProvider::class,
            'credentials' => [
                [
                    'key' => 'api_key',
                    'label' => 'API Key',
                    'type' => 'secret',
                    'required' => true,
                    'rules' => ['required', 'string'],
                    'secret' => true,
                ],
            ],
            'settings' => [
                [
                    'key' => 'endpoint',
                    'type' => 'url',
                    'required' => true,
                ],
                [
                    'key' => 'model',
                    'type' => 'text',
                    'required' => true,
                ],
                [
                    'key' => 'size',
                    'type' => 'select',
                    'required' => true,
                    'options' => ['1024x1024'],
                ],
                [
                    'key' => 'timeout_seconds',
                    'type' => 'integer',
                    'required' => true,
                ],
            ],
            'output_fields' => [
                [
                    'key' => 'prompt',
                    'label' => 'پرامپت خروجی',
                    'type' => 'textarea',
                    'required' => true,
                    'rules' => ['required', 'string', 'max:20000'],
                    'secret' => false,
                ],
            ],
            'defaults' => [
                'endpoint' => 'https://api.gapgpt.app/v1/images/edits',
                'model' => 'gpt-image-2',
                'size' => '1024x1024',
                'timeout_seconds' => 900,
                'concurrency_limit' => 1,
            ],
        ],
    ],
];
```

قوانین کانفیگ:

- config فقط schema و default غیرحساس دارد؛ secret واقعی در config، `.env`
  مشترک، seed یا repository ذخیره نمی‌شود.
- backend validation از schema type ساخته یا به FormRequest اختصاصی type delegate
  می‌شود؛ validation فقط در JavaScript انجام نمی‌شود.
- پاسخ registry نباید provider class، rule داخلی حساس یا default secret را
  افشا کند.
- fieldهای `secret` در API و Blade فقط masked هستند.
- ارسال secret خالی در update به معنی حفظ مقدار قبلی است؛ حذف secret باید action
  صریح و مجاز داشته باشد.
- field ناشناخته در settings، credentials یا output config باید reject شود تا
  mass assignment پنهان رخ ندهد.

## نمونه‌های اجرایی مدل

مدل‌ها در جدول `ai_models` ذخیره می‌شوند.

فیلدهای پیشنهادی:

- `id`
- `ai_model_category_id`
- `type`
- `name`
- `status`: `draft`, `active`, `paused`, `degraded`, `archived`
- `priority`
- `concurrency_limit`
- `settings` با cast ساختاریافته
- `credentials` با encrypted cast
- `health_status`
- `last_health_check_at`
- `last_assigned_at`
- `created_by`
- `updated_by`
- timestamps

تنظیمات هر خروجی در `ai_model_output_configs` ذخیره می‌شود:

- `ai_model_id`
- `ai_model_category_output_id`
- `is_active`
- `configuration` شامل fieldهای output type
- timestamps

قوانین:

- unique روی `(ai_model_id, ai_model_category_output_id)` اعمال می‌شود.
- category مدل پس از داشتن generation history قابل تغییر نیست؛ برای انتقال باید
  مدل جدید ساخته شود و مدل قبلی archive شود.
- type مدل پس از استفاده قابل تغییر نیست.
- تعداد عکس‌های قابل تولید توسط یک مدل برابر تعداد output configهای فعال آن
  است. داشبورد می‌تواند ابتدا `output_count` را دریافت کند و سپس به همان تعداد
  output دسته‌بندی را برای تنظیم نمایش دهد، اما مقدار نهایی از relationها مشتق
  می‌شود و نباید به صورت counter ناسازگار ذخیره شود.
- مدل sample برای هر output فعال باید `sample_asset_id` خصوصی داشته باشد.
- مدل GapGPT برای هر output فعال باید prompt معتبر داشته باشد.
- مدل OpenAI نیز prompt هر output را از همان کاربرد می‌گیرد و تنظیم prompt
  مستقل داخل تنظیمات مدل ندارد.
- مدل بدون credential لازم، output config کامل یا health معتبر فعال نمی‌شود.
- مدل دارای job در وضعیت processing حذف نمی‌شود؛ pause مانع assignment جدید
  می‌شود و job جاری را قطع نمی‌کند.
- حذف hard مدل دارای history ممنوع است؛ archive استفاده شود.

## API مدیریت دسته‌بندی، خروجی و مدل

تمام endpointهای این بخش فقط برای کاربر system با permission لازم قابل دسترسی
هستند. نام دقیق route می‌تواند با conventions پروژه تطبیق داده شود، اما قرارداد
منطقی زیر باید حفظ شود.

### registry نوع‌ها

```text
GET /api/v1/system/ai-model-types
GET /api/v1/system/ai-model-types/{type}
```

### دسته‌بندی‌ها

```text
GET    /api/v1/system/ai-model-categories
POST   /api/v1/system/ai-model-categories
GET    /api/v1/system/ai-model-categories/{category}
PATCH  /api/v1/system/ai-model-categories/{category}
DELETE /api/v1/system/ai-model-categories/{category}
```

### خروجی‌های دسته‌بندی

```text
GET    /api/v1/system/ai-model-categories/{category}/outputs
POST   /api/v1/system/ai-model-categories/{category}/outputs
GET    /api/v1/system/ai-model-categories/{category}/outputs/{output}
PATCH  /api/v1/system/ai-model-categories/{category}/outputs/{output}
DELETE /api/v1/system/ai-model-categories/{category}/outputs/{output}
```

### مدل‌ها

```text
GET    /api/v1/system/ai-models
POST   /api/v1/system/ai-models
GET    /api/v1/system/ai-models/{model}
PATCH  /api/v1/system/ai-models/{model}
DELETE /api/v1/system/ai-models/{model}
POST   /api/v1/system/ai-models/{model}/activate
POST   /api/v1/system/ai-models/{model}/pause
POST   /api/v1/system/ai-models/{model}/test-connection
GET    /api/v1/system/ai-models/{model}/jobs
```

قواعد API:

- از API Resource برای خروجی امن و FormRequest برای validation استفاده شود.
- secretها در response حذف یا masked شوند.
- error validation فارسی و machine-readable باشد.
- عملیات چندرکوردی مدل و output config داخل transaction انجام شود.
- optimistic locking یا بررسی `updated_at` برای جلوگیری از overwrite تنظیمات
  هم‌زمان در نظر گرفته شود.
- test connection نباید secret یا raw provider response را برگرداند.
- audit log تغییرات حساس مدل، credential، activation و category ثبت شود.

## دسترسی کلینیک به دسته‌بندی‌ها

دسترسی تجاری کلینیک در سطح دسته‌بندی است، اما نمایش عمومی و اجرای فعلی باید
هم‌زمان از entitlement، وضعیت category، وضعیت پلن پایه، وضعیت مدل سیستم، اتصال
فعال مدل به کلینیک، و output config فعال عبور کند. اگر هرکدام از این شرط‌ها
غیرفعال باشد، آن کاربرد نباید در landing کلینیک دیده شود و درخواست جدید نباید
ساخته شود.

قوانین:

- `subscription_service_entitlements.service_code` باید به `ai_model_categories.code`
  یا یک foreign key پایدار به category نگاشت شود.
- landing، profile categories و generation باید فقط از مدل‌هایی استفاده کنند که
  هم رکورد مدل و هم assignment آن در `clinic_ai_model_access` فعال است.
- کلینیک یا مراجعه‌کننده نمی‌تواند مدل خاصی را انتخاب کند.
- pause یا خرابی یک مدل نباید entitlement کلینیک را حذف کند؛ تا وقتی مدل سالم
  دیگری در pool وجود دارد سرویس ادامه می‌یابد.
- اگر هیچ مدل eligible موجود نباشد، درخواست جدید با خطای localized و status
  مناسب مانند `503 Service Unavailable` رد می‌شود و اعتبار مصرف نمی‌شود.
- entitlement فعال، اعتبار مثبت و category فعال همگی برای درخواست جدید لازم‌اند.
- مدیر سیستم می‌تواند هر category/application را از صفحه کاربردها فعال یا
  غیرفعال کند. category غیرفعال برای همه کلینیک‌ها از landing حذف می‌شود، حتی
  اگر entitlement و مدل کلینیک فعال باشند.
- اگر مدل هوش مصنوعی در صفحه مدل‌ها غیرفعال شود، همه کلینیک‌هایی که فقط از آن
  مدل برای آن کاربرد استفاده می‌کنند، آن کاربرد را در landing از دست می‌دهند و
  داشبورد کلینیک باید علت را به صورت غیرفعال شدن مدل توسط مدیر سیستم نشان دهد.

## پروفایل مراجعه‌کننده و تصویر اصلی

پروفایل عمومی در محدوده `(tenant_id, lead_id)` تعریف می‌شود. یک مراجعه‌کننده در
هر کلینیک فقط یک تصویر اصلی current دارد، ولی نسخه‌های قبلی برای آرشیو همان
کلینیک حفظ می‌شوند.

پیشنهاد مدل داده:

- `lead_profiles`: یک رکورد برای هر lead tenant
- `lead_source_images`: نسخه‌های تصویر اصلی
- `generated_assets`: نسخه‌های خروجی تولیدشده

فیلدهای مهم `lead_source_images`:

- `tenant_id`
- `lead_id`
- `version`
- `is_current`
- `replaced_at`
- `disk`, `path`, `mime`, `size`, `checksum`
- `status`
- timestamps

قوانین جایگزینی تصویر اصلی:

1. upload و validation فایل جدید کامل می‌شود.
2. فایل روی private disk ذخیره و checksum ثبت می‌شود.
3. داخل transaction و با lock روی profile، تصویر current قبلی `is_current=false`
   و `replaced_at` می‌شود.
4. تصویر جدید current می‌شود.
5. خروجی‌های تصویر قبلی در نمای عمومی current نمایش داده نمی‌شوند.
6. jobهای queued تصویر قبلی به صورت کنترل‌شده cancel می‌شوند؛ job processing
   بدون قطع ناامن کامل می‌شود، اما نتیجه آن archived و غیرcurrent خواهد بود.
7. فایل قبلی بلافاصله hard delete نمی‌شود؛ retention policy و دسترسی archive
   کلینیک اعمال می‌شود.

در رابط مراجعه‌کننده «حذف تصویر قبلی» به معنی خروج از پروفایل جاری است، نه حذف
فوری تاریخچه کلینیک.

## خروجی‌های جاری و آرشیو

برای هر ترکیب زیر فقط یک نتیجه current در پروفایل عمومی وجود دارد:

```text
(tenant_id, lead_id, current_source_image_id, category_output_id)
```

قوانین:

- request تکراری برای خروجی completed و current باید همان نتیجه را برگرداند و
  job جدید نسازد.
- تولید مجدد باید action صریح مانند `regenerate` داشته باشد.
- هنگام تولید مجدد، نتیجه جدید پس از موفقیت current می‌شود و نتیجه قبلی archive
  می‌شود.
- تا قبل از موفقیت نتیجه جدید، نتیجه completed قبلی می‌تواند در UI باقی بماند
  و وضعیت «در حال تولید نسخه جدید» نمایش داده شود.
- failure نسخه جدید نباید نتیجه موفق قبلی را حذف کند.
- مراجعه‌کننده فقط profile جاری و وضعیت درخواست‌های متعلق به خود را می‌بیند.
- کلینیک با permission مناسب می‌تواند تمام نسخه‌های source image، نتایج،
  درخواست‌ها و تلاش‌ها را در archive همان tenant ببیند.
- آرشیو tenant دیگر تحت هیچ شرایطی قابل مشاهده نیست.

## API پروفایل و تولید عمومی

endpointهای عمومی فقط پس از lead verification و resolve tenant معتبر قابل
استفاده‌اند.

```text
GET  /api/v1/profile
POST /api/v1/profile/source-image
GET  /api/v1/profile/source-images/current
GET  /api/v1/profile/categories
GET  /api/v1/profile/generations
POST /api/v1/generations
GET  /api/v1/generations/{generation}
POST /api/v1/generations/{generation}/regenerate
GET  /api/v1/assets/{asset}/stream
```

payload درخواست generation فقط شامل شناسه‌های domain-safe است:

```json
{
  "source_image_id": "uuid",
  "category_id": "uuid",
  "output_id": "uuid",
  "idempotency_key": "client-generated-uuid"
}
```

قوانین:

- `source_image_id` باید current و متعلق به tenant و lead session باشد.
- category باید active و دارای entitlement فعال tenant باشد.
- output باید active و متعلق به category باشد.
- client حق ارسال `model_id`, `provider`, `prompt`, `credentials`, `tenant_id` یا
  `credit_cost` ندارد.
- `Idempotency-Key` header نیز می‌تواند جایگزین field payload شود؛ قرارداد باید
  در کل پروژه یکسان باشد.
- unique idempotency در محدوده tenant و lead اعمال شود.
- API creation باید سریع پاسخ دهد و منتظر provider نماند.
- status response باید حداقل شامل `queued`, `processing`, `completed`, `failed`,
  `cancelled`، زمان‌ها و asset امن باشد.

## تولید مستقل خروجی‌ها

جریان ثابت و زنجیره‌ای سه مرحله حذف می‌شود. هر output یک درخواست مستقل دارد.

مثال:

- اگر مراجعه‌کننده فقط `final_result` را بخواهد، فقط همان درخواست و job ساخته
  می‌شود.
- اگر بعدا `intermediate_regrowth` را بخواهد، درخواست دوم مستقل ساخته می‌شود.
- نبودن خروجی اول مانع تولید خروجی دوم یا سوم نیست.
- ترتیب کلیک کاربر، ترتیب صف درخواست‌های خودش را تعیین می‌کند، نه یک dependency
  hard-coded بین outputها.

برای هر request یک رکورد پایدار و حداقل یک attempt ساخته می‌شود. group می‌تواند
برای نمایش دسته‌ای استفاده شود، اما correctness نباید به وجود یک batch سه‌تایی
وابسته باشد.

## مدل داده تولید

نام جدول‌ها می‌تواند با ساختار موجود تطبیق یابد، اما مفهوم‌های زیر باید مستقل
باشند:

### `generation_requests`

- tenant و lead
- source image
- category و output
- status
- current result asset
- idempotency key
- requested_at, completed_at, failed_at
- safe error code/message
- credit reservation reference

### `generation_attempts`

- generation request
- assigned model
- status: `queued`, `reserved`, `dispatched`, `processing`, `completed`, `failed`,
  `cancelled`, `expired`
- queue position metadata
- provider request opaque reference
- provider call started/finished timestamps
- attempt number
- safe failure classification

### `generated_assets`

- tenant و lead
- source image
- category و output
- generation request و attempt
- version
- is_current
- private storage metadata
- checksum
- archived_at

همه foreign keyها، unique constraintها و indexهای لازم برای queryهای tenant،
lead، category، output، model و status تعریف شوند.

## انتخاب مدل و توزیع بار

برای هر generation request، سیستم فقط مدل‌های زیر را eligible می‌داند:

- category مدل با category درخواست یکسان است.
- مدل `active` است.
- health مدل اجازه assignment می‌دهد.
- credential و settings معتبر هستند.
- output config فعال برای output درخواست وجود دارد.
- مدل archived، paused یا circuit-open نیست.

هر مدل `concurrency_limit` دارد. مقدار پیش‌فرض GapGPT برابر 1 است، مگر مستندات
provider و ظرفیت خریداری‌شده مقدار بیشتری را تضمین کند.

الگوریتم assignment باید داخل transaction کوتاه و با lock انجام شود:

1. مدل‌های eligible مرتب و lock شوند.
2. بار هر مدل از attemptهای غیرنهایی محاسبه شود.
3. ابتدا مدل‌هایی انتخاب‌پذیرند که `processing/reserved < concurrency_limit`
   دارند.
4. بین مدل‌های دارای ظرفیت، اولویت عددی کمتر مقدم است؛ یعنی اولویت `10` قبل از
   `20` و `30` انتخاب می‌شود تا ظرفیت همان مدل پر شود.
5. اگر همه مدل‌ها مشغول‌اند، مدلی با کمترین تعداد attempt غیرنهایی انتخاب شود.
6. در تساوی، اولویت عددی کمتر، سپس `last_assigned_at` قدیمی‌تر و سپس شناسه
   پایدار برای tie-break استفاده شود تا starvation کاهش یابد.
7. attempt با `ai_model_id` منتخب ثبت شود.
8. `last_assigned_at` مدل به‌روزرسانی شود.

محاسبه بار و ایجاد attempt باید در یک مرز اتمیک باشند تا دو درخواست هم‌زمان هر
دو یک مدل ظاهرا خالی را بدون رعایت ظرفیت انتخاب نکنند.

## صف منطقی هر مدل

هر مدل یک صف منطقی در دیتابیس دارد، حتی اگر همه jobها روی یک queue مشترک
اجرا شوند. در استقرار cPanel بدون Redis، همین رکوردهای دیتابیس و row lockها
مرجع رزرو ظرفیت هستند و Laravel database queue فقط attemptهای دارای slot را
به worker تحویل می‌دهد.

قوانین:

- attempt در ابتدا `queued` است و به مدل مشخص assign شده است.
- فقط تا سقف `concurrency_limit` attempt هر مدل به `reserved/dispatched` می‌رود.
- `ModelQueueCoordinator` مسئول enqueue، dispatch ظرفیت‌های آزاد، آزادسازی slot
  و dispatch مورد بعدی همان مدل است.
- پس از completed/failed/cancelled شدن attempt، coordinator بلافاصله قدیمی‌ترین
  job queued همان مدل را dispatch می‌کند.
- command زمان‌بندی‌شده باید reservationهای stale، worker crash و jobهای گیرکرده
  را recover کند.
- در استقرار Redis می‌توان semaphore ثانویه داشت؛ در استقرار database-only،
  lock روی مدل و attempt باید به‌تنهایی سقف ظرفیت را enforce کند.
- queue name پویا نباید مستلزم ساخت دستی worker برای هر مدل شود؛ معماری shared
  queue با assignment و semaphore ترجیح دارد.
- Horizon tags باید حداقل tenant، category، model و generation ID را بدون PII
  ثبت کنند.

در fallback سی‌پنل، scheduler هر دقیقه یک یا چند worker window مستقل حدود ۵۸
ثانیه‌ای اجرا می‌کند. تعداد processها باید با ظرفیت CPU/RAM هاست تنظیم شود؛ هر
lane lock مستقل دارد و اجرای تکراری همان lane هم‌پوشانی نمی‌کند. فاصله بررسی job
جدید حداکثر یک ثانیه است و timeout worker باید از timeout خود job کمتر نباشد. مقدار
`retry_after` هر connection نیز باید از timeout worker بزرگ‌تر باشد تا job پولی
در زمان اجرای provider دوباره تحویل داده نشود.

پاسخ صریح rate limit مانند `429` فقط وقتی قابل retry خودکار است که provider
به‌وضوح درخواست را نپذیرفته باشد. در این حالت attempt قبلی بسته، attempt جدید با
`available_at` ساخته، مدل تا `Retry-After` در cooldown قرار داده و reservation
اعتبار حفظ می‌شود. timeout یا قطع ارتباط پس از شروع تماس outcome مبهم است و
نباید خودکار retry یا آزادسازی اعتبار شود.

## idempotency و جلوگیری از درخواست تکراری provider

- ایجاد generation باید idempotent باشد.
- قبل از تماس provider، `provider_call_started_at` و attempt state به صورت اتمیک
  ثبت می‌شوند.
- پس از شروع تماس provider واقعی، retry خودکار queue یا HTTP که ممکن است هزینه
  دوباره ایجاد کند ممنوع است.
- timeout، `retry_after` و job timeout باید هماهنگ باشند.
- failure قبل از شروع تماس provider می‌تواند به صورت امن redispatch شود.
- failure مبهم پس از شروع تماس باید `requires_review` یا failure قابل بررسی شود؛
  retry فقط با action مدیریتی و ایجاد attempt جدید انجام می‌شود.
- provider reference باید opaque باشد و raw response ذخیره یا نمایش داده نشود.

## اعتبار و رزرو مصرف

entitlement در سطح دسته‌بندی/کاربرد است، اما موجودی اعتبار در سطح کل subscription
فعال کلینیک نگهداری و مصرف می‌شود. مدل، provider یا بسته خریداری‌شده محل مصرف
اعتبار را تعیین نمی‌کند.

قبل از enqueue:

1. entitlement فعال category بررسی می‌شود تا کلینیک اجازه دیدن و درخواست آن
   کاربرد را داشته باشد.
2. هزینه output از `credit_cost` معتبر خوانده می‌شود.
3. موجودی کل اعتبار subscription فعال داخل transaction و با row lock بررسی
   می‌شود.
4. یک ledger entry از نوع `reservation` یا `use` با `balance_after` کل اعتبار
   ثبت می‌شود.

پس از نتیجه:

- در success، reservation به `consumed` تبدیل می‌شود.
- در failure قطعی قبل یا حین provider که خروجی معتبر ایجاد نکرده، reservation
  `released` می‌شود.
- در وضعیت مبهم پس از provider call، رزرو تا reconciliation باقی می‌ماند و بدون
  تصمیم صریح مصرف یا آزاد نمی‌شود.
- idempotency مانع رزرو دوباره برای یک request تکراری می‌شود.
- balance کل کلینیک منفی نمی‌شود و double-spend با transaction و row lock ممنوع
  است.

مدل sample می‌تواند بر اساس سیاست محصول هزینه صفر یا هزینه تعریف‌شده output را
داشته باشد؛ این تصمیم در category output/plan است، نه داخل provider.

## SampleImageProvider

`sample_image` یک نوع مدل local و داخلی است که مانند سایر مدل‌ها توسط مدیران
دارای مجوز قابل ایجاد و ویرایش است.

قوانین:

- هیچ credential خارجی ندارد.
- برای هر output فعال یک asset نمونه خصوصی توسط مدیر سیستم بارگذاری می‌شود.
- asset نمونه باید در storage خصوصی سیستمی نگهداری شود، نه public webroot.
- هنگام اجرا، فایل نمونه به مسیر نتیجه tenant/lead کپی می‌شود.
- provider نمونه نباید source image را به سرویس خارجی بفرستد.
- provider نمونه نباید URL خارجی را download کند.
- حذف asset نمونه‌ای که مدل فعال به آن ارجاع دارد ممنوع است.
- تعداد outputهای sample از output configهای فعال همان مدل به دست می‌آید.

مسیر پیشنهادی نتیجه:

```text
storage/app/private/tenants/{tenant_uuid}/leads/{lead_uuid}/results/{source_image_uuid}/{category_code}/{output_code}/{asset_uuid}.jpg
```

## GapGPT Provider

`gapgpt` یک نوع مدل واقعی image edit است.

تنظیمات مدل:

- API key رمزنگاری‌شده
- endpoint
- model
- size
- timeout
- concurrency limit
- تنظیمات غیرحساس مجاز تعریف‌شده در schema

تنظیم هر output:

- prompt مستقل و اجباری
- تنظیمات خروجی دیگری که در schema type تعریف شده‌اند

قوانین درخواست:

- درخواست multipart است، نه JSON.
- endpoint پیش‌فرض:
  `https://api.gapgpt.app/v1/images/edits`
- field فایل: `image`
- fieldها: `model`, `prompt`, `n = 1`, `response_format = url`, `size`
- Laravel HTTP client باید multipart boundary را بسازد.
- `Content-Type: application/json` به صورت دستی تنظیم نمی‌شود.
- API key، prompt کامل، source image bytes، private path و raw response لاگ
  نمی‌شوند.
- URL خروجی provider باید با allowlist و محدودیت اندازه/نوع download شود و سپس
  در private storage ذخیره شود؛ URL خارجی مستقیما به client داده نمی‌شود.
- test connection باید درخواست کم‌هزینه یا endpoint مناسب provider را استفاده
  کند و بدون رضایت مدیر generation هزینه‌دار اجرا نکند.

## OpenAI Provider

`openai` یک نوع مدل واقعی برای endpoint ویرایش تصویر OpenAI یا endpoint سازگار
با همان قرارداد است. تعریف type و adapter آن در کد است، اما API key و همه
تنظیمات غیرحساس هر نمونه مدل از پنل مدیریت و رکورد مدل خوانده می‌شوند.
قرارداد مرجع در
[OpenAI Images Edit API](https://developers.openai.com/api/reference/resources/images/methods/edit)
قرار دارد.

تنظیمات مدل:

- `endpoint_url` با مقدار seed پیش‌فرض
  `https://api.openai.com/v1/images/edits`
- `model` با مقدار پیش‌فرض `gpt-image-1.5`
- `quality` با سه انتخاب `low`، `medium` و `high` و پیش‌فرض `low`
- `size` قابل تنظیم با پیش‌فرض محصول `816x816`
- `output_format` با پیش‌فرض `jpeg`
- `background` با پیش‌فرض `opaque`
- `moderation` با پیش‌فرض `auto`
- `output_compression` با پیش‌فرض `70`
- timeout اتصال و پاسخ provider

قوانین درخواست:

- درخواست `multipart/form-data` است و boundary را Laravel HTTP client می‌سازد.
- هدرهای `Authorization: Bearer <encrypted api key>` و
  `Accept: application/json` ارسال می‌شوند.
- نام فیلد فایل `image[]` است.
- `prompt` فقط snapshot پرامپت output انتخاب‌شده همان کاربرد است.
- `n` همیشه `1` است و client عمومی نمی‌تواند آن یا هیچ پارامتر provider را
  تغییر دهد.
- پاسخ GPT Image از `data[0].b64_json` decode می‌شود و فقط bytes تصویر برای
  نرمال‌سازی و ذخیره خصوصی به لایه بعد تحویل داده می‌شود.
- مقدار پیش‌فرض `816x816` بنا بر قرارداد محصول قابل تنظیم نگه داشته می‌شود.
  endpoint رسمی OpenAI در مستندات فعلی فقط `auto`، `1024x1024`، `1536x1024`
  و `1024x1536` را اعلام می‌کند؛ مدیر هنگام استفاده مستقیم از endpoint رسمی
  باید `size` را به یکی از مقادیر پشتیبانی‌شده تغییر دهد.
- API key، prompt، bytes تصویر، مسیر خصوصی و raw response لاگ یا نمایش داده
  نمی‌شوند و درخواست پولی retry خودکار ندارد.

## circuit breaker و سلامت مدل

برای جلوگیری از ارسال مداوم job به مدل خراب:

- failureهای قابل انتساب به provider طبقه‌بندی شوند.
- پس از آستانه مشخص failure متوالی، مدل به `degraded` یا circuit-open برود.
- مدل circuit-open assignment جدید نمی‌گیرد.
- پس از cooldown، health check محدود یا half-open اجرا می‌شود.
- auth failure یا credential invalid باید مدل را تا اصلاح مدیر pause/degraded
  کند.
- خطای ورودی یک lead نباید سلامت کلی مدل را خراب کند.
- تغییر health و circuit state audit شود.

## آپلود و assetهای خصوصی

- upload فقط پس از verify شماره و وجود lead session معتبر مجاز است.
- فایل باید image واقعی باشد و محدودیت MIME، extension، size، dimensions و
  decompression bomb را رعایت کند.
- نام فایل کاربر برای path نهایی استفاده نمی‌شود.
- checksum برای تشخیص فایل و integrity ثبت می‌شود.
- source، sample و generated asset در disk خصوصی ذخیره می‌شوند.
- خروجی معتبر provider، مستقل از فرمت یا ابعاد اولیه، پیش از ذخیره دائمی با حفظ
  نسبت ابعاد و بدون crop به JPG با کیفیت ۸۵ تبدیل می‌شود و ضلع بزرگ‌تر آن دقیقاً
  ۸۰۰ پیکسل خواهد بود. metadata، پسوند، MIME، اندازه و checksum باید بر اساس همین
  نسخه نرمال‌شده ثبت شوند.
- storage path هرگز به client داده نمی‌شود.
- stream endpoint باید tenant، lead/session یا dashboard policy، مالکیت و status
  را بررسی کند.
- archive download کلینیک باید audit log داشته باشد.
- retention، purge و legal hold باید به صورت command/job قابل اجرا و مستند باشند.

### پردازش اختیاری تصویر ورودی مدل

هر مدل می‌تواند از پنل مدیریت، پردازش تصویر ورودی را به صورت مستقل فعال یا
غیرفعال کند. این قابلیت به provider خاصی وابسته نیست و در مسیر مشترک اجرای مدل
اعمال می‌شود.

- پیش‌فرض محصول برای مدل جدید و همه دسترسی‌های ساخته‌شده توسط seeder، حالت فعال
  با ضلع بزرگ‌تر دقیقاً ۸۰۰ پیکسل و کیفیت JPG برابر ۸۵ است.
- در حالت غیرفعال، فایل اصلی بدون تبدیل به provider تحویل داده می‌شود.
- در حالت فعال، مقدار ضلع بزرگ‌تر و کیفیت JPG هر دو الزامی هستند.
- ضلع بزرگ‌تر بین ۱ تا ۸۱۹۲ پیکسل و کیفیت بین ۱ تا ۱۰۰ معتبر است.
- تصویر با حفظ نسبت ابعاد، بدون crop و بدون دفورمه شدن scale می‌شود؛ ضلع بزرگ‌تر
  دقیقاً برابر مقدار تنظیم‌شده خواهد بود.
- همه فرمت‌های ورودی در این مسیر پیش از تحویل به provider به JPG تبدیل می‌شوند
  و کیفیت تنظیم‌شده در encode خروجی اعمال می‌شود.
- فایل اصلی upload یا source image تغییر نمی‌کند. نسخه پردازش‌شده فقط در storage
  خصوصی ساخته می‌شود و پس از پایان فراخوانی provider حذف می‌گردد.
- اگر قابلیت فعال باشد اما مقدارها یا فایل ورودی معتبر نباشند، provider نباید با
  فایل اصلی اجرا شود و اجرای مدل باید با خطای امن شکست بخورد.
- تنظیم `size = 1024x1024` در درخواست GapGPT قرارداد provider است و جایگزین
  نرمال‌سازی ورودی/خروجی خصوصی با پیش‌فرض ۸۰۰ پیکسل و کیفیت ۸۵ نیست.

## لید، CRM و آرشیو کلینیک

lead متعلق به tenant است و uniqueness شماره با `(tenant_id, phone_normalized)`
اعمال می‌شود.

صفحه lead در داشبورد کلینیک باید بخش‌های زیر را داشته باشد:

- اطلاعات lead و CRM
- تصویر اصلی current
- تاریخچه پردازش‌ها به تفکیک هر نوبت بارگذاری؛ هر نوبت باید تصویر ورودی و همه
  نتایج وابسته به همان تصویر را کنار هم نمایش دهد.
- خروجی‌های current به تفکیک دسته‌بندی
- درخواست‌های queued/processing/failed
- آرشیو تصاویر اصلی قبلی
- آرشیو همه نسخه‌های خروجی
- تاریخچه attemptها و مدل استفاده‌شده بدون credential یا diagnostics حساس

تاریخچه پردازش باید با جدیدترین نوبت بارگذاری شروع شود. تصویر current علاوه بر
حضور در تاریخچه همان نوبت، در بالای صفحه به عنوان تصویر اصلی لید نمایش داده
می‌شود. بارگذاری تصویر جدید یا تولید مجدد خروجی نباید تصویر ورودی، درخواست یا
نسخه نتیجه قبلی را از تاریخچه کلینیک حذف یا با رکورد نوبت جدید مخلوط کند. همین
نمای گروه‌بندی‌شده برای کاربران مجاز کلینیک و مدیران سیستم استفاده می‌شود.

کلینیک فقط تاریخچه tenant خود را مشاهده می‌کند. مدل استفاده‌شده می‌تواند برای
عملیات و پشتیبانی نمایش داده شود، اما prompt سیستمی، credential و raw payload
برای کاربران کلینیک مخفی است.

هر اجرای مدل که از درخواست لید ایجاد می‌شود باید در `ai_model_usage_logs` به
همان `lead_id` و `clinic_id` متصل شود. گزارش مصرف کلینیک فقط رکوردهای tenant
فعال را نمایش می‌دهد؛ مدیر سیستم در صفحه مدل می‌تواند رکوردهای همه کلینیک‌ها را
ببیند. نوع کاربرد و شماره لید در جدول نمایش داده می‌شود و لینک پرونده لید همچنان
از policy و tenant scope مسیر لید عبور می‌کند.

## اشتراک، پلن و entitlement

پلن‌ها سه نوع دارند:

- `base_subscription`
- `service_add_on`
- `credit_top_up`

قوانین پایه نسخه قبلی حفظ می‌شوند، با این تغییر که service قابل خرید باید به
category فعال نگاشت شود و هر پلن فقط یک مقدار اعتبار کل (`usage_limit`) دارد.

- entitlement دسته‌بندی تعیین می‌کند کلینیک چه گروهی از خروجی‌ها را می‌بیند.
- status خود Plan خریداری‌شده نیز runtime است؛ اگر مدیر سیستم Plan خریداری‌شده
  را غیرفعال کند، subscription پایه دیگر برای landing و درخواست عمومی active
  محسوب نمی‌شود و داشبورد کلینیک باید صریحاً نشان دهد این غیرفعال‌سازی توسط
  مدیر سیستم اعمال شده است.
- پلن پایه، سرویس افزوده و بسته افزایش اعتبار همگی اعتبار را به موجودی کل
  subscription فعال اضافه می‌کنند.
- مصرف هر output، به اندازه `credit_cost` همان output، از موجودی کل کسر می‌شود؛
  مصرف از یک بسته یا سرویس خاص انجام نمی‌شود.
- هنگام تعریف یا ویرایش پلن پایه و سرویس افزوده، مدیر سیستم باید برای هر
  category دقیقاً یک مدل انتخاب کند. مدل انتخابی باید فعال، قابل اجرا، متعلق به
  همان category و به‌تنهایی پوشش‌دهنده همه outputهای فعال باشد.
- پس از پرداخت موفق، دقیقاً مدل‌های ثبت‌شده در snapshot فاکتور به کلینیک متصل
  می‌شوند. assignmentهای دستی دیگر حذف نمی‌شوند، اما مدل‌های پلن حتی اگر قبلاً
  روی کلینیک غیرفعال بوده‌اند فعال می‌شوند.
- حذف یا pause یک مدل، entitlement را تغییر نمی‌دهد.
- top-up فقط به کلینیکی تعلق می‌گیرد که subscription پایه فعال دارد.
- credit ledger باید source خرید، category مصرف‌شده و در صورت مصرف generation
  request را reference کند.
- داشبورد کلینیک باید گردش اعتبار را در صفحه مستقل «گزارش مصرف» زیر
  «گزارش‌های من» نشان دهد: هر grant/top-up به عنوان ردیف مستقل و مصرف‌ها به
  صورت مجموع مصرف روزانه.

## پرداخت و فاکتور

جریان پرداخت و activation idempotent نسخه قبلی حفظ می‌شود. line item پلن یا
add-on باید category/service code پایدار را نگه دارد تا تغییر نام category
تاریخچه مالی را خراب نکند.

هنگام صدور فاکتور، شناسه مدل‌های انتخاب‌شده پلن در metadata فاکتور snapshot
می‌شود. پیش از صدور درخواست درگاه، همان snapshot باید دوباره از نظر وضعیت مدل،
اعتبار، category و پوشش outputها اعتبارسنجی شود؛ در غیر این صورت خرید پیش از
پرداخت با پیام محلی‌سازی‌شده متوقف می‌شود. activation موفق باید entitlement،
اعتبار و assignment همان مدل‌ها را در یک transaction و فقط یک‌بار ایجاد کند.
callback تکراری نباید subscription یا assignment تکراری بسازد و top-up نباید
assignment مدل را تغییر دهد.

credential درگاه، authority کامل، raw callback و stack trace نباید در UI یا
JSON نمایش داده شود.

## داشبورد مدیریت سیستم

داشبورد سیستم باید بخش‌های زیر را اضافه یا بازطراحی کند:

### دسته‌بندی مدل‌ها

- صفحه list باید با آیکون اختصاصی کاربردها و ردیف‌های جدول استاندارد داشبورد
  نمایش داده شود، نه cardهای مستقل.
- list باید search/filter/sort/pagination داشته باشد.
- هر ردیف باید actionهای مجاز مشاهده، ویرایش، آرشیو و حذف را در action menu
  استاندارد داشته باشد.
- کاربردی که هیچ استفاده عملیاتی ندارد فقط قابل حذف است.
- کاربردی که در مدل، پلن، subscription، ledger یا درخواست تولید استفاده شده فقط
  قابل آرشیو است و حذف مستقیم ندارد.
- شمارنده خروجی‌ها باید از `ai_model_category_outputs` و شمارنده مدل‌ها باید از
  مجموع مدل‌های v2 (`ai_models`) و دسترسی‌های legacy (`ai_model_accesses`) همان
  code محاسبه شود.
- مشاهده کاربرد باید خروجی‌های دسته‌بندی، پرامپت خروجی، هزینه اعتبار و وضعیت را
  فقط خواندنی نشان دهد.
- مشاهده کاربرد نباید modal باشد؛ صفحه مشاهده فقط خواندنی است.
- مدیریت خروجی‌ها باید صفحه جداگانه داشته باشد و هر خروجی در جدول با action menu
  و فرم‌های دارای placeholder مدیریت شود. صفحه خروجی‌ها باید نام، کد و وضعیت
  کاربرد والد را واضح نمایش دهد.

### پلن‌ها و اعتبار

- ردیف پلن‌ها باید با action مشاهده و row-click به صفحه مشاهده پلن برود.
- هر پلن فقط یک فیلد اعتبار کل دارد.
- هر پلن، از جمله پلن پایه، می‌تواند به یک یا چند کاربرد/category تخصیص داده
  شود.
- انتخاب کاربردها در فرم ایجاد و ویرایش پلن باید به صورت گزینه‌های خوانا و
  چندانتخابی با نام و کد فنی کاربرد باشد.
- برای هر کاربرد پلن پایه یا سرویس افزوده، انتخاب دقیقاً یک مدل قابل اجرا الزامی
  است؛ مدل باید به‌تنهایی همه خروجی‌های فعال همان کاربرد را پوشش دهد و مدل‌های
  نامرتبط با کاربرد نباید پذیرفته شوند. بسته top-up مدل ندارد.
- صفحه مشاهده پلن باید نوع پلن، اعتبار کل، قیمت، مدت، وضعیت و کاربردهای
  تخصیص‌یافته و مدل دقیق هر کاربرد را خواندنی نشان دهد.
- موجودی داشبورد و billing باید از ledger کل subscription فعال محاسبه شود، نه
  از جمع مانده‌های جداگانه سرویس‌ها.

### تنظیمات سیستم

- گروه مستقل «مدیریت سیستم» در منوی پنل نمایش داده نمی‌شود. کلینیک‌ها، قالب‌ها،
  اعلان‌ها، کاربردهای هوش مصنوعی، مدل‌های هوش مصنوعی، درگاه‌های پیامک و تنظیمات
  runtime همگی با رعایت permission زیر گروه «تنظیمات» قرار می‌گیرند.
- گروه‌های دسترسی و پرداخت به‌ترتیب با عنوان‌های «امنیت» و «مالی» نمایش داده
  می‌شوند. نقش، مجوز، کاربرد AI، مدل AI و هر نوع گزارش باید آیکون محلی و متمایز
  داشته باشند.
- URL صفحات پنل باید ساختار منو را منعکس کند: منابع عملیاتی زیر `crm`، کاربران و
  نقش‌ها و مجوزها زیر `security`، پلن‌ها و پرداخت‌ها زیر `finance`، گزارش‌ها زیر
  `reports` و منابع مدیریتی سیستم زیر `settings` قرار می‌گیرند. کلیدهای فنی
  permission برای حفظ سازگاری نقش‌های موجود تغییر نمی‌کنند.
- منو، عنوان گروه در topbar و breadcrumb باید از کاتالوگ ناوبری مشترک استفاده
  کنند. breadcrumb شامل گروه، آیتم منو و عنوان صفحه است و آیکون هر آیتم را از
  همان تعریف منو می‌گیرد.
- صفحه مجوزها باید همان گروه‌های فارسی منو را نشان دهد و نام فارسی canonical هر
  مجوز را کنار کلید انگلیسی پایدار آن نمایش دهد.
- عنوان «نقش‌های مدیریتی» برای نقش ادمین‌ها و مدیران سیستم استفاده می‌شود. در
  فرم ایجاد و ویرایش کاربر، نام فارسی هر نقش همراه کد انگلیسی آن نمایش داده
  می‌شود.
- صفحه تنظیمات سیستم باید فقط تنظیمات runtime قابل مدیریت را با کنترل مناسب نوع
  داده نمایش دهد؛ مانند input عددی و select بولی.
- تنظیمات اثرگذار فعلی: مهلت پرداخت فاکتور، تعداد پیش‌فرض و حداکثر صفحه‌بندی،
  نام سیستم و شماره پشتیبانی.
- ذخیره تنظیمات باید cache تنظیمات را پاک کند و منطق runtime از مقدار جدید
  استفاده کند.
- کاتالوگ وضعیت‌ها قابل ویرایش از داشبورد یا ذخیره در `system_settings` نیست.
  کدهای مجاز و نگاشت کلید ترجمه آن‌ها به صورت سراسری در
  `config/state-catalog.php` تعریف می‌شوند و عنوان‌های فارسی در `lang/fa` باقی
  می‌مانند. فرم‌ها و validation باید از `App\Support\StateCatalog` استفاده کنند.
- registry نوع مدل در `config/ai-models.php` و کاربردهای AI در جداول category
  مرجع مستقل خود را دارند و نباید داخل کاتالوگ وضعیت‌ها تکرار شوند.

### مدل‌های هوش مصنوعی

فرم create/edit به ترتیب زیر عمل می‌کند:

1. انتخاب دسته‌بندی
2. انتخاب type از registry کانفیگ
3. وارد کردن نام و ظرفیت
4. رندر پویا و server-driven تنظیمات type
5. تعیین تعداد outputهای پشتیبانی‌شده یا انتخاب outputهای دسته‌بندی
6. رندر fieldهای تکرارشونده هر output
7. برای sample، upload تصویر نمونه هر output
8. برای GapGPT و OpenAI، prompt هر output و تنظیمات API
9. ذخیره امن credential
10. test connection/validation
11. activation پس از کامل بودن تنظیمات

نمایش مدل:

- category و type
- status و health
- concurrency limit
- تعداد queued/reserved/processing
- آخرین assignment و آخرین health check
- outputهای پشتیبانی‌شده
- credential فقط masked
- actionهای pause، activate، test و archive با permission

### مانیتور صف مدل

- فیلتر بر اساس category، model، tenant و status
- تعداد jobهای هر مدل
- زمان انتظار قدیمی‌ترین job
- attempts stale یا requires review
- retry/cancel کنترل‌شده با audit log
- عدم نمایش PII یا تصویر در log summary مگر از route خصوصی مجاز

## داشبورد کلینیک

کاربر کلینیک با permission مناسب باید بتواند:

- lead و پروفایل current را ببیند.
- خروجی‌های current را به تفکیک category ببیند.
- وضعیت jobهای مراجعه‌کننده را ببیند.
- source image و generated assetهای آرشیوی tenant خود را مشاهده کند.
- تاریخچه replacement و regeneration را ببیند.

کاربر کلینیک حق انتخاب مدل، تغییر prompt، دیدن credential یا تغییر صف سیستم را
ندارد.

### راهنمای کارکنان کلینیک

- منوی داشبورد کاربران کلینیک یک گروه مستقل «مرکز راهنما» با آیکون حلقه نجات
  دارد. زیرمنوهای «راهنمای کاربری» با آیکون کتاب‌خوان و «پرسش‌های متداول» با
  آیکون پرسش، به‌ترتیب در `/admin/help/guide` و `/admin/help/faq` قرار دارند و
  برای مدیر کلینیک، ویرایشگر و مشاهده‌گر قابل استفاده‌اند.
- متن این دو صفحه فقط برای کارهای کلینیک و کاربران آن نوشته می‌شود و نباید
  دستورالعمل امکانات داشبورد مدیریت اصلی HairVision را در خود داشته باشد.
- دسترسی به راهنما به فعال بودن پلن وابسته نیست تا کاربر در زمان پایان پلن نیز
  بتواند راهنمای پرداخت، دسترسی و رفع اشکال را بخواند.
- بالای هر صفحه یک فهرست طبقه‌بندی‌شده و قابل دسترس قرار دارد. فهرست راهنما به
  همه بخش‌ها و صفحه FAQ و فهرست FAQ به هر پرسش لینک مستقیم می‌دهد. هر بخش
  توضیح، مخاطب اختیاری و گام‌های مرتب دارد.
- پرسش‌های متداول با عنصر native و دسترس‌پذیر `details/summary` به صورت آکاردئون
  نمایش داده می‌شوند و استفاده از آن‌ها به JavaScript وابسته نیست. باز و بسته
  شدن پنل و چرخش آیکون transition کوتاه و نرم دارد.
- اسکرول لینک‌های داخلی در کل سایت نرم است، offset هدف‌ها رعایت می‌شود و در
  صورت فعال بودن `prefers-reduced-motion` اسکرول و transition به حالت بدون
  حرکت برمی‌گردند.
- در داشبورد، body به viewport ثابت است و fragment navigation فقط
  `admin-content` را جابه‌جا می‌کند. اسکرول خودکار اولیه مرورگر، restoration
  ریشه و scrollIntoView نباید بتوانند topbar را از صفحه خارج یا محتوای خارج از
  shell را نمایان کنند؛ این قرارداد برای لینک مستقیم، reload، back/forward و
  باز و بسته‌شدن modal یکسان است.
- محتوای راهنما system-owned است و `tenant_id` ندارد. اطلاعات صفحه‌ها در
  `guide_pages`، طبقه‌بندی‌ها در `guide_sections`، گام‌ها در `guide_steps` و
  پرسش‌ها در `guide_faqs` نگهداری می‌شوند تا هر آیتم مستقل و امن ویرایش شود.
- migration باید محتوای موجود `guide.content` را بدون حذف داده به رکوردهای
  رابطه‌ای منتقل و سپس setting قدیمی را حذف کند. seeder فقط رکوردهای گمشده را
  ایجاد می‌کند و ویرایش موجود مدیر سیستم را در reseed بازنویسی نمی‌کند.
- مدیر سیستم دارای permission پایدار `guide.manage` می‌تواند از مسیر
  `/admin/settings/guide` عنوان، مقدمه، بخش‌ها، ترتیب بخش‌ها، گام‌ها و مخاطب‌ها
  و از صفحه مستقل `/admin/settings/faqs` اطلاعات صفحه FAQ و پرسش‌ها را مدیریت
  کند. منوی تنظیمات برای این دو صفحه عنوان و آیکون متفاوت دارد.
- هر فرم مدیریتی فقط یک صفحه، بخش، گام یا پرسش را تغییر می‌دهد. افزودن گام از
  همان کارت بخش انجام می‌شود و ویرایشگر کامل فقط برای آیتم انتخاب‌شده بارگذاری
  می‌شود تا فرم تو‌در‌تو یا بازنویسی ناخواسته محتوای سایر آیتم‌ها رخ ندهد.
- عنوان‌ها، مخاطب‌ها و پرسش‌ها plain text و در Blade escaped هستند. مقدمه،
  توضیح بخش، بدنه گام و پاسخ FAQ با ویرایشگر دیداری WYSIWYG و نمای HTML قابل
  ویرایش‌اند تا محتوای HTML قبلی نیز راحت اصلاح شود.
- همه فیلدهای rich text پیش از ذخیره و رندر با allowlist محدود
  `SimpleRichTextSanitizer` پاک‌سازی می‌شوند. فقط قالب‌بندی متنی و لینک‌های امن
  نگه داشته می‌شود و script، handler رویداد، محتوای embed و scheme ناامن حذف
  می‌شوند؛ بنابراین دسترسی ویرایش محتوا مجوز انتشار کد اجرایی نیست.
- ویرایشگر دیداری باید selection را هنگام استفاده از toolbar حفظ کند، وضعیت
  ابزارهای فعال را دسترس‌پذیر نشان دهد، افزودن لینک را بدون prompt مرورگر انجام
  دهد، paste و HTML منبع را پاک‌سازی کند و پیش از submit با textarea اصلی
  همگام باشد. قالب‌های inline تولیدشده توسط مرورگر باید به `strong`، `em` و
  `u` معنایی تبدیل شوند؛ textarea نام‌دار یگانه منبع submit و fallback قابل
  ویرایش در صورت اجرا نشدن JavaScript است.
- محتوای ساختاریافته راهنما در صفحه عمومی تنظیمات سیستم به شکل JSON خام نمایش
  داده نمی‌شود و فقط از ویرایشگر اختصاصی مدیریت می‌شود.

### تم داشبورد مدیریت

- انتخاب حالت روشن یا تیره در مرورگر ادمین ذخیره می‌شود و باید پیش از بارگذاری
  stylesheet اعمال شود تا بازگشت کاربر به پنل باعث نمایش ترکیبی سطوح روشن و
  تیره نشود.
- رنگ‌های برند در هر دو حالت از دو انتهای لوگو، بنفش `#8B5CF6` و فیروزه‌ای
  `#14B8A6`، مشتق می‌شوند و باید بدون غالب شدن یکی از آن‌ها در focus، حالت فعال
  و کنش‌های اصلی حضور متوازن داشته باشند. gradient کنش اصلی باید از مشتق تیره‌تر
  همین دو رنگ استفاده کند تا کنتراست متن سفید حفظ شود.
- حالت تیره از سطوح ذغالی خنثی با اثر بسیار محدود و متوازن بنفش/فیروزه‌ای
  استفاده می‌کند؛ رنگ سبز یا سرمه‌ای غالب، خاکستری کاملاً بی‌رنگ و accentهای
  اشباع‌شده برای سطوح اصلی مجاز نیستند.
- کارت، جدول، فرم، modal، منو، badge، وضعیت disabled و همه حالت‌های responsive
  باید از یک منبع تم مشترک پیروی کنند و کنتراست متن، focus و وضعیت‌ها حفظ شود.
- لوگوی پنل در حالت تیره به نسخه مخصوص تیره تغییر می‌کند. کنترل تغییر تم و هر دو
  نسخه لوگو در صفحه ورود و تایید OTP داشبورد نیز در دسترس هستند.
- مسیر سایت نباید خط جداکننده زیرین یا underline متحرک زیر لینک‌ها نمایش دهد.
- مسیر سایت padding داخلی در بالا یا پایین ندارد. فاصله آن از محتوای بعدی فقط
  با gap بیرونی صفحه کنترل می‌شود.
- ناحیه اصلی `admin-content` در همه جهات و همه اندازه‌های صفحه `20px` padding
  دارد. نخستین و آخرین `th` در ردیف عنوان جدول باید گردی منطقی گوشه‌های بالایی
  متناسب با container جدول داشته باشند.
- این تم فقط متعلق به داشبورد و احراز هویت ادمین است و نباید ظاهر landing،
  قالب عمومی یا تجربه مراجعه‌کننده را تغییر دهد.

## رفتار رابط عمومی

- تعداد کارت‌های خروجی از API می‌آید.
- هر کارت action مستقل «تولید تصویر» دارد.
- کلیک روی خروجی سوم نیازمند تولید خروجی اول یا دوم نیست.
- status هر کارت مستقل است.
- refresh یا ترک صفحه باعث از بین رفتن status نمی‌شود.
- هنگام بازگشت، profile و generation list از سرور hydrate می‌شوند.
- اگر نتیجه قبلی موجود و regeneration در حال اجرا باشد، نتیجه قبلی همراه با
  indicator نمایش داده می‌شود.
- upload تصویر جدید باید قبل از جایگزینی، اثر آن بر jobهای جاری و خروجی‌های
  فعلی را به صورت واضح به کاربر اعلام کند.
- frontend نباید تعداد سه، نام stageها یا provider را hard code کند.

## تاریخ، عدد و زبان

قوانین زبان فارسی، RTL، NumberNormalizer و JalaliDate نسخه قبلی حفظ می‌شوند.
همه ورودی‌های عددی باید ارقام فارسی، عربی و لاتین را بپذیرند؛ فیلدهای
مقداری و مالی در داشبورد هنگام ورود با جداکننده هزارگان نمایش داده
می‌شوند، اما backend با `App\Support\NumberNormalizer` جداکننده‌ها را حذف و مقدار را با
ارقام لاتین پیش از validation و ذخیره‌سازی نرمال می‌کند. این رفتار نباید
مقادیر حساس مانند credential، token یا password را تغییر دهد.
کلیدهای فنی مانند category code، output code، provider type، route name و status
انگلیسی و پایدار می‌مانند.

## امنیت، خطا، لاگ و audit

اولویت‌ها:

- جلوگیری از نشت cross-tenant
- حفاظت از OTP، session، credential و prompt سیستمی
- خصوصی ماندن source، sample و result
- جلوگیری از duplicate provider call و duplicate charge
- جلوگیری از oversubscription ظرفیت مدل و اعتبار
- audit تغییرات مدل و عملیات آرشیو

نباید لاگ شوند:

- OTP
- API key و credential
- bearer token و cookie
- source image bytes
- private storage path کامل
- prompt کامل در log عمومی
- raw provider response
- URL امضاشده کامل
- شماره موبایل plain در log عملیاتی
- stack trace در response

لاگ امن می‌تواند شامل UUIDهای opaque، category code، output code، model ID،
status، duration، safe error class و correlation ID باشد.

## migration از نسخه 1.0.0

تغییر باید بدون حذف تاریخچه و به صورت مرحله‌ای انجام شود.

### مرحله 1: schema افزایشی

- ساخت `ai_model_categories`
- ساخت `ai_model_category_outputs`
- ساخت/تطبیق `ai_models`
- ساخت `ai_model_output_configs`
- ساخت/تطبیق `generation_requests`
- ساخت `generation_attempts`
- افزودن version/current/archive به source و generated assets
- افزودن reservation state به credit ledger در صورت نبود

### مرحله 2: seed و backfill

- ساخت category کاشت مو و کاشت ابرو از usageهای موجود
- ساخت outputهای `two_weeks`, `intermediate_regrowth`, `final_result` برای کاشت
  مو
- تبدیل هر `ai_model_access` فعلی به یک `ai_model` اجرایی
- انتقال encrypted credential بدون decrypt/re-encrypt غیرضروری در log
- ساخت output configهای مدل‌های موجود
- نگاشت sample resultهای فعلی به private sample assets
- نگاشت entitlementهای service به category
- نگاشت generation group/job/assetهای قبلی به request/output/attempt جدید

### مرحله 3: سازگاری و cutover

- feature flag برای flow جدید
- dual-read محدود فقط در دوره migration در صورت نیاز
- توقف write به ساختار legacy پس از صحت backfill
- اجرای integrity command و گزارش orphanها
- فعال‌سازی flow جدید برای tenant آزمایشی و سپس rollout کامل

### مرحله 4: cleanup

- حذف route و service legacy فقط بعد از تایید داده و تست rollback
- جدول‌ها یا ستون‌های legacy دارای history ابتدا deprecated و سپس در release
  جداگانه حذف شوند.
- migration destructive در همان release cutover انجام نشود.

## تست و کیفیت

هر تغییر رفتاری باید تست داشته باشد. حداقل پوشش نسخه 2.0.0:

### دسته‌بندی و مدل

- CRUD و authorization دسته‌بندی
- code immutability پس از استفاده
- delete conflict برای category/output/model دارای history
- CRUD خروجی دسته‌بندی
- registry type از config
- dynamic validation برای sample و GapGPT
- reject fieldهای ناشناخته
- encrypted credential و masking
- حفظ secret در update خالی
- activation فقط با config کامل
- tenant user نتواند model/category مدیریت کند

### entitlement

- کلینیک دارای category به همه مدل‌های eligible و فعال pool تخصیص‌یافته خود
  دسترسی اجرایی دارد.
- کلینیک بدون entitlement درخواست generation نمی‌سازد.
- category غیرفعال‌شده توسط مدیر سیستم از landing همه کلینیک‌ها حذف می‌شود.
- مدل غیرفعال‌شده توسط مدیر سیستم، کاربردهای وابسته همان کلینیک‌ها را از
  landing حذف می‌کند و در داشبورد کلینیک علت مشخص می‌شود.
- Plan خریداری‌شده غیرفعال‌شده توسط مدیر سیستم، کاربردهای عمومی آن subscription
  را پنهان می‌کند و در داشبورد کلینیک علت مشخص می‌شود.
- pause یک مدل entitlement را حذف نمی‌کند.
- نبود مدل eligible خطای امن و بدون مصرف اعتبار می‌دهد.

### generation مستقل

- درخواست مستقیم خروجی سوم بدون خروجی اول و دوم
- درخواست خروجی‌ها با ترتیب دلخواه
- idempotency درخواست تکراری
- regenerate نسخه جدید و archive نسخه قبلی
- failure regenerate نتیجه موفق قبلی را حفظ می‌کند.
- ترک session/page و بازیابی status در ورود بعدی
- scoping کامل tenant و lead

### انتخاب مدل و queue

- انتخاب مدل idle
- انتخاب کم‌بارترین مدل وقتی همه مشغول‌اند
- tie-break منصفانه
- رعایت concurrency limit در درخواست هم‌زمان
- صف منطقی مستقل مدل
- dispatch job بعدی پس از آزاد شدن slot
- recovery reservation stale
- pause مدل مانع assignment جدید و نه job جاری
- مدل فاقد output config eligible نباشد.
- circuit breaker و health state

### اعتبار

- reservation اتمیک
- جلوگیری از double reservation با idempotency
- consume در success
- release در failure قطعی
- حفظ reservation در outcome مبهم
- جلوگیری از balance منفی در concurrency

### پروفایل و آرشیو

- فقط یک source current برای lead tenant
- upload جدید source قبلی را archive کند.
- job queued source قبلی cancel شود.
- نتیجه job processing قبلی current نشود.
- current result unique برای source/category/output
- کلینیک archive tenant خود را ببیند.
- مراجعه‌کننده و tenant دیگر archive را نبینند.
- همان شماره در دو tenant پروفایل مستقل داشته باشد.

### providerها

- sample هیچ network call نداشته باشد.
- sample asset خصوصی و scoped کپی شود.
- GapGPT multipart صحیح باشد.
- OpenAI multipart با `image[]` و پارامترهای تنظیم‌شده صحیح باشد و خروجی
  `b64_json` را decode کند.
- prompt درست output منتخب استفاده شود.
- no automatic retry پس از provider call started
- download URL provider validate و خصوصی ذخیره شود.
- secret و raw response در log/response نباشد.

### regression

- tenant resolution
- OTP hash/rate limit/digit normalization
- session cookie separation
- CRM isolation
- dashboard policy
- billing و invoice idempotency
- Jalali و number normalization
- responsive component contracts
- مشاهده راهنما برای همه نقش‌های کلینیک و بدون پلن فعال
- مدیریت permission-backed محتوای راهنما، WYSIWYG، allowlist HTML و safe rendering

دستورهای اصلی:

```bash
php artisan test
```

در صورت تغییر frontend، Blade، CSS، JavaScript، font یا Vite:

```bash
npm run build
```

در صورت وجود static analysis و formatter پروژه، اجرای آن‌ها نیز الزامی است؛
برای مثال:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
```

نام دقیق command باید از `composer.json` پروژه خوانده شود و command فرضی به CI
تحمیل نشود.

## مستندات الزامی

علاوه بر به‌روزرسانی همین سند، حداقل مستندات زیر ایجاد یا اصلاح شوند:

- معماری دسته‌بندی، type و model instance
- schema کانفیگ type جدید
- قرارداد API و OpenAPI
- state machine generation request و attempt
- الگوریتم انتخاب مدل و queue coordinator
- credit reservation/reconciliation
- سیاست private asset و archive/retention
- راهنمای افزودن provider جدید
- runbook مدل degraded، queue stuck و outcome مبهم
- migration و rollback نسخه 2.0.0
- تغییرات dashboard و public profile

## روش نسخه‌گذاری تغییرات

- هر تغییر ابتدا زیر عنوان `Unreleased` در `CHANGELOG.md` ثبت می‌شود.
- تا وقتی نسخه رسمی برای تغییر تعیین نشده است، نباید به صورت گذشته‌نگر زیر
  یک نسخه منتشرشده قرار گیرد.
- هنگام آماده‌سازی انتشار، موارد مربوط از `Unreleased` به بخش نسخه جدید منتقل
  و شماره نسخه به صورت هماهنگ در metadata برنامه و اسناد مرجع ثبت می‌شود.
- نسخه باید همه تغییرات رفتاری، اصلاح خطا، migration، تست و مستندات همان
  انتشار، از جمله تغییرات محلی مرتبط و هنوز کامیت‌نشده، را صریحاً فهرست کند.
- اعلان انتشار فقط پس از موفقیت migration، تست کامل، PHPDoc audit و build
  frontend انجام می‌شود.

## معیار پذیرش نهایی

تغییر زمانی کامل است که:

1. system admin بتواند category و outputهای آن را از API و dashboard CRUD کند.
2. system admin بتواند مدل sample یا GapGPT را با فرم مبتنی بر config بسازد.
3. credentialها encrypted و masked باشند.
4. entitlement کلینیک در سطح category اعمال شود و حداقل یک assignment فعال مدل
   توسط مدیر سیستم برای کلینیک وجود داشته باشد.
5. مراجعه‌کننده بتواند هر output را مستقل و با ترتیب دلخواه درخواست کند.
6. چند مدل یک category با الگوریتم کم‌بارترین مدل استفاده شوند.
7. concurrency limit حتی زیر درخواست هم‌زمان نقض نشود.
8. درخواست پس از ترک صفحه persisted و در ورود بعدی قابل مشاهده باشد.
9. هر lead در هر tenant یک source image current داشته باشد.
10. source و resultهای قبلی برای کلینیک archive و برای tenant دیگر غیرقابل دسترس
    باشند.
11. migration تاریخچه نسخه قبلی را حذف نکند.
12. تمام تست‌ها، build و checks پروژه موفق باشند.
13. مستندات و OpenAPI با رفتار نهایی کد منطبق باشند.

## قانون تصمیم‌گیری برای توسعه بعدی

قبل از هر تغییر بپرسید:

1. داده system-owned است یا tenant-owned؟
2. category، type، model instance، output، request و attempt با هم اشتباه نشده‌اند؟
3. آیا tenant فقط از host/context مجاز resolve شده است؟
4. آیا lead و asset با tenant و session جاری scope شده‌اند؟
5. آیا client در حال انتخاب model/provider/prompt نیست؟
6. آیا credential، OTP، prompt، private path یا raw provider payload نشت نمی‌کند؟
7. آیا ظرفیت مدل و اعتبار به صورت اتمیک رزرو شده‌اند؟
8. آیا provider call ممکن است تکراری و هزینه‌زا شود؟
9. آیا خروجی current و archive با versioning صحیح مدیریت می‌شوند؟
10. آیا رفتار در چند tenant با یک شماره موبایل ایزوله باقی می‌ماند؟
11. آیا API، تست، migration، rollback و documentation به‌روزرسانی شده‌اند؟
