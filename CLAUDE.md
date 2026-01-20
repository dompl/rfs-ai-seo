# RFS AI SEO — Plugin Specification for Claude Code

## Project Overview

**Plugin Name:** RFS AI SEO  
**Slug:** `rfs-ai-seo`  
**Text Domain:** `rfs-ai-seo`  
**Version:** 1.0.0  
**Author:** Red Frog Studio  
**License:** GPL v2+

### Minimum Requirements

- WordPress 6.0+
- WooCommerce 8.0+
- PHP 8.0+
- Yoast SEO (required dependency)

### Technology Stack

- **AI Provider:** OpenAI API (GPT-4o / GPT-4o-mini selectable)
- **SEO Integration:** Yoast SEO meta fields
- **ACF:** Extended ACF (vinkla/extended-acf) with flexible content support
- **Version Control:** GitHub (public repository)
- **Updates:** Manual (no auto-update mechanism)

### Access Control

- Administrator role only
- Internal use / client installations

---

## What The Plugin Does

The plugin generates SEO meta titles and descriptions for WooCommerce products (including variations) and product categories using OpenAI. It integrates directly with Yoast SEO, writing to Yoast's meta fields so all existing Yoast features (sitemaps, schema, previews) continue working.

### Generation Workflow

1. User clicks "Generate" on a product/category edit screen OR selects items in bulk tool
2. Plugin collects content data (title, description, attributes, price, ACF fields, etc.)
3. Data is sent to OpenAI with customizable prompt template
4. AI returns title + description as JSON
5. Plugin shows preview with SERP mockup and character counts
6. User reviews and clicks "Save to Yoast" or edits manually first
7. Generation is logged (tokens, timestamp, content type)

### Bulk Generation

User selects products from a filterable list or applies to "all in category X missing meta." Each item is processed sequentially via AJAX (manual trigger, no background processing), with progress shown on screen.

---

## File Structure

```
rfs-ai-seo/
├── rfs-ai-seo.php                     # Main plugin file, bootstrap
├── uninstall.php                      # Cleanup on uninstall
├── README.md                          # GitHub readme
├── CHANGELOG.md                       # Version history
├── LICENSE                            # GPL v2+
├── CLAUDE.md                          # This specification file
├── .gitignore                         # Git ignore rules
│
├── includes/
│   ├── class-rfs-ai-seo.php          # Main plugin class, hooks initialization
│   ├── class-admin.php                # Admin pages, menus, notices
│   ├── class-settings.php             # Settings page, options handling
│   ├── class-generator.php            # Core generation logic
│   ├── class-openai-client.php        # OpenAI API communication
│   ├── class-content-collector.php    # Collects data from products/categories
│   ├── class-acf-handler.php          # ACF field extraction (including flex)
│   ├── class-yoast-integration.php    # Read/write Yoast meta fields
│   ├── class-meta-box.php             # Product/category meta box UI
│   ├── class-bulk-generator.php       # Bulk generation tool
│   ├── class-usage-logger.php         # Token/generation tracking
│   └── class-prompt-manager.php       # Prompt template handling
│
├── admin/
│   ├── views/
│   │   ├── settings-page.php          # Settings page template
│   │   ├── bulk-generator-page.php    # Bulk tool page template
│   │   ├── meta-box.php               # Meta box template
│   │   ├── serp-preview.php           # SERP preview partial
│   │   └── usage-stats.php            # Usage statistics partial
│   │
│   ├── css/
│   │   └── admin.css                  # Admin styles
│   │
│   └── js/
│       ├── meta-box.js                # Meta box interactions
│       ├── bulk-generator.js          # Bulk tool JS
│       ├── serp-preview.js            # Live SERP preview updates
│       └── character-counter.js       # Character count with color feedback
│
├── templates/
│   └── prompts/
│       ├── product-default.txt        # Default product prompt template
│       ├── product-variation.txt      # Variation-specific prompt template
│       └── category-default.txt       # Category prompt template
│
└── languages/
    └── rfs-ai-seo.pot                 # Translation template (for future use)
```

---

## Database Structure

### Options (wp_options)

| Option Name                         | Type   | Description                                     |
| ----------------------------------- | ------ | ----------------------------------------------- |
| `rfs_ai_seo_openai_api_key`         | string | OpenAI API key (encrypted with wp_salt)         |
| `rfs_ai_seo_openai_model`           | string | Model: `gpt-4o` or `gpt-4o-mini`                |
| `rfs_ai_seo_prompt_product`         | text   | Customizable product prompt template            |
| `rfs_ai_seo_prompt_variation`       | text   | Customizable variation prompt template          |
| `rfs_ai_seo_prompt_category`        | text   | Customizable category prompt template           |
| `rfs_ai_seo_acf_fields`             | array  | List of ACF field names to include (serialized) |
| `rfs_ai_seo_title_max_length`       | int    | Max title length (default: 60)                  |
| `rfs_ai_seo_description_max_length` | int    | Max description length (default: 155)           |
| `rfs_ai_seo_db_version`             | string | Database version for migrations                 |

### Custom Table: `{prefix}rfs_ai_seo_log`

Create this table on plugin activation using `dbDelta()`.

```sql
CREATE TABLE {prefix}rfs_ai_seo_log (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    object_id bigint(20) unsigned NOT NULL,
    object_type varchar(20) NOT NULL,
    tokens_prompt int(11) NOT NULL DEFAULT 0,
    tokens_completion int(11) NOT NULL DEFAULT 0,
    tokens_total int(11) NOT NULL DEFAULT 0,
    model varchar(50) NOT NULL,
    generated_title varchar(255) DEFAULT NULL,
    generated_description text DEFAULT NULL,
    user_id bigint(20) unsigned NOT NULL,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY object_id (object_id),
    KEY object_type (object_type),
    KEY created_at (created_at),
    KEY user_id (user_id)
) {charset_collate};
```

| Column                  | Type            | Description                                   |
| ----------------------- | --------------- | --------------------------------------------- |
| `id`                    | bigint unsigned | Auto-increment primary key                    |
| `object_id`             | bigint unsigned | Post ID or term ID                            |
| `object_type`           | varchar(20)     | `product`, `product_variation`, `product_cat` |
| `tokens_prompt`         | int             | Tokens used in prompt                         |
| `tokens_completion`     | int             | Tokens in response                            |
| `tokens_total`          | int             | Total tokens                                  |
| `model`                 | varchar(50)     | Model used                                    |
| `generated_title`       | varchar(255)    | Generated title                               |
| `generated_description` | text            | Generated description                         |
| `user_id`               | bigint unsigned | Admin who generated                           |
| `created_at`            | datetime        | Timestamp                                     |

---

## Class Specifications

### class-rfs-ai-seo.php (Main Plugin Class)

**Namespace:** `RFS_AI_SEO`  
**Class:** `RFS_AI_SEO`

**Responsibilities:**

- Singleton pattern for main instance
- Hook initialization
- Dependency checking (Yoast, WooCommerce)
- Load plugin textdomain
- Enqueue admin scripts/styles
- Register activation/deactivation hooks

**Key Methods:**

