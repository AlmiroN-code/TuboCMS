# Requirements Document

## Introduction

Данный документ описывает требования к интернационализации (i18n) платформы RexTube. Цель — обеспечить поддержку нескольких языков на сайте, позволяя пользователям выбирать предпочтительный язык интерфейса. Изначально будут поддерживаться английский (en, по умолчанию) и русский (ru) языки, с возможностью добавления новых языков в будущем.

## Glossary

- **i18n (Internationalization)**: Процесс подготовки приложения к поддержке нескольких языков
- **Locale**: Код языка/региона (например, `ru`, `en`, `de`)
- **Translation Catalog**: Файл с переводами для конкретного языка
- **Default Locale**: Язык по умолчанию, используемый при отсутствии выбора пользователя (английский)
- **Language Switcher**: UI-компонент для переключения языка интерфейса
- **Translation Key**: Уникальный идентификатор строки перевода

## Requirements

### Requirement 1

**User Story:** As a user, I want to view the website in my preferred language, so that I can understand all content and navigate easily.

#### Acceptance Criteria

1. WHEN a user visits the site for the first time THEN the System SHALL detect the browser's preferred language and apply it if supported
2. WHEN a user selects a language from the language switcher THEN the System SHALL immediately display all interface elements in the selected language
3. WHEN a user changes the language THEN the System SHALL persist the language preference in the session and/or cookie
4. WHEN a supported locale is not detected THEN the System SHALL fall back to the default locale (English)

### Requirement 2

**User Story:** As a user, I want to switch languages easily, so that I can change the interface language at any time.

#### Acceptance Criteria

1. WHEN a user views any page THEN the System SHALL display a language switcher in the header
2. WHEN a user clicks on the language switcher THEN the System SHALL show a dropdown with all available languages
3. WHEN a user selects a different language THEN the System SHALL redirect to the same page with the new locale applied
4. WHEN the language is changed THEN the System SHALL preserve the current URL path and query parameters

### Requirement 3

**User Story:** As a developer, I want all user-facing text to be translatable, so that the site can be fully localized.

#### Acceptance Criteria

1. WHEN rendering any template THEN the System SHALL use translation keys for all static text content
2. WHEN a translation key is missing THEN the System SHALL display the key itself as fallback
3. WHEN adding new text THEN the System SHALL require the text to be added to translation catalogs
4. WHEN translations are updated THEN the System SHALL reflect changes after cache clear

### Requirement 4

**User Story:** As an administrator, I want to manage available languages, so that I can control which languages are offered to users.

#### Acceptance Criteria

1. WHEN configuring the application THEN the System SHALL allow defining the list of supported locales
2. WHEN a locale is added to configuration THEN the System SHALL make it available in the language switcher
3. WHEN the default locale is configured THEN the System SHALL use it for users without language preference

### Requirement 5

**User Story:** As a user, I want my language preference to persist across sessions, so that I don't have to select it every time.

#### Acceptance Criteria

1. WHEN a user selects a language THEN the System SHALL store the preference in a cookie with long expiration (1 year)
2. WHEN a user returns to the site THEN the System SHALL restore the language from the stored cookie
3. WHEN a logged-in user changes language THEN the System SHALL optionally store the preference in the user profile
4. WHEN URLs are generated THEN the System SHALL NOT include locale prefixes (URLs remain language-neutral)

### Requirement 6

**User Story:** As a user, I want form validation messages in my language, so that I can understand errors and fix them.

#### Acceptance Criteria

1. WHEN form validation fails THEN the System SHALL display error messages in the user's selected language
2. WHEN a flash message is shown THEN the System SHALL display it in the user's selected language
3. WHEN security messages are displayed (login errors, etc.) THEN the System SHALL translate them to the user's language

### Requirement 7

**User Story:** As a developer, I want translation files organized by domain, so that translations are maintainable.

#### Acceptance Criteria

1. WHEN organizing translations THEN the System SHALL use separate files for different domains (messages, validators, security)
2. WHEN storing translations THEN the System SHALL use YAML format for translation catalogs
3. WHEN the application starts THEN the System SHALL load and cache all translation catalogs

### Requirement 8

**User Story:** As a user, I want dates and numbers formatted according to my locale, so that they are familiar and readable.

#### Acceptance Criteria

1. WHEN displaying dates THEN the System SHALL format them according to the user's locale
2. WHEN displaying numbers THEN the System SHALL use locale-appropriate decimal and thousand separators
3. WHEN displaying relative times (e.g., "2 hours ago") THEN the System SHALL translate them to the user's language
