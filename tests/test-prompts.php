<?php
require __DIR__ . '/lib.php';

use RFS_AI_SEO\Description_Generator;

$gen = new Description_Generator();
$data = ['product_name' => 'Test Blade', 'product_url' => 'https://example.test/p', 'product_description' => '', 'short_description' => '', 'categories' => 'Blades', 'attributes' => '', 'acf_all' => '', 'similar_products' => ''];

$p = $gen->build_description_prompt($data, 'We sell diamond tools.', ['tone' => 'technical', 'words' => 320, 'keywords' => 'diamond blade, 115mm', 'features' => true]);
rfs_assert(str_contains($p, 'Tone of voice: ' . Description_Generator::TONE_GUIDE['technical']), 'tone line');
rfs_assert(str_contains($p, 'Length: about 320 words'), 'length line');
rfs_assert(str_contains($p, 'focus keywords in naturally: diamond blade, 115mm'), 'keywords line');
rfs_assert(str_contains($p, 'Include a bullet-point list of key features'), 'features on');
rfs_assert(!str_contains($p, '200-400 words'), 'old fixed length removed');
rfs_assert(str_contains($p, 'We sell diamond tools.'), 'business context kept');
rfs_assert(str_contains($p, 'RESPOND IN JSON FORMAT ONLY'), 'JSON instruction kept');

$p = $gen->build_description_prompt($data, '', ['tone' => 'friendly', 'words' => 150, 'keywords' => '', 'features' => false]);
rfs_assert(str_contains($p, 'Do not use bullet lists'), 'features off');
rfs_assert(!str_contains($p, 'focus keywords'), 'no keywords line when empty');

$s = $gen->build_short_description_prompt($data, '', ['tone' => 'professional', 'sentences' => 3, 'keywords' => 'blade']);
rfs_assert(str_contains($s, 'exactly 3 sentences'), 'sentence count');
rfs_assert(str_contains($s, 'Tone of voice: ' . Description_Generator::TONE_GUIDE['professional']), 'short tone line');

rfs_done('prompts');