```php
public static function instance(): self
public function init(): void
public function check_dependencies(): bool
public function load_textdomain(): void
public function enqueue_admin_assets(string $hook): void
public static function activate(): void
public static function deactivate(): void
```

**Hooks to Register:**

- `plugins_loaded` → init
- `admin_enqueue_scripts` → enqueue_admin_assets
- `admin_notices` → display dependency warnings

---

### class-admin.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Admin`

**Responsibilities:**

- Register admin menu pages
- Handle admin notices
- Coordinate between settings, meta box, and bulk generator

**Key Methods:**

```php
public function register_menu(): void
public function render_settings_page(): void
public function render_bulk_page(): void
public function add_admin_notices(): void
```

**Menu Structure:**

- Top-level menu: "RFS AI SEO" (dashicons-search)
  - Settings (default)
  - Bulk Generate
  - Usage Stats

---

### class-settings.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Settings`

**Responsibilities:**

- Register settings with WordPress Settings API
- Render settings page with tabs
- Handle API key encryption/decryption
- Validate and sanitize inputs
- Test OpenAI connection

**Key Methods:**

```php
public function register_settings(): void
public function render_settings_page(): void
public function sanitize_api_key(string $value): string
public function encrypt_api_key(string $key): string
public function decrypt_api_key(string $encrypted): string
public function test_connection(): array // AJAX handler
public function get_option(string $key, mixed $default = null): mixed
```

**Settings Tabs:**

1. **API Settings**
   - API Key (password field)
   - Model selection (dropdown: gpt-4o, gpt-4o-mini)
   - Test connection button (AJAX)

2. **Prompt Templates**
   - Product prompt (textarea with placeholder docs)
   - Variation prompt (textarea)
   - Category prompt (textarea)
   - Reset to default buttons
   - Available variables documentation

3. **Content Settings**
   - Max title length (number, default: 60)
   - Max description length (number, default: 155)
   - ACF fields list (repeater-style: add/remove field name inputs)

4. **Usage Statistics**
   - Summary cards: generations this month, tokens this month, estimated cost
   - Last 50 generations table
   - Export CSV button

**Encryption:**

```php
// Encryption using wp_salt
public function encrypt_api_key(string $key): string {
    $salt = wp_salt('auth');
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($key, 'AES-256-CBC', $salt, 0, $iv);
    return base64_encode($iv . $encrypted);
}

public function decrypt_api_key(string $encrypted): string {
    $salt = wp_salt('auth');
    $data = base64_decode($encrypted);
    $iv = substr($data, 0, 16);
    $encrypted_data = substr($data, 16);
    return openssl_decrypt($encrypted_data, 'AES-256-CBC', $salt, 0, $iv);
}
```

---

### class-generator.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Generator`

**Responsibilities:**

- Orchestrate the generation process
- Coordinate content collection, API call, and result handling
- Handle single and bulk generation

**Key Methods:**

```php
public function generate_for_product(int $product_id): array|WP_Error
public function generate_for_variation(int $variation_id): array|WP_Error
public function generate_for_category(int $term_id): array|WP_Error
public function generate_single(int $object_id, string $object_type): array|WP_Error
public function process_bulk(array $items): array
```

**Return Format:**

```php
[
    'success' => true,
    'title' => 'Generated Meta Title',
    'description' => 'Generated meta description text.',
    'tokens' => [
        'prompt' => 150,
        'completion' => 50,
        'total' => 200
    ]
]
```

---

### class-openai-client.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `OpenAI_Client`

**Responsibilities:**

- Handle all OpenAI API communication
- Build request payloads
- Parse responses
- Handle errors and retries

**Key Methods:**

```php
public function __construct(string $api_key, string $model = 'gpt-4o')
public function generate_meta(string $prompt): array|WP_Error
public function test_connection(): bool|WP_Error
private function make_request(array $payload): array|WP_Error
private function parse_response(array $response): array
```

**API Configuration:**

```php
private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
private const TIMEOUT = 30;
private const MAX_RETRIES = 1;

private function build_payload(string $prompt): array {
    return [
        'model' => $this->model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an SEO specialist. Respond only in valid JSON format with "title" and "description" keys.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
    ];
}
```

**Error Handling:**

- Invalid API key → Clear error message, suggest checking key
- Rate limited (429) → Return error with retry suggestion
- Network error → Retry once, then fail with message
- Invalid JSON in response → Log raw response, return generic error
- Missing title/description in parsed JSON → Return validation error

---

### class-content-collector.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Content_Collector`

**Responsibilities:**

- Gather all relevant data for products, variations, and categories
- Format data for prompt insertion
- Handle WooCommerce-specific data extraction

**Key Methods:**

```php
public function collect_product_data(int $product_id): array
public function collect_variation_data(int $variation_id): array
public function collect_category_data(int $term_id): array
private function get_product_attributes(WC_Product $product): string
private function get_category_hierarchy(int $product_id): string
private function format_price(WC_Product $product): string
```

**Product Data Structure:**

```php
[
    'product_name' => 'Product Title',
    'product_description' => 'Full description...',
    'short_description' => 'Short desc...',
    'sku' => 'SKU123',
    'price' => '99.00 zł',
    'regular_price' => '129.00 zł',
    'sale_price' => '99.00 zł',
    'categories' => 'Category 1, Category 2',
    'category_hierarchy' => 'Parent > Child > Grandchild',
    'tags' => 'tag1, tag2',
    'attributes' => 'Color: Red, Size: Large',
    'stock_status' => 'In stock',
    'average_rating' => '4.5',
    'review_count' => '12',
    'acf_all' => 'Formatted ACF fields...'
]
```

**Variation Data Structure:**

```php
// Inherits all product data plus:
[
    'variation_description' => 'Variation specific desc...',
    'variation_attributes' => 'Color: Blue, Size: XL',
    'variation_price' => '109.00 zł',
    'variation_sku' => 'SKU123-BL-XL'
]
```

**Category Data Structure:**

```php
[
    'category_name' => 'Category Name',
    'category_description' => 'Category description...',
    'parent_category' => 'Parent Category Name',
    'category_hierarchy' => 'Grandparent > Parent > Current',
    'product_count' => '45',
    'acf_all' => 'Formatted ACF fields...'
]
```

---

### class-acf-handler.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `ACF_Handler`

**Responsibilities:**

- Extract values from configured ACF fields
- Handle flexible content, repeaters, and groups
- Format extracted data as readable text

**Key Methods:**

```php
public function get_fields_for_object(int $object_id, string $object_type = 'post'): string
public function get_configured_fields(): array
private function extract_field_value(string $field_name, int $object_id, string $object_type): mixed
private function process_flexible_content(array $layouts): string
private function process_repeater(array $rows): string
private function process_group(array $data): string
private function format_value(mixed $value, string $field_name): string
```

**Field Name Configuration:**

- Simple fields: `field_name`
- Nested fields use dot notation: `flex_field.layout_name.sub_field`
- Retrieve configured fields from `rfs_ai_seo_acf_fields` option

**Flexible Content Processing:**

