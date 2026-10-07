<?php
/**
 * FOODIIE — Phase 1 migration script.
 *
 * Run once from CLI or admin:
 *   php cms/migrate_phase1.php
 *
 * Safe to run multiple times — all operations are idempotent.
 *
 * What it does:
 *   1. Creates new tables (recipe_categories, recipe_category_map, shorts)
 *   2. ALTERs recipes + articles tables with new columns
 *   3. Seeds 60+ hierarchical recipe categories
 */

require_once __DIR__ . '/functions/db.php';
require_once __DIR__ . '/functions/helpers.php';

$pdo = db();

echo "FOODIIE Phase 1 Migration\n";
echo "=========================\n\n";

/* ------------------------------------------------------------------ */
/* Step 1: Run schema migrations                                      */
/* ------------------------------------------------------------------ */

echo "1. Running schema migrations...\n";
db_migrate($pdo);
echo "   ✓ New tables created (recipe_categories, recipe_category_map, shorts)\n";
echo "   ✓ New columns added to recipes and articles\n\n";

/* ------------------------------------------------------------------ */
/* Step 2: Seed recipe categories                                     */
/* ------------------------------------------------------------------ */

echo "2. Seeding recipe categories...\n";

// Check if already seeded.
$existing = (int) $pdo->query('SELECT COUNT(*) FROM recipe_categories')->fetchColumn();
if ($existing > 0) {
    echo "   ⊘ Already seeded ({$existing} categories exist). Skipping.\n";
    echo "   (To re-seed, delete all rows from recipe_categories first.)\n\n";
} else {
    seed_recipe_categories($pdo);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM recipe_categories')->fetchColumn();
    echo "   ✓ Seeded {$count} recipe categories.\n\n";
}

/* ------------------------------------------------------------------ */
/* Step 3: Verify                                                     */
/* ------------------------------------------------------------------ */

echo "3. Verification:\n";

// Check new tables exist
$tables = ['recipe_categories', 'recipe_category_map', 'shorts'];
foreach ($tables as $t) {
    $check = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='{$t}'")->fetch();
    echo "   " . ($check ? '✓' : '✗') . " Table: {$t}\n";
}

// Check new columns on recipes
$cols = $pdo->query("PRAGMA table_info(recipes)")->fetchAll();
$colNames = array_column($cols, 'name');
$newCols = ['course', 'diet_type', 'cooking_method', 'skill_level', 'rating_sum', 'rating_count'];
foreach ($newCols as $c) {
    echo "   " . (in_array($c, $colNames) ? '✓' : '✗') . " recipes.{$c}\n";
}

// Check article_type on articles
$cols = $pdo->query("PRAGMA table_info(articles)")->fetchAll();
$colNames = array_column($cols, 'name');
echo "   " . (in_array('article_type', $colNames) ? '✓' : '✗') . " articles.article_type\n";

// Category counts by type
$types = $pdo->query("SELECT type, COUNT(*) as cnt FROM recipe_categories GROUP BY type ORDER BY type")->fetchAll();
echo "\n   Category breakdown:\n";
foreach ($types as $t) {
    echo "   · {$t['type']}: {$t['cnt']}\n";
}

echo "\n✅ Phase 1 migration complete!\n";


/* ================================================================== */
/* Category seed data                                                 */
/* ================================================================== */

