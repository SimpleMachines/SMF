# SMF Dependency Injection Guide

## Overview

This guide documents the ongoing migration of SMF's static architecture to a modern Dependency Injection (DI) pattern using service classes.

## Refactored Services

### 1. ErrorHandlerService

**Purpose**: Centralized error handling and logging.

**Interface**: `SMF\Services\Contracts\ErrorHandlerServiceInterface`
**Implementation**: `SMF\Services\ErrorHandlerService`
**Facade**: `SMF\ErrorHandler` (for backward compatibility)

**Key Methods:**
- `log(string $message, string $level = 'error'): void`
- `handleError(int $errno, string $errstr, string $errfile, int $errline): bool`
- `handleException(\Throwable $exception): void`

**Example Usage:**
```php
use SMF\Services\Contracts\ErrorHandlerServiceInterface;

class MyService {
    public function __construct(
        private ErrorHandlerServiceInterface $errorHandler
    ) {}

    public function doSomething() {
        try {
            // ... work ...
        } catch (\Exception $e) {
            $this->errorHandler->log('Failed to do something: ' . $e->getMessage(), 'error');
        }
    }
}
```

### 2. SettingsService

**Purpose**: Manage file-based configuration from Settings.php.

**Interface**: `SMF\Services\Contracts\SettingsServiceInterface`
**Implementation**: `SMF\Services\SettingsService`
**Facade**: `SMF\Config::getSettingsService()`

**Key Methods:**
- `get(string $key, mixed $default = null): mixed`
- `getBoardUrl(): string`
- `getBoardDir(): string`
- `getSourcesDir(): string`
- `getDatabaseType(): string`
- `getDatabaseServer(): string`
- `getDatabaseName(): string`
- `isMaintenanceMode(): bool`

**Example Usage:**
```php
use SMF\Services\Contracts\SettingsServiceInterface;

class FileManager {
    public function __construct(
        private SettingsServiceInterface $settings
    ) {}

    public function getUploadPath(): string {
        return $this->settings->getBoardDir() . '/uploads';
    }

    public function isMaintenanceMode(): bool {
        return $this->settings->isMaintenanceMode();
    }
}
```

### 3. ModSettingsService

**Purpose**: Manage database-based runtime settings from the settings table.

**Interface**: `SMF\Services\Contracts\ModSettingsServiceInterface`
**Implementation**: `SMF\Services\ModSettingsService`
**Facade**: `SMF\Config::getModSettingsService()`

**Key Methods:**
- `get(string $key, mixed $default = null): mixed`
- `getAll(): array`
- `has(string $key): bool`
- `update(array $settings, bool $update = false): void`
- `delete(string|array $keys): void`
- `reload(): void`
- `clearCache(): void`

**Example Usage:**
```php
use SMF\Services\Contracts\ModSettingsServiceInterface;

class FeatureManager {
    public function __construct(
        private ModSettingsServiceInterface $modSettings
    ) {}

    public function isFeatureEnabled(string $feature): bool {
        return (bool) $this->modSettings->get($feature . '_enabled', false);
    }


### Step 2: Register Your Service in the DI Container

Add your service to `Sources/Infrastructure/ServicesList.php`:

```php
<?php

use SMF\Services\ErrorHandlerService;
use SMF\Services\ModSettingsService;
use SMF\Services\SettingsService;
use SMF\Services\MyAwesomeService;

return [
    // Existing services
    SettingsService::class => [
        'shared' => true,  // Singleton pattern
    ],
    ModSettingsService::class => [
        'shared' => true,
    ],
    ErrorHandlerService::class => [
        'shared' => true,
    ],

    // Your new service
    MyAwesomeService::class => [
        'arguments' => [
            SettingsService::class,         // Will auto-inject SettingsService
            ModSettingsService::class,      // Will auto-inject ModSettingsService
            ErrorHandlerService::class,     // Will auto-inject ErrorHandlerService
        ],
        'shared' => true,  // Use 'false' if you need a new instance each time
    ],
];
```

**Registration Options:**

- **`shared: true`**: Service is a singleton (one instance shared across app)
- **`shared: false`**: New instance created each time it's requested
- **`arguments`**: List of dependencies to inject (in constructor order)

### Step 3: Retrieve and Use Your Service

#### Option 1: Constructor Injection (Recommended for New Code)

**This is the preferred method for all new code.**

```php
class HigherLevelService
{
    public function __construct(
        private MyAwesomeService $myService
    ) {}

