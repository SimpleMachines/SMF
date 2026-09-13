# SMF Dependency Injection Documentation

Welcome to the SMF Dependency Injection documentation! This directory contains comprehensive guides for understanding and using the new service-based architecture.

## Documentation Index

### 1. [Dependency Injection Guide](DEPENDENCY_INJECTION_GUIDE.md)
**Complete guide for using DI in SMF**

- Step-by-step tutorial for creating services
- Complete examples with code
- Testing strategies
- Best practices and patterns
- Troubleshooting guide

**Best for**: Developers who want to create new features using DI or understand how the system works.

### 2. [Refactored Services Summary](REFACTORED_SERVICES_SUMMARY.md)
**Detailed documentation of all refactored services**

- Complete list of refactored services
- API reference for each service
- Independence analysis
- Usage examples
- Migration patterns
- Performance considerations

**Best for**: Developers who need a quick reference for using existing services.

## Quick Start

### For New Features

Create a new service with dependency injection:

```php
<?php

namespace SMF\Actions;

use SMF\Services\Contracts\SettingsServiceInterface;
use SMF\Services\Contracts\ModSettingsServiceInterface;

class MyNewAction
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private ModSettingsServiceInterface $modSettings
    ) {}

    public function execute(): void
    {
        $boardDir = $this->settings->getBoardDir();
        $enabled = $this->modSettings->get('feature_enabled', false);

        // Your logic here...
    }
}
```

Register in `Sources/Infrastructure/ServicesList.php`:

```php
use SMF\Actions\MyNewAction;
use SMF\Services\SettingsService;
use SMF\Services\ModSettingsService;

return [
    MyNewAction::class => [
        'arguments' => [
            SettingsService::class,
            ModSettingsService::class,
        ],
        'shared' => false,
    ],
];
```

Use your service:

```php
use SMF\Infrastructure\Container;

$action = Container::get(MyNewAction::class);
$action->execute();
```

### WARNING: For Legacy Code Only

**Facades are deprecated for new code. Use constructor injection or Container instead.**

For existing code that hasn't been refactored yet:

```php
// DEPRECATED - Only use in existing code
$settings = \SMF\Config::getSettingsService();
$modSettings = \SMF\Config::getModSettingsService();

// Use them
$boardUrl = $settings->getBoardUrl();
$enabled = $modSettings->get('feature_enabled', false);
```

**When refactoring existing code, migrate to:**
- Constructor injection (preferred)
- Container direct access (if injection not possible)

## Refactoring Status

| Service | Status | Documentation |
|---------|--------|---------------|
| **ErrorHandlerService** | Complete | [View Details](REFACTORED_SERVICES_SUMMARY.md#1-errorhandlerservice) |
| **SettingsService** | Complete | [View Details](REFACTORED_SERVICES_SUMMARY.md#2-settingsservice) |
| **ModSettingsService** | Complete | [View Details](REFACTORED_SERVICES_SUMMARY.md#3-modsettingsservice) |

## Additional Resources

### In This Repository

- **[CONFIG_SERVICES_MIGRATION_GUIDE.md](../CONFIG_SERVICES_MIGRATION_GUIDE.md)** - Configuration services migration guide
- **[CONFIG_SERVICES_INDEPENDENCE_SUMMARY.md](../CONFIG_SERVICES_INDEPENDENCE_SUMMARY.md)** - Architecture and independence details

### External Resources

- **[SMF DI Migration Plan](https://github.com/MissAllSunday/SMF2.1/blob/Dependency-injection-proposal/DEPENDENCY_INJECTION_MIGRATION_PLAN.md)** - Overall migration strategy and roadmap

## Common Tasks

### Creating a New Service

1. Create interface in `Sources/Services/Contracts/`
2. Create implementation in `Sources/Services/`
3. Register in `Sources/Infrastructure/ServicesList.php`
4. Write tests
5. Update documentation

[Full guide →](DEPENDENCY_INJECTION_GUIDE.md#complete-example-building-a-new-feature)

### Using Existing Services

```php
use SMF\Services\Contracts\ModSettingsServiceInterface;

class MyClass
{
    public function __construct(
        private ModSettingsServiceInterface $modSettings
    ) {}
}
```

[Full guide →](DEPENDENCY_INJECTION_GUIDE.md#using-dependency-injection)

### Testing with Mocks

```php
$mock = $this->createMock(ModSettingsServiceInterface::class);
$mock->method('get')->willReturn('test_value');

$service = new MyService($mock);
```

[Full guide →](DEPENDENCY_INJECTION_GUIDE.md#testing-with-dependency-injection)

## FAQ

**Q: Do I need to refactor existing code to use services?**
A: No! Existing code continues to work. Only new code should use DI. Refactor existing code gradually when you touch it.

**Q: How should I access services in new code?**
A: Use constructor injection (preferred) or Container direct access. **Do NOT use facades in new code.**

**Q: Can I still use Config::getSettingsService() in existing code?**
A: Yes, but only in existing code that hasn't been refactored yet. This pattern is deprecated for new code.

**Q: Can I create multiple instances of a service?**
A: Yes! Use `'shared' => false` in ServicesList.php. Most services use `'shared' => true` (singleton) for performance.

**Q: What if I need a service that doesn't exist yet?**
A: Create it! Follow the guide in [DEPENDENCY_INJECTION_GUIDE.md](DEPENDENCY_INJECTION_GUIDE.md#complete-example-building-a-new-feature)

## Contributing

When adding or modifying services:

1. Follow existing patterns
2. Write comprehensive tests
3. Update this documentation
4. Maintain backward compatibility
5. Use interfaces, not concrete classes
6. Keep constructors simple

## Documentation Standards

When documenting new services:

- Add to [REFACTORED_SERVICES_SUMMARY.md](REFACTORED_SERVICES_SUMMARY.md)
- Include usage examples
- Document all public methods
- Show testing examples

---

**Last Updated**: 2026-04-02
**Current Version**: SMF 3.0 Alpha 4
**Services Refactored**: 3 core services complete (ErrorHandler, Settings, ModSettings)