```php
private function process_flexible_content(array $layouts): string {
    $output = [];

    foreach ($layouts as $layout) {
        $layout_name = $layout['acf_fc_layout'] ?? 'unknown';
        $layout_output = ucfirst(str_replace('_', ' ', $layout_name)) . ':';

        foreach ($layout as $key => $value) {
            if ($key === 'acf_fc_layout') continue;

            if (is_array($value)) {
                $value = $this->format_array_value($value);
            }

            if (!empty($value)) {
                $layout_output .= "\n  - " . ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
            }
        }

        $output[] = $layout_output;
    }

    return implode("\n", $output);
}
```

**Output Format Example:**

```
Features:
  - Content: High durability coating resistant to UV damage
Specs Block:
  - Thickness: 2mm
  - Coverage: 10m²/L
List Block:
  - Items: Waterproof, Scratchproof, Easy application
```

---

### class-yoast-integration.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Yoast_Integration`

**Responsibilities:**

- Check if Yoast is active
- Read existing Yoast meta
- Write generated meta to Yoast fields
- Handle both post meta and term meta

**Key Methods:**

```php
public static function is_active(): bool
public function get_meta(int $object_id, string $object_type): array
public function save_meta(int $object_id, string $object_type, string $title, string $description): bool
public function has_meta(int $object_id, string $object_type): array
```

**Meta Field Keys:**

For products/variations (post meta):

```php
'_yoast_wpseo_title'     // Meta title
'_yoast_wpseo_metadesc'  // Meta description
```

For categories (term meta):

```php
'wpseo_title'  // Meta title
'wpseo_desc'   // Meta description
```

**Implementation:**

```php
public function get_meta(int $object_id, string $object_type): array {
    if ($object_type === 'product_cat') {
        return [
            'title' => get_term_meta($object_id, 'wpseo_title', true),
            'description' => get_term_meta($object_id, 'wpseo_desc', true)
        ];
    }

    return [
        'title' => get_post_meta($object_id, '_yoast_wpseo_title', true),
        'description' => get_post_meta($object_id, '_yoast_wpseo_metadesc', true)
    ];
}

public function save_meta(int $object_id, string $object_type, string $title, string $description): bool {
    if ($object_type === 'product_cat') {
        update_term_meta($object_id, 'wpseo_title', sanitize_text_field($title));
        update_term_meta($object_id, 'wpseo_desc', sanitize_textarea_field($description));
        return true;
    }

    update_post_meta($object_id, '_yoast_wpseo_title', sanitize_text_field($title));
    update_post_meta($object_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($description));
    return true;
}

public function has_meta(int $object_id, string $object_type): array {
    $meta = $this->get_meta($object_id, $object_type);
    return [
        'has_title' => !empty($meta['title']),
        'has_description' => !empty($meta['description']),
        'has_both' => !empty($meta['title']) && !empty($meta['description'])
    ];
}
```

---

### class-meta-box.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Meta_Box`

**Responsibilities:**

- Register meta boxes on product edit screens
- Register fields on product category edit screens
- Handle AJAX generation requests
- Render meta box UI

**Key Methods:**

```php
public function register_meta_boxes(): void
public function render_product_meta_box(WP_Post $post): void
public function add_variation_meta_box(): void // WooCommerce variation panel
public function render_category_fields(WP_Term $term): void
public function ajax_generate(): void // AJAX handler
public function ajax_save_meta(): void // AJAX handler
```

**AJAX Actions:**

- `rfs_ai_seo_generate` → Generate meta for single item
- `rfs_ai_seo_save_meta` → Save generated meta to Yoast

**Meta Box UI Components:**

1. **Current Meta Display**
   - Show current Yoast title (or "Not set")
   - Show current Yoast description (or "Not set")

2. **Generate Button**
   - "Generate with AI" primary button
   - Spinner during generation
   - Confirmation modal if meta already exists

3. **Preview Panel** (shown after generation)
   - SERP Preview mockup (Google-style)
   - Editable title field with character counter
   - Editable description textarea with character counter
   - Live preview updates

4. **Character Counters**
   - Title: Green (0-55), Yellow (56-60), Red (61+)
   - Description: Green (0-150), Yellow (151-155), Red (156+)

5. **Action Buttons**
   - "Save to Yoast" - saves meta
   - "Regenerate" - new generation
   - "Cancel" - discard preview

**Variation Meta Box:**
Add inline generate button in WooCommerce variation panels using `woocommerce_product_after_variable_attributes` hook.

---

### class-bulk-generator.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Bulk_Generator`

**Responsibilities:**

- Render bulk generation admin page
- Handle filtering and listing of items
- Process AJAX bulk generation requests
- Track progress

**Key Methods:**

```php
public function render_page(): void
public function get_items(array $filters): array
public function ajax_get_items(): void
public function ajax_generate_single(): void
public function ajax_get_progress(): void
```

**Filter Parameters:**

```php
[
    'object_type' => 'product', // product, product_variation, product_cat
    'category' => 0, // category ID or 0 for all
    'meta_status' => 'all', // all, missing_title, missing_description, missing_both, has_existing
    'search' => '', // search by name/SKU
    'per_page' => 20,
    'page' => 1
]
```

**Bulk Page UI:**

1. **Filter Panel**
   - Content type dropdown
   - Category filter (show only for products)
   - Meta status filter
   - Search input
   - Filter button

2. **Results Table**
   - Checkbox column
   - Thumbnail (products only)
   - Name (linked to edit screen)
   - Current title (truncated or "—")
   - Current description (truncated or "—")
   - Status badge: Complete (green), Partial (yellow), Empty (red)

3. **Bulk Actions**
   - "Generate for selected" button
   - "Generate for all filtered" button (with count warning)
   - Confirmation modals

4. **Progress Panel** (during generation)
   - Progress bar with percentage
   - Current item name
   - Success/fail counters
   - Stop button
   - Scrollable log:
     - ✓ Item Name — Success
     - ✗ Item Name — Error message

5. **Completion Summary**
   - Total processed
   - Successful count
   - Failed count (with list)
   - Total tokens used
   - Link to usage stats

---

### class-usage-logger.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Usage_Logger`

**Responsibilities:**

- Log all generations to custom table
- Calculate usage statistics
- Export logs as CSV

**Key Methods:**

```php
public function log(int $object_id, string $object_type, array $data): int
public function get_stats(string $period = 'month'): array
public function get_recent_logs(int $limit = 50): array
public function get_monthly_totals(): array
public function export_csv(string $start_date, string $end_date): string
public function cleanup_old_logs(int $days = 90): int
```

**Log Data Structure:**

```php
$data = [
    'tokens_prompt' => 150,
    'tokens_completion' => 50,
    'tokens_total' => 200,
    'model' => 'gpt-4o',
    'generated_title' => 'Title...',
    'generated_description' => 'Description...'
];
```

**Stats Return Format:**

```php
[
    'total_generations' => 125,
    'total_tokens' => 25000,
    'estimated_cost' => 0.75, // Based on OpenAI pricing
    'by_type' => [
        'product' => 80,
        'product_variation' => 35,
        'product_cat' => 10
    ],
    'by_model' => [
        'gpt-4o' => 100,
        'gpt-4o-mini' => 25
    ]
]
```

**Cost Calculation (approximate):**

