# Implementation Plan

- [x] 1. Configure Symfony Translation component






  - [x] 1.1 Create translation configuration file

    - Create `config/packages/translation.yaml` with default locale `en` and enabled locales `['en', 'ru']`
    - Configure fallback to English
    - _Requirements: 4.1, 4.3_
  - [x] 1.2 Create base translation files structure


    - Create `translations/messages.en.yaml` with empty structure
    - Create `translations/messages.ru.yaml` with empty structure
    - Create `translations/validators.en.yaml` and `validators.ru.yaml`
    - Create `translations/security.en.yaml` and `security.ru.yaml`
    - _Requirements: 7.1, 7.2_

- [x] 2. Implement LocaleSubscriber for locale detection





  - [x] 2.1 Create LocaleSubscriber event subscriber


    - Create `src/EventSubscriber/LocaleSubscriber.php`
    - Implement locale detection from cookie, then Accept-Language header
    - Set locale on Request and session
    - _Requirements: 1.1, 1.3, 1.4, 5.2_
  - [x] 2.2 Write property test for locale detection


    - **Property 2: Browser Locale Detection**
    - Test Accept-Language parsing and fallback behavior
    - **Validates: Requirements 1.1, 1.4**
  - [x] 2.3 Write property test for locale persistence


    - **Property 1: Locale Persistence Round Trip**
    - Test cookie storage and retrieval
    - **Validates: Requirements 1.3, 5.1, 5.2**

- [x] 3. Implement LocaleController for language switching






  - [x] 3.1 Create LocaleController

    - Create `src/Controller/LocaleController.php`
    - Implement `switchLocale` action that sets cookie and redirects
    - Validate locale against supported locales list
    - Set cookie with 1 year expiration
    - _Requirements: 2.3, 5.1_

  - [x] 3.2 Write property test for URL preservation

    - **Property 3: URL Preservation on Locale Switch**
    - Test redirect preserves path and query parameters
    - **Validates: Requirements 2.3, 2.4, 5.4**

- [x] 4. Create Twig extension for locale helpers






  - [x] 4.1 Create LocaleExtension

    - Create `src/Twig/LocaleExtension.php`
    - Implement `getAvailableLocales()` function
    - Implement `getCurrentLocale()` function
    - Implement `getLocaleName(locale)` function for display names
    - _Requirements: 2.1, 2.2_

- [x] 5. Create language switcher UI component






  - [x] 5.1 Create language switcher partial template

    - Create `templates/partials/_language_switcher.html.twig`
    - Implement dropdown with available languages
    - Show current language with flag/name
    - _Requirements: 2.1, 2.2_
  - [x] 5.2 Integrate language switcher into header


    - Modify `templates/partials/_header.html.twig`
    - Add language switcher next to theme toggle button
    - _Requirements: 2.1_

- [x] 6. Checkpoint - Ensure all tests pass







  - Ensure all tests pass, ask the user if questions arise.

- [x] 7. Extract and translate header/navigation texts






  - [x] 7.1 Add navigation translations to message files

    - Add keys for Home, Videos, Categories, Community, Popular, Trending
    - Add keys for Login, Logout, Sign Up, My Profile, My Videos, Admin Panel
    - Add keys for Search placeholder
    - _Requirements: 3.1_

  - [x] 7.2 Update header template with translation keys

    - Replace hardcoded text in `_header.html.twig` with `|trans` filter
    - _Requirements: 3.1, 1.2_

- [x] 8. Extract and translate video-related texts



  - [x] 8.1 Add video translations to message files


    - Add keys for video list titles, pagination, load more
    - Add keys for video card elements (views, duration)
    - Add keys for search results, no videos found
    - _Requirements: 3.1_


  - [x] 8.2 Update video templates with translation keys

    - Update `video/list.html.twig`, `video/_card.html.twig`
    - Update `video/search.html.twig`, `video/detail.html.twig`
    - _Requirements: 3.1, 1.2_

- [x] 9. Extract and translate category texts





  - [x] 9.1 Add category translations to message files

    - Add keys for category page titles
    - Add keys for "X videos" count format
    - _Requirements: 3.1_
  - [x] 9.2 Update category templates with translation keys


    - Update `category/index.html.twig`, `category/show.html.twig`
    - _Requirements: 3.1, 1.2_

- [x] 10. Extract and translate authentication texts





  - [x] 10.1 Add auth translations to message files

    - Add keys for login page (title, labels, buttons)
    - Add keys for registration page
    - Add keys for error messages
    - _Requirements: 3.1, 6.3_
  - [x] 10.2 Update security templates with translation keys


    - Update `security/login.html.twig`, `security/register.html.twig`
    - _Requirements: 3.1, 1.2_


  - [x] 10.3 Add security domain translations




    - Add translations for authentication error messages
    - _Requirements: 6.3_

- [x] 11. Extract and translate home page texts





  - [x] 11.1 Add home page translations

    - Add keys for section titles (Featured, New Videos, Popular Videos)
    - Add keys for "View all" links
    - _Requirements: 3.1_


  - [x] 11.2 Update home template with translation keys




    - Update `home/index.html.twig`
    - _Requirements: 3.1, 1.2_

- [x] 12. Extract and translate footer and common elements





  - [x] 12.1 Add footer translations

    - Add keys for footer links and copyright
    - _Requirements: 3.1_

  - [x] 12.2 Update footer template

    - Update `templates/partials/_footer.html.twig`
    - _Requirements: 3.1, 1.2_

- [x] 13. Translate form validation messages





  - [x] 13.1 Add validator translations

    - Add translations for common validation errors
    - Add translations for custom validation messages
    - _Requirements: 6.1_
  - [x] 13.2 Write property test for message translation


    - **Property 5: Message Translation Consistency**
    - Test that translations return correct locale values
    - **Validates: Requirements 1.2, 6.1, 6.2, 6.3**

- [-] 14. Implement locale-aware date/number formatting



  - [x] 14.1 Create formatting Twig extension


    - Create or extend Twig extension for locale-aware formatting
    - Implement date formatting filter
    - Implement number formatting filter
    - Implement relative time filter ("2 hours ago")
    - _Requirements: 8.1, 8.2, 8.3_
  - [x] 14.2 Update templates to use locale-aware formatting


    - Replace date displays with locale-aware filter
    - Replace number displays with locale-aware filter
    - _Requirements: 8.1, 8.2, 8.3_

  - [ ] 14.3 Write property tests for formatting

    - **Property 6: Locale-Aware Date Formatting**
    - **Property 7: Locale-Aware Number Formatting**
    - **Validates: Requirements 8.1, 8.2, 8.3**

- [ ] 15. Translate flash messages in controllers
  - [ ] 15.1 Update controllers to use translation keys for flash messages
    - Replace hardcoded flash messages with translation keys
    - Update VideoController, SecurityController, etc.
    - _Requirements: 6.2_

- [ ] 16. Checkpoint - Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 17. Final integration and cleanup
  - [ ] 17.1 Review all templates for untranslated text
    - Scan templates for remaining hardcoded strings
    - Add missing translations
    - _Requirements: 3.1_
  - [ ] 17.2 Write property test for translation fallback
    - **Property 4: Translation Key Fallback**
    - Test that missing keys return the key itself
    - **Validates: Requirements 3.2**
