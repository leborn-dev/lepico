# Secure dependency migration

This maintenance update replaces end-of-life Twig 1 and Symfony YAML 2 dependencies. Back up an existing site and test its themes and plugins before upgrading.

- PHP **8.2+** and the `dom`, `mbstring`, and **`intl`** extensions are required. PHP 5/7 and PHP 8.0/8.1 are no longer supported by this dependency set.
- Composer resolves Twig **3.x**, Symfony YAML **7.4 LTS**, stable Parsedown **1.8.x**, and stable Parsedown Extra **0.9.x**. Keep Composer's advisory blocking enabled; no advisory exceptions are required.
- Core Twig integration and the example `DummyPlugin` use namespaced classes. Update third-party plugins, including `onTwigRegistered()` type hints: `Twig_Environment` → `\Twig\Environment`, `Twig_Extension` → `\Twig\Extension\AbstractExtension`, `Twig_SimpleFilter` → `\Twig\TwigFilter`, `Twig_SimpleFunction` → `\Twig\TwigFunction`, `Twig_Error_Runtime` → `\Twig\Error\RuntimeError`, and `Twig_Markup` → `\Twig\Markup`.
- Twig 3 removed legacy syntax such as `{% for page in pages if condition %}`. Put `{% if condition %}` inside the loop instead. Review existing themes against the [Twig 3 documentation](https://twig.symfony.com/doc/3.x/) and [extension API](https://twig.symfony.com/doc/3.x/advanced.html). Pico's key-path `map` filter, `sort_by`, `markdown`, `content`, `link`, `url` and `pages()` remain available. Pico's `map('title')` keeps its existing behavior and overrides Twig's built-in `map` filter.
- YAML now follows the current parser's validation rules. Quote scalars beginning with `%`, for example `date_format: '%D %T'`; the supplied config template is corrected. Invalid YAML raises a parser exception. Custom parser integrations should use [Symfony YAML's current API](https://symfony.com/doc/7.4/components/yaml.html).
- Existing `%` date formats now use [`php81_bc/strftime`](https://github.com/alphp/strftime) and ICU instead of PHP's deprecated `strftime()`. The configured system `locale` is passed to the formatter, and the default `%D %T` keeps its two-digit year. Localized names and `%c`, `%x`, `%X` can differ slightly from libc output. Ensure the host has the required system locales and ICU locale data installed. Quoted and unquoted integer `Time` headers are normalized to validated UNIX timestamps. A page's explicit `Formatted Date` still takes precedence.
- A minimal MIT-licensed default theme is now bundled, so a fresh clone can render the sample pages. Keep your custom theme when upgrading. This starter theme escapes titles, navigation labels, descriptions and URLs through Twig. Parsed Markdown `content` remains trusted HTML, as before: only trusted authors should edit content/templates; this is not an untrusted Markdown sandbox.

Run `composer install --no-plugins --no-scripts`, `composer audit`, and `composer test`. The tests cover YAML config/frontmatter/date handling, Markdown, Twig helper filters/functions, plugin registration, escaping, starter pages, and actual HTTP 200/404 responses. The French locale regression runs when `fr_FR.UTF-8` is installed and reports a skip otherwise.

Upstream support details: [Symfony 7.4 LTS](https://symfony.com/releases/7.4), [Twig prerequisites](https://twig.symfony.com/doc/3.x/intro.html), [Parsedown Extra](https://github.com/erusev/parsedown-extra).

## Legacy era and alternate-digit date formats

The libc-specific **E/O modifiers are not supported for automatic date formatting** by the new formatter. Examples include `%Od %B %EY`, `%Ec`, and `%Ex`. LePico does not silently remove the modifiers: that could change Japanese era dates or alternate digits into a different date representation.

When an affected page has no explicit `Formatted Date`, LePico keeps rendering the page and retains its original `date` and `time`, but temporarily **omits `date_formatted`**. Themes displaying that value will show no formatted date until the setting/content is migrated. One actionable diagnostic per Pico instance is written to PHP's error log, listing the unsupported modifiers. Literal escaped text such as `%%Ec` remains supported.

Before upgrading, preserve the desired rendered date from the existing site in each affected page's frontmatter, for example:

```yaml
---
Date: 2026-10-18
Formatted Date: '令和8年10月18日'
---
```

This existing override preserves the exact era/number representation and takes precedence even while the old E/O `date_format` remains configured. For automatically calculated dates, deliberately select a supported format such as `date_format: '%Y-%m-%d'` only if Gregorian numeric dates are appropriate for your site. Do not mechanically strip E/O modifiers. Plugins requiring dynamic alternate-calendar formatting can generate the `date_formatted` metadata explicitly with `IntlDateFormatter`, selecting the intended locale/calendar and ICU pattern; libc E/O formats and ICU patterns are not interchangeable.