```php
private function calculate_cost(int $prompt_tokens, int $completion_tokens, string $model): float {
    $rates = [
        'gpt-4o' => ['prompt' => 0.005, 'completion' => 0.015], // per 1K tokens
        'gpt-4o-mini' => ['prompt' => 0.00015, 'completion' => 0.0006]
    ];

    $rate = $rates[$model] ?? $rates['gpt-4o'];

    return (($prompt_tokens / 1000) * $rate['prompt']) +
           (($completion_tokens / 1000) * $rate['completion']);
}
```

---

### class-prompt-manager.php

**Namespace:** `RFS_AI_SEO`  
**Class:** `Prompt_Manager`

**Responsibilities:**

- Load prompt templates (from settings or defaults)
- Compile prompts with variable replacement
- Provide list of available variables

**Key Methods:**

```php
public function get_template(string $type): string
public function compile_prompt(string $template, array $data): string
public function get_default_template(string $type): string
public function get_available_variables(string $type): array
public function reset_to_default(string $type): bool
```

**Variable Replacement:**

```php
public function compile_prompt(string $template, array $data): string {
    $settings = new Settings();

    // Add system variables
    $data['max_title_length'] = $settings->get_option('title_max_length', 60);
    $data['max_description_length'] = $settings->get_option('description_max_length', 155);

    // Replace all {variable} placeholders
    return preg_replace_callback('/\{([a-z_:]+)\}/', function($matches) use ($data) {
        $key = $matches[1];
        return $data[$key] ?? '';
    }, $template);
}
```

---

## Prompt Template Variables

### Product Variables

| Variable                   | Description                         |
| -------------------------- | ----------------------------------- |
| `{product_name}`           | Product title                       |
| `{product_description}`    | Full description (HTML stripped)    |
| `{short_description}`      | Short description                   |
| `{sku}`                    | Product SKU                         |
| `{price}`                  | Current/active price                |
| `{regular_price}`          | Regular price                       |
| `{sale_price}`             | Sale price (if on sale)             |
| `{categories}`             | Comma-separated category names      |
| `{category_hierarchy}`     | Full category path (Parent > Child) |
| `{tags}`                   | Comma-separated tags                |
| `{attributes}`             | Formatted attributes (Name: Value)  |
| `{stock_status}`           | In stock / Out of stock             |
| `{average_rating}`         | Review average (e.g., 4.5)          |
| `{review_count}`           | Number of reviews                   |
| `{acf:field_name}`         | Specific ACF field value            |
| `{acf_all}`                | All configured ACF fields formatted |
| `{max_title_length}`       | From settings (system variable)     |
| `{max_description_length}` | From settings (system variable)     |

### Variation Variables (inherits product variables plus)

| Variable                  | Description                    |
| ------------------------- | ------------------------------ |
| `{variation_description}` | Variation-specific description |
| `{variation_attributes}`  | This variation's attributes    |
| `{variation_price}`       | Variation price                |
| `{variation_sku}`         | Variation SKU                  |

### Category Variables

| Variable                   | Description                                |
| -------------------------- | ------------------------------------------ |
| `{category_name}`          | Category name                              |
| `{category_description}`   | Category description                       |
| `{parent_category}`        | Parent category name                       |
| `{category_hierarchy}`     | Full path (Grandparent > Parent > Current) |
| `{product_count}`          | Number of products in category             |
| `{acf:field_name}`         | ACF field on category                      |
| `{acf_all}`                | All configured ACF fields                  |
| `{max_title_length}`       | From settings                              |
| `{max_description_length}` | From settings                              |

---

## Default Prompt Templates

### Product (templates/prompts/product-default.txt)

```
Generate SEO metadata for this WooCommerce product.

PRODUCT DATA:
- Name: {product_name}
- Categories: {category_hierarchy}
- Short description: {short_description}
- Full description: {product_description}
- Price: {price}
- Attributes: {attributes}
- Additional info: {acf_all}

REQUIREMENTS:
- Meta title: Maximum {max_title_length} characters. Include product name and key selling point. Make it compelling for clicks.
- Meta description: Maximum {max_description_length} characters. Summarize key benefits, include a subtle call-to-action. Do not use generic phrases like "Buy now" or "Shop today".

RESPOND IN JSON FORMAT ONLY:
{"title": "your meta title here", "description": "your meta description here"}
```

### Variation (templates/prompts/product-variation.txt)

```
Generate SEO metadata for this product variation.

PARENT PRODUCT: {product_name}
VARIATION DETAILS:
- Attributes: {variation_attributes}
- Description: {variation_description}
- Price: {variation_price}
- SKU: {variation_sku}

PARENT CONTEXT:
- Categories: {category_hierarchy}
- Main description: {short_description}

REQUIREMENTS:
- Meta title: Maximum {max_title_length} characters. Include parent product name and variation-specific attributes (like color, size).
- Meta description: Maximum {max_description_length} characters. Focus on this specific variation's unique aspects while maintaining parent product context.

RESPOND IN JSON FORMAT ONLY:
{"title": "your meta title here", "description": "your meta description here"}
```

### Category (templates/prompts/category-default.txt)

```
Generate SEO metadata for this product category.

CATEGORY DATA:
- Name: {category_name}
- Description: {category_description}
- Parent category: {parent_category}
- Number of products: {product_count}
- Additional info: {acf_all}

REQUIREMENTS:
- Meta title: Maximum {max_title_length} characters. Include category name, make it search-friendly and descriptive.
- Meta description: Maximum {max_description_length} characters. Describe what types of products are in this category, encourage browsing. Avoid generic phrases.

RESPOND IN JSON FORMAT ONLY:
{"title": "your meta title here", "description": "your meta description here"}
```

---

## Admin UI Specifications

### Settings Page Layout

**Location:** Admin menu → RFS AI SEO → Settings

**Tab 1: API Settings**

```
┌─────────────────────────────────────────────────────────┐
│ API Settings                                            │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ OpenAI API Key                                          │
│ ┌─────────────────────────────────┐ [Test Connection]   │
│ │ ••••••••••••••••••••            │                     │
│ └─────────────────────────────────┘                     │
│ Get your API key from platform.openai.com               │
│                                                         │
│ Model                                                   │
│ ┌─────────────────────────────────┐                     │
│ │ GPT-4o                        ▼ │                     │
│ └─────────────────────────────────┘                     │
│ GPT-4o: More capable, higher cost                       │
│ GPT-4o-mini: Faster, lower cost                         │
│                                                         │
│                               [Save Changes]            │
└─────────────────────────────────────────────────────────┘
```

**Tab 2: Prompt Templates**

```
┌─────────────────────────────────────────────────────────┐
│ Prompt Templates                                        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Product Prompt                          [Reset Default] │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ Generate SEO metadata for this WooCommerce product. │ │
│ │ ...                                                 │ │
│ │                                                     │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ Available variables: {product_name}, {product_desc...   │
│                                                         │
│ [Similar for Variation and Category]                    │
│                                                         │
│                               [Save Changes]            │
└─────────────────────────────────────────────────────────┘
```

**Tab 3: Content Settings**

