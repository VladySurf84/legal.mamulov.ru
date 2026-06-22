# Tailwind Plus components

В проект локально положены архивы Tailwind Plus:

- `_ui_archives/application-ui-v4.zip`
- `_ui_archives/ecommerce-v4.zip`
- `_ui_archives/marketing-v4.zip`

Архивы используются как локальная библиотека примеров. Их не нужно деплоить на сервер и обычно не нужно коммитить в репозиторий.

## Как подключаем к Laravel

Основной путь для нашего проекта:

1. Берем вариант компонента из папки `html`.
2. Переносим разметку в Blade.
3. Повторяемые элементы оформляем как Blade components в `resources/views/components/ui`.
4. Компоненты с состоянием, загрузкой, фильтрами или серверными действиями оформляем как Livewire components.
5. React/Vue варианты используем только как справочник по поведению, не как готовый код для вставки.

Сырые HTML-примеры Application UI хранятся в `docs/iu/tailwind/application`, а не в `resources/views`, чтобы Tailwind не воспринимал всю библиотеку примеров как реальные шаблоны проекта.

## Что использовать в первую очередь

### Application UI

Главный архив для закрытой бухгалтерской части.

Папки:

- `html/application-shells` - каркасы приложения, навигация, шапки.
- `html/forms` - формы, input, select, combobox, toggles, login.
- `html/lists/tables` - таблицы, sticky header, summary rows, grouped rows.
- `html/elements` - кнопки, badges, dropdowns, avatars.
- `html/overlays` - modal dialogs, drawers, notifications.
- `html/navigation` - pagination, tabs, breadcrumbs, sidebars.
- `html/data-display` - stats, calendars, description lists.
- `html/page-examples` - готовые страницы как источник композиции.

Уже заведенные компоненты:

- `resources/views/components/ui/button.blade.php`
- `resources/views/components/ui/select.blade.php`
- `resources/views/components/ui/select-with-secondary-text.blade.php`
- `resources/views/components/ui/multi-select-with-secondary-text.blade.php`
- `resources/views/components/ui/airdatepicker/date-range.blade.php`
- `resources/views/components/ui/sticky-table.blade.php`
- `resources/views/components/ui/sticky-table-th.blade.php`
- `resources/views/components/ui/sticky-table-td.blade.php`

Пример таблицы:

```blade
<x-ui.sticky-table title="Банковские счета" description="Список счетов">
    <x-slot:actions>
        <button type="button" class="block rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-xs hover:bg-indigo-500">
            Добавить
        </button>
    </x-slot:actions>

    <x-slot:head>
        <tr>
            <x-ui.sticky-table-th first>Название</x-ui.sticky-table-th>
            <x-ui.sticky-table-th>Счет</x-ui.sticky-table-th>
            <x-ui.sticky-table-th last align="right">Баланс</x-ui.sticky-table-th>
        </tr>
    </x-slot:head>

    <tr>
        <x-ui.sticky-table-td first strong>ИП Мамулов</x-ui.sticky-table-td>
        <x-ui.sticky-table-td>408028...</x-ui.sticky-table-td>
        <x-ui.sticky-table-td last align="right">100 000,00</x-ui.sticky-table-td>
    </tr>
</x-ui.sticky-table>
```

Пример secondary button:

```blade
<x-ui.button type="submit" size="lg">
    Обновить
</x-ui.button>
```

Пример soft variant:

```blade
<x-ui.button type="submit" size="lg" variant="soft">
    Обновить
</x-ui.button>
```

Пример native select:

```blade
<x-ui.select
    label="Юрлицо"
    name="legal_id"
    :value="$filters['legal_id'] ?? ''"
    :options="$legals->map(fn ($legal) => [
        'value' => $legal->legal_id,
        'label' => $legal->legal_name,
    ])"
    placeholder="Все юрлица"
/>
```

Пример multiselect:

```blade
<x-ui.multi-select-with-secondary-text
    label="Счета"
    name="account_numbers"
    :value="$filters['account_numbers'] ?? []"
    :options="$accounts->map(fn ($account) => [
        'value' => $account->account_number,
        'label' => $account->legalEntity?->legal_name,
        'secondary' => $account->account_number,
    ])"
    placeholder="Все счета"
/>
```

Пример date range:

```blade
<x-ui.airdatepicker.date-range
    label="Период"
    name-from="date_from"
    name-to="date_to"
    :value-from="$filters['date_from'] ?? null"
    :value-to="$filters['date_to'] ?? null"
/>
```

### Ecommerce

Использовать точечно, если понадобятся карточки, списки, фильтры или интерфейсы выбора товаров/услуг. Для текущей бухгалтерии это не основной источник.

Папки:

- `html/components`
- `html/page-examples`

### Marketing

Использовать только для публичных страниц, если они появятся. Сейчас проект считается закрытой частью, поэтому marketing-компоненты обычно не нужны.

Папки:

- `html/elements`
- `html/feedback`
- `html/sections`
- `html/page-examples`

## Интерактивные компоненты

Часть Tailwind Plus HTML использует кастомные элементы:

- `el-select`
- `el-options`
- `el-option`
- `el-dropdown`
- похожие `el-*` элементы

Для них нужен `@tailwindplus/elements`.

Варианты подключения:

- локально через npm, если решим добавить зависимость в `package.json`;
- через `<script src="https://cdn.jsdelivr.net/npm/@tailwindplus/elements@1" type="module"></script>`, если компонент нужен точечно.

Текущий компонент `x-ui.select-with-secondary-text` подключает script через `@once`, чтобы не дублировать его на странице.

## Правило выбора

- Простая разметка без поведения: Blade component.
- Форма с отправкой обычного POST/GET: Blade + controller.
- Фильтры, пагинация, live search, загрузка файла, фоновые действия: Livewire component.
- Сложный frontend-only виджет: сначала проверить, можно ли сделать через Tailwind Plus Elements; React/Vue не добавляем без отдельного решения.
