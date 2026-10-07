<?php
/**
 * FOODIIE — static site generator.
 *
 * DUAL MODE:
 *  - CLI:  php cms/generator/generate.php [--publish-due]
 *          --publish-due : publish due scheduled items, then regenerate.
 *          default       : full generate_site().
 *          Prints a text summary and exits.
 *  - Include library (e.g. admin preview / regenerate): require this file —
 *    it defines functions only and performs NO side effects.
 */

require_once __DIR__ . '/../functions/template.php';
require_once __DIR__ . '/../functions/recipe_categories.php';

if (!function_exists('generate_site')) {

/* ------------------------------------------------------------------ */
/* publish_due_scheduled()                                            */
/* ------------------------------------------------------------------ */

/**
 * Flip scheduled items whose publish_at has passed to 'published'.
 * Runs at the start of every generate_site().
 */
function publish_due_scheduled(): int
{
    $total = 0;
    foreach (['articles', 'recipes', 'videos', 'shorts'] as $table) {
        $stmt = db()->prepare(
            "UPDATE {$table} SET status = 'published'
             WHERE status = 'scheduled' AND publish_at <= :now"
        );
        $stmt->execute([':now' => now_utc()]);
        $total += $stmt->rowCount();
    }
    return $total;
}

/* ------------------------------------------------------------------ */
/* published_rows()                                                   */
/* ------------------------------------------------------------------ */

function published_rows(string $table, ?int $limit = null, string $order = 'publish_at DESC, id DESC'): array
{
    $allowed = ['articles', 'recipes', 'videos', 'pages', 'categories', 'shorts'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Unknown table: ' . $table);
    }
    // Whitelist the ORDER BY clause (no user input reaches it unfiltered).
    if (!preg_match('/^[a-z0-9_,\s]+$/i', $order)) {
        $order = 'publish_at DESC, id DESC';
    }
    $sql = "SELECT * FROM {$table}
            WHERE status = 'published'
              AND (publish_at IS NULL OR TRIM(publish_at) = '' OR publish_at <= :now)
            ORDER BY {$order}";
    if ($limit !== null && $limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute([':now' => now_utc()]);
    return $stmt->fetchAll();
}

/* ------------------------------------------------------------------ */
/* categories / authors                                               */
/* ------------------------------------------------------------------ */

function category_map(): array
{
    $map = [];
    foreach (db()->query('SELECT * FROM categories ORDER BY sort ASC, name ASC')->fetchAll() as $row) {
        $map[(int) $row['id']] = $row;
    }
    return $map;
}

function category_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function author_name(?int $id): string
{
    if ($id) {
        $stmt = db()->prepare('SELECT name FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if ($row && trim((string) $row['name']) !== '') {
            return (string) $row['name'];
        }
    }
    return setting('default_author', 'Foodiie Editorial Team');
}

/* ------------------------------------------------------------------ */
/* cards                                                              */
/* ------------------------------------------------------------------ */

/**
 * Normalized card for templates/partials/card.php.
 * Keys: type, title, url, image, category, description, date, meta.
 */
function card_for(string $type, array $row): array
{
    $cats = category_map();
    switch ($type) {
        case 'article':
            $title = (string) ($row['title'] ?? '');
            $slug = (string) ($row['slug'] ?? '');
            $url = u('articles/' . $slug . '/');
            $image = public_image_url((string) ($row['image'] ?? ''));
            $cat = $cats[(int) ($row['category_id'] ?? 0)]['name'] ?? '';
            $desc = (string) ($row['description'] ?? '');
            if ($desc === '') {
                $desc = excerpt((string) ($row['body'] ?? ''));
            }
            $meta = ((int) ($row['reading_time'] ?? 0)) > 0
                ? (int) $row['reading_time'] . ' min read'
                : '';
            break;
        case 'recipe':
            $title = (string) ($row['name'] ?? '');
            $slug = (string) ($row['slug'] ?? '');
            $url = u('recipes/' . $slug . '/');
            $image = public_image_url((string) ($row['image'] ?? ''));
            $cat = trim((string) ($row['cuisine'] ?? '')) !== '' ? (string) $row['cuisine'] : 'Recipes';
            $desc = (string) ($row['description'] ?? '');
            $meta = trim((string) ($row['total_time'] ?? '')) !== ''
                ? (string) $row['total_time']
                : (string) ($row['difficulty'] ?? '');
            $has_video = !empty($row['youtube_video_id']) && (string) ($row['youtube_enabled'] ?? '1') !== '0';
            break;
        case 'video':
            $title = (string) ($row['title'] ?? '');
            $slug = (string) ($row['slug'] ?? '');
            $url = u('videos/' . $slug . '/');
            $image = video_thumbnail_url($row);
            $cat = $cats[(int) ($row['category_id'] ?? 0)]['name'] ?? 'Videos';
            $desc = (string) ($row['description'] ?? '');
            $meta = '';
            break;
        case 'short':
            $title = (string) ($row['title'] ?? '');
            $slug = (string) ($row['slug'] ?? '');
            $vid = (string) ($row['video_id'] ?? '');
            $url = u('shorts/');
            $image = !empty($row['thumbnail']) ? $row['thumbnail'] : ($vid ? "https://img.youtube.com/vi/{$vid}/hqdefault.jpg" : '');
            $cat = 'Shorts';
            $desc = (string) ($row['description'] ?? '');
            $meta = 'YouTube Short';
            break;
        default:
            throw new InvalidArgumentException('Unknown card type: ' . $type);
    }
    return [
        'type' => $type,
        'title' => $title,
        'url' => $url,
        'image' => $image,
        'category' => $cat,
        'description' => $desc,
        'date' => fmt_date($row['publish_at'] ?? null),
        'meta' => $meta,
        'has_video' => $has_video ?? false,
    ];
}

/**
 * Related items: same category first, then tag overlap. Excludes self.
 * Returns normalized cards.
 */
function related_items(string $type, array $row, int $limit = 3): array
{
    $candidates = published_rows($type === 'article' ? 'articles' : ($type === 'recipe' ? 'recipes' : 'videos'));
    $self_id = (int) ($row['id'] ?? 0);
    $self_tags = array_map('mb_strtolower', parse_tags((string) ($row['tags'] ?? '')));
    $self_cat = (int) ($row['category_id'] ?? 0);

    $scored = [];
    foreach ($candidates as $cand) {
        if ((int) ($cand['id'] ?? 0) === $self_id) {
            continue;
        }
        $score = 0;
        $cand_cat = (int) ($cand['category_id'] ?? 0);
        if ($self_cat > 0 && $cand_cat === $self_cat) {
            $score += 100;
        }
        $cand_tags = array_map('mb_strtolower', parse_tags((string) ($cand['tags'] ?? '')));
        $score += count(array_intersect($self_tags, $cand_tags));
        $scored[] = ['score' => $score, 'row' => $cand];
    }
    usort($scored, function ($a, $b) {
        if ($a['score'] !== $b['score']) {
            return $b['score'] <=> $a['score'];
        }
        return strcmp((string) ($b['row']['publish_at'] ?? ''), (string) ($a['row']['publish_at'] ?? ''));
    });

    $cards = [];
    foreach (array_slice($scored, 0, $limit) as $s) {
        $cards[] = card_for($type, $s['row']);
    }
    return $cards;
}

/* ------------------------------------------------------------------ */
/* render_*_page()                                                     */
/* ------------------------------------------------------------------ */

function render_article_page(array $row): string
{
    $cats = category_map();
    $cat = $cats[(int) ($row['category_id'] ?? 0)] ?? null;
    $cat_name = $cat ? (string) $cat['name'] : '';
    $cat_path = $cat ? $cat['slug'] . '/' : '';

    $url_path = 'articles/' . $row['slug'] . '/';
    $row['url'] = u($url_path);
    $row['image_url'] = public_image_url((string) ($row['image'] ?? ''));

    $page = [
        'title' => trim((string) ($row['seo_title'] ?? '')) !== ''
            ? (string) $row['seo_title']
            : (string) $row['title'] . ' | FOODIIE',
        'description' => trim((string) ($row['seo_description'] ?? '')) !== ''
            ? (string) $row['seo_description']
            : (trim((string) ($row['description'] ?? '')) !== ''
                ? (string) $row['description']
                : excerpt((string) ($row['body'] ?? ''))),
        'canonical' => trim((string) ($row['canonical'] ?? '')) !== ''
            ? (string) $row['canonical']
            : $url_path,
        'og_image' => trim((string) ($row['og_image'] ?? '')) !== ''
            ? (string) $row['og_image']
            : $row['image_url'],
        'type' => 'article',
    ];

    $breadcrumb = [['name' => 'Home', 'url' => '/']];
    if ($cat) {
        $breadcrumb[] = ['name' => $cat['name'], 'url' => $cat_path];
    }
    $breadcrumb[] = ['name' => $row['title'], 'url' => $url_path];

    $jsonld = [
        jsonld_article($row, $url_path, $cat_name, author_name(isset($row['author_id']) ? (int) $row['author_id'] : null)),
        jsonld_breadcrumb($breadcrumb),
    ];

    return render('article', [
        'page' => $page,
        'jsonld' => $jsonld,
        'article' => $row,
        'row' => $row,
        'category' => $cat,
        'category_name' => $cat_name,
        'author_name' => author_name(isset($row['author_id']) ? (int) $row['author_id'] : null),
        'related' => related_items('article', $row, 3),
    ]);
}

function render_recipe_page(array $row): string
{
    $url_path = 'recipes/' . $row['slug'] . '/';
    $row['url'] = u($url_path);
    $row['image_url'] = public_image_url((string) ($row['image'] ?? ''));

    $page = [
        'title' => trim((string) ($row['seo_title'] ?? '')) !== ''
            ? (string) $row['seo_title']
            : (string) $row['name'] . ' | FOODIIE Recipes',
        'description' => trim((string) ($row['seo_description'] ?? '')) !== ''
            ? (string) $row['seo_description']
            : excerpt((string) ($row['description'] ?? '')),
        'canonical' => $url_path,
        'og_image' => $row['image_url'],
        'type' => 'article',
    ];

    $jsonld = [
        jsonld_recipe($row, $url_path),
        jsonld_breadcrumb([
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Recipes', 'url' => 'recipes/'],
            ['name' => $row['name'], 'url' => $url_path],
        ]),
    ];

    return render('recipe', [
        'page' => $page,
        'jsonld' => $jsonld,
        'recipe' => $row,
        'row' => $row,
        'related' => related_items('recipe', $row, 3),
    ]);
}

function render_video_page(array $row): string
{
    $cats = category_map();
    $cat = $cats[(int) ($row['category_id'] ?? 0)] ?? null;
    $cat_name = $cat ? (string) $cat['name'] : '';
    $cat_path = $cat ? $cat['slug'] . '/' : '';

    $url_path = 'videos/' . $row['slug'] . '/';
    $row['url'] = u($url_path);
    $row['image_url'] = video_thumbnail_url($row);

    $page = [
        'title' => trim((string) ($row['seo_title'] ?? '')) !== ''
            ? (string) $row['seo_title']
            : (string) $row['title'] . ' | FOODIIE',
        'description' => trim((string) ($row['seo_description'] ?? '')) !== ''
            ? (string) $row['seo_description']
            : excerpt((string) ($row['description'] ?? '')),
        'canonical' => $url_path,
        'og_image' => $row['image_url'],
        'type' => 'article',
    ];

    $breadcrumb = [['name' => 'Home', 'url' => '/']];
    if ($cat) {
        $breadcrumb[] = ['name' => $cat['name'], 'url' => $cat_path];
    } else {
        $breadcrumb[] = ['name' => 'Videos', 'url' => 'videos/'];
    }
    $breadcrumb[] = ['name' => $row['title'], 'url' => $url_path];

    $jsonld = [
        jsonld_video($row, $url_path),
        jsonld_breadcrumb($breadcrumb),
    ];

    return render('video', [
        'page' => $page,
        'jsonld' => $jsonld,
        'video' => $row,
        'row' => $row,
        'category' => $cat,
        'category_name' => $cat_name,
        'related' => related_items('video', $row, 3),
    ]);
}

/* ------------------------------------------------------------------ */
/* generate_articles() / generate_recipes() / generate_videos()        */
/* ------------------------------------------------------------------ */

function generate_articles(): int
{
    $n = 0;
    foreach (published_rows('articles') as $row) {
        write_public('articles/' . $row['slug'], render_article_page($row));
        $n++;
    }
    return $n;
}

function generate_recipes(): int
{
    $n = 0;
    foreach (published_rows('recipes') as $row) {
        write_public('recipes/' . $row['slug'], render_recipe_page($row));
        $n++;
    }
    return $n;
}

function generate_videos(): int
{
    $n = 0;
    foreach (published_rows('videos') as $row) {
        write_public('videos/' . $row['slug'], render_video_page($row));
        $n++;
    }
    return $n;
}

/* ------------------------------------------------------------------ */
/* generate_shorts()                                                  */
/* ------------------------------------------------------------------ */

function generate_shorts(): int
{
    $shorts = published_rows('shorts', null, 'publish_at DESC, id DESC');
    $page = [
        'title' => 'Food Shorts — Quick Kitchen Hacks & Recipes | FOODIIE',
        'description' => 'Watch quick 60-second recipe hacks, food tips, and viral street food moments on FOODIIE Shorts.',
        'canonical' => 'shorts/',
        'og_image' => '',
        'type' => 'website',
    ];
    $jsonld = [
        jsonld_breadcrumb([
            ['name' => 'Home', 'url' => '/'],
            ['name' => 'Shorts', 'url' => 'shorts/'],
        ]),
    ];
    write_public('shorts', render('shorts', [
        'page' => $page,
        'jsonld' => $jsonld,
        'shorts' => $shorts,
    ]));
    return count($shorts);
}

/* ------------------------------------------------------------------ */
/* generate_recipe_category_archives()                                */
/* ------------------------------------------------------------------ */

function generate_recipe_category_archives(): int
{
    $cats = rc_all();
    $n = 0;
    $all_recipes = published_rows('recipes');

    $prefix_map = [
        'cuisine'    => 'cuisine/',
        'course'     => 'course/',
        'diet'       => 'diet/',
        'ingredient' => 'recipes/ingredient/',
        'style'      => 'style/',
        'dessert'    => 'dessert/',
        'occasion'   => 'occasion/',
    ];

    $label_map = [
        'cuisine'    => 'Cuisines',
        'course'     => 'Courses',
        'diet'       => 'Diet & Health',
        'ingredient' => 'By Ingredient',
        'style'      => 'Cooking Styles',
        'dessert'    => 'Desserts',
        'occasion'   => 'Occasions',
    ];

    foreach ($cats as $cat) {
        $slug = (string) $cat['slug'];
        $type = (string) ($cat['type'] ?? 'category');

        $prefix = $prefix_map[$type] ?? 'category/';
        $url_path = $prefix . $slug;

        // Collect matching recipes
        $mapped_ids = rc_recipe_ids((int) $cat['id'], true);

        $matching = [];
        foreach ($all_recipes as $rec) {
            $rid = (int) $rec['id'];
            $is_match = in_array($rid, $mapped_ids, true);

            // Direct column match fallback
            if (!$is_match) {
                if ($type === 'cuisine' && (strcasecmp((string)$rec['cuisine'], $cat['name']) === 0 || strcasecmp((string)$rec['cuisine'], $cat['slug']) === 0)) {
                    $is_match = true;
                } elseif ($type === 'course' && (strcasecmp((string)$rec['course'], $cat['name']) === 0 || strcasecmp((string)$rec['course'], $cat['slug']) === 0)) {
                    $is_match = true;
                } elseif ($type === 'diet' && (strcasecmp((string)$rec['diet_type'], $cat['name']) === 0 || strcasecmp((string)$rec['diet_type'], $cat['slug']) === 0)) {
                    $is_match = true;
                }
            }
            if ($is_match) {
                $matching[] = $rec;
            }
        }

        $cards = array_map(fn($r) => card_for('recipe', $r), $matching);
        $type_label = $label_map[$type] ?? 'Recipes';

        $page = [
            'title' => trim((string) ($cat['seo_title'] ?? '')) !== ''
                ? (string) $cat['seo_title']
                : (string) $cat['name'] . ' Recipes | FOODIIE',
            'description' => trim((string) ($cat['seo_description'] ?? '')) !== ''
                ? (string) $cat['seo_description']
                : (trim((string) ($cat['description'] ?? '')) !== ''
                    ? (string) $cat['description']
                    : 'Explore delicious and authentic ' . $cat['name'] . ' recipes on FOODIIE.'),
            'canonical' => $url_path . '/',
            'og_image' => public_image_url((string) ($cat['image'] ?? '')),
            'type' => 'website',
        ];

        $breadcrumb_trail = [
            ['name' => $type_label, 'url' => 'recipes/'],
            ['name' => $cat['name'], 'url' => ''],
        ];

        $jsonld = [
            jsonld_breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => $type_label, 'url' => 'recipes/'],
                ['name' => $cat['name'], 'url' => $url_path . '/'],
            ]),
        ];

        write_public($url_path, render('archive', [
            'page' => $page,
            'jsonld' => $jsonld,
            'category' => $cat,
            'items' => $cards,
            'cards' => $cards,
            'breadcrumb_trail' => $breadcrumb_trail,
        ]));
        $n++;
    }
    return $n;
}