    public function doWork(): void
    {
        $this->myService->performTask();
    }
}

// Register in ServicesList.php
return [
    HigherLevelService::class => [
        'arguments' => [
            MyAwesomeService::class,  // Auto-injected
        ],
        'shared' => true,
    ],
];
```

**Why this is best:**
- ✅ Testable (inject mocks)
- ✅ Clear dependencies
- ✅ Type-safe
- ✅ IDE autocomplete support

#### Option 2: Container Direct Access (For Actions/Controllers)

**Use when you can't use constructor injection (e.g., legacy action classes).**

```php
use SMF\Infrastructure\Container;
use SMF\Services\MyAwesomeService;

// Get service from container
$myService = Container::get(MyAwesomeService::class);
$result = $myService->performTask();
```

**When to use:**
- Actions that can't easily use constructor injection
- One-off service access in procedural code
- Bootstrapping/initialization code

#### ⚠️ Facade Pattern (Deprecated - Existing Code Only)

**Do NOT use facades in new code. Only for backward compatibility with existing code.**

```php
// ⛔ DEPRECATED - Do not use in new code
$settings = \SMF\Config::getSettingsService();
$modSettings = \SMF\Config::getModSettingsService();

// ⛔ LEGACY - Still works but avoid in new code
$boardUrl = \SMF\Config::$boardurl;
$setting = \SMF\Config::$modSettings['some_setting'];
```

**Why facades are deprecated:**
- ❌ Tight coupling to static classes
- ❌ Harder to test
- ❌ Hidden dependencies
- ❌ Not following DI principles

**Facades are only maintained for:**
- Backward compatibility with existing code
- Gradual migration of legacy code
- Code that hasn't been refactored yet

## Complete Example: Building a New Feature

Let's build a complete feature using DI from scratch.

### 1. Create Service Interface

`Sources/Services/Contracts/CacheServiceInterface.php`:
```php
<?php

namespace SMF\Services\Contracts;

interface CacheServiceInterface
{
    public function get(string $key): mixed;
    public function put(string $key, mixed $value, int $ttl = 0): bool;
    public function delete(string $key): bool;
}
```

### 2. Create Service Implementation

`Sources/Services/CacheService.php`:
```php
<?php

namespace SMF\Services;

use SMF\Cache\CacheApi;
use SMF\Services\Contracts\CacheServiceInterface;
use SMF\Services\Contracts\ModSettingsServiceInterface;
use SMF\Services\Contracts\ErrorHandlerServiceInterface;

class CacheService implements CacheServiceInterface
{
    public function __construct(
        private ModSettingsServiceInterface $modSettings,
        private ErrorHandlerServiceInterface $errorHandler
    ) {
        // Load cache system
        CacheApi::load();
    }

    public function get(string $key): mixed
    {
        try {
            return CacheApi::get($key);
        } catch (\Exception $e) {
            $this->errorHandler->log('Cache get failed: ' . $e->getMessage());
            return null;
        }
    }

    public function put(string $key, mixed $value, int $ttl = 0): bool
    {
        try {
            return CacheApi::put($key, $value, $ttl);
        } catch (\Exception $e) {
            $this->errorHandler->log('Cache put failed: ' . $e->getMessage());
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return CacheApi::put($key, null);
        } catch (\Exception $e) {
            $this->errorHandler->log('Cache delete failed: ' . $e->getMessage());
            return false;
        }
    }
}
```

### 3. Register in Container

`Sources/Infrastructure/ServicesList.php`:
```php
use SMF\Services\CacheService;

return [
    // ... existing services ...

    CacheService::class => [
        'arguments' => [
            ModSettingsService::class,
            ErrorHandlerService::class,
        ],
        'shared' => true,
    ],
];
```

### 4. Use in Your Code

```php
use SMF\Services\Contracts\CacheServiceInterface;
use SMF\Services\Contracts\ModSettingsServiceInterface;

class UserProfileService
{
    public function __construct(
        private CacheServiceInterface $cache,
        private ModSettingsServiceInterface $modSettings
    ) {}

