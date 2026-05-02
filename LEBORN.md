# About this fork

`LePico` is a [Leborn](https://github.com/leborn-dev) fork of [Pico CMS](https://github.com/picocms/Pico).

## Why this fork exists

The original Pico CMS was officially declared end-of-life by its maintainers (see upstream README). With over 3,900 GitHub stars and active production usage, Pico deserves a modern second life.

## What we are doing

This fork goes through 5 phases:

- **Phase A: Setup and Analysis** - Docker dev environment, codebase overview, dependency status, compatibility issues
- **Phase B: PHP 8.x compatibility** - Make the codebase run on PHP 8.1, 8.2, 8.3
- **Phase C: Twig 1->3 and Symfony YAML 2.8->6 upgrade** - Update EOL dependencies to current versions
- **Phase D: Tests and CI** - Test coverage and matrix CI on GitHub Actions
- **Phase E: AI-native rebirth and v0.1.0 release** - Chat-based content updates via LINE / WhatsApp / Slack / Telegram. Voice-to-Markdown. AI multilingual auto-translation. AI-assisted SEO suggestions.

## Status

This is an **early-stage** fork. The repository was initialized on 2026-05-02 with a full mirror of the upstream codebase. Modernization and Leborn-specific features are tracked in [Issues](../../issues).

Estimated duration to v0.1.0: **1 week** (with Claude Code-augmented development).

## Original project

- Name: Pico CMS
- Repository: https://github.com/picocms/Pico
- License: MIT

This fork retains all upstream commit history (see `git log`). Original maintainers and contributors are credited in commit metadata. See `NOTICE` for the formal attribution.

## About Leborn

[Leborn](https://github.com/leborn-dev) is an initiative to revive popular but stalled open-source projects with AI-native enhancements designed for the 2026 era of software. Leborn is sponsored and operated by [LLL Sdn Bhd](https://lll.dev) (Malaysia).

The name "Leborn" is from "Reborn" with the R replaced by L (for LLL).

## License

This fork retains the original MIT license. See `LICENSE`.