/* ------------------------------------------------------------------ */
/* generate_article_type_archives()                                  */
/* ------------------------------------------------------------------ */

function generate_article_type_archives(): int
{
    $definitions = [
        'food-news'    => ['name' => 'Food News', 'slug' => 'food-news', 'desc' => 'Latest culinary news, trending restaurant openings, and food industry buzz.'],
        'kitchen-hack' => ['name' => 'Kitchen Hacks', 'slug' => 'kitchen-hacks', 'desc' => 'Smart cooking tips, food prep shortcuts, and easy kitchen tricks.'],
        'health'       => ['name' => 'Health & Nutrition', 'slug' => 'health', 'desc' => 'Nutritional guides, healthy diet tips, and wellness-focused food insights.'],
        'food-fact'    => ['name' => 'Food Facts', 'slug' => 'food-facts', 'desc' => 'Fascinating origins, nutritional truths, and fun facts about your favorite foods.'],
        'food-tip'     => ['name' => 'Cooking Tips', 'slug' => 'food-tips', 'desc' => 'Master kitchen fundamentals with expert culinary advice and step-by-step guides.'],
        'trending'     => ['name' => 'Trending Food', 'slug' => 'trending', 'desc' => 'Viral recipes, trending dishes, and popular foodie discussions.'],
    ];

    $all_articles = published_rows('articles');
    $n = 0;

    foreach ($definitions as $type_key => $def) {
        $matching = [];
        foreach ($all_articles as $art) {
            if (($art['article_type'] ?? '') === $type_key) {
                $matching[] = $art;
            }
        }

        $cards = array_map(fn($r) => card_for('article', $r), $matching);

        $cat_mock = [
            'name' => $def['name'],
            'slug' => $def['slug'],
            'description' => $def['desc'],
            'image' => '',
        ];

        $page = [
            'title' => $def['name'] . ' | FOODIIE',
            'description' => $def['desc'],
            'canonical' => $def['slug'] . '/',
            'og_image' => '',
            'type' => 'website',
        ];

        $breadcrumb_trail = [
            ['name' => 'Features', 'url' => 'articles/'],
            ['name' => $def['name'], 'url' => ''],
        ];

        $jsonld = [
            jsonld_breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'Articles', 'url' => 'articles/'],
                ['name' => $def['name'], 'url' => $def['slug'] . '/'],
            ]),
        ];

        write_public($def['slug'], render('archive', [
            'page' => $page,
            'jsonld' => $jsonld,
            'category' => $cat_mock,
            'items' => $cards,
            'cards' => $cards,
            'breadcrumb_trail' => $breadcrumb_trail,
        ]));
        $n++;
    }
    return $n;
}

