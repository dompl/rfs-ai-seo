# Changelog

All notable changes to RFS AI SEO will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-10-16

### Changed
- All OpenAI calls now go through the ATS AI Portal gateway plugin instead of a stored API key. Requests carry a feature (`content` or `images`) and a purpose string, and the gateway returns the exact charge in GBP per call.
- Image generation moved to OpenAI `gpt-image-1`; text generation stays on `gpt-4o` / `gpt-4o-mini`.
- Added content generation controls: tone, target length, keywords, key-features toggle, and a skip-existing option on the bulk description pages.
- Usage logging now records the charged amount in GBP (`charged_gbp`) returned by the gateway for each request, rather than an estimated cost.
- Added an upgrade routine (`maybe_upgrade()`) that runs once per version bump to backfill `charged_gbp` and drop options the plugin no longer reads.
- Added a shared AI credit balance bar (`ats_ai_portal_credits_bar` action) to the dashboard, usage statistics and bulk generator pages.

### Removed
- The in-plugin credit ledger (`Credits_Manager`) and its admin page.
- Stripe top-up integration (`Stripe_Client` and the Stripe settings/test AJAX handler).
- Gemini/Nano Banana image generation support.
- The currency selector and cost multiplier/spending cap settings; all figures are now in pounds, charged by the gateway.
- The `RFS_AI_SEO_OPEN_AI_KEY` and `RFS_AI_SEO_GEMINI_API_KEY` wp-config constants and the stored OpenAI API key option (API key encryption is no longer needed).

## [Unreleased]

### Added
- Phase 1: Core Structure
  - Main plugin bootstrap and activation/deactivation hooks
  - Database table creation for usage logging
  - Settings management with API key encryption (AES-256-CBC)
  - Admin menu structure with Settings, Bulk Generate, and Usage Stats pages
  - Dependency checking for WooCommerce and Yoast SEO
  - Default prompt templates for products, variations, and categories
  - ACF fields configuration interface
  - Character limit settings (title and description)

- Phase 2: OpenAI Integration
  - OpenAI client class for API communication
  - Connection test functionality with AJAX handler
  - Comprehensive error handling (invalid API key, rate limits, network errors, invalid JSON)
  - Request retry mechanism for network failures
  - Token usage tracking from API responses
  - Real-time connection testing from settings page

### Security
- API key encryption using WordPress salts
- AJAX nonce verification
- Capability checks for all admin actions
- Input sanitization and output escaping
- SQL prepared statements for database queries

## [1.0.0] - TBD

### Planned
- Content collection from WooCommerce products and categories
- ACF field extraction with flexible content support
- Meta generation with SERP preview
- Bulk generation tool with progress tracking
- Usage statistics and CSV export
- Product and category meta boxes
- Character counters with visual feedback