    public function getUserProfile(int $userId): ?array
    {
        // Try cache first
        $cacheKey = 'user_profile_' . $userId;
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        // Load from database (simplified)
        $profile = $this->loadFromDatabase($userId);

        // Cache for future requests
        $ttl = (int) $this->modSettings->get('profile_cache_ttl', 3600);
        $this->cache->put($cacheKey, $profile, $ttl);

        return $profile;
    }
}
```

## Testing with Dependency Injection

One of the biggest benefits of DI is testability. Here's how to write tests:

### Example: Unit Test with Mocks

```php
use PHPUnit\Framework\TestCase;
use SMF\Services\Contracts\ModSettingsServiceInterface;
use SMF\Services\Contracts\CacheServiceInterface;

class UserProfileServiceTest extends TestCase
{
    public function testGetUserProfileUsesCache(): void
    {
        // Create mocks
        $cacheMock = $this->createMock(CacheServiceInterface::class);
        $modSettingsMock = $this->createMock(ModSettingsServiceInterface::class);

        // Set expectations
        $cacheMock->expects($this->once())
            ->method('get')
            ->with('user_profile_123')
            ->willReturn(['id' => 123, 'name' => 'Test User']);

        // Never should hit database if cache works
        $cacheMock->expects($this->never())
            ->method('put');

        // Create service with mocks
        $service = new UserProfileService($cacheMock, $modSettingsMock);

        // Test
        $profile = $service->getUserProfile(123);

        // Assert
        $this->assertEquals('Test User', $profile['name']);
    }
}
```

**Benefits:**
- ✅ No database needed for tests
- ✅ Complete control over dependencies
- ✅ Fast test execution
- ✅ Test edge cases easily

## Best Practices

### 1. Use Constructor Injection for New Code

**Good - Constructor Injection:**
```php
class MyService
{
    public function __construct(
        private ModSettingsServiceInterface $modSettings
    ) {}
}
```

**Bad - Facade/Static Access:**
```php
class MyService
{
    public function doWork()
    {
        // ⛔ Don't do this in new code
        $settings = Config::getModSettingsService();
        $value = Config::$modSettings['key'];
    }
}
```

**Why?** Constructor injection makes dependencies explicit and code testable.

### 2. Always Use Interfaces, Not Implementations

**Good:**
```php
public function __construct(
    private ModSettingsServiceInterface $modSettings  // Interface
) {}
```

**Bad:**
```php
public function __construct(
    private ModSettingsService $modSettings  // Concrete class
) {}
```

**Why?** Interfaces allow swapping implementations and better mocking in tests.

### 3. Keep Constructor Simple

**Good:**
```php
public function __construct(
    private SettingsServiceInterface $settings
) {}
```

**Bad:**
```php
public function __construct(
    private SettingsServiceInterface $settings
) {
    // Don't do heavy work here!
    $this->loadAllData();
    $this->processEverything();
}
```

**Why?** Heavy work in constructors makes testing difficult and slows down initialization.

### 4. Use Lazy Loading for Expensive Operations

```php
class HeavyService
{
    private ?array $data = null;

    public function __construct(
        private ModSettingsServiceInterface $modSettings
    ) {}

    public function getData(): array
    {
        if ($this->data === null) {
            $this->data = $this->loadExpensiveData();
        }
        return $this->data;
    }
}
```

### 5. Mark Services as Shared When Appropriate

```php
// ServicesList.php
return [
    // Stateless services should be shared (singleton)
    CacheService::class => [
        'shared' => true,
    ],

    // Stateful services might need multiple instances
    SessionHandler::class => [
        'shared' => false,  // New instance per request
    ],
];
```

## Migration Strategy

### Phase 1: Backward Compatible Services (Current)

**Status**: ✅ Complete

- Create service classes that work alongside static classes
- Services can use facades for backward compatibility
- Old code continues to work unchanged

### Phase 2: New Code Uses DI (In Progress)

**Status**: 🔄 Ongoing

- All new features use dependency injection
- Gradually refactor existing code when touched
- No breaking changes to existing functionality

### Phase 3: Full Migration (Future)

**Status**: ⏳ Planned

- Static facades become thin wrappers around services
- All business logic in services
- Global state minimized

## Quick Reference

### Currently Available Services

| Service | Interface | Purpose |
|---------|-----------|---------|
| **ErrorHandlerService** | `ErrorHandlerServiceInterface` | Error handling and logging |
| **SettingsService** | `SettingsServiceInterface` | Settings.php configuration |
| **ModSettingsService** | `ModSettingsServiceInterface` | Database settings |

### Container Methods

```php
use SMF\Infrastructure\Container;