/* ------------------------------------------------------------------ */
/* generate_archives()                                                */
/* ------------------------------------------------------------------ */

function generate_archives(): int
{
    $cats = category_map();
    $n = 0;
    foreach ($cats as $cat) {
        $slug = (string) $cat['slug'];
        $type = 'article';
        if ($slug === 'recipes') {
            $rows = published_rows('recipes');
            $type = 'recipe';
        } elseif ($slug === 'videos') {
            $rows = published_rows('videos');
            $type = 'video';
        } else {
            $rows = [];
            foreach (published_rows('articles') as $a) {
                if ((int) ($a['category_id'] ?? 0) === (int) $cat['id']) {
                    $rows[] = $a;
                }
            }
        }
        $cards = array_map(fn($r) => card_for($type, $r), $rows);

        $page = [
            'title' => trim((string) ($cat['seo_title'] ?? '')) !== ''
                ? (string) $cat['seo_title']
                : (string) $cat['name'] . ' | FOODIIE',
            'description' => trim((string) ($cat['seo_description'] ?? '')) !== ''
                ? (string) $cat['seo_description']
                : (string) ($cat['description'] ?? ''),
            'canonical' => $slug . '/',
            'og_image' => public_image_url((string) ($cat['image'] ?? '')),
            'type' => 'website',
        ];
        $jsonld = [
            jsonld_breadcrumb([
                ['name' => 'Home', 'url' => '/'],
                ['name' => $cat['name'], 'url' => $slug . '/'],
            ]),
        ];
        write_public($slug, render('archive', [
            'page' => $page,
            'jsonld' => $jsonld,
            'category' => $cat,
            'items' => $cards,
            'cards' => $cards,
        ]));
        $n++;

        // Legacy /category/<slug> redirect -> /<slug>/
        $target = u($slug . '/');
        $redirect = '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">' .
            '<title>Redirecting&hellip;</title>' .
            '<meta name="robots" content="noindex">' .
            '<link rel="canonical" href="' . esc($target) . '">' .
            '<meta http-equiv="refresh" content="0; url=' . esc($target) . '">' .
            "</head><body><p>Redirecting to <a href=\"" . esc($target) . "\">" . esc($target) . "</a>&hellip;</p></body></html>\n";
        write_public('category/' . $slug, $redirect);
    }
    return $n;
}

