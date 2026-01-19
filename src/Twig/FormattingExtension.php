<?php

namespace App\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension для форматирования дат и чисел с учётом локали
 * Requirements: 8.1, 8.2, 8.3
 */
class FormattingExtension extends AbstractExtension
{
    /**
     * Конфигурация форматов дат для локалей
     */
    private const DATE_FORMATS = [
        'en' => [
            'short' => 'M j, Y',           // Dec 27, 2025
            'medium' => 'F j, Y',          // December 27, 2025
            'long' => 'l, F j, Y',         // Saturday, December 27, 2025
            'datetime' => 'M j, Y g:i A',  // Dec 27, 2025 3:45 PM
        ],
        'ru' => [
            'short' => 'd.m.Y',            // 27.12.2025
            'medium' => 'j F Y',           // 27 декабря 2025
            'long' => 'l, j F Y',          // суббота, 27 декабря 2025
            'datetime' => 'd.m.Y H:i',     // 27.12.2025 15:45
        ],
    ];

    /**
     * Названия месяцев на русском (родительный падеж)
     */
    private const RUSSIAN_MONTHS = [
        1 => 'января', 2 => 'февраля', 3 => 'марта',
        4 => 'апреля', 5 => 'мая', 6 => 'июня',
        7 => 'июля', 8 => 'августа', 9 => 'сентября',
        10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];

    /**
     * Названия дней недели на русском
     */
    private const RUSSIAN_WEEKDAYS = [
        'Monday' => 'понедельник',
        'Tuesday' => 'вторник',
        'Wednesday' => 'среда',
        'Thursday' => 'четверг',
        'Friday' => 'пятница',
        'Saturday' => 'суббота',
        'Sunday' => 'воскресенье',
    ];