// Get a service
$service = Container::get(MyService::class);

// Check if service exists
if (Container::has(MyService::class)) {
    // ...
}
```

### ⚠️ Facade Access (Deprecated - Existing Code Only)

**Do NOT use in new code. Only for backward compatibility.**

```php
// ⛔ DEPRECATED - Existing code only
$settings = \SMF\Config::getSettingsService();
$modSettings = \SMF\Config::getModSettingsService();
\SMF\ErrorHandler::log('message', 'error');

// ✅ NEW CODE - Use constructor injection instead
public function __construct(
    private SettingsServiceInterface $settings,
    private ModSettingsServiceInterface $modSettings,
    private ErrorHandlerServiceInterface $errorHandler
) {}
```

## Common Patterns

### Pattern 1: Service Factory

```php
class UserServiceFactory
{
    public function __construct(
        private ModSettingsServiceInterface $modSettings
    ) {}

    public function createUserService(int $userId): UserService
    {
        return new UserService($userId, $this->modSettings);
    }
}
```

### Pattern 2: Optional Dependencies

```php
class OptionalDepsService
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private ?CacheServiceInterface $cache = null  // Optional
    ) {}

    public function doWork(): void
    {
        if ($this->cache !== null) {
            // Use cache if available
        }
    }
}
```

### Pattern 3: Multiple Implementations

```php
// Development vs Production
interface LoggerInterface {
    public function log(string $message): void;
}

class FileLogger implements LoggerInterface {
    public function log(string $message): void {
        file_put_contents('log.txt', $message, FILE_APPEND);
    }
}

class ConsoleLogger implements LoggerInterface {
    public function log(string $message): void {
        echo $message . PHP_EOL;
    }
}

// In ServicesList.php, choose implementation:
return [
    LoggerInterface::class => [
        'class' => \defined('SMF_DEBUG') ? ConsoleLogger::class : FileLogger::class,
        'shared' => true,
    ],
];
```

## Troubleshooting

### Problem: Service Not Found

**Error**: `Service not found: MyService`

**Solution**: Register service in `Sources/Infrastructure/ServicesList.php`

### Problem: Circular Dependency

**Error**: `Circular dependency detected`

**Solution**: Refactor to break the circle:
- Use interfaces
- Extract shared logic to a new service
- Use lazy loading or events

### Problem: Wrong Dependencies Injected

**Error**: Type mismatch or unexpected behavior

**Solution**: Check argument order in `ServicesList.php` matches constructor order

## Additional Resources

- **Migration Plan**: See `DEPENDENCY_INJECTION_MIGRATION_PLAN.md` for the full roadmap
- **Config Services Guide**: See `CONFIG_SERVICES_MIGRATION_GUIDE.md` for configuration details
- **Independence Summary**: See `CONFIG_SERVICES_INDEPENDENCE_SUMMARY.md` for architecture details

## Contributing New Services

When adding a new service:

1. ✅ Create interface in `Sources/Services/Contracts/`
2. ✅ Create implementation in `Sources/Services/`
3. ✅ Register in `Sources/Infrastructure/ServicesList.php`
4. ✅ Write unit tests
5. ✅ Update this documentation
6. ✅ Add facade method if needed for backward compatibility

**Template:**

```php
// 1. Interface
namespace SMF\Services\Contracts;

interface YourServiceInterface {
    public function doSomething(): void;
}

// 2. Implementation
namespace SMF\Services;

class YourService implements Contracts\YourServiceInterface {
    public function __construct(
        private SettingsServiceInterface $settings
    ) {}

    public function doSomething(): void {
        // Implementation
    }
}

// 3. Registration (ServicesList.php)
return [
    YourService::class => [
        'arguments' => [SettingsService::class],
        'shared' => true,
    ],
];
```

---

**Remember**: The goal is gradual, non-breaking migration to improve testability and maintainability! 🚀


