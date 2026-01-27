# SMF3 Dependency Injection Migration Documentation

## 📚 Documentation Overview

This directory contains comprehensive documentation for migrating SMF3 from static/global variables to modern Dependency Injection using League\Container.

### Documents

1. **[DI_MIGRATION_SUMMARY.md](DI_MIGRATION_SUMMARY.md)** - Start here!
   - Executive summary
   - Quick overview of the problem and solution
   - High-level overview and benefits
   - Best for: Quick overview

2. **[DEPENDENCY_INJECTION_MIGRATION_PLAN.md](DEPENDENCY_INJECTION_MIGRATION_PLAN.md)** - Complete technical plan
   - Detailed service interfaces and implementations
   - Complete global/static variable inventory
   - Phase-by-phase implementation roadmap
   - Code examples and patterns
   - Testing strategy
   - Performance considerations
   - Best for: Developers implementing the migration

3. **[DI_MIGRATION_QUICK_REFERENCE.md](DI_MIGRATION_QUICK_REFERENCE.md)** - Daily reference guide
   - Quick lookup table for common migrations
   - Before/after code examples
   - Service method reference
   - Migration patterns
   - Best for: Developers actively migrating code

## 🎯 Quick Start

### For Developers Starting Migration

1. Read the [Summary](DI_MIGRATION_SUMMARY.md) to understand the "why"
2. Review the [Quick Reference](DI_MIGRATION_QUICK_REFERENCE.md) for common patterns
3. Consult the [Full Plan](DEPENDENCY_INJECTION_MIGRATION_PLAN.md) for detailed implementation

### For Code Reviewers

Use the [Quick Reference](DI_MIGRATION_QUICK_REFERENCE.md) to verify migrations follow the correct patterns.

## 📊 Key Statistics

- **Total static/global usages**: ~8,300+
- **Highest priority**: `Utils::$context` (5,733 usages)
- **Second priority**: `Config::$modSettings` (2,270 usages)
- **Risk level**: Low (backward compatible approach)

## 🏗️ Architecture Overview

```
Static Variables (Current)          Services (Target)
├── Config::$modSettings      →     ModSettingsService
├── Config::$sourcedir        →     PathService
├── Config::$scripturl        →     UrlService
└── Utils::$context           →     AssetService
                                    PageContextService
                                    TemplateContextService
```

## 🚀 Migration Phases

1. **Foundation**: Create service interfaces and core services
2. **Context Breakdown**: Split Utils::$context into focused services
3. **Supporting Services**: Path and URL services
4. **Gradual Migration**: Migrate Actions and components
5. **Testing & Refinement**: Comprehensive testing

## 💡 Example Migration

**Before:**
```php
class ProfileAction {
    public function execute() {
        $username = Config::$modSettings['default_username'];
        Utils::$context['page_title'] = 'Profile';
    }
}
```

**After:**
```php
class ProfileAction {
    public function __construct(
        private ModSettingsServiceInterface $modSettings,
        private PageContextServiceInterface $pageContext
    ) {}

    public function execute() {
        $username = $this->modSettings->getString('default_username');
        $this->pageContext->setPageTitle('Profile');
    }
}
```

## ✅ Benefits

- **Testability**: Easy to mock dependencies in unit tests
- **Type Safety**: IDE autocomplete and type checking
- **Clear Dependencies**: Constructor shows what a class needs
- **Maintainability**: Changes isolated to service implementations
- **Modern Architecture**: Industry-standard dependency injection

## 🔧 Tools & Technologies

- **DI Container**: League\Container (already included)
- **Pattern**: Service Provider pattern
- **Injection**: Constructor injection (preferred)
- **Backward Compatibility**: Helper functions during transition

## 🤝 Contributing

When migrating code:
1. Follow patterns in the Quick Reference
2. Write tests for migrated code
3. Update documentation if you discover new patterns
4. Submit PRs with clear before/after examples

## 📞 Questions?

- Review the [Full Plan](DEPENDENCY_INJECTION_MIGRATION_PLAN.md) for detailed answers
- Check the [Quick Reference](DI_MIGRATION_QUICK_REFERENCE.md) for common scenarios
- Consult the team lead for architectural decisions

## 🗺️ Roadmap

- [x] Document current state and usage statistics
- [x] Design service architecture
- [x] Create comprehensive migration plan
- [ ] Implement proof of concept (ModSettingsService)
- [ ] Migrate first Action as example
- [ ] Execute gradual migration

---

**Last Updated**: 2026-01-27
**Status**: Planning Phase

