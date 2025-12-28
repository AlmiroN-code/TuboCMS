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
        ];
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
