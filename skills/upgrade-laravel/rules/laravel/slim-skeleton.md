---
title: Optional Migration to the Laravel 11+ Application Structure
tags: laravel, bootstrap-app, middleware, exceptions, providers, event-discovery
---

## Optional Migration to the Laravel 11+ Structure

Only when the user asks for it, and only after the version upgrade is green and committed. Laravel 11, 12 and 13 all run the Laravel 10 structure (`app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, `RouteServiceProvider`, providers listed in `config/app.php`). The official 11.x guide recommends against migrating as part of the upgrade.

API checked against `laravel/framework` 11.57 (`Illuminate\Foundation\Configuration\ApplicationBuilder`, `Middleware`, `Exceptions`).

### Where Things Move

| Laravel 10 location | Laravel 11+ location |
|---|---|
| `app/Http/Kernel.php` `$middleware`, `$middlewareGroups`, `$middlewareAliases` | `->withMiddleware(function (Middleware $middleware) { ... })`: `append()`, `prepend()`, `web(append: [...])`, `api(...)`, `alias([...])`, `priority([...])` |
| `VerifyCsrfToken::$except`, `EncryptCookies::$except`, `TrustProxies`, `TrustHosts` | `$middleware->validateCsrfTokens(except: [...])` (13: `preventRequestForgery(except: [...])`), `encryptCookies(except: [...])`, `trustProxies(at: ...)`, `trustHosts(...)` |
| `Authenticate::redirectTo()`, `RedirectIfAuthenticated` | `$middleware->redirectGuestsTo(...)`, `redirectUsersTo(...)` |
| `app/Exceptions/Handler.php` `register()`, `$dontReport`, `$dontFlash` | `->withExceptions(function (Exceptions $exceptions) { ... })`: `report()`, `render()`, `dontReport()`, `dontFlash()`, `shouldRenderJsonWhen()` |
| `app/Console/Kernel.php` `schedule()` | `routes/console.php` with the `Schedule` facade, or `->withSchedule(function (Schedule $schedule) { ... })` |
| `app/Console/Kernel.php` `commands()` | Commands in `app/Console/Commands` are discovered; others via `->withCommands([...])` |
| `RouteServiceProvider` | `->withRouting(web: ..., api: ..., commands: ..., channels: ..., health: '/up', then: fn () => ...)`; `apiPrefix:` defaults to `'api'` |
| `config/app.php` `providers` | `bootstrap/providers.php` |
| `EventServiceProvider::$listen` | Event discovery (see below), or `Event::listen()` in `AppServiceProvider::boot()` |

`routes/api.php` and `routes/channels.php` are not in the 11 skeleton; `php artisan install:api` and `php artisan install:broadcasting` add them. Config files the skeleton dropped still work if the app has them; `php artisan config:publish` restores framework defaults when needed.

Move one area at a time and run the suite after each. Custom middleware logic (a `TrustProxies` subclass with `$headers`, an `Authenticate` that returns different URLs per guard) must move with its behaviour intact; compare request handling before and after rather than assuming the builder call is equivalent.

### Event Discovery Turns On

`Application::configure()` calls `withEvents()`, which registers the framework's own `EventServiceProvider` with discovery enabled for `app/Listeners`. In the Laravel 10 structure discovery was off: `shouldDiscoverEvents()` returns `true` only for the framework class itself, not for an app subclass that doesn't override it.

**Incorrect:** moving `EventServiceProvider::$listen` into `Event::listen()` calls and deleting the provider. Every listener in `app/Listeners` with a typed `handle(OrderShipped $event)` is now registered twice: once by discovery, once by hand. Mail goes out twice.

**Correct:** rely on discovery for listeners in `app/Listeners` and only register the rest by hand, or keep the explicit registrations and turn discovery off with `->withEvents(discover: false)`. Then check with `php artisan event:list` that each listener appears once.

### Keep It Honest

- Delete an old file only after everything it configured has moved and the suite passes.
- Packages that register middleware by group name (`web`, `api`) keep working; packages that expect `App\Http\Kernel` to exist (some older ones type-hint it) may not. Grep `vendor/` for `App\Http\Kernel` before deleting it.
- Report it as a separate commit: "Adopt Laravel 11 application structure".
