# RFS AI SEO

WordPress plugin that generates SEO meta titles and descriptions for WooCommerce products and categories using OpenAI, with Yoast SEO integration.

## Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.0+
- Yoast SEO
- ATS AI Portal plugin (active), connected to a gateway account with credit

## Features

- AI-powered meta title and description generation
- AI image generation with gpt-image-1
- Single item and bulk generation
- SERP preview with character counters
- Customizable prompt templates
- ACF field support (including flexible content)
- Usage tracking and statistics
- Direct Yoast SEO integration
- Text models: GPT-4o, GPT-4o-mini

## Installation

1. Download the plugin zip
2. Upload to WordPress via Plugins > Add New > Upload
3. Make sure the ATS AI Portal plugin is installed and active
4. Activate the plugin
5. Go to RFS AI SEO > Settings and review the content and image defaults
6. Configure prompt templates as needed

### Connecting to the AI Portal

This plugin no longer stores any API key of its own. All AI requests go through the ATS AI Portal gateway plugin, which holds the OpenAI key and meters spend.

Seal the gateway keys from the command line:

```bash
wp ats secret set gateway_key_content
wp ats secret set gateway_key_images
```

`gateway_key_content` covers text generation (meta, descriptions, alt text, the AI trainer), `gateway_key_images` covers product image generation. The dashboard shows "Not connected" until both are set.

Top up credit any time from AI Tools > AI Credits in wp-admin. Every generation deducts its exact cost in pounds from that balance; there is no separate billing or currency setting in this plugin.

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
6. Configure image generation settings (quality, size) in Settings > API Settings

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

### API Settings

- **Model** - text generation model, `gpt-4o` or `gpt-4o-mini`
- **Image quality** - `low`, `medium` or `high`, passed straight to gpt-image-1
- **Image size** - square (1024x1024), landscape (1536x1024) or portrait (1024x1536)

### Content Defaults

Starting values for the description generators, overridable per run on each bulk page and product meta box:

- Company name, used to give the AI business context
- Tone of voice (professional, friendly, technical)
- Description length in words, short description length in sentences
- Whether to include a bullet-point key features list

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