/* ------------------------------------------------------------------ */
/* generate_home()                                                    */
/* ------------------------------------------------------------------ */

function generate_home(): void
{
    $trending = array_map(fn($r) => card_for('article', $r), published_rows('articles', 6));
    $recipes_raw = published_rows('recipes', 8);
    $latest_recipes = array_map(fn($r) => card_for('recipe', $r), $recipes_raw);
    $featured_recipe = !empty($recipes_raw) ? $recipes_raw[0] : null;

    $healthy_cat = category_by_slug('healthy-food');
    $facts_cat = category_by_slug('food-facts');
    $healthy = [];
    $facts = [];
    foreach (published_rows('articles', 40) as $a) {
        $cid = (int) ($a['category_id'] ?? 0);
        if ($healthy_cat && count($healthy) < 4 && $cid === (int) $healthy_cat['id']) {
            $healthy[] = card_for('article', $a);
        }
        if ($facts_cat && count($facts) < 4 && $cid === (int) $facts_cat['id']) {
            $facts[] = card_for('article', $a);
        }
    }
    // Raw rows (not card_for()): the home template needs video_id for the
    // click-to-play facade. card_for() drops that key, which left data-id=""
    // and made every homepage video unplayable.
    $videos = published_rows('videos', 4);
    $shorts = published_rows('shorts', 6, 'publish_at DESC, id DESC');

    // Popular Ingredients for circular explorer
    $popular_ingredients = [
        ['name' => 'Paneer', 'slug' => 'paneer-recipes', 'image' => 'assets/uploads/seed/paneer-sandwich.jpg', 'count' => '40+ Recipes'],
        ['name' => 'Eggs', 'slug' => 'egg-recipes', 'image' => 'assets/uploads/seed/masala-omelette.jpg', 'count' => '25+ Recipes'],
        ['name' => 'Rice & Poha', 'slug' => 'rice-recipes', 'image' => 'assets/uploads/seed/vegetable-poha.jpg', 'count' => '35+ Recipes'],
        ['name' => 'Pasta & Bakes', 'slug' => 'potato-recipes', 'image' => 'assets/uploads/seed/vegetable-pasta.jpg', 'count' => '20+ Recipes'],
        ['name' => 'Lassi & Drinks', 'slug' => 'mango-lassi', 'image' => 'assets/uploads/seed/mango-lassi.jpg', 'count' => '15+ Recipes', 'url' => 'recipes/mango-lassi/'],
        ['name' => 'Street Food', 'slug' => 'street-food', 'image' => 'assets/uploads/seed/indian-street-foods-you-should-try.jpg', 'count' => '50+ Recipes', 'url' => 'street-food/'],
    ];

    // Popular Cuisines for explorer grid
    $popular_cuisines = [
        ['name' => 'North Indian', 'slug' => 'north-indian', 'desc' => 'Rich curries, stuffed parathas & tandoori delights', 'badge' => 'Popular', 'emoji' => '🍛'],
        ['name' => 'South Indian', 'slug' => 'south-indian', 'desc' => 'Crispy dosas, fluffy idlis, vada & coconut chutneys', 'badge' => 'Classic', 'emoji' => '🥥'],
        ['name' => 'Indo-Chinese', 'slug' => 'chinese', 'desc' => 'Spicy wok noodles, chilli paneer & sizzling gravies', 'badge' => 'Street Fav', 'emoji' => '🥢'],
        ['name' => 'Italian Classics', 'slug' => 'italian', 'desc' => 'Stone-baked pizzas, creamy pastas & garlic breads', 'badge' => 'World', 'emoji' => '🍕'],
        ['name' => 'Royal Punjabi', 'slug' => 'punjabi', 'desc' => 'Dal makhani, butter gravies & kulcha platters', 'badge' => 'Hearty', 'emoji' => '🫓'],
        ['name' => 'Healthy Diet', 'slug' => 'healthy-food', 'desc' => 'Clean eating, high-protein & fresh nourishing bowls', 'badge' => 'Wellness', 'emoji' => '🥗', 'url' => 'health/'],
    ];

    $page = [
        'title' => 'FOODIIE | Discover. Cook. Eat Better.',
        'description' => setting('site_tagline', 'Discover. Cook. Eat Better.'),
        'canonical' => '/',
        'og_image' => '',
        'type' => 'website',
    ];
    $jsonld = [jsonld_website(), jsonld_organization()];

    write_public('', render('home', [
        'page' => $page,
        'jsonld' => $jsonld,
        'trending' => $trending,
        'latest_recipes' => $latest_recipes,
        'featured_recipe' => $featured_recipe,
        'healthy_items' => $healthy,
        'fact_items' => $facts,
        'videos' => $videos,
        'shorts' => $shorts,
        'popular_ingredients' => $popular_ingredients,
        'popular_cuisines' => $popular_cuisines,
    ]));
}