```
┌─────────────────────────────────────────────────────────┐
│ Content Settings                                        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Maximum Title Length                                    │
│ ┌───────┐                                               │
│ │ 60    │ characters                                    │
│ └───────┘                                               │
│                                                         │
│ Maximum Description Length                              │
│ ┌───────┐                                               │
│ │ 155   │ characters                                    │
│ └───────┘                                               │
│                                                         │
│ ACF Fields to Include                                   │
│ ┌─────────────────────────────────┐ [Remove]            │
│ │ product_features                │                     │
│ └─────────────────────────────────┘                     │
│ ┌─────────────────────────────────┐ [Remove]            │
│ │ specifications.material         │                     │
│ └─────────────────────────────────┘                     │
│ [+ Add Field]                                           │
│                                                         │
│ Use dot notation for nested fields                      │
│                                                         │
│                               [Save Changes]            │
└─────────────────────────────────────────────────────────┘
```

**Tab 4: Usage Statistics**

```
┌─────────────────────────────────────────────────────────┐
│ Usage Statistics                                        │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐      │
│ │     125      │ │   25,430     │ │    $0.76     │      │
│ │ Generations  │ │    Tokens    │ │ Est. Cost    │      │
│ │  this month  │ │  this month  │ │  this month  │      │
│ └──────────────┘ └──────────────┘ └──────────────┘      │
│                                                         │
│ Recent Generations                      [Export CSV]    │
│ ┌───────────────────────────────────────────────────┐   │
│ │ Date       │ Type    │ Item      │ Tokens │ Model │   │
│ ├───────────────────────────────────────────────────┤   │
│ │ 2024-01-15 │ Product │ Widget A  │ 198    │ 4o    │   │
│ │ 2024-01-15 │ Cat     │ Tools     │ 156    │ 4o    │   │
│ │ ...                                               │   │
│ └───────────────────────────────────────────────────┘   │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### Product Meta Box

**Location:** Product edit screen, side panel (high priority)

```
┌─────────────────────────────────────────────────────────┐
│ RFS AI SEO                                          [-] │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ Current Meta                                            │
│ Title: Premium Widget for Industrial Use                │
│ Description: High-quality widget designed for...        │
│                                                         │
│        [Generate with AI]                               │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**After Generation (Preview State):**

```
┌─────────────────────────────────────────────────────────┐
│ RFS AI SEO                                          [-] │
├─────────────────────────────────────────────────────────┤
│                                                         │
│ SERP Preview                                            │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ Premium Industrial Widget - Heavy Duty | Shop       │ │
│ │ https://example.com › products › widget             │ │
│ │ High-performance industrial widget built for        │ │
│ │ demanding applications. Durable construction...     │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ Meta Title                                    52/60 ███ │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ Premium Industrial Widget - Heavy Duty | Shop       │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ Meta Description                            142/155 ███ │
│ ┌─────────────────────────────────────────────────────┐ │
│ │ High-performance industrial widget built for        │ │
│ │ demanding applications. Durable construction,       │ │
│ │ precision engineering. Shop now.                    │ │
│ └─────────────────────────────────────────────────────┘ │
│                                                         │
│ [Save to Yoast]  [Regenerate]  [Cancel]                 │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

**Character Counter Colors:**

- Green bar: Within optimal range
- Yellow bar: Near limit
- Red bar: Over limit

### Bulk Generator Page

**Location:** Admin menu → RFS AI SEO → Bulk Generate

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Bulk Generate SEO Meta                                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│ Filters                                                                     │
│ ┌────────────┐ ┌────────────┐ ┌──────────────┐ ┌──────────────┐            │
│ │ Products ▼ │ │ All Cats ▼ │ │ Missing Both▼│ │ Search...    │ [Filter]   │
│ └────────────┘ └────────────┘ └──────────────┘ └──────────────┘            │
│                                                                             │
│ ┌───────────────────────────────────────────────────────────────────────┐   │
│ │ ☑ │ 🖼 │ Name              │ Title          │ Description    │ Status │   │
│ ├───────────────────────────────────────────────────────────────────────┤   │
│ │ ☐ │ 🖼 │ Premium Widget    │ Premium Wid... │ High-quality...│ ●●     │   │
│ │ ☑ │ 🖼 │ Basic Widget      │ —              │ —              │ ○○     │   │
│ │ ☐ │ 🖼 │ Pro Widget        │ Pro Widget ... │ —              │ ●○     │   │
│ │ ☑ │ 🖼 │ Widget Deluxe     │ —              │ —              │ ○○     │   │
│ └───────────────────────────────────────────────────────────────────────┘   │
│                                                                             │
│ Selected: 2                                                                 │
│ [Generate for Selected (2)]  [Generate for All Filtered (45)]              │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

**During Generation:**

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ Generating...                                              [Stop]           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│ ████████████████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░  12 of 45 (27%)           │
│                                                                             │
│ Current: Widget Deluxe                                                      │
│ ✓ Success: 11  ✗ Failed: 1                                                  │
│                                                                             │
│ Log:                                                                        │
│ ┌─────────────────────────────────────────────────────────────────────────┐ │
│ │ ✓ Premium Widget — Generated successfully                               │ │
│ │ ✓ Basic Widget — Generated successfully                                 │ │
│ │ ✗ Pro Widget — API error: Rate limit exceeded                           │ │
│ │ ✓ Widget Standard — Generated successfully                              │ │
│ │ ...                                                                     │ │
│ └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## JavaScript Specifications

### admin/js/meta-box.js

**Responsibilities:**

- Handle generate button click
- Show/hide confirmation modal
- Make AJAX requests
- Update preview panel
- Handle save/regenerate/cancel actions

**Key Functions:**

```javascript
const RFS_AI_SEO_MetaBox = {
	init() {},

	bindEvents() {},

	showConfirmModal(callback) {},

	generate(objectId, objectType) {
		// Show spinner
		// AJAX call to rfs_ai_seo_generate
		// On success: showPreview(response)
		// On error: showError(response)
	},

	showPreview(data) {
		// Populate preview fields
		// Show preview panel
		// Initialize character counters
	},

	saveMeta() {
		// AJAX call to rfs_ai_seo_save_meta
		// On success: update current meta display, hide preview
		// On error: show error message
	},

	regenerate() {
		// Confirm if needed
		// Call generate()
	},

	cancel() {
		// Hide preview panel
	},
};
```

### admin/js/bulk-generator.js

**Responsibilities:**

- Handle filter form submission
- Manage item selection
- Process bulk generation with progress
- Handle stop functionality

**Key Functions:**

```javascript
const RFS_AI_SEO_BulkGenerator = {
	items: [],
	currentIndex: 0,
	isRunning: false,
	results: { success: 0, failed: 0, tokens: 0 },

	init() {},

	loadItems(filters) {
		// AJAX call to get filtered items
		// Render table
	},

	startBulkGeneration(itemIds) {
		// Show progress panel
		// Set isRunning = true
		// Call processNext()
	},

	processNext() {
		// If !isRunning or done, call complete()
		// Get next item
		// AJAX generate for item
		// Update progress
		// Log result
		// Call processNext() recursively
	},

	stop() {
		// Set isRunning = false
		// Show partial completion
	},

	complete() {
		// Show summary
		// Reset state
	},

	updateProgress(current, total) {},

	logResult(item, success, message) {},
};
```

### admin/js/serp-preview.js

**Responsibilities:**

- Render Google-style SERP preview
- Update preview live as user types
- Truncate title/description appropriately

**Key Functions:**

```javascript
const RFS_AI_SEO_SerpPreview = {
	titleInput: null,
	descInput: null,
	previewEl: null,

	init(titleInputId, descInputId, previewId) {},

	bindEvents() {
		// Listen to input events
		// Debounce updates
	},

	update() {
		// Get current values
		// Truncate if needed (with ...)
		// Update preview HTML
	},

	truncateTitle(title, maxPixels = 600) {},

	truncateDescription(desc, maxChars = 155) {},
};
```

### admin/js/character-counter.js

**Responsibilities:**

- Count characters in input
- Update counter display
- Apply color classes based on limits

**Key Functions:**

```javascript
const RFS_AI_SEO_CharCounter = {
	init(inputId, counterId, limits) {
		// limits = { optimal: 55, warning: 60, max: 70 }
	},

	update(input, counter, limits) {
		const length = input.value.length;
		counter.textContent = `${length}/${limits.warning}`;

		counter.classList.remove("green", "yellow", "red");
		if (length <= limits.optimal) {
			counter.classList.add("green");
		} else if (length <= limits.warning) {
			counter.classList.add("yellow");
		} else {
			counter.classList.add("red");
		}
	},
};
```

---

## CSS Specifications (admin/css/admin.css)

```css
/* RFS AI SEO Admin Styles */

