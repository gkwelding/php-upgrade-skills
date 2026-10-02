---
title: Flex Recipe Updates
tags: symfony, flex, recipes, configuration
---

## Flex Recipe Updates

After a Symfony bump, recipes for `symfony/framework-bundle`, `symfony/security-bundle`, `phpunit/phpunit`, `doctrine/doctrine-bundle` and others often have newer versions: changed config files, new defaults, new files. Commands checked against `symfony/flex` 2.11.

### Commands

```
composer recipes                                   # installed recipes; outdated ones are marked
composer recipes symfony/framework-bundle          # details for one recipe
composer recipes:update symfony/framework-bundle   # update one recipe
composer recipes:update                            # choose from the outdated ones
```

`recipes:update` computes the difference between the recipe version you installed and the current one, and applies it as a patch. Conflicts appear as normal git conflict markers. If the recipe history can't be found, Flex suggests `composer recipes:install <package> --force -v`, which overwrites the recipe's files with the current version: treat it like a fresh copy and merge your changes back by hand.

### Rules

- **Clean tree first, one recipe per commit.** The diff of each recipe update must be reviewable on its own.
- **Read every hunk.** Recipes update defaults for new projects. A hunk that removes a setting the project added (a firewall, a `framework.session` option, a `when@prod` block, a custom `phpunit.dist.xml` `<php>` value) undoes project behaviour. Keep the project's setting and take only the new parts.
- **Conflicts are decisions, not chores.** Resolve them by keeping the project's intent; ask if unclear.
- **New default values change behaviour.** For example the 7.0 defaults table (`6.4-to-7.md`) only applies to options the app doesn't set. A recipe that starts setting one, or stops setting one, changes the app.
- **Not mandatory.** An outdated recipe still works. If a recipe diff is large and the step is otherwise green, it's acceptable to report it and leave it for a separate change.

`symfony.lock` records the installed recipe versions. `recipes:update` updates it and stages it, along with the patched files, in git; review with `git diff --cached` and commit it with the recipe change.