/* ------------------------------------------------------------------ */
/* generate_search_page()                                             */
/* ------------------------------------------------------------------ */

function generate_search_page(): void
{
    $page = [
        'title' => 'Search | FOODIIE',
        'description' => 'Search recipes, articles and videos on FOODIIE.',
        'canonical' => 'search/',
        'og_image' => '',
        'type' => 'website',
        'robots' => 'noindex, follow',
    ];
    write_public('search', render('search', [
        'page' => $page,
        'jsonld' => [],
    ]));
}

/* ------------------------------------------------------------------ */
/* generate_static_pages()                                            */
/* ------------------------------------------------------------------ */

function generate_static_pages(): int
{
    $n = 0;
    $rows = db()->query('SELECT * FROM pages ORDER BY slug ASC')->fetchAll();
    foreach ($rows as $row) {
        $slug = (string) $row['slug'];
        $page = [
            'title' => trim((string) ($row['seo_title'] ?? '')) !== ''
                ? (string) $row['seo_title']
                : (string) $row['title'] . ' | FOODIIE',
            'description' => trim((string) ($row['seo_description'] ?? '')) !== ''
                ? (string) $row['seo_description']
                : excerpt((string) ($row['body'] ?? '')),
            'canonical' => $slug . '/',
            'og_image' => '',
            'type' => 'website',
        ];
        write_public($slug, render('page', [
            'page' => $page,
            'jsonld' => [],
            'page_row' => $row,
        ]));
        $n++;
    }
    return $n;
}

