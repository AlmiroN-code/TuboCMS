# Прогресс: Унификация стилей sidebar в админке

## Задача
Применить единый стиль с `border-dashed` ко всем пунктам меню в sidebar админки

## Выполнено
✅ Video Management (2 пункта: All videos, Categories)
✅ Posts & Publications (2 пункта: Posts, Post Categories)  
✅ Channels (1 пункт: Channels)
✅ Users & Groups (4 пункта: Users, Roles, Permissions, Models)

## Осталось сделать
- [ ] Advertising (нужно проверить актуальную структуру - есть Ads Dashboard, Ads, Campaigns и др.)
- [ ] Settings (7 пунктов: Settings, Storage, Transcoding, Encoding Profiles, SEO Settings, Analytics)
- [ ] System (6 пунктов: Мониторинг, Worker, Кэш, Уведомления, Workflow, Планировщик)

## Шаблон для применения
```twig
<div class="px-6 py-2 mx-2 ltr:pl-5 rtl:pr-5">
    <div class="space-y-1 border-0 border-l border-dashed border-slate-300 ltr:pl-1 rtl:pr-1">
        <div><a title="Название" class="relative flex w-full cursor-pointer items-center rounded-lg py-2 px-5 text-sm text-start before:absolute before:-left-0.5 before:top-[18px] before:h-px before:w-3 before:border-t before:border-dashed before:border-gray-300 before:content-[&quot;&quot;] focus:text-accent {{ условие ? 'bg-transparent font-medium text-accent-hover' : 'text-body-dark hover:text-accent focus:text-accent' }}" href="{{ path('маршрут') }}"><span>Название</span></a></div>
    </div>
</div>
```

## Следующий шаг
Продолжить с секции Advertising, затем Settings и System