/* Meta Box */
.rfs-ai-seo-meta-box {
}
.rfs-ai-seo-current-meta {
}
.rfs-ai-seo-current-meta .label {
	font-weight: 600;
	color: #1d2327;
}
.rfs-ai-seo-current-meta .value {
	color: #50575e;
}
.rfs-ai-seo-current-meta .not-set {
	color: #a7aaad;
	font-style: italic;
}

/* Generate Button */
.rfs-ai-seo-generate-btn {
	width: 100%;
	text-align: center;
	margin: 15px 0;
}

/* Preview Panel */
.rfs-ai-seo-preview {
	display: none;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 15px;
	margin-top: 15px;
	background: #f6f7f7;
}
.rfs-ai-seo-preview.active {
	display: block;
}

/* SERP Preview */
.rfs-ai-seo-serp-preview {
	background: #fff;
	border: 1px solid #dadce0;
	border-radius: 8px;
	padding: 15px;
	margin-bottom: 15px;
	font-family: Arial, sans-serif;
}
.rfs-ai-seo-serp-title {
	color: #1a0dab;
	font-size: 18px;
	line-height: 1.3;
	margin-bottom: 3px;
	cursor: pointer;
}
.rfs-ai-seo-serp-title:hover {
	text-decoration: underline;
}
.rfs-ai-seo-serp-url {
	color: #006621;
	font-size: 14px;
	margin-bottom: 3px;
}
.rfs-ai-seo-serp-description {
	color: #545454;
	font-size: 13px;
	line-height: 1.4;
}

/* Character Counter */
.rfs-ai-seo-char-counter {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	margin-bottom: 5px;
	font-size: 12px;
}
.rfs-ai-seo-char-counter .count {
	margin-right: 8px;
}
.rfs-ai-seo-char-counter .bar {
	width: 50px;
	height: 4px;
	border-radius: 2px;
	background: #c3c4c7;
}
.rfs-ai-seo-char-counter.green .count {
	color: #00a32a;
}
.rfs-ai-seo-char-counter.green .bar {
	background: #00a32a;
}
.rfs-ai-seo-char-counter.yellow .count {
	color: #dba617;
}
.rfs-ai-seo-char-counter.yellow .bar {
	background: #dba617;
}
.rfs-ai-seo-char-counter.red .count {
	color: #d63638;
}
.rfs-ai-seo-char-counter.red .bar {
	background: #d63638;
}

/* Edit Fields */
.rfs-ai-seo-field {
	margin-bottom: 15px;
}
.rfs-ai-seo-field label {
	display: block;
	font-weight: 600;
	margin-bottom: 5px;
}
.rfs-ai-seo-field input[type="text"],
.rfs-ai-seo-field textarea {
	width: 100%;
}
.rfs-ai-seo-field textarea {
	min-height: 80px;
	resize: vertical;
}

/* Action Buttons */
.rfs-ai-seo-actions {
	display: flex;
	gap: 10px;
	margin-top: 15px;
}

/* Bulk Generator */
.rfs-ai-seo-bulk-filters {
	display: flex;
	gap: 10px;
	align-items: flex-end;
	margin-bottom: 20px;
	flex-wrap: wrap;
}
.rfs-ai-seo-bulk-filters .filter-group {
	display: flex;
	flex-direction: column;
}
.rfs-ai-seo-bulk-filters label {
	font-size: 12px;
	margin-bottom: 3px;
}

/* Bulk Table */
.rfs-ai-seo-bulk-table {
	width: 100%;
	border-collapse: collapse;
}
.rfs-ai-seo-bulk-table th,
.rfs-ai-seo-bulk-table td {
	padding: 10px;
	text-align: left;
	border-bottom: 1px solid #c3c4c7;
}
.rfs-ai-seo-bulk-table th {
	background: #f6f7f7;
}
.rfs-ai-seo-bulk-table .thumb {
	width: 40px;
}
.rfs-ai-seo-bulk-table .thumb img {
	width: 40px;
	height: 40px;
	object-fit: cover;
	border-radius: 4px;
}
.rfs-ai-seo-bulk-table .status {
	width: 60px;
	text-align: center;
}

/* Status Badges */
.rfs-ai-seo-status {
	display: inline-flex;
	gap: 3px;
}
.rfs-ai-seo-status-dot {
	width: 10px;
	height: 10px;
	border-radius: 50%;
	background: #c3c4c7;
}
.rfs-ai-seo-status-dot.filled {
	background: #00a32a;
}

/* Progress Panel */
.rfs-ai-seo-progress {
	display: none;
	background: #f6f7f7;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	margin-top: 20px;
}
.rfs-ai-seo-progress.active {
	display: block;
}
.rfs-ai-seo-progress-bar {
	height: 20px;
	background: #c3c4c7;
	border-radius: 10px;
	overflow: hidden;
	margin-bottom: 15px;
}
.rfs-ai-seo-progress-bar-fill {
	height: 100%;
	background: #2271b1;
	transition: width 0.3s ease;
}
.rfs-ai-seo-progress-stats {
	display: flex;
	gap: 20px;
	margin-bottom: 15px;
}
.rfs-ai-seo-progress-log {
	max-height: 200px;
	overflow-y: auto;
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 10px;
	font-family: monospace;
	font-size: 12px;
}
.rfs-ai-seo-log-entry {
	padding: 3px 0;
}
.rfs-ai-seo-log-entry.success {
	color: #00a32a;
}
.rfs-ai-seo-log-entry.error {
	color: #d63638;
}

