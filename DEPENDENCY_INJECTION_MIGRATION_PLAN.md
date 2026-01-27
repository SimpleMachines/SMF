# Dependency Injection Migration Plan for SMF3

## Executive Summary

This document outlines a comprehensive, gradual migration strategy to move SMF3's most-used static variables and global state into proper services using Dependency Injection (DI) with the already-included `League\Container` package.

**Current State Analysis:**
- `Utils::$context` - **5,733 usages** (highest priority)
- `Config::$modSettings` - **2,270 usages** (high priority)
- `Config::$sourcedir` and other path variables - **~30 usages** (medium priority)
- `Utils::$smcFunc` - **8 usages** (low priority, already being phased out)

**Migration Benefits:**
- Improved testability through dependency injection
- Better code organization and separation of concerns
- Reduced global state and hidden dependencies
- Easier to track data flow and debug issues
- Maintains backward compatibility during transition

---

## Phase 1: Foundation & High-Impact Services

### 1.1 ConfigService - Wrapping Config Static Properties

**Current Usage:**
```php
Config::$sourcedir
Config::$boarddir
Config::$packagesdir
Config::$vendordir
Config::$languagesdir
Config::$cachedir
Config::$scripturl
Config::$boardurl
Config::$db_prefix
Config::$db_type
// ... and many more
```

**Proposed Service Interface:**
```php
namespace SMF\Services;

interface ConfigServiceInterface
{
    // Path getters
    public function getSourceDir(): string;
    public function getBoardDir(): string;
    public function getPackagesDir(): string;
    public function getVendorDir(): string;
    public function getLanguagesDir(): string;
    public function getCacheDir(): string;

    // URL getters
    public function getScriptUrl(): string;
    public function getBoardUrl(): string;

    // Database config
    public function getDbPrefix(): string;
    public function getDbType(): string;
    public function getDbServer(): string;
    public function getDbName(): string;

    // Cache config
    public function getCacheAccelerator(): string;
    public function getCacheEnable(): int;

    // Maintenance
    public function getMaintenanceMode(): int;
    public function isInMaintenance(): bool;
}
```

**Implementation Strategy:**
- Create `ConfigService` class implementing `ConfigServiceInterface`
- Initially, methods will simply return `Config::$property` values
- Register as shared service in `Container::init()`
- Gradually refactor code to inject `ConfigServiceInterface` instead of accessing `Config::` directly

**Priority:** Medium (30 usages, but foundational for other services)

---

### 1.2 ModSettingsService - Wrapping Config::$modSettings

**Current Usage:**
```php
Config::$modSettings['setting_name']
Config::$modSettings['cache_enable']
Config::$modSettings['registration_method']
// ... 2,270+ usages throughout codebase
```

**Proposed Service Interface:**
```php
namespace SMF\Services;

interface ModSettingsServiceInterface
{
    // Generic getter
    public function get(string $key, mixed $default = null): mixed;

    // Type-safe getters for common settings
    public function getString(string $key, string $default = ''): string;
    public function getInt(string $key, int $default = 0): int;
    public function getBool(string $key, bool $default = false): bool;
    public function getArray(string $key, array $default = []): array;

    // Check existence
    public function has(string $key): bool;

    // Setter (for admin operations)
    public function set(string $key, mixed $value): void;

    // Bulk operations
    public function getAll(): array;
    public function setMultiple(array $settings): void;
}
```




**Implementation Strategy:**
- Create `ModSettingsService` implementing `ModSettingsServiceInterface`
- Initially wraps `Config::$modSettings` array access
- Register as shared service in `Container::init()`

**Priority:** HIGH (2,270 usages)

---

### 1.3 ContextService - Wrapping Utils::$context

**Current Usage:** 5,733+ usages throughout codebase

**Proposed Service Breakdown:**

#### AssetService - JavaScript/CSS Management
```php
interface AssetServiceInterface {
    public function addJavaScriptFile(string $file, array $options = []): void;
    public function addCssFile(string $file, array $options = []): void;
    public function addHtmlHeader(string $header): void;
}
```