/* ------------------------------------------------------------------ */
/* generate_404()                                                     */
/* ------------------------------------------------------------------ */

function generate_404(): void
{
    $page = [
        'title' => 'Page not found | FOODIIE',
        'description' => 'The page you are looking for does not exist.',
        'canonical' => '/',
        'og_image' => '',
        'type' => 'website',
        'robots' => 'noindex, follow',
    ];
    write_public_file('404.html', render('404', [
        'page' => $page,
        'jsonld' => [],
    ]));
}

/* ------------------------------------------------------------------ */
/* generate_sitemap()                                                 */
/* ------------------------------------------------------------------ */

function generate_sitemap(): int
{
    $urls = [];
    $urls[] = ['loc' => abs_url(''), 'lastmod' => gmdate('Y-m-d')];
    $urls[] = ['loc' => abs_url('shorts/'), 'lastmod' => gmdate('Y-m-d')];

    // Feature archives
    foreach (['food-news', 'kitchen-hacks', 'health', 'food-facts', 'food-tips', 'trending'] as $fslug) {
        $urls[] = [
            'loc' => abs_url($fslug . '/'),
            'lastmod' => gmdate('Y-m-d'),
        ];
    }

    $cats = category_map();
    foreach ($cats as $cat) {
        $urls[] = [
            'loc' => abs_url($cat['slug'] . '/'),
            'lastmod' => gmdate('Y-m-d'),
        ];
    }

    $rc_prefixes = [
        'cuisine'    => 'cuisine/',
        'course'     => 'course/',
        'diet'       => 'diet/',
        'ingredient' => 'recipes/ingredient/',
        'style'      => 'style/',
        'dessert'    => 'dessert/',
        'occasion'   => 'occasion/',
    ];
    $rcs = rc_all();
    foreach ($rcs as $rc) {
        $prefix = $rc_prefixes[$rc['type'] ?? ''] ?? 'category/';
        $urls[] = [
            'loc' => abs_url($prefix . $rc['slug'] . '/'),
            'lastmod' => gmdate('Y-m-d'),
        ];
    }

    $rows = db()->query('SELECT slug, title, updated_at FROM pages ORDER BY slug ASC')->fetchAll();
    foreach ($rows as $r) {
        $urls[] = [
            'loc' => abs_url($r['slug'] . '/'),
            'lastmod' => date('Y-m-d', strtotime((string) $r['updated_at']) ?: time()),
        ];
    }
    foreach (published_rows('articles') as $r) {
        $urls[] = [
            'loc' => abs_url('articles/' . $r['slug'] . '/'),
            'lastmod' => date('Y-m-d', strtotime((string) $r['updated_at']) ?: time()),
        ];
    }
    foreach (published_rows('recipes') as $r) {
        $urls[] = [
            'loc' => abs_url('recipes/' . $r['slug'] . '/'),
            'lastmod' => date('Y-m-d', strtotime((string) $r['updated_at']) ?: time()),
        ];
    }
    foreach (published_rows('videos') as $r) {
        $urls[] = [
            'loc' => abs_url('videos/' . $r['slug'] . '/'),
            'lastmod' => date('Y-m-d', strtotime((string) $r['updated_at']) ?: time()),
        ];
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $u) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . esc($u['loc']) . "</loc>\n";
        $xml .= '    <lastmod>' . esc($u['lastmod']) . "</lastmod>\n";
        $xml .= "  </url>\n";
    }
    $xml .= "</urlset>\n";

    write_public_file('sitemap.xml', $xml);
    return count($urls);
}

