# RFS AI SEO

WordPress plugin that generates SEO meta titles and descriptions for WooCommerce products and categories using OpenAI, with Yoast SEO integration.

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.0+
- Yoast SEO
- OpenAI API key

## Features

- AI-powered meta title and description generation
- AI image generation with DALL-E
- Single item and bulk generation
- SERP preview with character counters
- Customizable prompt templates
- ACF field support (including flexible content)
- Usage tracking and statistics
- Direct Yoast SEO integration
- Multiple AI model support (GPT-4o, GPT-4o-mini, Nano Banana)

## Installation

1. Download the plugin zip
2. Upload to WordPress via Plugins > Add New > Upload
3. Activate the plugin
4. Go to RFS AI SEO > Settings
5. Enter your OpenAI API key (or define it in wp-config.php)
6. Configure prompt templates as needed

### API Key Configuration

For better security, you can define your OpenAI API key in `wp-config.php` instead of storing it in the database:

```php
define('RFS_AI_SEO_OPEN_AI_KEY', 'your-openai-api-key-here');
```

When the API key is defined in wp-config.php, it takes precedence over the database setting and cannot be changed through the admin interface.

## Usage

### Single Generation

1. Edit any WooCommerce product or category
2. Find the "RFS AI SEO" meta box
3. Click "Generate with AI"
4. Review the preview and edit if needed
5. Click "Save to Yoast"

### Bulk Generation

1. Go to RFS AI SEO > Bulk Generate
2. Filter items by type, category, or meta status
3. Select items or use "Generate for all filtered"
4. Monitor progress
5. Review results

### Image Generation

1. Go to WordPress Media Library
2. Click on any image to view attachment details
3. Add an "AI Image Description" for better prompts (optional)
4. Click "Regenerate Image with AI"
5. The original image is preserved and a new AI-generated version is created
6. Configure image generation settings (model, size, quality, style) in Settings > API Settings

## Configuration

### Prompt Templates

Customize AI prompts in Settings > Prompt Templates. Available variables:

- `{product_name}`, `{product_description}`, `{short_description}`
- `{price}`, `{categories}`, `{attributes}`
- `{acf:field_name}`, `{acf_all}`
- And more...

### ACF Fields

List ACF field names in Settings > Content Settings to include them in generation.
Use dot notation for nested fields: `flex_field.layout.sub_field`

## Development Status

### Phase 1: Core Structure ✅
- Main plugin file and bootstrap
- Database table creation
- Settings management with API key encryption
- Admin menu and pages
- Dependency checking

### Phase 2: OpenAI Integration ✅
- OpenAI client class
- Connection test functionality
- API error handling
- Token usage tracking

### Phase 3: Content Collection (Coming Soon)
- Content collector for products
- Content collector for categories
- ACF handler

### Phase 4: Generation (Coming Soon)
- Prompt manager with templates
- Generator class
- Yoast integration (read/write)

### Phase 5: Single Item UI (Coming Soon)
- Meta box registration
- SERP preview component
- Character counter
- AJAX handlers

### Phase 6: Bulk Generation (Coming Soon)
- Bulk generator page
- Filter and listing functionality
- Bulk processing with progress

### Phase 7: Usage & Polish (Coming Soon)
- Usage logger
- Statistics display
- CSV export

## License

GPL v2 or later

## Author

Red Frog Studio