/* Settings Page */
.rfs-ai-seo-settings-tabs {
	margin-bottom: 20px;
}
.rfs-ai-seo-stats-cards {
	display: flex;
	gap: 20px;
	margin-bottom: 30px;
}
.rfs-ai-seo-stat-card {
	background: #fff;
	border: 1px solid #c3c4c7;
	border-radius: 4px;
	padding: 20px;
	text-align: center;
	min-width: 150px;
}
.rfs-ai-seo-stat-card .number {
	font-size: 32px;
	font-weight: 600;
	color: #1d2327;
}
.rfs-ai-seo-stat-card .label {
	color: #50575e;
	font-size: 13px;
}

/* ACF Fields Repeater */
.rfs-ai-seo-acf-fields {
	margin-bottom: 15px;
}
.rfs-ai-seo-acf-field-row {
	display: flex;
	gap: 10px;
	margin-bottom: 8px;
}
.rfs-ai-seo-acf-field-row input {
	flex: 1;
}

/* Confirmation Modal */
.rfs-ai-seo-modal-overlay {
	display: none;
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.5);
	z-index: 100000;
	align-items: center;
	justify-content: center;
}
.rfs-ai-seo-modal-overlay.active {
	display: flex;
}
.rfs-ai-seo-modal {
	background: #fff;
	border-radius: 4px;
	padding: 20px;
	max-width: 400px;
	width: 90%;
}
.rfs-ai-seo-modal-title {
	font-size: 18px;
	font-weight: 600;
	margin-bottom: 10px;
}
.rfs-ai-seo-modal-actions {
	display: flex;
	gap: 10px;
	justify-content: flex-end;
	margin-top: 20px;
}

/* Spinner */
.rfs-ai-seo-spinner {
	display: none;
}
.rfs-ai-seo-spinner.active {
	display: inline-block;
}
```

---

## AJAX Endpoints

All AJAX handlers should:

- Verify nonce with `check_ajax_referer('rfs_ai_seo_nonce', 'nonce')`
- Check capability with `current_user_can('manage_options')`
- Return JSON with `wp_send_json_success()` or `wp_send_json_error()`

### rfs_ai_seo_generate

**Purpose:** Generate meta for single item

**Request:**

```javascript
{
    action: 'rfs_ai_seo_generate',
    nonce: '...',
    object_id: 123,
    object_type: 'product' // product, product_variation, product_cat
}
```

**Response (success):**

```javascript
{
    success: true,
    data: {
        title: 'Generated Meta Title',
        description: 'Generated meta description...',
        current: {
            title: 'Existing title or empty',
            description: 'Existing desc or empty'
        },
        tokens: {
            prompt: 150,
            completion: 50,
            total: 200
        }
    }
}
```

**Response (error):**

```javascript
{
    success: false,
    data: {
        message: 'Error message here',
        code: 'api_error' // api_error, invalid_request, no_content, etc.
    }
}
```

### rfs_ai_seo_save_meta

**Purpose:** Save generated meta to Yoast fields

**Request:**

```javascript
{
    action: 'rfs_ai_seo_save_meta',
    nonce: '...',
    object_id: 123,
    object_type: 'product',
    title: 'Meta title to save',
    description: 'Meta description to save'
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        message: 'Meta saved successfully'
    }
}
```

### rfs_ai_seo_test_connection

**Purpose:** Test OpenAI API connection

**Request:**

```javascript
{
    action: 'rfs_ai_seo_test_connection',
    nonce: '...'
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        message: 'Connection successful',
        model: 'gpt-4o'
    }
}
```

### rfs_ai_seo_get_items

**Purpose:** Get filtered items for bulk generator

**Request:**

```javascript
{
    action: 'rfs_ai_seo_get_items',
    nonce: '...',
    object_type: 'product',
    category: 0,
    meta_status: 'missing_both',
    search: '',
    per_page: 20,
    page: 1
}
```

**Response:**

```javascript
{
    success: true,
    data: {
        items: [
            {
                id: 123,
                name: 'Product Name',
                edit_url: 'https://...',
                thumbnail: 'https://...',
                current_title: 'Title or null',
                current_description: 'Desc or null',
                status: 'empty' // complete, partial, empty
            }
        ],
        total: 45,
        pages: 3
    }
}
```

### rfs_ai_seo_export_log

**Purpose:** Export usage log as CSV

**Request:**

```javascript
{
    action: 'rfs_ai_seo_export_log',
    nonce: '...',
    start_date: '2024-01-01',
    end_date: '2024-01-31'
}
```

**Response:** File download (CSV)

---

## Hooks and Filters

### Filters

```php
/**
 * Modify collected content before sending to AI
 *
 * @param array $data Collected content data
 * @param int $object_id Post ID or term ID
 * @param string $object_type Object type
 * @return array Modified data
 */
apply_filters('rfs_ai_seo_content_data', $data, $object_id, $object_type);

/**
 * Modify prompt before sending to OpenAI
 *
 * @param string $prompt Compiled prompt
 * @param int $object_id Post ID or term ID
 * @param string $object_type Object type
 * @return string Modified prompt
 */
apply_filters('rfs_ai_seo_prompt', $prompt, $object_id, $object_type);

/**
 * Modify generated meta before displaying/saving
 *
 * @param array $meta Generated meta ['title' => '', 'description' => '']
 * @param int $object_id Post ID or term ID
 * @param string $object_type Object type
 * @return array Modified meta
 */
apply_filters('rfs_ai_seo_generated_meta', $meta, $object_id, $object_type);

/**
 * Modify OpenAI request parameters
 *
 * @param array $params API request parameters
 * @return array Modified parameters
 */
apply_filters('rfs_ai_seo_openai_params', $params);

/**
 * Filter ACF fields to include
 *
 * @param array $fields Configured field names
 * @param int $object_id Post ID or term ID
 * @param string $object_type Object type
 * @return array Modified field list
 */
apply_filters('rfs_ai_seo_acf_fields', $fields, $object_id, $object_type);
```

### Actions

```php
/**
 * Fired after successful generation
 *
 * @param int $object_id Post ID or term ID
 * @param string $object_type Object type
 * @param array $meta Generated meta
 */
do_action('rfs_ai_seo_generated', $object_id, $object_type, $meta);

/**
 * Fired after meta is saved to Yoast
 *
 * @param int $object_id Post ID or term ID
 * @param string $object_type Object type
 * @param array $meta Saved meta
 */
do_action('rfs_ai_seo_saved', $object_id, $object_type, $meta);

/**
 * Fired after bulk generation completes
 *
 * @param array $results Bulk generation results
 */
do_action('rfs_ai_seo_bulk_complete', $results);

/**
 * Fired when generation fails
 *
 * @param int $object_id Post ID or term ID
 * @param string $object_type Object type
 * @param WP_Error $error Error object
 */
do_action('rfs_ai_seo_generation_failed', $object_id, $object_type, $error);
```

---

## Activation / Deactivation / Uninstall

### Activation (rfs-ai-seo.php or separate activation class)

```php
public static function activate(): void {
    // 1. Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('RFS AI SEO requires PHP 8.0 or higher.');
    }

    // 2. Check WordPress version
    if (version_compare(get_bloginfo('version'), '6.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('RFS AI SEO requires WordPress 6.0 or higher.');
    }

    // 3. Check WooCommerce
    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('RFS AI SEO requires WooCommerce 8.0 or higher.');
    }

    // 4. Check Yoast
    if (!defined('WPSEO_VERSION')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('RFS AI SEO requires Yoast SEO to be installed and activated.');
    }

    // 5. Create database table
    self::create_tables();

    // 6. Set default options
    self::set_default_options();

    // 7. Set DB version
    update_option('rfs_ai_seo_db_version', '1.0.0');
}

