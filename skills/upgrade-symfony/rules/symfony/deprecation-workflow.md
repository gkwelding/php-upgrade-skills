---
title: Symfony Deprecation-Driven Upgrades
tags: symfony, deprecations, composer, flex, extra-symfony-require
---

## Symfony Deprecation-Driven Upgrades

Symfony's major releases are the last minor minus its deprecated code: 7.0 is 6.4 without deprecations, 8.0 is 7.4 without deprecations (the 8.0 UPGRADE file says so). So the work happens on the last minor of the current major, and the composer bump comes last. Checked against `symfony/framework-bundle` 6.4.47, 7.4.20 and 8.1.8 and the official `setup/upgrade_major` docs.

### The Order

1. **Get to the last minor of the current major** (6.4 or 7.4) if not there. That's a minor upgrade: `extra.symfony.require` to `"6.4.*"`, `composer update "symfony/*" --with-all-dependencies`. Read the `UPGRADE-6.x.md` / `UPGRADE-7.x.md` files for each skipped minor (they list the few `[BC BREAK]` items). Green suite, commit.
2. **Remove every deprecation** on that minor (below). Commit.
3. **Bump the major**: `extra.symfony.require` to `"7.4.*"` (or `"8.0.*"`, `"8.1.*"`), `composer update "symfony/*" --with-all-dependencies`. You can go straight to the newest minor of the new major (6.4 to 7.4); the docs allow skipping minors.
4. `rm -rf var/cache/*` (not `cache:clear`, which may not boot after a major bump), recipes (`flex-recipes.md`), the version's rule file, suite. Commit.
5. For 7.4 to 8.x, repeat from step 2 on 7.4: 7.1 to 7.4 deprecated a lot that 8.0 removes.

### `extra.symfony.require`

Flex projects pin all `symfony/*` packages through one setting:

```json
"require": {
    "symfony/console": "7.4.*",
    "symfony/framework-bundle": "7.4.*"
},
"extra": {
    "symfony": {
        "require": "7.4.*"
    }
}
```

Change `extra.symfony.require` and every `symfony/*` constraint that names the old version (`6.4.*`). Packages with their own versioning (`symfony/monolog-bundle`, `symfony/ux-*`, `symfony/maker-bundle`, `symfony/polyfill-*`, `symfony/flex`) keep their own constraints; update them only if Composer says they block.

### Finding Deprecations

Fix deprecations from your code first; deprecations from vendor code are usually fixed by updating that package.

- **Tests.** With PHPUnit 10+ run `vendor/bin/phpunit --display-all-issues`. The current `phpunit/phpunit` recipe's `phpunit.dist.xml` sets `<source ignoreSuppressionOfDeprecations="true" ignoreIndirectDeprecations="true">` and registers `trigger_deprecation` and Doctrine's `Deprecation::trigger` as `<deprecationTrigger>`s, so PHPUnit reports deprecations your code causes. Older projects lack this and see nothing (`shared/verification.md`). Projects using `symfony/phpunit-bridge` get its "Remaining deprecation notices" summary instead.
- **Container.** `bin/console debug:container --deprecations` lists deprecations raised while compiling the container: config options, service definitions, bundle extensions. Tests don't always catch these.
- **Runtime.** The web profiler's Logs panel and the dev log, for pages and commands the tests don't reach.

Messages start `Since symfony/<package> <version>:`. Everything with a version up to the current minor must go before the bump.

### Native Return Types (6.4 to 7.0)

Symfony 7 added native return types. A class extending or implementing a Symfony type without the return type is a fatal "must be compatible" error on 7.0. On 6.4 it is a deprecation. Symfony ships a script:

```
composer dump-autoload -o        # needs a full class map; "exclude-from-classmap" must not hide classes
vendor/bin/patch-type-declarations
```

Without `SYMFONY_PATCH_TYPE_DECLARATIONS` it runs with `force=2`: every possible return type is added, which is what the docs recommend for applications. For a bundle or library other people extend, adding return types is itself a BC break for them; the docs' two-release process (`force=1`, then `force=phpdoc`, then `force=2` in the next major) applies. Run the code style fixer afterwards. Rector's composer-based Symfony set adds many of the same return types (`shared/rector.md`).

### Rector

```php
->withComposerBased(symfony: true)
->withAttributesSets(symfony: true, doctrine: true) // 6.4: annotations to attributes
```

Rules are bound to installed versions. Some are useful before the bump (on 6.4: `Annotation\Route` to `Attribute\Route`, `UriSigner` and `FileLinkFormatter` moves), others only after it (`AddVoteArgumentToVoteOnAttributeRector`, `ConstraintOptionsToNamedArgumentsRector` and `CommandDefaultNameAndDescriptionToAsCommandAttributeRector` need 7.3+). On 7.4, run Rector again before the 8 bump.