#### PageContextService - Page Metadata
```php
interface PageContextServiceInterface {
    public function getPageTitle(): string;
    public function setPageTitle(string $title): void;
    public function getCurrentAction(): string;
    public function getLinktree(): array;
}
```

**Priority:** HIGHEST (5,733 usages)

---

## Phase 2: Supporting Services

### 2.1 PathService
```php
interface PathServiceInterface {
    public function getSourcePath(string $relativePath = ''): string;
    public function getBoardPath(string $relativePath = ''): string;
    public function getCachePath(string $relativePath = ''): string;
}
```

### 2.2 UrlService
```php
interface UrlServiceInterface {
    public function action(string $action, array $params = []): string;
    public function profile(int $userId): string;
    public function topic(int $topicId): string;
}
```

---

## Complete Global/Static Variables Inventory

### High Priority (Immediate Migration Candidates)

| Variable | Current Location | Usage Count | Proposed Service | Priority |
|----------|-----------------|-------------|------------------|----------|
| `$context` | `Utils::$context` | 5,733 | `PageContextService`, `AssetService`, `TemplateContextService` | HIGHEST |
| `$modSettings` | `Config::$modSettings` | 2,270 | `ModSettingsService` | HIGH |
| `$sourcedir` | `Config::$sourcedir` | ~30 | `PathService` | MEDIUM |
| `$boarddir` | `Config::$boarddir` | ~25 | `PathService` | MEDIUM |
| `$scripturl` | `Config::$scripturl` | ~200 | `UrlService` | MEDIUM-HIGH |
| `$boardurl` | `Config::$boardurl` | ~50 | `UrlService` | MEDIUM |

### Medium Priority

| Variable | Current Location | Proposed Service | Notes |
|----------|-----------------|------------------|-------|
| `$cachedir` | `Config::$cachedir` | `PathService` | Cache path management |
| `$packagesdir` | `Config::$packagesdir` | `PathService` | Package management |
| `$languagesdir` | `Config::$languagesdir` | `PathService` | Language files |
| `$db_prefix` | `Config::$db_prefix` | `ConfigService` | Database config |
| `$db_type` | `Config::$db_type` | `ConfigService` | Database config |
| `$maintenance` | `Config::$maintenance` | `ConfigService` | Site status |

### Low Priority (Backward Compatibility Layer)

| Variable | Current Location | Status | Notes |
|----------|-----------------|--------|-------|
| `$smcFunc` | `Utils::$smcFunc` | 8 usages | Already being phased out |
| `$user_info` | `User::$me` | Aliased | Use `User::$me` directly |
| `$user_profile` | `User::$profiles` | Aliased | Use `User::$profiles` directly |
| `$txt` | `Lang::$txt` | Active | Consider `LangService` in future |

---

## Implementation Roadmap

### Stage 1: Foundation (Weeks 1-2)
- [ ] Create service interfaces in `Sources/Services/`
- [ ] Implement `ConfigService`
- [ ] Implement `ModSettingsService`
- [ ] Update `Container::init()` to register new services
- [ ] Create service provider pattern for cleaner registration

### Stage 2: Context Breakdown (Weeks 3-5)
- [ ] Implement `AssetService`
- [ ] Implement `PageContextService`
- [ ] Implement `TemplateContextService`
- [ ] Create facade layer in `Utils::$context` to delegate to services
- [ ] Update 2-3 high-traffic Actions to use DI

### Stage 3: Path & URL Services (Weeks 6-7)
- [ ] Implement `PathService`
- [ ] Implement `UrlService`
- [ ] Refactor file inclusion patterns
- [ ] Refactor URL generation patterns

### Stage 4: Gradual Migration (Weeks 8-16)
- [ ] Migrate Admin actions (high value, contained scope)
- [ ] Migrate Profile actions
- [ ] Migrate Board/Topic actions
- [ ] Update templates to use service-provided data

