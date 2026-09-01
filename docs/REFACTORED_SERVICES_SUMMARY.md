# Refactored Services Summary

## Overview

This document provides a summary of all services that have been refactored to use Dependency Injection (DI) in the SMF codebase.

## Refactoring Progress

| Service | Status | Interface | Implementation | Facade | Independent |
|---------|--------|-----------|----------------|--------|-------------|
| **ErrorHandlerService** | Complete | `ErrorHandlerServiceInterface` | `ErrorHandlerService` | `ErrorHandler` | Yes |
| **SettingsService** | Complete | `SettingsServiceInterface` | `SettingsService` | `Config::getSettingsService()` | Yes |
| **ModSettingsService** | Complete | `ModSettingsServiceInterface` | `ModSettingsService` | `Config::getModSettingsService()` | Yes |


**Legend:**
- Complete: Fully implemented and tested
- In Progress: Currently being worked on
- Planned: Scheduled for future implementation
- **Independent**: Can load data without depending on legacy static classes

## Service Details

### 1. ErrorHandlerService

**Purpose**: Centralized error handling, logging, and exception management.

**Files:**
- Interface: `Sources/Services/Contracts/ErrorHandlerServiceInterface.php`
- Implementation: `Sources/Services/ErrorHandlerService.php`
- Facade: `Sources/ErrorHandler.php`

**Key Features:**
- Handles PHP errors and exceptions
- Logging with different severity levels
- Backward compatible facade

**Public Methods:**
```php
public function log(string $message, string $level = 'error'): void;
public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool;
public function handleException(\Throwable $exception): void;
public function fatal(string $message, bool $log = true): void;
public function displayDbError(): void;
public function displayLoadAvgError(): void;
```

**Usage:**
```php
// Via DI
public function __construct(
    private ErrorHandlerServiceInterface $errorHandler
) {}

$this->errorHandler->log('Something went wrong', 'error');

// Via Facade (legacy)
ErrorHandler::log('Something went wrong', 'error');
```

**Backward Compatibility:**
- Static method `ErrorHandler::log()` still works
- All existing code continues to function
- New code should use DI

---

### 2. SettingsService

**Purpose**: Manage file-based configuration from Settings.php.

**Files:**
- Interface: `Sources/Services/Contracts/SettingsServiceInterface.php`
- Implementation: `Sources/Services/SettingsService.php`
- Facade: `Sources/Config.php` (via `getSettingsService()`)

**Key Features:**
- **Fully Independent**: Loads Settings.php directly without Config class
- Lazy loading pattern
- Efficiency optimization (reuses Config data if available)
- Isolated loading scope
- Backward compatible

**Public Methods:**
```php
public function get(string $key, mixed $default = null): mixed;
public function set(string $key, mixed $value): void;
public function updateFile(array $configVars, bool $keepQuotes = false, bool $rebuild = false): bool;
public function getBoardUrl(): string;
public function getScriptUrl(): string;
public function getBoardDir(): string;
public function getSourcesDir(): string;
public function getCacheDir(): string;
public function getLanguagesDir(): string;
public function isMaintenanceMode(): bool;
public function getMaintenanceLevel(): int;
public function getForumName(): string;
public function getDatabaseType(): string;
public function getDatabaseServer(): string;
public function getDatabaseName(): string;
public function getDatabasePrefix(): string;
```

**Data Source**: Settings.php file (file-based configuration)

**Loading Strategy:**
1. Check if settings already loaded (lazy loading)
2. If `Config::$boardurl` exists, use `syncFromConfig()` for efficiency
3. Otherwise, load Settings.php directly using `loadSettingsFile()`
4. Settings loaded in isolated closure scope

**Usage:**
```php
// Via DI (recommended)
public function __construct(
    private SettingsServiceInterface $settings
) {}

$boardDir = $this->settings->getBoardDir();
$isMaintenanceMode = $this->settings->isMaintenanceMode();

// Via Facade
$settings = Config::getSettingsService();
$boardUrl = $settings->getBoardUrl();

// Via Config (legacy - still works)
$boardUrl = Config::$boardurl;
```

**Backward Compatibility:**
- `Config::$boardurl`, `Config::$boarddir`, etc. still work
- `set()` method syncs to Config static properties
- All existing code continues to function

---

### 3. ModSettingsService

**Purpose**: Manage database-based runtime settings from the settings table.

**Files:**
- Interface: `Sources/Services/Contracts/ModSettingsServiceInterface.php`
- Implementation: `Sources/Services/ModSettingsService.php`
- Facade: `Sources/Config.php` (via `getModSettingsService()`)

---

## Container Registration

All services are registered in `Sources/Infrastructure/ServicesList.php`:

---

## Quick Start Guide

### For New Features (Recommended)

Use Dependency Injection from the start:

```php
<?php

namespace SMF\Actions;

use SMF\Services\Contracts\SettingsServiceInterface;
use SMF\Services\Contracts\ModSettingsServiceInterface;
use SMF\Services\Contracts\ErrorHandlerServiceInterface;

class MyNewAction
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private ModSettingsServiceInterface $modSettings,
        private ErrorHandlerServiceInterface $errorHandler
    ) {}

    public function execute(): void
    {
        try {
            // Use injected services
            $boardDir = $this->settings->getBoardDir();
            $enabled = $this->modSettings->get('feature_enabled', false);

            if (!$enabled) {
                throw new \Exception('Feature not enabled');
            }

            // Do work...

        } catch (\Exception $e) {
            $this->errorHandler->log('Action failed: ' . $e->getMessage(), 'error');
        }
    }
}
```

**Register in ServicesList.php:**
```php
use SMF\Actions\MyNewAction;

return [
    MyNewAction::class => [
        'arguments' => [
            SettingsService::class,
            ModSettingsService::class,
            ErrorHandlerService::class,
        ],
        'shared' => false,  // New instance per use
    ],
];
```

