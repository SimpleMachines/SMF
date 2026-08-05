# Dependency Injection Migration Plan

This document outlines global variables identified as candidates for refactoring into services using the dependency injection container. The goal is to reduce reliance on global state, improve testability, and enhance maintainability.

## Identified Global Variables for Refactoring

### 1. `$context`

*   **Current Usage:** Managed via `SMF\Utils::$context`. This is a large, mutable array used to pass data to templates and various parts of the application.
*   **Reason for Refactoring:** Its global nature makes it difficult to track data flow, leads to tight coupling, and can cause unexpected side effects. It should be broken down into smaller, more focused services or view models.
*   **Proposed Service(s):**
    *   `ContextService`: A service to manage general context data, potentially with methods for specific context areas (e.g., `getContext()->getPageTitle()`, `getContext()->getMetaTags()`).
    *   More granular services for specific data types currently stored in `$context` (e.g., `MenuService`, `AlertService`, `ThemeDataService`).

### 2. `$modSettings`

*   **Current Usage:** Managed via `SMF\Config::$modSettings`. This array holds forum-wide settings loaded from the database.
*   **Reason for Refactoring:** While already encapsulated in `Config` as a static property, direct access to `Config::$modSettings` still represents a global dependency. Injecting a `SettingsService` would allow for easier mocking in tests and clearer dependency management.
*   **Proposed Service(s):**
    *   `SettingsService`: A service to provide access to forum settings, potentially with methods for specific setting categories (e.g., `getSettings()->getGeneralSetting('boardurl')`, `getSettings()->getMailSetting('webmaster_email')`).

### 3. `$smcFunc`

*   **Current Usage:** Managed via `SMF\Utils::$smcFunc`. This is an array of backward compatibility aliases for various utility functions.
*   **Reason for Refactoring:** This is explicitly a backward compatibility layer. The underlying utility functions should be directly called or encapsulated within dedicated utility services.
*   **Proposed Service(s):**
    *   Individual utility services (e.g., `StringService` for string manipulation, `HtmlService` for HTML-related functions). The `Utils` class itself could become a service.

### 4. `$scripturl`

*   **Current Usage:** Managed via `SMF\Config::$scripturl`. Represents the base URL for the forum's `index.php`.
*   **Reason for Refactoring:** A core application URL that is globally accessible. Could be part of a `UrlService` or `ApplicationConfigService`.
*   **Proposed Service(s):**
    *   `UrlService`: Provides methods for generating various URLs, including the base script URL.

### 5. `$settings` (Theme-related)

*   **Current Usage:** Managed via `SMF\Theme::$current->settings`. Holds theme-specific settings.
*   **Reason for Refactoring:** Similar to `$modSettings`, direct access to `Theme::$current->settings` is a global dependency. Theme settings should be accessible through a dedicated service.
*   **Proposed Service(s):**
    *   `ThemeSettingsService`: Provides access to the current theme's settings.

### 6. `$options` (Theme-related)

*   **Current Usage:** Managed via `SMF\Theme::$current->options`. Holds user-configurable theme options.
*   **Reason for Refactoring:** Similar to `$settings`, these are globally accessible theme options.
*   **Proposed Service(s):**
    *   `UserThemeOptionsService`: Provides access to the current user's theme options.

### 7. `$user_info` (and related user globals like `$user_profile`, `$memberContext`, `$user_settings`)

*   **Current Usage:** Managed via `SMF\User::$me`, `SMF\User::$profiles`, `SMF\User::$loaded[$id]->formatted`. These represent the current user's data and other loaded user profiles.
*   **Reason for Refactoring:** User data is fundamental and frequently accessed. Encapsulating this within a `UserService` or `CurrentUser` service would centralize access and allow for easier management of user state and permissions.
*   **Proposed Service(s):**
    *   `UserService`: Provides methods to retrieve user data, check permissions, and manage user-related operations.
    *   `CurrentUser`: A specific service or a method on `UserService` to get the currently logged-in user's object.

### 8. All other public static properties of `SMF\Config`

*   **Current Usage:** Directly accessed as `SMF\Config::$propertyName` (e.g., `$maintenance`, `$boardurl`, `$db_type`).
*   **Reason for Refactoring:** These are core application configuration values. While already in a static class, injecting them through a `ConfigurationService` or specific services (e.g., `DatabaseConfigService`) would align with DI principles.
*   **Proposed Service(s):**
    *   `ConfigurationService`: A general service to access all application configuration values.
    *   More specific services like `DatabaseConfigService`, `CacheConfigService`, `PathsConfigService` for related groups of settings.

## Next Steps

1.  Prioritize the refactoring based on usage frequency and impact (e.g., `$context` and `$modSettings` are high priority).
2.  Define interfaces for the proposed services to ensure loose coupling.
3.  Implement the services and register them with the `League\Container` instance in `SMF\Container::init()`.
4.  Replace direct static property access (`SMF\Utils::$context`, `SMF\Config::$modSettings`, etc.) with injected service calls.
5.  Update relevant code to use the new services.
6.  Write comprehensive tests for the new services and updated components.
