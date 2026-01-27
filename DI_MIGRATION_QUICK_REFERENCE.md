# SMF3 Dependency Injection - Quick Reference Guide

## Service Lookup Table

Quick reference for migrating from static/global variables to services.

### Configuration & Settings

| Old Pattern | New Service | Method | Example |
|------------|-------------|--------|---------|
| `Config::$modSettings['key']` | `ModSettingsServiceInterface` | `get('key')` | `$this->modSettings->get('key')` |
| `Config::$sourcedir` | `PathServiceInterface` | `getSourcePath()` | `$this->paths->getSourcePath()` |
| `Config::$boarddir` | `PathServiceInterface` | `getBoardPath()` | `$this->paths->getBoardPath()` |
| `Config::$cachedir` | `PathServiceInterface` | `getCachePath()` | `$this->paths->getCachePath()` |
| `Config::$scripturl` | `UrlServiceInterface` | `getScriptUrl()` | `$this->urls->getScriptUrl()` |
| `Config::$boardurl` | `UrlServiceInterface` | `getBoardUrl()` | `$this->urls->getBoardUrl()` |

### Context Variables

| Old Pattern | New Service | Method | Example |
|------------|-------------|--------|---------|
| `Utils::$context['page_title']` | `PageContextServiceInterface` | `getPageTitle()` | `$this->pageContext->getPageTitle()` |
| `Utils::$context['page_title'] = 'X'` | `PageContextServiceInterface` | `setPageTitle('X')` | `$this->pageContext->setPageTitle('X')` |
| `Utils::$context['linktree']` | `PageContextServiceInterface` | `getLinktree()` | `$this->pageContext->getLinktree()` |
| `Utils::$context['current_action']` | `PageContextServiceInterface` | `getCurrentAction()` | `$this->pageContext->getCurrentAction()` |
| `Utils::$context['javascript_files'][]` | `AssetServiceInterface` | `addJavaScriptFile()` | `$this->assets->addJavaScriptFile('file.js')` |
| `Utils::$context['css_files'][]` | `AssetServiceInterface` | `addCssFile()` | `$this->assets->addCssFile('style.css')` |
| `Utils::$context['html_headers'] .=` | `AssetServiceInterface` | `addHtmlHeader()` | `$this->assets->addHtmlHeader('<meta...')` |
| `Utils::$context['template_layers'][]` | `TemplateContextServiceInterface` | `addLayer()` | `$this->templateContext->addLayer('layer')` |
| `Utils::$context['sub_template']` | `TemplateContextServiceInterface` | `setSubTemplate()` | `$this->templateContext->setSubTemplate('tpl')` |

## Common Migration Patterns

### Pattern 1: Simple Action Migration

**Before:**
```php
class MyAction implements ActionInterface
{
    public function execute(): void
    {
        $enabled = !empty(Config::$modSettings['feature_enabled']);
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
        private PageContextServiceInterface $pageContext
    ) {}

    public function execute(): void
    {
        $enabled = $this->modSettings->getBool('feature_enabled');
        $this->pageContext->setPageTitle('My Page');
    }
}
```

### Pattern 2: File Path Construction

**Before:**
```php
require_once Config::$sourcedir . '/SomeFile.php';
$file = Config::$cachedir . '/data.cache';
```

**After:**
```php
require_once $this->paths->getSourcePath('SomeFile.php');
$file = $this->paths->getCachePath('data.cache');
```

### Pattern 3: URL Generation

**Before:**
```php
$url = Config::$scripturl . '?action=profile;u=' . $userId;
```

**After:**
```php
$url = $this->urls->action('profile', ['u' => $userId]);
// or
$url = $this->urls->profile($userId);
```

### Pattern 4: Asset Loading

**Before:**
```php
Utils::$context['javascript_files'][] = 'script.js';
Utils::$context['css_files'][] = 'style.css';
Utils::$context['html_headers'] .= '<meta name="description" content="...">';
```

**After:**
```php
$this->assets->addJavaScriptFile('script.js');
$this->assets->addCssFile('style.css');
$this->assets->addHtmlHeader('<meta name="description" content="...">');
```

## Helper Functions (Transition Period)

For legacy code that can't easily use DI:

```php
// Get a setting
$value = modSettings('setting_name', 'default');

// Get the service
$modSettings = modSettings();
$value = $modSettings->get('setting_name');

// Get config value
$sourcedir = config('sourcedir');
```

## Service Registration

Add to `Container::init()` or use Service Providers:

```php
// Direct registration
$container->add(ModSettingsServiceInterface::class, ModSettingsService::class)
    ->setShared(true);

// With dependencies
$container->add(PathServiceInterface::class, PathService::class)
    ->setShared(true)
    ->addArgument(ConfigServiceInterface::class);
```

## Testing with Services

```php
class MyActionTest extends TestCase
{
    public function testExecute(): void
    {
        // Create mocks
        $modSettings = $this->createMock(ModSettingsServiceInterface::class);
        $pageContext = $this->createMock(PageContextServiceInterface::class);
        
        // Set expectations
        $modSettings->expects($this->once())
            ->method('getBool')
            ->with('feature_enabled')
            ->willReturn(true);
            
        $pageContext->expects($this->once())
            ->method('setPageTitle')
            ->with('My Page');
        
        // Test
        $action = new MyAction($modSettings, $pageContext);
        $action->execute();
    }
}
```

## Priority Order for Migration

1. **HIGHEST**: `Utils::$context` (5,733 usages) → Break into AssetService, PageContextService, TemplateContextService
2. **HIGH**: `Config::$modSettings` (2,270 usages) → ModSettingsService
3. **MEDIUM-HIGH**: `Config::$scripturl` (~200 usages) → UrlService
4. **MEDIUM**: Path variables (~30-50 usages each) → PathService
5. **LOW**: `Utils::$smcFunc` (8 usages) → Already being phased out

## Checklist for Each Migration

- [ ] Identify all static/global dependencies in the class
- [ ] Add constructor parameters for required services
- [ ] Update class instantiation to use container or auto-wiring
- [ ] Replace all static calls with service method calls
- [ ] Update or create tests with mocked services
- [ ] Verify functionality works as expected
- [ ] Check for performance regressions