/* ------------------------------------------------------------------ */
/* generate_search_index()                                            */
/* ------------------------------------------------------------------ */

function generate_search_index(): int
{
    $items = [];
    $cats = category_map();

    foreach (published_rows('articles') as $r) {
        $items[] = [
            'title' => (string) $r['title'],
            'description' => (string) ($r['description'] ?: excerpt((string) $r['body'])),
            'url' => u('articles/' . $r['slug'] . '/'),
            'category' => $cats[(int) ($r['category_id'] ?? 0)]['name'] ?? '',
            'article_type' => (string) ($r['article_type'] ?? 'article'),
            'tags' => parse_tags((string) ($r['tags'] ?? '')),
            'type' => 'article',
            'image' => public_image_url((string) ($r['image'] ?? '')),
        ];
    }
    foreach (published_rows('recipes') as $r) {
        $items[] = [
            'title' => (string) $r['name'],
            'description' => (string) ($r['description'] ?? ''),
            'url' => u('recipes/' . $r['slug'] . '/'),
            'category' => 'Recipes',
            'cuisine' => (string) ($r['cuisine'] ?? ''),
            'course' => (string) ($r['course'] ?? ''),
            'diet' => (string) ($r['diet_type'] ?? ''),
            'method' => (string) ($r['cooking_method'] ?? ''),
            'skill' => (string) ($r['skill_level'] ?? ''),
            'time' => (string) ($r['total_time'] ?: $r['cook_time'] ?: $r['prep_time'] ?: ''),
            'tags' => parse_tags((string) ($r['tags'] ?? '')),
            'type' => 'recipe',
            'image' => public_image_url((string) ($r['image'] ?? '')),
        ];
    }
    foreach (published_rows('videos') as $r) {
        $items[] = [
            'title' => (string) $r['title'],
            'description' => (string) ($r['description'] ?? ''),
            'url' => u('videos/' . $r['slug'] . '/'),
            'category' => $cats[(int) ($r['category_id'] ?? 0)]['name'] ?? 'Videos',
            'tags' => parse_tags((string) ($r['tags'] ?? '')),
            'type' => 'video',
            'image' => video_thumbnail_url($r),
        ];
    }
    foreach (published_rows('shorts') as $r) {
        $vid = (string) ($r['video_id'] ?? '');
        $items[] = [
            'title' => (string) $r['title'],
            'description' => (string) ($r['description'] ?? ''),
            'url' => u('shorts/'),
            'category' => 'Shorts',
            'tags' => parse_tags((string) ($r['tags'] ?? '')),
            'type' => 'short',
            'image' => !empty($r['thumbnail']) ? $r['thumbnail'] : ($vid ? "https://img.youtube.com/vi/{$vid}/hqdefault.jpg" : ''),
        ];
    }
    foreach (db()->query('SELECT * FROM pages ORDER BY slug ASC')->fetchAll() as $r) {
        $items[] = [
            'title' => (string) $r['title'],
            'description' => excerpt((string) ($r['body'] ?? '')),
            'url' => u($r['slug'] . '/'),
            'category' => '',
            'tags' => [],
            'type' => 'page',
            'image' => '',
        ];
    }

    write_public_file(
        'search-index.json',
        json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );
    return count($items);
}

/* ------------------------------------------------------------------ */
/* generate_robots() / generate_htaccess()                            */
/* ------------------------------------------------------------------ */

function generate_robots(): void
{
    if (env('APP_ENV', 'production') !== 'production') {
        $txt = "User-agent: *\nDisallow: /\n";
    } else {
        $txt  = "User-agent: *\n";
        $txt .= "Allow: /\n";
        $txt .= "Disallow: /api/\n";
        $txt .= "Sitemap: " . abs_url('sitemap.xml') . "\n";
    }
    write_public_file('robots.txt', $txt);
}

function generate_htaccess(): void
{
    $prefix = base_path() === '' ? '/' : base_path() . '/';
    write_public_file('.htaccess', "ErrorDocument 404 {$prefix}404.html\n");
}

/* ------------------------------------------------------------------ */
/* copy_public_images() / copy_api()                                  */
/* ------------------------------------------------------------------ */

