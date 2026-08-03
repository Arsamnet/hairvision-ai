<?php

namespace App\Support;

/**
 * Provides safe starter photo guidance for AI application upload flows.
 *
 * @since 2.1.0
 */
final class AiModelCategoryUploadGuideDefaults
{
    /**
     * Return the category-specific guidance shipped with seeded applications.
     *
     * @return array<string, string>
     *
     * @since 2.1.0
     */
    public static function seeded(): array
    {
        return [
            'hair_transplant' => <<<'HTML'
<p><strong>برای شبیه‌سازی دقیق‌تر کاشت مو:</strong></p>
<ul>
<li>صورت، خط رویش و بالای سر کاملاً داخل کادر و رو به دوربین باشد.</li>
<li>موها را کنار بزنید تا خط رویش و نواحی کم‌پشت به‌وضوح دیده شوند.</li>
<li>از نور طبیعی و یکنواخت استفاده کنید؛ عکس تاریک، ضدنور یا دارای سایه شدید نباشد.</li>
<li>عکس واضح، جدید و بدون فیلتر زیبایی، کلاه یا پوشش روی سر باشد.</li>
<li>فقط یک نفر در تصویر باشد و حالت صورت طبیعی و بدون چرخش شدید بماند.</li>
</ul>
<p><strong>پیشنهاد:</strong> دوربین را هم‌سطح صورت نگه دارید و از فاصله‌ای عکس بگیرید که چهره و فرم کلی سر بریده نشوند.</p>
HTML,
            'eyebrow_transplant' => <<<'HTML'
<p><strong>برای شبیه‌سازی دقیق‌تر کاشت ابرو:</strong></p>
<ul>
<li>صورت کامل و هر دو ابرو، مستقیم و هم‌سطح دوربین در کادر باشند.</li>
<li>موها را از اطراف ابرو کنار بزنید و در صورت امکان عینک را بردارید.</li>
<li>ابروها بدون مداد، سایه، ژل رنگی یا آرایش پوشاننده ثبت شوند.</li>
<li>نور طبیعی و یکنواخت باشد و عکس بدون تاری، فیلتر زیبایی یا روتوش گرفته شود.</li>
<li>فقط یک نفر با حالت چهره خنثی در تصویر باشد.</li>
</ul>
<p><strong>پیشنهاد:</strong> دوربین را روبه‌روی صورت نگه دارید تا فرم و تقارن طبیعی هر دو ابرو قابل مشاهده باشد.</p>
HTML,
        ];
    }

    /**
     * Resolve seeded or generic photo guidance for one application.
     *
     * @param  string  $code  Stable application code.
     * @param  string|null  $name  Public application name.
     * @return string Safe starter rich-text HTML.
     *
     * @since 2.1.0
     */
    public static function forCategory(string $code, ?string $name = null): string
    {
        $seeded = self::seeded();

        if (isset($seeded[$code])) {
            return $seeded[$code];
        }

        $safeName = htmlspecialchars(trim((string) $name), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $subject = $safeName !== '' ? $safeName : 'این کاربرد';

        return '<p><strong>راهنمای عکس مناسب برای '.$subject.':</strong></p>'
            .'<ul>'
            .'<li>صورت و ناحیه مورد درمان کاملاً داخل کادر و رو به دوربین باشند.</li>'
            .'<li>عکس واضح، جدید و با نور طبیعی و یکنواخت گرفته شود.</li>'
            .'<li>از فیلتر زیبایی، روتوش و پوشاندن ناحیه مورد درمان خودداری کنید.</li>'
            .'<li>فقط یک نفر در تصویر باشد و چهره چرخش شدید نداشته باشد.</li>'
            .'</ul>';
    }
}
