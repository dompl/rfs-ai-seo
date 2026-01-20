# Changelog

All notable changes to RFS AI SEO will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