/** Copy storage uploads referenced by published content into public/assets/uploads/. */
function copy_public_images(): int
{
    $values = [];
    foreach (published_rows('articles') as $r) {
        $values[] = (string) ($r['image'] ?? '');
        $values[] = (string) ($r['og_image'] ?? '');
    }
    foreach (published_rows('recipes') as $r) {
        $values[] = (string) ($r['image'] ?? '');
    }
    foreach (published_rows('videos') as $r) {
        $values[] = (string) ($r['thumbnail'] ?? '');
    }
    foreach (category_map() as $c) {
        $values[] = (string) ($c['image'] ?? '');
    }

    $n = 0;
    $seen = [];
    foreach ($values as $v) {
        $v = trim($v);
        if ($v === '' || !str_starts_with($v, 'uploads/')) {
            continue;
        }
        if (isset($seen[$v])) {
            continue;
        }
        $seen[$v] = true;
        // Guard against path traversal in stored values.
        if (str_contains($v, '..')) {
            continue;
        }
        $src = storage_path($v);
        $dst = FOODIIE_ROOT . '/public/assets/' . $v;
        if (!is_file($src)) {
            continue;
        }
        $dir = dirname($dst);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (copy($src, $dst)) {
            $n++;
        }
    }
    return $n;
}

/** Copy api/*.php -> public/api/*.php so forms work on a PHP host. */
function copy_api(): int
{
    $dst_dir = FOODIIE_ROOT . '/public/api';
    if (!is_dir($dst_dir)) {
        mkdir($dst_dir, 0755, true);
    }
    $n = 0;
    foreach (glob(FOODIIE_ROOT . '/api/*.php') ?: [] as $src) {
        if (is_file($src) && copy($src, $dst_dir . '/' . basename($src))) {
            $n++;
        }
    }
    return $n;
}

/* ------------------------------------------------------------------ */
/* clean_stale()                                                      */
/* ------------------------------------------------------------------ */

function generator_rm_dir(string $dir): void
{
    if (!is_dir($dir) || is_link($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}

/**
 * Remove stale generated dirs before regenerating, so items that became
 * unpublished/draft disappear. NEVER touches public/assets or public/api
 * (api is overwritten by copy_api()).
 */
function clean_stale(): void
{
    $public = FOODIIE_ROOT . '/public';
    $dirs = [
        'articles', 'recipes', 'videos', 'shorts', 'category',
        'cuisine', 'course', 'diet', 'style', 'dessert', 'occasion',
        'food-news', 'kitchen-hacks', 'food-facts', 'health', 'food-tips', 'trending'
    ];
    foreach ($dirs as $dir) {
        generator_rm_dir($public . '/' . $dir);
    }
    // Category archive dirs are named by category slug.
    foreach (db()->query('SELECT slug FROM categories')->fetchAll() as $c) {
        $slug = trim((string) ($c['slug'] ?? ''));
        if ($slug === '' || $slug === 'assets' || $slug === 'api') {
            continue;
        }
        generator_rm_dir($public . '/' . $slug);
    }
}

/* ------------------------------------------------------------------ */
/* generate_site()                                                    */
/* ------------------------------------------------------------------ */

function generate_site(): array
{
    $summary = [];
    $summary['scheduled_published'] = publish_due_scheduled();

    clean_stale();

    $summary['articles'] = generate_articles();
    $summary['recipes'] = generate_recipes();
    $summary['videos'] = generate_videos();
    $summary['shorts'] = generate_shorts();
    $summary['categories'] = generate_archives();
    $summary['recipe_categories'] = generate_recipe_category_archives();
    $summary['article_types'] = generate_article_type_archives();
    generate_home();
    $summary['home'] = 1;
    generate_search_page();
    $summary['search_page'] = 1;
    $summary['pages'] = generate_static_pages();
    generate_404();
    $summary['sitemap_urls'] = generate_sitemap();
    $summary['search_items'] = generate_search_index();
    generate_robots();
    generate_htaccess();
    $summary['copied_images'] = copy_public_images();
    $summary['copied_api'] = copy_api();

    return $summary;
}

} // end function_exists guard

/* ------------------------------------------------------------------ */
/* CLI entry point — only when run directly, never on include.        */
/* ------------------------------------------------------------------ */

if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $opts = array_slice($argv, 1);
    if (in_array('--help', $opts, true) || in_array('-h', $opts, true)) {
        echo "Usage: php cms/generator/generate.php [--publish-due]\n";
        echo "  --publish-due  Publish due scheduled items, then regenerate.\n";
        echo "  (default)      Full site generation (publishes due items first).\n";
        exit(0);
    }
    // --publish-due: publish due scheduled items, then regenerate.
    // generate_site() already publishes due items at its start; call it either way.
    $summary = generate_site();

    $parts = [];
    if (($summary['scheduled_published'] ?? 0) > 0) {
        $parts[] = $summary['scheduled_published'] . ' scheduled item(s) published';
    }
    $parts[] = $summary['articles'] . ' articles';
    $parts[] = $summary['recipes'] . ' recipes';
    $parts[] = $summary['videos'] . ' videos';
    $parts[] = ($summary['shorts'] ?? 0) . ' shorts';
    $parts[] = $summary['categories'] . ' category archives';
    $parts[] = ($summary['recipe_categories'] ?? 0) . ' recipe category archives';
    $parts[] = ($summary['article_types'] ?? 0) . ' feature archives';
    $parts[] = $summary['pages'] . ' static pages';
    $parts[] = $summary['search_items'] . ' search index entries';
    $parts[] = $summary['copied_images'] . ' images copied';
    $parts[] = $summary['copied_api'] . ' api files copied';
    echo 'Generated: ' . implode(', ', $parts) . ".\n";
    exit(0);
}