### Stage 5: Testing & Refinement (Weeks 17-20)
- [ ] Comprehensive unit tests for all services
- [ ] Integration tests for DI container
- [ ] Performance benchmarking
- [ ] Documentation updates

---


## Technical Implementation Guide

### Container Configuration

**Current State** (`Sources/Container.php`):
```php
public static function init(): LeagueContainer
{
    $container = new LeagueContainer();

    // Enable auto-wiring
    $container->delegate(new ReflectionContainer());

    // Register core services
    $container->add(DatabaseServiceInterface::class, DatabaseService::class)->setShared(true);

    self::$instance = $container;
    return $container;
}
```

**Proposed Enhanced Configuration:**
```php
public static function init(): LeagueContainer
{
    $container = new LeagueContainer();

    // Enable auto-wiring
    $container->delegate(new ReflectionContainer());

    // Register service providers
    $container->addServiceProvider(new CoreServiceProvider());
    $container->addServiceProvider(new ContextServiceProvider());
    $container->addServiceProvider(new ConfigServiceProvider());

    self::$instance = $container;
    return $container;
}
```

### Service Provider Pattern

Create `Sources/Services/Providers/CoreServiceProvider.php`:
```php
<?php

namespace SMF\Services\Providers;

use League\Container\ServiceProvider\AbstractServiceProvider;
use SMF\Services\{
    ConfigService,
    ConfigServiceInterface,
    ModSettingsService,
    ModSettingsServiceInterface,
    PathService,
    PathServiceInterface,
    UrlService,
    UrlServiceInterface
};

class CoreServiceProvider extends AbstractServiceProvider
{
    protected $provides = [
        ConfigServiceInterface::class,
        ModSettingsServiceInterface::class,
        PathServiceInterface::class,
        UrlServiceInterface::class,
    ];

    public function register(): void
    {
        // Config Service - Shared singleton
        $this->getContainer()
            ->add(ConfigServiceInterface::class, ConfigService::class)
            ->setShared(true);

        // ModSettings Service - Shared singleton
        $this->getContainer()
            ->add(ModSettingsServiceInterface::class, ModSettingsService::class)
            ->setShared(true);

        // Path Service - Shared singleton
        $this->getContainer()
            ->add(PathServiceInterface::class, PathService::class)
            ->setShared(true)
            ->addArgument(ConfigServiceInterface::class);

        // URL Service - Shared singleton
        $this->getContainer()
            ->add(UrlServiceInterface::class, UrlService::class)
            ->setShared(true)
            ->addArgument(ConfigServiceInterface::class);
    }
}
```

---

## Migration Patterns & Examples

### Pattern 1: Constructor Injection (Preferred)

**Before:**
```php
class MyAction implements ActionInterface
{
    public function execute(): void
    {
        $setting = Config::$modSettings['some_setting'];
        $path = Config::$sourcedir . '/SomeFile.php';
        Utils::$context['page_title'] = 'My Page';
    }
}
```

**After:**
```php
class MyAction implements ActionInterface
{
    public function __construct(
        private ModSettingsServiceInterface $modSettings,
        private PathServiceInterface $paths,
        private PageContextServiceInterface $pageContext
    ) {}

    public function execute(): void
    {
        $setting = $this->modSettings->get('some_setting');
        $path = $this->paths->getSourcePath('SomeFile.php');
        $this->pageContext->setPageTitle('My Page');
    }
}
```

### Pattern 2: Container Resolution for Legacy Code

**Helper Function** (`Sources/Services/helpers.php`):
```php
<?php

use SMF\Container;
use SMF\Services\ModSettingsServiceInterface;

if (!function_exists('modSettings')) {
    function modSettings(?string $key = null, mixed $default = null): mixed
    {
        $service = Container::getInstance()->get(ModSettingsServiceInterface::class);

        if ($key === null) {
            return $service;
        }

        return $service->get($key, $default);
    }
}

if (!function_exists('config')) {
    function config(?string $key = null): mixed
    {
        $service = Container::getInstance()->get(ConfigServiceInterface::class);

        if ($key === null) {
            return $service;
        }

        return match($key) {
            'sourcedir' => $service->getSourceDir(),
            'boarddir' => $service->getBoardDir(),
            'scripturl' => $service->getScriptUrl(),
            default => null
        };
    }
}
```