private static function create_tables(): void {
    global $wpdb;

    $table_name = $wpdb->prefix . 'rfs_ai_seo_log';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        object_id bigint(20) unsigned NOT NULL,
        object_type varchar(20) NOT NULL,
        tokens_prompt int(11) NOT NULL DEFAULT 0,
        tokens_completion int(11) NOT NULL DEFAULT 0,
        tokens_total int(11) NOT NULL DEFAULT 0,
        model varchar(50) NOT NULL,
        generated_title varchar(255) DEFAULT NULL,
        generated_description text DEFAULT NULL,
        user_id bigint(20) unsigned NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY object_id (object_id),
        KEY object_type (object_type),
        KEY created_at (created_at),
        KEY user_id (user_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

private static function set_default_options(): void {
    $defaults = [
        'rfs_ai_seo_openai_model' => 'gpt-4o',
        'rfs_ai_seo_title_max_length' => 60,
        'rfs_ai_seo_description_max_length' => 155,
        'rfs_ai_seo_acf_fields' => [],
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            update_option($key, $value);
        }
    }

    // Set default prompts from template files
    $prompt_manager = new Prompt_Manager();
    if (get_option('rfs_ai_seo_prompt_product') === false) {
        update_option('rfs_ai_seo_prompt_product', $prompt_manager->get_default_template('product'));
    }
    if (get_option('rfs_ai_seo_prompt_variation') === false) {
        update_option('rfs_ai_seo_prompt_variation', $prompt_manager->get_default_template('variation'));
    }
    if (get_option('rfs_ai_seo_prompt_category') === false) {
        update_option('rfs_ai_seo_prompt_category', $prompt_manager->get_default_template('category'));
    }
}
```

### Deactivation

```php
public static function deactivate(): void {
    // Clean up transients
    delete_transient('rfs_ai_seo_connection_test');

    // Optionally flush rewrite rules
    flush_rewrite_rules();
}
```

### Uninstall (uninstall.php)

```php
<?php
// Exit if not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

// Delete options
$options = [
    'rfs_ai_seo_openai_api_key',
    'rfs_ai_seo_openai_model',
    'rfs_ai_seo_prompt_product',
    'rfs_ai_seo_prompt_variation',
    'rfs_ai_seo_prompt_category',
    'rfs_ai_seo_acf_fields',
    'rfs_ai_seo_title_max_length',
    'rfs_ai_seo_description_max_length',
    'rfs_ai_seo_db_version',
];

foreach ($options as $option) {
    delete_option($option);
}

// Drop custom table
$table_name = $wpdb->prefix . 'rfs_ai_seo_log';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear any transients
delete_transient('rfs_ai_seo_connection_test');
```

---

## Security Checklist

- [ ] All AJAX handlers verify nonce with `check_ajax_referer()`
- [ ] All AJAX handlers check `current_user_can('manage_options')`
- [ ] API key is encrypted before storing in database
- [ ] All user inputs are sanitized with appropriate functions
- [ ] All outputs are escaped with `esc_html()`, `esc_attr()`, etc.
- [ ] All database queries use `$wpdb->prepare()`
- [ ] No direct file access (check `ABSPATH` at top of files)
- [ ] API key never exposed in JavaScript or HTML source

---

## Coding Standards

- Follow WordPress Coding Standards (WPCS)
- Use `RFS_AI_SEO` namespace for all classes
- PHPDoc blocks on all classes, methods, and functions
- Type hints on method parameters and return types (PHP 8.0+)
- All strings translation-ready with `__()` or `esc_html__()` using `rfs-ai-seo` text domain
- Use WordPress functions where available (no direct cURL, use `wp_remote_post()`)
- Consistent indentation (tabs for PHP, spaces for JS/CSS per WP standards)

---

## Git Configuration

### .gitignore

```gitignore
# Dependencies
/vendor/
/node_modules/

# Build artifacts
*.map
*.min.css
*.min.js

# IDE and editors
.idea/
.vscode/
*.swp
*.swo
*~
.DS_Store

# WordPress
wp-config.php
wp-content/uploads/
wp-content/upgrade/
wp-content/backup-db/
wp-content/advanced-cache.php
wp-content/wp-cache-config.php
wp-content/cache/
wp-content/backups/

# Logs
*.log
debug.log

# Environment
.env
.env.local
.env.*.local

# OS
Thumbs.db
ehthumbs.db

# Package files
*.zip
*.tar.gz

# Testing
/tests/
phpunit.xml
.phpunit.result.cache

# Composer
composer.lock

# NPM
package-lock.json
```

### README.md (GitHub)

```markdown
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
- Single item and bulk generation
- SERP preview with character counters
- Customizable prompt templates
- ACF field support (including flexible content)
- Usage tracking and statistics
- Direct Yoast SEO integration

## Installation

1. Download the plugin zip
2. Upload to WordPress via Plugins > Add New > Upload
3. Activate the plugin
4. Go to RFS AI SEO > Settings
5. Enter your OpenAI API key
6. Configure prompt templates as needed

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

## License

GPL v2 or later

## Author

Red Frog Studio
```

---

## Build Order Recommendation

1. **Phase 1: Core Structure**
   - Main plugin file with activation/deactivation
   - Database table creation
   - Settings class with API key storage
   - Admin menu registration

2. **Phase 2: OpenAI Integration**
   - OpenAI client class
   - Connection test functionality
   - Basic API call handling

3. **Phase 3: Content Collection**
   - Content collector for products
   - Content collector for categories
   - ACF handler

4. **Phase 4: Generation**
   - Prompt manager with templates
   - Generator class
   - Yoast integration (read/write)

5. **Phase 5: Single Item UI**
   - Meta box registration
   - Meta box template and JS
   - SERP preview component
   - Character counter
   - AJAX handlers

6. **Phase 6: Bulk Generation**
   - Bulk generator page
   - Filter and listing functionality
   - Bulk processing with progress
   - Results logging

7. **Phase 7: Usage & Polish**
   - Usage logger
   - Statistics display
   - CSV export
   - Final testing and cleanup

---

## Testing Checklist

- [ ] Plugin activates without errors
- [ ] Dependency checks work (Yoast, WooCommerce)
- [ ] API key saves and encrypts properly
- [ ] Connection test works
- [ ] Single product generation works
- [ ] Single variation generation works
- [ ] Single category generation works
- [ ] Meta saves to Yoast fields correctly
- [ ] Overwrite confirmation appears
- [ ] SERP preview updates live
- [ ] Character counters work with correct colors
- [ ] Bulk filtering works
- [ ] Bulk selection works
- [ ] Bulk generation processes correctly
- [ ] Progress display updates
- [ ] Stop button works
- [ ] Usage logging records correctly
- [ ] Statistics display correctly
- [ ] CSV export works
- [ ] ACF fields extract correctly
- [ ] Flexible content fields work
- [ ] All AJAX endpoints secured
- [ ] No PHP errors/warnings in debug mode