    public function __construct(
        private RequestStack $requestStack,
        private string $defaultLocale
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('locale_date', [$this, 'formatDate']),
            new TwigFilter('locale_number', [$this, 'formatNumber']),
            new TwigFilter('time_ago', [$this, 'formatTimeAgo']),
            new TwigFilter('country_flag', [$this, 'getCountryFlag']),
        ];
    }

    /**
     * Конвертирует название страны в код ISO для использования с flagcdn
     * 
     * @param string|null $country Название страны или код страны
     * @return string Код страны в нижнем регистре или пустая строка
     */
    public function getCountryFlag(?string $country): string
    {
        if ($country === null || $country === '') {
            return '';
        }

        // Маппинг названий стран на коды ISO 3166-1 alpha-2
        $countryMap = [
            // English names
            'usa' => 'us', 'united states' => 'us', 'america' => 'us',
            'uk' => 'gb', 'united kingdom' => 'gb', 'england' => 'gb', 'britain' => 'gb',
            'russia' => 'ru', 'russian federation' => 'ru',
            'germany' => 'de', 'deutschland' => 'de',
            'france' => 'fr',
            'italy' => 'it', 'italia' => 'it',
            'spain' => 'es', 'españa' => 'es',
            'japan' => 'jp', 'nippon' => 'jp',
            'china' => 'cn',
            'brazil' => 'br', 'brasil' => 'br',
            'canada' => 'ca',
            'australia' => 'au',
            'mexico' => 'mx', 'méxico' => 'mx',
            'netherlands' => 'nl', 'holland' => 'nl',
            'poland' => 'pl', 'polska' => 'pl',
            'ukraine' => 'ua', 'україна' => 'ua',
            'czech republic' => 'cz', 'czechia' => 'cz',
            'hungary' => 'hu', 'magyarország' => 'hu',
            'romania' => 'ro', 'românia' => 'ro',
            'sweden' => 'se', 'sverige' => 'se',
            'norway' => 'no', 'norge' => 'no',
            'denmark' => 'dk', 'danmark' => 'dk',
            'finland' => 'fi', 'suomi' => 'fi',
            'austria' => 'at', 'österreich' => 'at',
            'switzerland' => 'ch', 'schweiz' => 'ch',
            'belgium' => 'be', 'belgique' => 'be',
            'portugal' => 'pt',
            'greece' => 'gr', 'ελλάδα' => 'gr',
            'turkey' => 'tr', 'türkiye' => 'tr',
            'india' => 'in', 'भारत' => 'in',
            'south korea' => 'kr', 'korea' => 'kr',
            'argentina' => 'ar',
            'colombia' => 'co',
            'venezuela' => 've',
            'chile' => 'cl',
            'peru' => 'pe', 'perú' => 'pe',
            'thailand' => 'th', 'ประเทศไทย' => 'th',
            'philippines' => 'ph',
            'indonesia' => 'id',
            'vietnam' => 'vn', 'việt nam' => 'vn',
            'malaysia' => 'my',
            'singapore' => 'sg',
            'south africa' => 'za',
            'egypt' => 'eg', 'مصر' => 'eg',
            'israel' => 'il', 'ישראל' => 'il',
            'ireland' => 'ie', 'éire' => 'ie',
            'new zealand' => 'nz',
            'slovakia' => 'sk', 'slovensko' => 'sk',
            'croatia' => 'hr', 'hrvatska' => 'hr',
            'serbia' => 'rs', 'србија' => 'rs',
            'bulgaria' => 'bg', 'българия' => 'bg',
            'latvia' => 'lv', 'latvija' => 'lv',
            'lithuania' => 'lt', 'lietuva' => 'lt',
            'estonia' => 'ee', 'eesti' => 'ee',
            'belarus' => 'by', 'беларусь' => 'by',
            'kazakhstan' => 'kz', 'қазақстан' => 'kz',
            // Russian names
            'сша' => 'us', 'америка' => 'us',
            'великобритания' => 'gb', 'англия' => 'gb',
            'россия' => 'ru',
            'германия' => 'de',
            'франция' => 'fr',
            'италия' => 'it',
            'испания' => 'es',
            'япония' => 'jp',
            'китай' => 'cn',
            'бразилия' => 'br',
            'канада' => 'ca',
            'австралия' => 'au',
            'мексика' => 'mx',
            'нидерланды' => 'nl', 'голландия' => 'nl',
            'польша' => 'pl',
            'украина' => 'ua',
            'чехия' => 'cz',
            'венгрия' => 'hu',
            'румыния' => 'ro',
            'швеция' => 'se',
            'норвегия' => 'no',
            'дания' => 'dk',
            'финляндия' => 'fi',
            'австрия' => 'at',
            'швейцария' => 'ch',
            'бельгия' => 'be',
            'португалия' => 'pt',
            'греция' => 'gr',
            'турция' => 'tr',
            'индия' => 'in',
            'южная корея' => 'kr', 'корея' => 'kr',
            'аргентина' => 'ar',
            'колумбия' => 'co',
            'венесуэла' => 've',
            'чили' => 'cl',
            'перу' => 'pe',
            'таиланд' => 'th',
            'филиппины' => 'ph',
            'индонезия' => 'id',
            'вьетнам' => 'vn',
            'малайзия' => 'my',
            'сингапур' => 'sg',
            'юар' => 'za', 'южная африка' => 'za',
            'египет' => 'eg',
            'израиль' => 'il',
            'ирландия' => 'ie',
            'новая зеландия' => 'nz',
            'словакия' => 'sk',
            'хорватия' => 'hr',
            'сербия' => 'rs',
            'болгария' => 'bg',
            'латвия' => 'lv',
            'литва' => 'lt',
            'эстония' => 'ee',
            'казахстан' => 'kz',
        ];

        $normalized = mb_strtolower(trim($country));
        
        // Если это уже код страны (2 буквы)
        if (strlen($country) === 2 && ctype_alpha($country)) {
            return strtolower($country);
        }
        
        return $countryMap[$normalized] ?? '';
    }


    /**
     * Форматирует дату с учётом локали
     * 
     * @param \DateTimeInterface|string|null $date Дата для форматирования
     * @param string $format Формат: 'short', 'medium', 'long', 'datetime'
     * @return string Отформатированная дата
     */
    public function formatDate(\DateTimeInterface|string|null $date, string $format = 'medium'): string
    {
        if ($date === null) {
            return '';
        }

        if (is_string($date)) {
            try {
                $date = new \DateTime($date);
            } catch (\Exception) {
                return $date;
            }
        }

        $locale = $this->getCurrentLocale();
        $formatString = self::DATE_FORMATS[$locale][$format] ?? self::DATE_FORMATS['en'][$format] ?? 'Y-m-d';

        $formatted = $date->format($formatString);

        // Для русской локали заменяем месяцы и дни недели
        if ($locale === 'ru') {
            $formatted = $this->localizeRussianDate($date, $formatted);
        }

        return $formatted;
    }

    /**
     * Форматирует число с учётом локали
     * 
     * @param int|float|null $number Число для форматирования
     * @param int $decimals Количество десятичных знаков
     * @return string Отформатированное число
     */
    public function formatNumber(int|float|null $number, int $decimals = 0): string
    {
        if ($number === null) {
            return '';
        }

        $locale = $this->getCurrentLocale();

        return match ($locale) {
            'ru' => number_format($number, $decimals, ',', ' '),
            default => number_format($number, $decimals, '.', ','),
        };
    }

    /**
     * Форматирует относительное время ("2 часа назад")
     * 
     * @param \DateTimeInterface|string|null $date Дата
     * @return string Относительное время
     */
    public function formatTimeAgo(\DateTimeInterface|string|null $date): string
    {
        if ($date === null) {
            return '';
        }

        if (is_string($date)) {
            try {
                $date = new \DateTime($date);
            } catch (\Exception) {
                return $date;
            }
        }

        $now = new \DateTime();
        $diff = $now->diff($date);
        $locale = $this->getCurrentLocale();

        // Если дата в будущем
        if ($diff->invert === 0) {
            return $this->formatDate($date, 'short');
        }

        return $this->formatDiffToString($diff, $locale);
    }


    /**
     * Получает текущую локаль из запроса
     */
    private function getCurrentLocale(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if ($request === null) {
            return $this->defaultLocale;
        }

        return $request->getLocale();
    }

    /**
     * Локализует дату для русского языка
     */
    private function localizeRussianDate(\DateTimeInterface $date, string $formatted): string
    {
        // Заменяем английские названия месяцев на русские
        $month = (int) $date->format('n');
        $englishMonth = $date->format('F');
        
        if (isset(self::RUSSIAN_MONTHS[$month])) {
            $formatted = str_replace($englishMonth, self::RUSSIAN_MONTHS[$month], $formatted);
        }

        // Заменяем английские названия дней недели на русские
        $englishWeekday = $date->format('l');
        if (isset(self::RUSSIAN_WEEKDAYS[$englishWeekday])) {
            $formatted = str_replace($englishWeekday, self::RUSSIAN_WEEKDAYS[$englishWeekday], $formatted);
        }

        return $formatted;
    }

    /**
     * Преобразует DateInterval в строку относительного времени
     */
    private function formatDiffToString(\DateInterval $diff, string $locale): string
    {
        if ($diff->y > 0) {
            return $this->pluralize($diff->y, 'year', $locale);
        }
        if ($diff->m > 0) {
            return $this->pluralize($diff->m, 'month', $locale);
        }
        if ($diff->d >= 7) {
            $weeks = (int) floor($diff->d / 7);
            return $this->pluralize($weeks, 'week', $locale);
        }
        if ($diff->d > 0) {
            return $this->pluralize($diff->d, 'day', $locale);
        }
        if ($diff->h > 0) {
            return $this->pluralize($diff->h, 'hour', $locale);
        }
        if ($diff->i > 0) {
            return $this->pluralize($diff->i, 'minute', $locale);
        }

        return $locale === 'ru' ? 'только что' : 'just now';
    }

    /**
     * Склоняет слово в зависимости от числа и локали
     */
    private function pluralize(int $count, string $unit, string $locale): string
    {
        if ($locale === 'ru') {
            return $this->pluralizeRussian($count, $unit);
        }

        return $this->pluralizeEnglish($count, $unit);
    }


    /**
     * Склонение для английского языка
     */
    private function pluralizeEnglish(int $count, string $unit): string
    {
        $units = [
            'year' => ['year', 'years'],
            'month' => ['month', 'months'],
            'week' => ['week', 'weeks'],
            'day' => ['day', 'days'],
            'hour' => ['hour', 'hours'],
            'minute' => ['min', 'min'],
        ];

        $form = $count === 1 ? $units[$unit][0] : $units[$unit][1];
        
        return "$count $form ago";
    }

    /**
     * Склонение для русского языка
     */
    private function pluralizeRussian(int $count, string $unit): string
    {
        $units = [
            'year' => ['год', 'года', 'лет'],
            'month' => ['месяц', 'месяца', 'месяцев'],
            'week' => ['неделю', 'недели', 'недель'],
            'day' => ['день', 'дня', 'дней'],
            'hour' => ['час', 'часа', 'часов'],
            'minute' => ['мин.', 'мин.', 'мин.'],
        ];

        $form = $this->getRussianPluralForm($count, $units[$unit]);
        
        return "$count $form назад";
    }

    /**
     * Определяет форму множественного числа для русского языка
     */
    private function getRussianPluralForm(int $count, array $forms): string
    {
        $mod10 = $count % 10;
        $mod100 = $count % 100;

        if ($mod100 >= 11 && $mod100 <= 19) {
            return $forms[2]; // много (лет, месяцев, дней...)
        }

        return match ($mod10) {
            1 => $forms[0],       // 1 год, 21 год
            2, 3, 4 => $forms[1], // 2 года, 3 года, 4 года
            default => $forms[2], // 5 лет, 6 лет...
        };
    }
}
