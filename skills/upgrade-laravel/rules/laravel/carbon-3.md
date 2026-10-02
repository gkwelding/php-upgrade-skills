---
title: Carbon 2 to 3
tags: laravel, carbon, dates, diffInDays
---

## Carbon 2 to 3

Laravel 11 accepts Carbon 2 or 3; Laravel 12 and 13 require Carbon 3 (`nesbot/carbon ^3.8.4`). The change that bites is silent: no error, different numbers.

### `diffIn*()` Is Signed and Fractional

| | Carbon 2.73 | Carbon 3.14 |
|---|---|---|
| Signature | `diffInDays($date = null, $absolute = true)` | `diffInDays($date = null, bool $absolute = false, bool $utc = false): float` |
| `$start->diffInDays($end)` (9.5 days later) | `9` | `9.5` |
| `$end->diffInDays($start)` | `9` | `-9.5` |
| `$start->diffInHours($end)` | `228` (int) | `228.0` (float) |

The same applies to `diffInYears()`, `diffInQuarters()`, `diffInMonths()`, `diffInWeeks()`, `diffInHours()`, `diffInMinutes()`, `diffInSeconds()`, `diffInMilliseconds()` and `diffInMicroseconds()`: all return `float` and default to signed. `diffInWeekdays()`, `diffInWeekendDays()` and the `*Filtered()` methods still return `int`, but are signed by default too.

Typical breakage: "days remaining" showing `-3.25`, `if ($a->diffInDays($b) > 30)` flipping because the sign changed, `str_repeat()` or array offsets receiving a float, `assertSame(9, ...)` failing against `9.5`, integer columns receiving fractions.

**Incorrect:** assuming the old behaviour still holds.

```php
$daysOverdue = $invoice->due_at->diffInDays(now()); // Carbon 3: 12.4 when overdue, -3.5 when not yet due
```

**Correct:** say what you mean: direction, absolute value and rounding.

```php
$daysOverdue = (int) $invoice->due_at->diffInDays(now(), true); // Carbon 2 behaviour: absolute, truncated
```

Keep the old semantics unless the user wants the new ones. `(int)` truncates towards zero like Carbon 2 did; use `floor()` / `round()` only if that was the intent.

### Finding the Call Sites

```
grep -rnE "diffIn[A-Za-z]*\(" app src tests resources
```

Check each: which way round are the dates, does the caller need an int, does it need absolute. Blade templates and Livewire components count. Tests asserting exact integers are the fastest way to find them; production code without tests needs reading.

### Other Packages

Packages that type against Carbon 2 (`nesbot/carbon ^2`) block the upgrade. `composer why-not nesbot/carbon ^3.0` lists them. Treat them like any other blocker (`shared/upgrade-loop.md`).