function seed_recipe_categories(PDO $pdo): void
{
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT INTO recipe_categories (parent_id, name, slug, type, description, sort)
             VALUES (:pid, :name, :slug, :type, :desc, :sort)
             ON CONFLICT(slug) DO NOTHING'
        );

        $sort = 0;

        // Helper to insert and return the ID.
        $add = function (
            ?int $parent,
            string $name,
            string $slug,
            string $type,
            string $desc = ''
        ) use ($insert, &$sort, $pdo): int {
            $sort++;
            $insert->execute([
                ':pid'  => $parent,
                ':name' => $name,
                ':slug' => $slug,
                ':type' => $type,
                ':desc' => $desc,
                ':sort' => $sort,
            ]);
            // If ON CONFLICT hit, fetch existing ID.
            $id = (int) $pdo->lastInsertId();
            if ($id === 0) {
                $stmt = $pdo->prepare('SELECT id FROM recipe_categories WHERE slug = :s');
                $stmt->execute([':s' => $slug]);
                $id = (int) $stmt->fetchColumn();
            }
            return $id;
        };

        /* ---- By Ingredient ---- */
        $add(null, 'Paneer Recipes',    'paneer-recipes',    'ingredient', 'Delicious paneer recipes from creamy curries to snacks and starters.');
        $add(null, 'Chicken Recipes',   'chicken-recipes',   'ingredient', 'Easy and flavorful chicken recipes — curries, grilled, fried and more.');
        $add(null, 'Biryani Recipes',   'biryani-recipes',   'ingredient', 'Aromatic biryani recipes from Hyderabadi to Lucknowi styles.');
        $add(null, 'Potato Recipes',    'potato-recipes',    'ingredient', 'Versatile potato dishes — aloo gobi, paratha, fries and more.');
        $add(null, 'Egg Recipes',       'egg-recipes',       'ingredient', 'Quick egg recipes from omelettes to egg curries.');
        $add(null, 'Mutton Recipes',    'mutton-recipes',    'ingredient', 'Rich mutton and lamb curries, kebabs and roasts.');
        $add(null, 'Rice Recipes',      'rice-recipes',      'ingredient', 'Rice dishes beyond biryani — pulao, fried rice, lemon rice and more.');
        $add(null, 'Seafood Recipes',   'seafood-recipes',   'ingredient', 'Fish, prawns and seafood dishes from coastal cuisines.');
        $add(null, 'Dal Recipes',       'dal-recipes',       'ingredient', 'Comforting dal and lentil recipes for everyday meals.');

        /* ---- Cuisines ---- */
        $northIndian = $add(null, 'North Indian',   'north-indian',   'cuisine', 'Rich, buttery curries, tandoor dishes and breads from northern India.');
        $add(null, 'South Indian',   'south-indian',   'cuisine', 'Dosas, idlis, sambar and coconut-based dishes from southern India.');
        $add(null, 'Punjabi',        'punjabi',        'cuisine', 'Hearty Punjabi cuisine — butter chicken, dal makhani and more.');
        $add(null, 'Bengali',        'bengali',        'cuisine', 'Sweet and subtle Bengali dishes — fish curries, mishti and more.');
        $add(null, 'Gujarati',       'gujarati',       'cuisine', 'Sweet-savory Gujarati thali favourites — dhokla, thepla, undhiyu.');
        $add(null, 'Rajasthani',     'rajasthani',     'cuisine', 'Royal Rajasthani cuisine — dal baati, gatte ki sabzi and more.');
        $add(null, 'Kashmiri',       'kashmiri',       'cuisine', 'Aromatic Kashmiri cuisine — rogan josh, dum aloo, kahwa.');
        $add(null, 'Maharashtrian',  'maharashtrian',  'cuisine', 'Marathi favourites — vada pav, misal pav, puran poli.');
        $add(null, 'Goan',           'goan',           'cuisine', 'Goan coastal flavours — vindaloo, fish curry, bebinca.');
        $add(null, 'Mughlai',        'mughlai',        'cuisine', 'Rich Mughlai cooking — kebabs, korma, shahi dishes.');
        $add(null, 'Chinese',        'chinese',        'cuisine', 'Indo-Chinese and authentic Chinese recipes.');
        $add(null, 'Italian',        'italian',        'cuisine', 'Italian classics — pasta, pizza, risotto and more.');
        $add(null, 'Thai',           'thai',           'cuisine', 'Thai curries, stir-fries, pad thai and more.');
        $add(null, 'Mexican',        'mexican',        'cuisine', 'Tacos, burritos, nachos and Mexican-inspired dishes.');
        $add(null, 'Mediterranean',  'mediterranean',  'cuisine', 'Fresh Mediterranean cooking — hummus, falafel, grilled dishes.');
        $add(null, 'Continental',    'continental',    'cuisine', 'Western-style continental cooking — roasts, pastas, bakes.');
        $add(null, 'American',       'american',       'cuisine', 'American comfort food — burgers, mac and cheese, BBQ.');
        $add(null, 'French',         'french',         'cuisine', 'French classics — quiche, ratatouille, crème brûlée.');
        $add(null, 'Asian',          'asian',          'cuisine', 'Flavours from across Asia — Japanese, Korean, Vietnamese.');
        $add(null, 'Fusion',         'fusion',         'cuisine', 'Creative cross-cuisine dishes that blend flavours.');
        $add(null, 'Jain',           'jain',           'cuisine', 'Jain-friendly recipes without onion, garlic and root vegetables.');
        $add(null, 'Satvik',         'satvik',         'cuisine', 'Pure satvik vegetarian cooking for fasting and everyday.');

        /* ---- Courses & Meals ---- */
        $add(null, 'Breakfast',      'breakfast',      'course', 'Morning meal recipes — parathas, idlis, smoothies and more.');
        $add(null, 'Lunch',          'lunch',          'course', 'Satisfying lunch recipes — rice bowls, thalis, salads.');
        $add(null, 'Dinner',         'dinner',         'course', 'Dinner ideas — curries, rotis, one-pot meals.');
        $add(null, 'Snacks',         'snacks',         'course', 'Tea-time snacks and quick bites — samosas, pakoras, chaat.');
        $add(null, 'Appetizers',     'appetizers',     'course', 'Starters and appetizers to kick off any meal.');
        $add(null, 'Soups',          'soups',          'course', 'Warm and comforting soup recipes for every season.');
        $add(null, 'Salads',         'salads',         'course', 'Fresh salad recipes — from simple raita to Greek salads.');
        $add(null, 'Sandwiches',     'sandwiches',     'course', 'Quick sandwich and wrap recipes for busy days.');
        $add(null, 'Finger Food',    'finger-food',    'course', 'Bite-sized finger foods perfect for parties.');
        $add(null, 'Dips & Chutneys','dips-chutneys',  'course', 'Dips, sauces and chutneys to pair with any dish.');
        $add(null, 'Kids Food',      'kids-food',      'course', 'Kid-friendly recipes they will actually eat.');
        $add(null, 'Party Snacks',   'party-snacks',   'course', 'Crowd-pleasing party snack recipes.');

        /* ---- Diet & Health ---- */
        $add(null, 'Vegan',          'vegan',          'diet', 'Plant-based recipes without any animal products.');
        $add(null, 'Keto',           'keto',           'diet', 'Low-carb, high-fat keto-friendly recipes.');
        $add(null, 'Low-Carb',       'low-carb',       'diet', 'Reduced carbohydrate recipes for lighter meals.');
        $add(null, 'Gluten Free',    'gluten-free',    'diet', 'Recipes free from wheat and gluten-containing grains.');
        $add(null, 'High Protein',   'high-protein',   'diet', 'Protein-rich recipes for fitness and muscle building.');
        $add(null, 'Low Calorie',    'low-calorie',    'diet', 'Light, lower-calorie meals without sacrificing taste.');
        $add(null, 'Diabetic',       'diabetic',       'diet', 'Blood sugar-friendly recipes for diabetic diets.');
        $add(null, 'Dairy Free',     'dairy-free',     'diet', 'Recipes without milk, cream, cheese or butter.');
        $add(null, 'High Fibre',     'high-fibre',     'diet', 'Fibre-rich recipes for better digestion and health.');

        /* ---- Cooking Style ---- */
        $add(null, 'Street Food',    'street-food-style', 'style', 'India\'s legendary street food — chaat, vada pav, rolls.');
        $add(null, 'Comfort Food',   'comfort-food',   'style', 'Warm, nostalgic dishes that feel like home.');
        $add(null, 'One-Pot Meals',  'one-pot-meals',  'style', 'Easy one-pot recipes for minimal cleanup.');
        $add(null, 'Tandoor',        'tandoor',        'style', 'Tandoor and clay-oven recipes — naan, tikka, kebabs.');
        $add(null, 'Microwave',      'microwave',      'style', 'Quick microwave recipes when you\'re short on time.');
        $add(null, 'Grilled',        'grilled',        'style', 'Grilled and BBQ recipes for smoky flavour.');
        $add(null, 'Baked',          'baked',          'style', 'Oven-baked dishes — casseroles, gratins, breads.');
        $add(null, 'Instant Pot',    'instant-pot',    'style', 'Pressure cooker and instant pot recipes.');

        /* ---- Desserts (with sub-categories) ---- */
        $dessertParent = $add(null, 'Desserts', 'desserts-all', 'dessert', 'Sweet treats — cakes, mithai, puddings and frozen desserts.');
        $add($dessertParent, 'Cakes',                 'cakes',                  'dessert', 'Cake recipes — chocolate, vanilla, eggless and more.');
        $add($dessertParent, 'Cookies',               'cookies',                'dessert', 'Crispy, chewy cookie recipes for every occasion.');
        $add($dessertParent, 'No-Bake Desserts',      'no-bake-desserts',       'dessert', 'Sweet treats that need no oven.');
        $add($dessertParent, 'Ice Cream & Kulfi',     'ice-cream-kulfi',        'dessert', 'Frozen desserts — ice cream, kulfi, sorbet.');
        $add($dessertParent, 'Indian Sweets',         'indian-sweets',          'dessert', 'Traditional mithai — gulab jamun, rasgulla, barfi.');
        $add($dessertParent, 'Halwa & Sheera',        'halwa-sheera',           'dessert', 'Classic halwa recipes — sooji, gajar, moong dal.');
        $add($dessertParent, 'Puddings',              'puddings',               'dessert', 'Creamy puddings and custards.');

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