**Usage in Legacy Code:**
```php
// Old way (still works during transition)
$setting = Config::$modSettings['some_setting'];

// New helper function way
$setting = modSettings('some_setting');

// Or get the service
$modSettings = modSettings();
$setting = $modSettings->get('some_setting');
```

---

## Example Service Implementations

### ModSettingsService Implementation

`Sources/Services/ModSettingsService.php`:
```php
<?php

namespace SMF\Services;

use SMF\Config;

class ModSettingsService implements ModSettingsServiceInterface
{
    private array $settings;

    public function __construct()
    {
        // Initially, wrap the static array
        $this->settings = &Config::$modSettings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) ($this->settings[$key] ?? $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->settings[$key] ?? $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return !empty($this->settings[$key]);
    }


    public function getArray(string $key, array $default = []): array
    {
        $value = $this->settings[$key] ?? $default;
        return is_array($value) ? $value : $default;
    }

    public function has(string $key): bool
    {
        return isset($this->settings[$key]);
    }

    public function set(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
    }

    public function getAll(): array
    {
        return $this->settings;
    }

    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->settings[$key] = $value;
        }
    }
}
```

---

## Backward Compatibility Strategy

### Phase 1: Dual Access (Months 1-6)
Both old and new patterns work simultaneously:
```php
// Old way - still works
$value = Config::$modSettings['setting'];

// New way - preferred
$value = $this->modSettings->get('setting');
```

### Phase 2: Deprecation Warnings (Months 7-12)
Add deprecation notices to static access:
```php
class Config
{
    public static function __get($name)
    {
        if ($name === 'modSettings') {
            trigger_error(
                'Direct access to Config::$modSettings is deprecated. Use ModSettingsService instead.',
                E_USER_DEPRECATED
            );
            return self::$modSettings;
        }
    }
}
```

### Phase 3: Migration Complete (Month 13+)
Remove static properties, keep only service-based access.

---

## Testing Strategy

### Unit Tests for Services

`Tests/Services/ModSettingsServiceTest.php`:
```php
<?php

namespace SMF\Tests\Services;

use PHPUnit\Framework\TestCase;
use SMF\Services\ModSettingsService;

class ModSettingsServiceTest extends TestCase
{
    private ModSettingsService $service;

    protected function setUp(): void
    {
        $this->service = new ModSettingsService();
    }

    public function testGetReturnsValue(): void
    {
        $this->service->set('test_key', 'test_value');
        $this->assertEquals('test_value', $this->service->get('test_key'));
    }

    public function testGetReturnsDefault(): void
    {
        $this->assertEquals('default', $this->service->get('nonexistent', 'default'));
    }

    public function testGetIntReturnsInteger(): void
    {
        $this->service->set('number', '42');
        $this->assertSame(42, $this->service->getInt('number'));
    }

    public function testGetBoolReturnsBool(): void
    {
        $this->service->set('enabled', 1);
        $this->assertTrue($this->service->getBool('enabled'));

        $this->service->set('disabled', 0);
        $this->assertFalse($this->service->getBool('disabled'));
    }
}
```

### Integration Tests

`Tests/Integration/ContainerTest.php`:
```php
<?php

namespace SMF\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SMF\Container;
use SMF\Services\ModSettingsServiceInterface;
use SMF\Services\ConfigServiceInterface;

class ContainerTest extends TestCase
{
    public function testContainerResolvesModSettingsService(): void
    {
        $container = Container::getInstance();
        $service = $container->get(ModSettingsServiceInterface::class);

        $this->assertInstanceOf(ModSettingsServiceInterface::class, $service);
    }

    public function testServicesAreSingletons(): void
    {
        $container = Container::getInstance();
        $service1 = $container->get(ModSettingsServiceInterface::class);
        $service2 = $container->get(ModSettingsServiceInterface::class);

        $this->assertSame($service1, $service2);
    }
}
```

