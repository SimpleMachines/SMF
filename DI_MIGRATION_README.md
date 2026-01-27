# SMF3 Dependency Injection Migration Documentation

## 📚 Documentation Overview

This directory contains comprehensive documentation for migrating SMF3 from static/global variables to modern Dependency Injection using League\Container.

### Documents

1. **[DI_MIGRATION_SUMMARY.md](DI_MIGRATION_SUMMARY.md)** - Start here!
   - Executive summary
   - Quick overview of the problem and solution
   - High-level timeline and benefits
   - Best for: Management, stakeholders, quick overview

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

### For Project Managers

The [Summary](DI_MIGRATION_SUMMARY.md) provides timeline, resources, and success metrics.

## 📊 Key Statistics

- **Total static/global usages**: ~8,300+
- **Highest priority**: `Utils::$context` (5,733 usages)
- **Second priority**: `Config::$modSettings` (2,270 usages)
- **Estimated timeline**: 20 weeks
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

1. **Foundation** (Weeks 1-2): Create service interfaces and core services
2. **Context Breakdown** (Weeks 3-5): Split Utils::$context into focused services
3. **Supporting Services** (Weeks 6-7): Path and URL services
4. **Gradual Migration** (Weeks 8-16): Migrate Actions and components
5. **Testing & Refinement** (Weeks 17-20): Comprehensive testing

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

## 📈 Success Metrics

- Reduce static property access by 80%
- Increase test coverage to 70%+
- Page load time impact < 5%
- All new code uses DI

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
- [ ] Roll out to team
- [ ] Execute full migration plan

---

**Last Updated**: 2026-01-27
**Status**: Planning Phase
**Next Milestone**: Proof of Concept