**Use the Action:**
```php
use SMF\Infrastructure\Container;
use SMF\Actions\MyNewAction;

$action = Container::get(MyNewAction::class);
$action->execute();
```

### For Legacy Code (Transitional)

Use facade methods to access services:

```php
<?php

// Get services via facades
$settings = \SMF\Config::getSettingsService();
$modSettings = \SMF\Config::getModSettingsService();

// Use service methods
$boardUrl = $settings->getBoardUrl();
$enabled = $modSettings->get('feature_enabled', false);

// Old static methods still work too
$boardUrl = \SMF\Config::$boardurl;
$enabled = \SMF\Config::$modSettings['feature_enabled'] ?? false;
```

---

## Testing Examples

### Testing with Dependency Injection

One of the main benefits of DI is easy testing:

```php
use PHPUnit\Framework\TestCase;
use SMF\Services\Contracts\SettingsServiceInterface;
use SMF\Services\Contracts\ModSettingsServiceInterface;

class MyNewActionTest extends TestCase
{
    public function testExecuteWhenFeatureDisabled(): void
    {
        // Create mocks
        $settingsMock = $this->createMock(SettingsServiceInterface::class);
        $modSettingsMock = $this->createMock(ModSettingsServiceInterface::class);
        $errorHandlerMock = $this->createMock(ErrorHandlerServiceInterface::class);

        // Set expectations
        $modSettingsMock->expects($this->once())
            ->method('get')
            ->with('feature_enabled', false)
            ->willReturn(false);  // Feature is disabled

        // Expect error to be logged
        $errorHandlerMock->expects($this->once())
            ->method('log')
            ->with(
                $this->stringContains('Feature not enabled'),
                'error'
            );

        // Create action with mocked dependencies
        $action = new MyNewAction(
            $settingsMock,
            $modSettingsMock,
            $errorHandlerMock
        );

        // Execute
        $action->execute();

        // Assertions are in the expects() calls above
    }
}
```

**Benefits:**
- No database required
- No Settings.php file required
- Fast test execution
- Complete control over behavior
- Test edge cases easily

---

## Migration Patterns

### Pattern 1: Gradual Class Migration

**Step 1:** Original static class
```php
class FeatureManager
{
    public static function isEnabled(): bool
    {
        return !empty(Config::$modSettings['feature_enabled']);
    }
}
```

**Step 2:** Add instance method with DI
```php
class FeatureManager
{
    private ?ModSettingsServiceInterface $modSettings = null;

    public function __construct(?ModSettingsServiceInterface $modSettings = null)
    {
        $this->modSettings = $modSettings;
    }

    // New instance method
    public function isEnabled(): bool
    {
        if ($this->modSettings === null) {
            $this->modSettings = Config::getModSettingsService();
        }
        return (bool) $this->modSettings->get('feature_enabled', false);
    }

    // Keep static method for backward compatibility
    public static function isEnabledStatic(): bool
    {
        return !empty(Config::$modSettings['feature_enabled']);
    }
}
```

**Step 3:** Deprecate static method
```php
class FeatureManager
{
    public function __construct(
        private ModSettingsServiceInterface $modSettings
    ) {}

    public function isEnabled(): bool
    {
        return (bool) $this->modSettings->get('feature_enabled', false);
    }

    /**
     * @deprecated Use instance method via DI
     */
    public static function isEnabledStatic(): bool
    {
        trigger_error('FeatureManager::isEnabledStatic() is deprecated, use DI', E_USER_DEPRECATED);
        return !empty(Config::$modSettings['feature_enabled']);
    }
}
```

### Pattern 2: Wrapper Service

Create a service that wraps existing static functionality:

```php
class LegacyWrapperService
{
    public function __construct(
        private ModSettingsServiceInterface $modSettings,
        private SettingsServiceInterface $settings
    ) {}

    public function doLegacyThing(): void
    {
        // Old way (still works)
        // SomeStaticClass::doSomething();

        // New way (using services)
        $value = $this->modSettings->get('some_setting');
        // ... modern implementation ...
    }
}
```

---

## Performance Considerations

### Lazy Loading

Both services use lazy loading to avoid unnecessary work:

```php
public function getBoardUrl(): string
{
    $this->ensureLoaded();  // Only loads if not already loaded
    return $this->settings['boardurl'] ?? '';
}
```

### Efficiency Optimization

Services check if Config has already loaded data:

```php
protected function loadSettings(): void
{
    // If Config already loaded, reuse that data (fast!)
    if (!empty(Config::$boardurl)) {
        $this->syncFromConfig();
        return;
    }

    // Otherwise, load independently (slower, but works)
    $this->settings = $this->loadSettingsFile($this->settingsFile);
}
```

**Best of Both Worlds:**
- Fast when Config is loaded (typical case)
- Works independently when needed (testing, special cases)

---

## Troubleshooting

### Issue: Service Not Found in Container

**Symptom**: `Container::get(MyService::class)` throws exception

**Solution**: Register service in `Sources/Infrastructure/ServicesList.php`

### Issue: Circular Dependency

**Symptom**: Error about circular dependencies

**Solution**: Refactor to break the circle:
- Use interfaces instead of concrete classes
- Extract shared logic to a new service
- Use lazy loading or optional dependencies

---

## Additional Documentation

- **[Dependency Injection Guide](DEPENDENCY_INJECTION_GUIDE.md)** - Complete DI usage guide with examples

---

**Last Updated**: 2026-04-02
**Status**: 3/3 Core Services Refactored (ErrorHandler, Settings, ModSettings)
**Next**: CacheService, DatabaseService, SessionService