---

## Performance Considerations

### Benchmarking Results (Expected)

| Access Pattern | Operations/sec | Memory | Notes |
|---------------|----------------|--------|-------|
| Direct static access | ~10M | Baseline | Current approach |
| Service via DI | ~9.5M | +0.1KB | Negligible overhead |
| Service via container | ~8M | +0.5KB | Acceptable for non-hot paths |
| Helper function | ~7.5M | +1KB | Convenience vs performance |

**Recommendation:** Use constructor injection for hot paths, helper functions for legacy code.

### Optimization Tips

1. **Lazy Loading:** Services are only instantiated when needed
2. **Shared Instances:** All services registered as `setShared(true)`
3. **Avoid Container in Loops:** Inject dependencies in constructor
4. **Cache Service References:** Don't resolve from container repeatedly

---

## Migration Checklist

### For Each Service

- [ ] Define interface in `Sources/Services/`
- [ ] Implement service class
- [ ] Write unit tests
- [ ] Register in service provider
- [ ] Update container initialization
- [ ] Create helper functions (if needed)
- [ ] Document usage in this plan
- [ ] Migrate 2-3 example usages
- [ ] Performance benchmark
- [ ] Code review

### For Each Migrated Component

- [ ] Identify all static/global dependencies
- [ ] Add constructor parameters for services
- [ ] Update instantiation to use container
- [ ] Replace static calls with service calls
- [ ] Update tests to inject mocks
- [ ] Verify functionality
- [ ] Check performance impact

---

## Common Pitfalls & Solutions

### Pitfall 1: Circular Dependencies
**Problem:** ServiceA needs ServiceB, ServiceB needs ServiceA

**Solution:**
- Refactor to remove circular dependency
- Use events/hooks for decoupling
- Introduce a third service that both depend on

### Pitfall 2: Service Instantiation Too Early
**Problem:** Service tries to access Config before it's loaded

**Solution:**
- Use lazy initialization in service constructor
- Defer heavy operations to first method call
- Use factory pattern for complex initialization

### Pitfall 3: Template Access to Services
**Problem:** Templates can't use constructor injection

**Solution:**
```php
// In Action class
public function execute(): void
{
    Utils::$context['page_title'] = $this->pageContext->getPageTitle();
    Utils::$context['services'] = [
        'assets' => $this->assets,
        'urls' => $this->urls,
    ];
}

// In template
echo $context['services']['urls']->action('profile', ['u' => $user_id]);
```

---

## Success Metrics

### Code Quality Metrics
- [ ] Reduce static property access by 80%
- [ ] Increase test coverage to 70%+
- [ ] Zero new static property additions
- [ ] All new Actions use DI

### Performance Metrics
- [ ] Page load time impact < 5%
- [ ] Memory usage increase < 10%
- [ ] No degradation in database query performance

### Developer Experience
- [ ] Onboarding documentation complete
- [ ] IDE autocomplete works for all services
- [ ] Clear migration examples for each pattern
- [ ] Positive team feedback on new patterns

---

## Conclusion

This migration plan provides a structured, gradual approach to modernizing SMF3's architecture by:

1. **Prioritizing high-impact changes** (Utils::$context, Config::$modSettings)
2. **Maintaining backward compatibility** throughout the transition
3. **Using proven patterns** (Service Provider, Constructor Injection)
4. **Providing clear examples** for each migration scenario
5. **Ensuring testability** with comprehensive test coverage

The migration will span approximately 20 weeks, with each phase building on the previous one. The end result will be a more maintainable, testable, and modern codebase that's easier to extend and debug.

**Next Steps:**
1. Review and approve this plan
2. Create initial service interfaces
3. Implement first service (ModSettingsService)
4. Migrate one Action as proof of concept
5. Iterate and refine based on learnings
