<?php
/**
 * FOODIIE — installer seed data.
 *
 * foodiie_seed(PDO $pdo, bool $demo, string $site_name): void
 *
 * Inserts the base data every site needs (settings, ad slots, categories,
 * starter pages) and, when $demo is true, sample content:
 * 10 articles, 5 recipes and 5 videos.
 *
 * Idempotent: every insert uses an ON CONFLICT upsert / DO NOTHING guard,
 * so re-running the seed never creates duplicates. All bodies are passed
 * through sanitize_html() before insert. All queries use prepared statements.
 */

require_once __DIR__ . '/functions/config.php';
require_once __DIR__ . '/functions/helpers.php';
require_once __DIR__ . '/functions/sanitize.php';

/** Seed base data (+ optional demo content) into a freshly installed DB. */
function foodiie_seed(PDO $pdo, bool $demo, string $site_name): void
{
    $pdo->beginTransaction();
    try {
        foodiie_seed_settings($pdo, $site_name);
        foodiie_seed_ads($pdo);
        foodiie_seed_categories($pdo);
        foodiie_seed_pages($pdo);
        if ($demo) {
            $author_id = foodiie_seed_author_id($pdo);
            foodiie_seed_articles($pdo, $author_id);
            foodiie_seed_articles_part2($pdo, $author_id);
            foodiie_seed_recipes($pdo);
            foodiie_seed_videos($pdo);
            foodiie_seed_images($pdo);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/** First user (the installer-created admin) — used as author for demo rows. */
function foodiie_seed_author_id(PDO $pdo): ?int
{
    $row = $pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetch();
    return $row ? (int) $row['id'] : null;
}

/** Insert or replace a setting. */
function foodiie_seed_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare('INSERT INTO settings(key, value) VALUES(:k, :v)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

/* ------------------------------------------------------------------ */
/* Settings                                                            */
/* ------------------------------------------------------------------ */

function foodiie_seed_settings(PDO $pdo, string $site_name): void
{
    foodiie_seed_setting($pdo, 'site_name', $site_name);
    foodiie_seed_setting($pdo, 'site_tagline', 'Discover. Cook. Eat Better.');
    foodiie_seed_setting($pdo, 'default_author', 'Foodiie Editorial Team');
    foodiie_seed_setting($pdo, 'cookie_consent_enabled', '0');
    foodiie_seed_setting($pdo, 'ga_enabled', '0');
}

/* ------------------------------------------------------------------ */
/* Ad slots                                                            */
/* ------------------------------------------------------------------ */

function foodiie_seed_ads(PDO $pdo): void
{
    $slots = ['header', 'homepage', 'article_top', 'article_middle', 'article_bottom', 'sidebar', 'footer'];
    $stmt = $pdo->prepare('INSERT INTO ads(slot, enabled, code, device) VALUES(:s, 0, \'\', \'both\')
        ON CONFLICT(slot) DO NOTHING');
    foreach ($slots as $slot) {
        $stmt->execute([':s' => $slot]);
    }
}

/* ------------------------------------------------------------------ */
/* Categories                                                          */
/* ------------------------------------------------------------------ */

function foodiie_seed_categories(PDO $pdo): void
{
    $categories = [
        ['Recipes', 'recipes', 'Step-by-step recipes for breakfast, lunch, dinner, snacks and more — tested in our kitchen so they work in yours.'],
        ['Breakfast', 'breakfast', 'Quick and wholesome breakfast ideas to start your day right, from 5-minute bowls to slow weekend brunches.'],
        ['Lunch', 'lunch', 'Midday meals that are filling and practical — office lunches, tiffins, and family tables sorted.'],
        ['Dinner', 'dinner', 'Evening meals for busy weeknights and relaxed weekends, from one-pot wonders to full thalis.'],
        ['Snacks', 'snacks', 'Evening cravings, party bites and 4 pm hunger — snacks that hit the spot without the fuss.'],
        ['Desserts', 'desserts', 'Mithai, cakes, puddings and frozen treats — because every good meal deserves a sweet ending.'],
        ['Healthy Food', 'healthy-food', 'Nutrition-backed ideas for eating better every day: lighter versions, smart swaps and balanced plates.'],
        ['Food Facts', 'food-facts', 'Surprising stories and science behind what we eat — ingredients, origins and everyday food trivia.'],
        ['Food Tips', 'food-tips', 'Kitchen hacks, cooking techniques and storage tricks that make you faster and better in the kitchen.'],
        ['Food Trends', 'food-trends', 'What is hot in the food world — viral dishes, new cuisines and the trends actually worth your attention.'],
        ['Street Food', 'street-food', 'Chaat, vada pav, rolls and more — celebrating India’s legendary street food culture, city by city.'],
        ['Drinks', 'drinks', 'Chai, coffee, coolers, smoothies and festive drinks — sips for every season and every mood.'],
        ['Videos', 'videos', 'Watch and cook along — recipe videos, kitchen skills and food stories in motion.'],
    ];
    $stmt = $pdo->prepare('INSERT INTO categories(name, slug, description, sort)
        VALUES(:name, :slug, :description, :sort)
        ON CONFLICT(slug) DO NOTHING');
    $sort = 0;
    foreach ($categories as [$name, $slug, $description]) {
        $sort++;
        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':description' => $description,
            ':sort' => $sort,
        ]);
    }
}

/** Category id by slug, or null. */
function foodiie_category_id(PDO $pdo, string $slug): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE slug = :s LIMIT 1');
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch();
    return $row ? (int) $row['id'] : null;
}

/* ------------------------------------------------------------------ */
/* Pages                                                               */
/* ------------------------------------------------------------------ */

function foodiie_seed_pages(PDO $pdo): void
{
    $now = gmdate('Y-m-d H:i:s');
    $legal_comment = '<!-- TEMPLATE: requires legal review before production use -->';
    $legal_note = '<p><strong>Please note:</strong> this page is a starter template. It is not legal advice and has not been reviewed for your jurisdiction. Review and customize it with a qualified professional before using it in production.</p>';

    $pages = [
        [
            'slug' => 'about',
            'title' => 'About Us',
            'body' => <<<'HTML'
<p><strong>Foodiie</strong> is a food-first publication for people who love to cook, eat and learn about food. We publish practical recipes, honest kitchen tips, food science explained simply, and stories from India's vibrant food culture.</p>
<p>Our recipes are written to work in a real home kitchen: ingredients you can actually find, steps in plain language, and timings that respect a busy schedule. Every recipe is tested before it is published, and when something can go wrong, we tell you how to avoid it.</p>
<p>Beyond recipes, we cover the why behind the what — why onions make you cry, why bread rises, which food trends are worth trying and which are just noise. No fluff, no filler: just useful food content.</p>
<p>Foodiie is run by a small editorial team of home cooks and food writers. If you have a recipe, a tip or a story to share, we would love to hear from you on our contact page.</p>
HTML,
            'seo_title' => 'About Us',
            'seo_description' => 'Learn what Foodiie is about: practical tested recipes, kitchen tips and food stories from India and beyond.',
        ],
        [
            'slug' => 'contact',
            'title' => 'Contact Us',
            'body' => <<<'HTML'
<p>Have a question, a recipe correction, a story tip or a business enquiry? We read every message and reply as soon as we can — usually within two working days.</p>
<p>The fastest way to reach us is through the contact form on this page. For press, partnerships and advertising enquiries, please mention the nature of your enquiry in the subject line so it reaches the right person.</p>
<p>You can also write to us directly at <strong>hello@foodiie.in</strong>. Please replace this address with your own before going live.</p>
<p>We love hearing from readers: tell us what you cooked, what worked, and what you would like us to cover next.</p>
HTML,
            'seo_title' => 'Contact Us',
            'seo_description' => 'Get in touch with the Foodiie team — questions, feedback, press and partnership enquiries.',
        ],
        [
            'slug' => 'privacy-policy',
            'title' => 'Privacy Policy',
            'template' => true,
            'body' => <<<'HTML'
<p>This Privacy Policy explains what information we collect when you use this website, how we use it, and the choices you have. We collect the minimum data needed to run the site: pages you visit (in anonymized analytics, if enabled), information you voluntarily share through forms (such as your name and email when you contact us), and technical data like your browser type.</p>
<p>We use this information to operate and improve the website, respond to your messages, and — if you opt in — send updates. We do not sell your personal information. Third-party services we use (such as analytics or advertising providers, when enabled) may set their own cookies; their use of data is governed by their own policies.</p>
<p>Cookies help the site remember your preferences and measure traffic. You can block or delete cookies in your browser settings, though some features may not work as intended. If we show a cookie consent banner, your choice is respected and stored.</p>
<p>If you would like a copy of the data we hold about you, or want it corrected or deleted, contact us and we will respond within a reasonable time. This policy may be updated occasionally; the latest version will always be posted on this page.</p>
HTML,
            'seo_title' => 'Privacy Policy',
            'seo_description' => 'How Foodiie collects, uses and protects your information.',
        ],
        [
            'slug' => 'terms',
            'title' => 'Terms of Use',
            'template' => true,
            'body' => <<<'HTML'
<p>By using this website you agree to these terms. The content here — recipes, articles and videos — is provided for general information and personal, non-commercial use. You are welcome to cook from our recipes and share links to our pages; please do not republish our content in full elsewhere without permission.</p>
<p>All content is provided "as is" without warranties of any kind. Cooking involves heat, sharp tools and allergens: follow food-safety basics, adjust recipes to your needs, and use your judgment. We are not responsible for the outcome of any recipe you try.</p>
<p>We may update or remove content at any time, and we may change these terms; continued use of the site after changes means you accept the updated terms. If you do not agree with any part of these terms, please do not use the site.</p>
HTML,
            'seo_title' => 'Terms of Use',
            'seo_description' => 'The terms governing your use of the Foodiie website.',
        ],
        [
            'slug' => 'disclaimer',
            'title' => 'Disclaimer',
            'template' => true,
            'body' => <<<'HTML'
<p>The content on this website is for general information and entertainment purposes only. It is not professional advice — medical, nutritional or otherwise. If you have a health condition, food allergy or dietary restriction, consult a qualified professional before changing your diet.</p>
<p>Recipe results can vary with ingredients, equipment and technique. Nutritional information, where shown, is an estimate and should not be treated as exact. Always follow basic food-safety practices: cook foods to safe temperatures, store perishables properly, and when in doubt, throw it out.</p>
<p>Some pages may contain affiliate links, which means we may earn a commission if you buy through them, at no extra cost to you. External links lead to sites we do not control, and we are not responsible for their content.</p>
HTML,
            'seo_title' => 'Disclaimer',
            'seo_description' => 'Important disclaimers about Foodiie content, recipes and health information.',
        ],
        [
            'slug' => 'cookie-policy',
            'title' => 'Cookie Policy',
            'template' => true,
            'body' => <<<'HTML'
<p>Cookies are small text files stored on your device that help websites remember you and understand how they are used. This site uses a small number of cookies for essential functions (such as remembering your cookie-consent choice) and, when enabled, analytics and advertising cookies from third-party providers.</p>
<p>Essential cookies are required for the site to work and cannot be switched off. Analytics cookies help us understand which content readers enjoy so we can make more of it. Advertising cookies, when ads are enabled, may be used by ad providers to show relevant ads and measure their performance.</p>
<p>You can control cookies through your browser settings: block them entirely, delete existing ones, or get notified before a cookie is set. Note that blocking essential cookies may break parts of the site. Where we show a consent banner, you can change your choice at any time.</p>
HTML,
            'seo_title' => 'Cookie Policy',
            'seo_description' => 'How Foodiie uses cookies and how you can control them.',
        ],
        [
            'slug' => 'affiliate-disclosure',
            'title' => 'Affiliate Disclosure',
            'template' => true,
            'body' => <<<'HTML'
<p>Transparency matters to us, so here it is plainly: some of the links on this website are affiliate links. That means if you click one and make a purchase, we may earn a small commission — at no extra cost to you.</p>
<p>These commissions help keep the site running and our content free. They never influence what we recommend: we only link to products and ingredients we genuinely think are useful, and we will always tell you when a link is affiliated where it is practical to do so.</p>
<p>If you prefer not to use affiliate links, you can always search for the product name yourself. Thank you for supporting independent food publishing.</p>
HTML,
            'seo_title' => 'Affiliate Disclosure',
            'seo_description' => 'How Foodiie discloses affiliate links and commissions.',
        ],
        [
            'slug' => 'advertising',
            'title' => 'Advertise With Us',
            'body' => <<<'HTML'
<p>Foodiie reaches home cooks, food lovers and kitchen experimenters across India. If your brand sells ingredients, kitchenware, appliances or food experiences, our readers are your customers.</p>
<p>We offer display advertising across the site — header, in-article and sidebar placements — as well as sponsored content opportunities that are always clearly labelled. We only work with brands we are comfortable recommending to our own families.</p>
<p>For our media kit, audience numbers and rate card, write to <strong>ads@foodiie.in</strong> with a short note about your brand and campaign goals. Please replace this address with your own before going live.</p>
HTML,
            'seo_title' => 'Advertise With Us',
            'seo_description' => 'Advertising and partnership opportunities with Foodiie.',
        ],
    ];

    $stmt = $pdo->prepare('INSERT INTO pages(slug, title, body, seo_title, seo_description, updated_at)
        VALUES(:slug, :title, :body, :seo_title, :seo_description, :updated_at)
        ON CONFLICT(slug) DO NOTHING');

    foreach ($pages as $page) {
        $body = sanitize_html($page['body']);
        // Legal pages must BEGIN with the review template comment (sanitize_html
        // strips comments, so it is prepended after sanitizing).
        if (!empty($page['template'])) {
            $body = $legal_comment . "\n" . $legal_note . "\n" . $body;
        }
        $stmt->execute([
            ':slug' => $page['slug'],
            ':title' => $page['title'],
            ':body' => $body,
            ':seo_title' => $page['seo_title'],
            ':seo_description' => $page['seo_description'],
            ':updated_at' => $now,
        ]);
    }
}

/* ------------------------------------------------------------------ */
/* Demo articles                                                       */
/* ------------------------------------------------------------------ */

function foodiie_seed_articles(PDO $pdo, ?int $author_id): void
{
    $articles = [
        [
            'title' => '10 Easy Breakfast Ideas for Busy Mornings',
            'description' => 'No time in the morning? These 10 quick, wholesome breakfast ideas take 15 minutes or less — and actually keep you full till lunch.',
            'category' => 'breakfast',
            'tags' => 'breakfast, quick meals, morning routine, healthy',
            'days_ago' => 30,
            'body' => <<<'HTML'
<p>The hardest meal of the day is not dinner — it is breakfast on a weekday. You have twenty minutes, half-awake eyes, and a stomach that will complain loudly by 11 am if you skip it. The trick is not waking up earlier. It is having a short list of breakfasts you can make without thinking. Here are ten that actually work.</p>
<h2>10 breakfasts that take 15 minutes or less</h2>
<ol>
<li><strong>Overnight oats.</strong> Mix oats, milk or curd, chia seeds and chopped fruit in a jar before bed. Breakfast makes itself while you sleep.</li>
<li><strong>Poha.</strong> Rinse flattened rice, temper with mustard seeds, curry leaves, onion and peanuts. Light, filling and ready in 10 minutes.</li>
<li><strong>Vegetable upma.</strong> Roast rava, cook with water, and throw in whatever vegetables are in the fridge. One pan, endless variations.</li>
<li><strong>Banana peanut-butter toast.</strong> Whole-grain toast, a thick smear of peanut butter, banana slices and a pinch of cinnamon. Protein, fibre and zero cooking.</li>
<li><strong>Moong dal chilla.</strong> Blend soaked moong dal with ginger and green chilli, spread thin on a tawa like a dosa. Crisp, high-protein and surprisingly quick.</li>
<li><strong>Curd rice bowl.</strong> Leftover rice + curd + salt + a quick tadka of mustard seeds and curry leaves. Add pomegranate or cucumber for crunch.</li>
<li><strong>Egg bhurji roll.</strong> Scramble eggs with onion, tomato and turmeric, roll into a chapati with chutney. A complete meal you can eat one-handed.</li>
<li><strong>Fruit and nut smoothie.</strong> Banana, milk or curd, a spoon of peanut butter and a few dates. Blend for 60 seconds and drink on the go.</li>
<li><strong>Idli with chutney.</strong> If you keep batter in the fridge, steaming idlis takes 10 minutes flat. Make chutney in bulk on Sunday.</li>
<li><strong>Sprouts chaat.</strong> Boiled moong sprouts tossed with onion, tomato, lemon and chaat masala. Crunchy, fresh and genuinely filling.</li>
</ol>
<h2>Make mornings easier</h2>
<p>A good breakfast habit is built the night before, not at 8 am. A few small systems help enormously:</p>
<ul>
<li><strong>Prep one thing nightly.</strong> Chop vegetables, soak dal or set out the pan. Future-you will be grateful.</li>
<li><strong>Keep a "breakfast shelf".</strong> Oats, poha, rava, peanut butter and dry fruits in one place means no hunting.</li>
<li><strong>Repeat winners.</strong> You do not need 30 breakfasts. Five you love, on rotation, beats novelty every time.</li>
<li><strong>Include protein.</strong> Eggs, paneer, curd, nuts or dal — protein is what keeps you full till lunch.</li>
</ul>
<div class="callout"><strong>The 10-minute rule:</strong> if a breakfast takes longer than 10 minutes of active work on a weekday, save it for the weekend. Weekday breakfasts should be assembly, not projects.</div>
<p>Start with any three from this list and rotate them for two weeks. Once they are automatic, add more. Busy mornings do not have to mean skipped breakfasts — they just need a plan.</p>
HTML,
        ],
        [
            'title' => '7 Interesting Facts About Coffee',
            'description' => 'Coffee is a fruit, decaf is not caffeine-free, and Finland drinks more than anyone. Seven genuinely surprising coffee facts.',
            'category' => 'food-facts',
            'tags' => 'coffee, food facts, beverages, trivia',
            'days_ago' => 27,
            'body' => <<<'HTML'
<p>Coffee is the world's most popular pick-me-up, yet most of us drink it on autopilot. Behind that morning cup is a strange and fascinating history. Here are seven facts that might change how you see your next brew.</p>
<h2>1. Coffee is a fruit</h2>
<p>Those "coffee beans" are not beans at all — they are the seeds of a cherry-like fruit that grows on the coffee plant. In some places the sweet fruit pulp is dried and brewed as <em>cascara</em>, a tea-like drink. You have been drinking fruit seeds your whole life.</p>
<h2>2. A shot of espresso has less caffeine than a mug of filter coffee</h2>
<p>Espresso tastes stronger, so people assume it packs more caffeine. Per ounce it does — but a standard serving is tiny. A full mug of filter coffee (around 240 ml) typically contains more total caffeine than a single espresso shot. Size matters more than intensity.</p>
<h2>3. Decaf is not caffeine-free</h2>
<p>Decaffeination removes most — not all — of the caffeine. A cup of decaf still contains a small amount, roughly 2–5 mg compared to about 95 mg in regular coffee. If you are genuinely sensitive to caffeine, decaf is "less", not "none".</p>
<h2>4. The "coffee nap" is real</h2>
<p>Drinking coffee and then napping for 15–20 minutes can leave you more alert than either alone. Caffeine takes about 20 minutes to kick in, so you wake up just as it starts working — while the nap clears the sleepiness caffeine cannot fix. It sounds backwards, but the science holds up.</p>
<h2>5. Finland drinks the most coffee on Earth</h2>
<p>The average Finn drinks around four cups a day — more per person than anywhere else in the world. Coffee breaks are even written into many Finnish labour agreements. Italy may have the romance, but Finland has the volume.</p>
<h2>6. Coffee was first eaten, not drunk</h2>
<p>Long before brewing, people in coffee-growing regions mixed coffee cherries with animal fat into energy balls — an early energy bar. The drink we know came centuries later, and it took even longer to reach Europe.</p>
<h2>7. Light roast has slightly more caffeine than dark roast</h2>
<p>Roasting burns off a tiny bit of caffeine, so lighter roasts retain marginally more — at least when measured by scoop. (By weight the difference nearly vanishes, because dark-roast beans are lighter.) Either way, roast level changes flavour far more than kick.</p>
<blockquote><p>"Coffee is a language in itself." — and like any language, it rewards the curious. The more you know about the cup, the better it tastes.</p></blockquote>
<div class="callout"><strong>Try this:</strong> next time you brew, taste your coffee black before adding milk or sugar. You will notice flavours — chocolate, fruit, nuts — that were hiding behind the additives all along.</div>
<p>Which fact surprised you most? Coffee rewards curiosity, so keep experimenting with brews, roasts and methods. Your perfect cup is out there.</p>
HTML,
        ],
        [
            'title' => 'Healthy Evening Snacks',
            'description' => 'Beat the 5 pm hunger with snacks that are actually satisfying: 8 healthy evening snack ideas plus the simple formula behind them.',
            'category' => 'snacks',
            'tags' => 'snacks, healthy, evening, weight management',
            'days_ago' => 24,
            'body' => <<<'HTML'
<p>Four or five in the evening is when good intentions go to die. Lunch is a distant memory, dinner is hours away, and the packet of chips in the cupboard starts whispering your name. The problem is not snacking — it is snacking on things that leave you hungrier than before.</p>
<h2>What makes a snack actually healthy?</h2>
<p>Forget "low calorie" as the goal. A good evening snack has a simple formula: <strong>protein or fibre + something you enjoy eating</strong>. Protein and fibre slow digestion, steady your blood sugar and keep you full till dinner. A 200-calorie snack with protein beats a 100-calorie snack of pure starch every time.</p>
<h2>8 snacks that satisfy</h2>
<ol>
<li><strong>Roasted makhana.</strong> Fox nuts roasted in a teaspoon of ghee with salt and pepper. Crunchy like chips, far lighter, and the roasted flavour is genuinely addictive.</li>
<li><strong>Sprouts chaat.</strong> Boiled moong sprouts with chopped onion, tomato, cucumber, lemon and chaat masala. Fresh, tangy and packed with protein.</li>
<li><strong>Vegetable sticks with hummus.</strong> Carrot, cucumber and capsicum sticks with a small bowl of hummus. The crunch satisfies the chips craving.</li>
<li><strong>Roasted chana.</strong> Keep a jar of roasted chickpeas spiced with chaat masala. A handful delivers protein and fibre for very few calories.</li>
<li><strong>Fruit with peanut butter.</strong> Apple or banana slices with a tablespoon of peanut butter. Sweet, creamy and far more filling than fruit alone.</li>
<li><strong>Paneer tikka bites.</strong> Cube paneer, toss with tikka masala and curd, and pan-sear. High protein, deeply satisfying, ready in 10 minutes.</li>
<li><strong>A small bowl of soup.</strong> Tomato, dal or vegetable soup takes the edge off hunger fast, especially on cold evenings. Warm food registers as more filling.</li>
<li><strong>Curd with roasted nuts.</strong> A small katori of curd topped with almonds and a drizzle of honey. Creamy, cooling and protein-rich.</li>
</ol>
<h2>Set yourself up to succeed</h2>
<ul>
<li><strong>Prep on Sunday.</strong> Boil sprouts, roast a batch of makhana and chana, chop vegetables. Evening-you will not prep; morning-you must.</li>
<li><strong>Portion first.</strong> Serve your snack in a bowl instead of eating from the packet. It is a small change with a big effect.</li>
<li><strong>Drink water first.</strong> Thirst often disguises itself as hunger. A glass of water, wait ten minutes, then decide.</li>
</ul>
<div class="callout"><strong>The 4 pm rule:</strong> plan your evening snack the way you plan lunch. A snack you chose deliberately is a healthy habit; a snack you grabbed in desperation is a gamble.</div>
<p>Healthy snacking is not about willpower — it is about availability. Keep two or three of these ready, and the chips can stay whispering.</p>
HTML,
        ],
        [
            'title' => 'Common Cooking Mistakes (and How to Fix Them)',
            'description' => 'Overcrowded pans, burnt garlic, bland dal — the 7 mistakes almost every home cook makes, and the simple fixes for each.',
            'category' => 'food-tips',
            'tags' => 'cooking tips, kitchen basics, mistakes, techniques',
            'days_ago' => 21,
            'body' => <<<'HTML'
<p>Every cook — professional or home — learned by making the same handful of mistakes. The difference between a frustrating dinner and a great one is rarely talent. It is usually one of these seven errors, and every one of them has a simple fix.</p>
<h2>1. Overcrowding the pan</h2>
<p><strong>The mistake:</strong> piling vegetables or paneer into a crowded pan, where they steam in their own moisture instead of browning. You get soggy, grey food instead of golden, crisp food.</p>
<p><strong>The fix:</strong> cook in batches, or use a bigger pan. Food needs space for moisture to escape. If you hear sizzling, you are frying; if you hear nothing, you are steaming.</p>
<h2>2. Not heating the pan (and oil) properly</h2>
<p><strong>The mistake:</strong> adding food to a cold or lukewarm pan, where it sticks, absorbs oil and cooks unevenly.</p>
<p><strong>The fix:</strong> heat the pan first, then add oil, then wait until the oil shimmers. A drop of water should sizzle and dance — that is your green light.</p>
<h2>3. Under-seasoning — or seasoning only at the end</h2>
<p><strong>The mistake:</strong> food that tastes flat despite good ingredients. Most home cooks use far less salt than needed, and add it all at the end.</p>
<p><strong>The fix:</strong> season in layers — a pinch when the onions go in, a pinch with the vegetables, then adjust at the end. Salt added early penetrates; salt added late just sits on top.</p>
<h2>4. Burning garlic and spices</h2>
<p><strong>The mistake:</strong> garlic, ginger and ground spices go from fragrant to bitter in under a minute. Burnt garlic ruins the whole dish, and you cannot fix it — only start over.</p>
<p><strong>The fix:</strong> keep the heat medium, keep things moving, and add garlic after onions have softened, not before. For tadka, have everything measured and beside the stove before the oil heats.</p>
<h2>5. Not letting the tadka bloom</h2>
<p><strong>The mistake:</strong> rushing the tempering — mustard seeds barely splutter, curry leaves go in cold oil, and the dish misses that deep, restaurant-like aroma.</p>
<p><strong>The fix:</strong> wait for the mustard seeds to actually pop, let curry leaves crisp for a few seconds, and give hing two seconds in hot oil. Those 30 extra seconds are where the flavour lives.</p>
<h2>6. Cooking on the wrong heat</h2>
<p><strong>The mistake:</strong> everything on high because you are in a hurry (burnt outside, raw inside), or everything on low (pale, lifeless food).</p>
<p><strong>The fix:</strong> high heat for searing and stir-frying, medium for most curries, low for simmering and slow-cooking. Match the heat to the job, and adjust as you go.</p>
<h2>7. Not tasting as you cook</h2>
<p><strong>The mistake:</strong> serving a dish you have not tasted since the raw-ingredient stage, then discovering at the table that it needs salt, acid or heat.</p>
<p><strong>The fix:</strong> taste at least three times — midway, near the end, and just before serving. Keep lemon, salt and chilli within reach for final adjustments. Tasting is not cheating; it is cooking.</p>
<div class="callout"><strong>Remember:</strong> mistakes are tuition. Every burnt tadka and oversalted dal teaches you something a recipe never could. The only real mistake is making the same one twice without noticing.</div>
<p>Fix one of these this week and you will taste the difference immediately. Cooking well is not about fancy techniques — it is about avoiding the small errors that quietly ruin good ingredients.</p>
HTML,
        ],
        [
            'title' => 'Indian Street Foods You Should Try',
            'description' => 'From Mumbai\'s vada pav to Kolkata\'s kathi rolls — 8 iconic Indian street foods and what makes each one special.',
            'category' => 'street-food',
            'tags' => 'street food, indian food, chaat, travel food',
            'days_ago' => 18,
            'body' => <<<'HTML'
<p>Some of India's best cooking never happens in restaurants. It happens on carts, at roadside stalls and in bustling bazaars, served on paper plates with plastic spoons — and it is unforgettable. If you want to understand Indian food, start on the street. Here are eight classics worth seeking out.</p>
<h2>1. Pani puri (golgappe / puchka)</h2>
<p>Crisp hollow puris cracked open, filled with spiced potato and dunked in tangy, minty pani. It is eaten whole, in one bite, standing at the stall — and the vendor's rhythm of filling and serving is part of the theatre. Every region has its version, and every region insists its own is best.</p>
<h2>2. Vada pav (Mumbai)</h2>
<p>Mumbai's answer to the burger: a spiced potato fritter tucked into a soft pav with garlic chutney and a fried green chilli on the side. Cheap, filling and deeply satisfying, it fuels the city's famously fast life. Eat it the local way — with one hand, while walking.</p>
<h2>3. Aloo tikki chaat (Delhi)</h2>
<p>Griddled potato patties layered with chole, curd, tamarind and mint chutneys, onions and sev. Sweet, sour, spicy, crunchy and creamy in a single plate — chaat is less a dish and more a masterclass in balancing flavours.</p>
<h2>4. Kathi rolls (Kolkata)</h2>
<p>Flaky parathas wrapped around kebabs, eggs or paneer with onions and a squeeze of lime. Born in Kolkata, perfected everywhere, the kathi roll is India's most successful food export within its own borders.</p>
<h2>5. Dabeli (Gujarat / Mumbai)</h2>
<p>A sweet-spicy potato filling in a bun, topped with pomegranate, peanuts, onions and sev. It hits every taste bud at once — and the pomegranate jewels on top make it as pretty as it is delicious.</p>
<h2>6. Momos (Delhi and the Northeast)</h2>
<p>Steamed dumplings with fiery red chutney, now found on nearly every Indian street corner. The best ones have thin, delicate wrappers and juicy fillings — and the chutney should make your eyes water just a little.</p>
<h2>7. Misal pav (Maharashtra)</h2>
<p>A fiery sprouted-bean curry topped with farsan, onions and lemon, mopped up with pav. This is breakfast with attitude — especially the Kolhapuri version, which does not believe in moderation.</p>
<h2>8. Jhal muri (Kolkata)</h2>
<p>Puffed rice tossed with mustard oil, roasted peanuts, chopped vegetables and spices, served in a paper cone. Light, crunchy and endlessly munchable — proof that the simplest street foods are often the best.</p>
<div class="callout"><strong>Street-smart tip:</strong> eat where the crowd is. A busy stall means high turnover, which means fresher food. Watch for clean water handling, and when in doubt, choose cooked-over-raw.</div>
<p>Street food is not just eating — it is sightseeing with your taste buds. Pick a city, find its busiest corner, and eat what the locals are eating. You will not regret it.</p>
HTML,
        ],
    ];

    foodiie_insert_articles($pdo, $articles, $author_id);
}

/* ------------------------------------------------------------------ */
/* (articles 6-10 continue below)                                      */
/* ------------------------------------------------------------------ */

function foodiie_seed_articles_part2(PDO $pdo, ?int $author_id): void
{
    $articles = [
        [
            'title' => 'Simple Ways to Eat More Vegetables',
            'description' => 'You know you should eat more vegetables. Here are 8 practical, no-preaching ways to actually do it — starting today.',
            'category' => 'healthy-food',
            'tags' => 'vegetables, healthy eating, nutrition, habits',
            'days_ago' => 15,
            'body' => <<<'HTML'
<p>Almost everyone agrees vegetables are good for them, and almost everyone eats fewer than they should. The gap is not knowledge — it is friction. Vegetables lose to convenience, habit and the packet of biscuits. These eight strategies remove the friction instead of relying on willpower.</p>
<h2>1. Start meals with vegetables</h2>
<p>Have a small salad, a bowl of soup or sliced cucumber before the main course. You will eat more vegetables simply because you meet them when you are hungriest — and you will naturally eat a little less of everything else.</p>
<h2>2. Double the vegetables in dishes you already cook</h2>
<p>Making dal? Add spinach or bottle gourd. Cooking pasta? Double the peppers and halve nothing. Khichdi with extra vegetables is still khichdi. This works because you change no habits — just ratios.</p>
<h2>3. Keep cut vegetables at eye level</h2>
<p>Wash and chop carrots, cucumbers and capsicum on Sunday and store them in a clear box at the front of the fridge. Vegetables you can see get eaten; vegetables hiding in the crisper drawer get discovered two weeks later.</p>
<h2>4. Make vegetables the easy choice when hungry</h2>
<p>Hunger makes you grab whatever is closest. If roasted makhana, fruit or cut veggies are within arm's reach and chips require a trip to the shop, vegetables win by default. Design your kitchen like a trap — for good habits.</p>
<h2>5. Try one new vegetable a month</h2>
<p>Boredom kills vegetable habits. Each month, buy one vegetable you have never cooked — drumsticks, raw banana, kohlrabi, red cabbage. Novelty keeps things interesting, and you might discover a new favourite.</p>
<h2>6. Roast them</h2>
<p>Almost every vegetable tastes better roasted: toss with a little oil, salt and spices, and roast at high heat until the edges caramelize. Roasting concentrates flavour and adds crunch. This single technique has converted more vegetable-skeptics than any other.</p>
<h2>7. Hide them in plain sight</h2>
<p>Blend spinach into smoothies, grate zucchini or carrot into sauces, mix cauliflower into mashed potatoes. Nobody is too old for hidden vegetables — they are a legitimate strategy, not a trick.</p>
<h2>8. Eat the rainbow (literally)</h2>
<p>Different colours signal different nutrients: green for folate, orange for beta-carotene, purple for anthocyanins, red for lycopene. Aiming for three colours per meal is an easy visual rule that guarantees variety without counting anything.</p>
<div class="callout"><strong>The real secret:</strong> do not aim for perfection. Adding one extra serving of vegetables a day — consistently, for months — beats a week of salads followed by giving up. Small, permanent changes win.</div>
<p>Pick two strategies from this list and try them this week. Eating more vegetables is not a diet or a challenge — it is just a series of small, easy decisions made on autopilot.</p>
HTML,
        ],
        [
            'title' => 'Why Homemade Food Can Be Useful',
            'description' => 'Cheaper, fresher and exactly how you like it — the real, practical benefits of cooking at home more often.',
            'category' => 'healthy-food',
            'tags' => 'home cooking, healthy eating, budget, lifestyle',
            'days_ago' => 12,
            'body' => <<<'HTML'
<p>Eating out is fun, and nobody is suggesting you stop. But the humble home-cooked meal has quiet superpowers that restaurants and takeaways simply cannot match. Here is why cooking at home more often is one of the highest-return habits you can build.</p>
<h2>You control what goes in</h2>
<p>Restaurant food is engineered to taste amazing, which usually means more oil, salt and sugar than you would ever use yourself. At home, you decide: a teaspoon of oil instead of three, salt to your taste, whole grains instead of refined. Small differences, multiplied over hundreds of meals, become enormous.</p>
<h2>It is genuinely cheaper</h2>
<p>A home-cooked dal-chawal-sabzi meal costs a fraction of its restaurant equivalent — often one-fourth to one-fifth the price. Cook at home five extra times a week and the savings over a year can fund a holiday. Your wallet notices before your waistline does.</p>
<h2>Fresher food, fewer unknowns</h2>
<p>Home cooking means fresh ingredients, no preservatives, no reheated mystery oils, and full knowledge of what you are eating. For anyone managing blood sugar, blood pressure, allergies or weight, that transparency is not a luxury — it is essential.</p>
<h2>Portions that make sense</h2>
<p>Restaurant portions keep growing because value sells. At home, you serve yourself a reasonable plate — and seconds are a conscious choice, not a default. Portion control without any dieting, simply because the serving bowl is not bottomless.</p>
<h2>Cooking is a life skill that compounds</h2>
<p>The first month of cooking at home is slow and slightly chaotic. By month three, you have ten reliable dishes, faster knife skills and a stocked pantry. By year one, you cook better than most takeaways — and you enjoy it. Skills compound like interest.</p>
<h2>It brings people together</h2>
<p>Some of the best conversations happen while chopping vegetables or waiting for the pressure cooker. Cooking with family or friends turns a chore into quality time, and sharing a meal you made hits differently than sharing a delivery order.</p>
<div class="callout"><strong>Balance, not purity:</strong> the goal is not "never eat out". It is making home the default and restaurants the treat. An 80/20 split — mostly home-cooked, with joyful eating out — beats both extremes.</div>
<p>Start small: cook one extra meal at home this week. Then another. Homemade food is not about being perfect — it is about stacking hundreds of small, better choices until they become simply how you eat.</p>
HTML,
        ],
        [
            'title' => 'Popular Food Trends Right Now',
            'description' => 'High-protein everything, millet comebacks, matcha mania — the food trends dominating right now, and which ones are actually worth trying.',
            'category' => 'food-trends',
            'tags' => 'food trends, 2026, healthy eating, viral food',
            'days_ago' => 9,
            'body' => <<<'HTML'
<p>Food trends move fast: what is viral today is forgotten by next season. But beneath the noise, a few real shifts are changing how we eat. Here is an honest look at the trends dominating right now — what they are, why they caught on, and whether they deserve a place in your kitchen.</p>
<h2>1. High-protein everything</h2>
<p>Protein has become the nutrient of the moment, and brands are adding it to everything from water to desserts. The sensible core — eat enough protein, especially as you age — is solid advice. The gimmicky end (protein cola, anyone?) is marketing. <strong>Verdict:</strong> keep the habit, skip the hype products.</p>
<h2>2. The millet comeback</h2>
<p>Millets — ragi, jowar, bajra, foxtail — are ancient grains getting a modern revival, and this one has real substance. They are nutritious, need less water to grow than rice or wheat, and fit naturally into Indian cooking. <strong>Verdict:</strong> genuinely worth trying; start by swapping one grain meal a week.</p>
<h2>3. Fermented foods go mainstream</h2>
<p>Kimchi, kombucha, kefir and good old Indian pickles, curd and kanji are riding the gut-health wave. Fermentation adds flavour complexity and beneficial microbes alike. <strong>Verdict:</strong> delicious and traditional — India was fermenting long before it was trendy.</p>
<h2>4. Matcha mania</h2>
<p>The vibrant green tea powder is everywhere: lattes, desserts, even savoury dishes. It offers steady energy without coffee's jitters, plus a distinctive grassy flavour people either love or hate. <strong>Verdict:</strong> try it once; if the taste clicks, it is a fine ritual.</p>
<h2>5. Snack plates as meals</h2>
<p>The "girl dinner" phenomenon — a curated plate of cheese, fruit, crackers, dips and pickles instead of a cooked meal — resonated because it is honest about how people actually eat. Done well, it is balanced and fun; done badly, it is just snacks. <strong>Verdict:</strong> great concept, add protein and vegetables.</p>
<h2>6. Smarter snacking</h2>
<p>Roasted makhana, millet chips and baked snacks are replacing fried namkeen on store shelves. Not all are as healthy as claimed — check the label — but the direction is right. <strong>Verdict:</strong> an upgrade over chips, but whole foods still win.</p>
<blockquote><p>Trends are suggestions, not commandments. The test is simple: does it make your everyday eating better, or just more photogenic?</p></blockquote>
<div class="callout"><strong>How to judge any food trend:</strong> ask three questions — Is it nutritious? Is it sustainable for daily life? Would you still eat it if nobody posted about it? Two yeses means it is worth a try.</div>
<p>Trends come and go, but the fundamentals never change: eat mostly whole foods, cook often, enjoy your meals. Adopt the trends that serve those goals, and let the rest pass by.</p>
HTML,
        ],
        [
            'title' => 'Easy Lunch Ideas for Workdays',
            'description' => 'Ditch the sad desk lunch. Nine easy workday lunch ideas plus a simple meal-prep system that makes the whole week effortless.',
            'category' => 'lunch',
            'tags' => 'lunch, meal prep, office food, tiffin',
            'days_ago' => 6,
            'body' => <<<'HTML'
<p>Lunch is the meal most likely to go wrong on workdays. Mornings are rushed, the canteen is uninspiring, and by 1 pm you will eat whatever is closest. The fix is not complicated: a short list of reliable lunches and a tiny bit of prep. Here is both.</p>
<h2>The workday lunch formula</h2>
<p>Every good lunch follows the same pattern: <strong>a grain + a protein + vegetables + something tasty</strong> (chutney, pickle, raita, dressing). Memorize the formula and you can improvise lunches forever without recipes.</p>
<h2>9 lunches that travel well</h2>
<ol>
<li><strong>Rajma chawal.</strong> The ultimate comfort lunch. Make a big batch Sunday; it tastes better on day two anyway.</li>
<li><strong>Vegetable pulao + raita.</strong> One-pot, endlessly variable, and the raita keeps it fresh even after hours in a tiffin.</li>
<li><strong>Paneer or egg wrap.</strong> Filling of choice + chutney + onions in a chapati or tortilla. Eat with one hand, type with the other.</li>
<li><strong>Dal khichdi.</strong> Underrated as a tiffin hero: complete protein, gentle on the stomach, reheats perfectly.</li>
<li><strong>Chole + jeera rice.</strong> Another batch-cooking champion. Pack a lemon wedge and sliced onions separately.</li>
<li><strong>Stuffed parathas.</strong> Aloo, paneer or mixed-veg parathas with curd and pickle. Make a stack on Sunday, freeze, and toast fresh each morning.</li>
<li><strong>Pasta salad.</strong> Cooked pasta with roasted vegetables, chickpeas and a lemon-olive oil dressing. Better cold than hot — ideal for offices without microwaves.</li>
<li><strong>Buddha bowl.</strong> Rice or millets + roasted vegetables + paneer/tofu/egg + a punchy dressing. Deconstruct in the tiffin, assemble at the desk.</li>
<li><strong>Idli / dosa + sambar.</strong> Fermented, light and satisfying. Batter keeps all week in the fridge.</li>
</ol>
<h2>The 30-minute Sunday system</h2>
<ul>
<li><strong>Cook two grains.</strong> Rice and one millet/quinoa cover the week's base.</li>
<li><strong>Cook two proteins.</strong> A dal and a paneer/egg/chole preparation.</li>
<li><strong>Chop vegetables.</strong> Washed, chopped and boxed — the barrier to cooking vegetables drops to zero.</li>
<li><strong>Make one chutney or dressing.</strong> Flavour insurance for the whole week.</li>
</ul>
<div class="callout"><strong>Tiffin rules:</strong> pack components separately (wet away from dry), cool food before closing the lid, and invest in one good leak-proof box. Soggy lunch is a motivation killer.</div>
<p>Good workday lunches are not about gourmet cooking — they are about removing decisions at 8 am. Pick three ideas from this list, prep on Sunday, and lunch takes care of itself all week.</p>
HTML,
        ],
        [
            'title' => 'Foods That Pair Surprisingly Well',
            'description' => 'Mango with chilli salt, coffee with cardamom, watermelon with salt — 8 unexpected food pairings that just work, and the science of why.',
            'category' => 'food-facts',
            'tags' => 'food pairings, flavour, food science, trivia',
            'days_ago' => 3,
            'body' => <<<'HTML'
<p>Some of the best flavour combinations sound wrong until you taste them. Behind every surprising pairing is real food science — contrasting tastes that balance each other, or shared aroma compounds that harmonize. Here are eight to try.</p>
<h2>1. Mango + chilli salt</h2>
<p>India knew this long before it was a trend: raw mango with salt and chilli is electric. Salt suppresses bitterness and amplifies sweetness, while chilli's heat makes the mango taste more intensely mango. Sweet + salty + spicy is a power trio.</p>
<h2>2. Watermelon + salt</h2>
<p>A pinch of salt on watermelon sounds like sabotage, but it works the same magic: salt mutes the melon's faint bitterness and makes it taste sweeter and juicier. Try flaky sea salt for the full effect.</p>
<h2>3. Dark chocolate + sea salt</h2>
<p>Salt does for chocolate what it does for watermelon — it rounds off bitterness and lifts the complex flavours underneath. This is why salted chocolate took over the world: it is not a gimmick, it is chemistry.</p>
<h2>4. Coffee + cardamom</h2>
<p>A pinch of ground cardamom in coffee grounds before brewing adds a warm, aromatic lift that pairs beautifully with coffee's natural bitterness. Middle Eastern coffee culture has done this for centuries. Start with a tiny pinch — cardamom is potent.</p>
<h2>5. Peanut butter + banana</h2>
<p>Creamy, salty peanut butter against sweet, soft banana is a textural and flavour match made in heaven. Add a drizzle of honey or a sprinkle of cinnamon and it becomes dessert-worthy.</p>
<h2>6. Cheese + honey</h2>
<p>Salty cheese drizzled with honey — especially aged or blue cheese — is a classic pairing for a reason. The honey's sweetness tames the salt and funk, and each makes the other taste more like itself.</p>
<h2>7. Curd rice + pickle</h2>
<p>Cool, mild curd rice against fiery, tangy pickle is India's original contrast pairing. The fat and coolness of the curd soothe the palate while the pickle provides excitement. Comfort and thrill in one plate.</p>
<h2>8. Popcorn + chaat masala</h2>
<p>Butter-salted popcorn is fine; popcorn tossed with chaat masala, a squeeze of lime and a little melted butter is transformative. Tangy, spicy, salty, crunchy — movie night will never be the same.</p>
<div class="callout"><strong>The pairing principle:</strong> most great pairings follow one of two rules — <em>contrast</em> (sweet vs salty, cool vs spicy) or <em>harmony</em> (shared flavours, like chocolate and coffee). Use these rules to invent your own.</div>
<p>Flavour is an experiment, and the kitchen is the lab. Try one surprising pairing this week — the worst case is a funny story, and the best case is a new obsession.</p>
HTML,
        ],
    ];

    foodiie_insert_articles($pdo, $articles, $author_id);
}

/** Shared insert for both article batches. */
function foodiie_insert_articles(PDO $pdo, array $articles, ?int $author_id): void
{
    $stmt = $pdo->prepare('INSERT INTO articles(title, slug, description, body, image, image_alt,
            author_id, category_id, tags, status, publish_at, created_at, updated_at,
            reading_time, seo_title, seo_description)
        VALUES(:title, :slug, :description, :body, :image, :image_alt,
            :author_id, :category_id, :tags, \'published\', :publish_at, :created_at, :updated_at,
            :reading_time, :seo_title, :seo_description)
        ON CONFLICT(slug) DO NOTHING');

    $now = gmdate('Y-m-d H:i:s');
    foreach ($articles as $a) {
        $slug = slugify($a['title']);
        // sanitize_html() strips comments, so the demo marker is prepended after.
        $body = '<!-- demo content -->' . "\n" . sanitize_html($a['body']);
        $publish_at = gmdate('Y-m-d H:i:s', time() - ((int) $a['days_ago']) * 86400 - 8 * 3600);
        $stmt->execute([
            ':title' => $a['title'],
            ':slug' => $slug,
            ':description' => $a['description'],
            ':body' => $body,
            ':image' => 'uploads/seed/' . $slug . '.jpg',
            ':image_alt' => $a['title'],
            ':author_id' => $author_id,
            ':category_id' => foodiie_category_id($pdo, $a['category']),
            ':tags' => $a['tags'],
            ':publish_at' => $publish_at,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':reading_time' => reading_time($body),
            ':seo_title' => $a['title'],
            ':seo_description' => $a['description'],
        ]);
    }
}

/* ------------------------------------------------------------------ */
/* Demo recipes                                                        */
/* ------------------------------------------------------------------ */

function foodiie_seed_recipes(PDO $pdo): void
{
    $recipes = [
        [
            'name' => 'Vegetable Poha',
            'description' => 'Light, fluffy flattened rice tossed with vegetables, crunchy peanuts and a squeeze of lemon — India’s favourite 15-minute breakfast.',
            'prep_time' => '10 mins', 'cook_time' => '15 mins', 'total_time' => '25 mins',
            'servings' => '2', 'difficulty' => 'Easy', 'cuisine' => 'Indian',
            'ingredients' => [
                '2 cups thick poha (flattened rice)',
                '1 onion, finely chopped',
                '1 small potato, diced (optional)',
                '1/4 cup green peas',
                '2 green chillies, slit',
                '8-10 curry leaves',
                '1/2 tsp mustard seeds',
                '1/4 tsp turmeric powder',
                '2 tbsp roasted peanuts',
                '1 tbsp oil',
                'Salt to taste',
                '1 tsp lemon juice',
                '2 tbsp chopped coriander leaves',
            ],
            'instructions' => [
                'Rinse the poha in a colander under running water for 30 seconds, then set aside for 5 minutes to soften. It should feel moist, not mushy.',
                'Heat oil in a kadhai. Add mustard seeds and let them splutter, then add curry leaves, green chillies and peanuts. Sauté for a minute.',
                'Add the onion (and potato, if using) and cook until the onion turns translucent, about 3-4 minutes.',
                'Stir in the turmeric, salt and green peas. Cook for 2 minutes.',
                'Gently fold in the softened poha, mixing carefully so the flakes do not break.',
                'Cover and steam on low heat for 3-4 minutes.',
                'Turn off the heat, squeeze over the lemon juice and garnish with coriander. Serve hot.',
            ],
            'tags' => 'breakfast, poha, indian, quick, vegetarian',
            'days_ago' => 28,
        ],
        [
            'name' => 'Masala Omelette',
            'description' => 'Fluffy eggs loaded with onion, tomato, green chilli and warm spices — the 10-minute breakfast that never disappoints.',
            'prep_time' => '5 mins', 'cook_time' => '10 mins', 'total_time' => '15 mins',
            'servings' => '1', 'difficulty' => 'Easy', 'cuisine' => 'Indian',
            'ingredients' => [
                '3 eggs',
                '1 small onion, finely chopped',
                '1 small tomato, finely chopped',
                '1 green chilli, finely chopped',
                '2 tbsp chopped coriander leaves',
                '1/4 tsp turmeric powder',
                '1/4 tsp red chilli powder',
                'Salt to taste',
                '1 tsp butter or oil',
            ],
            'instructions' => [
                'Crack the eggs into a bowl and whisk with salt, turmeric and red chilli powder until slightly frothy.',
                'Stir in the onion, tomato, green chilli and half the coriander.',
                'Heat butter in a non-stick pan over medium heat until it foams.',
                'Pour in the egg mixture and tilt the pan so it spreads evenly.',
                'Cook for 2-3 minutes until the bottom is golden, then flip and cook 1-2 minutes more.',
                'Slide onto a plate, garnish with the remaining coriander and serve hot with toast or pav.',
            ],
            'tags' => 'breakfast, eggs, indian, quick, protein',
            'days_ago' => 25,
        ],
        [
            'name' => 'Paneer Sandwich',
            'description' => 'Crisp golden bread stuffed with spiced paneer, crunchy vegetables and tangy green chutney — a cafe-style sandwich at home.',
            'prep_time' => '10 mins', 'cook_time' => '10 mins', 'total_time' => '20 mins',
            'servings' => '2', 'difficulty' => 'Easy', 'cuisine' => 'Indian',
            'ingredients' => [
                '4 bread slices',
                '100 g paneer, grated or crumbled',
                '1 small onion, finely chopped',
                '1/2 capsicum, finely chopped',
                '1 small tomato, finely chopped (seeds removed)',
                '2 tbsp green chutney',
                '1/2 tsp chaat masala',
                '1/4 tsp black pepper powder',
                'Salt to taste',
                '2 tbsp chopped coriander leaves',
                'Butter, for toasting',
            ],
            'instructions' => [
                'In a bowl, mix the paneer, onion, capsicum, tomato, coriander, chaat masala, pepper and salt.',
                'Spread green chutney on one side of each bread slice.',
                'Divide the paneer mixture between two slices and cover with the remaining slices, chutney-side down.',
                'Heat a tawa or sandwich press. Butter the outside of each sandwich.',
                'Toast 2-3 minutes per side until crisp and golden.',
                'Cut diagonally and serve hot with extra chutney or ketchup.',
            ],
            'tags' => 'snacks, sandwich, paneer, tiffin, vegetarian',
            'days_ago' => 22,
        ],
        [
            'name' => 'Vegetable Pasta',
            'description' => 'A colourful, weeknight-friendly pasta with garden vegetables in a light garlic-tomato sauce — ready in 30 minutes.',
            'prep_time' => '10 mins', 'cook_time' => '20 mins', 'total_time' => '30 mins',
            'servings' => '2-3', 'difficulty' => 'Medium', 'cuisine' => 'Italian',
            'ingredients' => [
                '200 g penne or fusilli pasta',
                '1 cup mixed vegetables (carrot, beans, peas, corn)',
                '1 capsicum, diced',
                '4 garlic cloves, minced',
                '1 cup tomato passata or puree',
                '2 tbsp olive oil',
                '1 tsp chilli flakes',
                '1 tsp dried oregano',
                'Salt to taste',
                '1/4 cup grated cheese (optional)',
                'Fresh basil or coriander, to garnish',
            ],
            'instructions' => [
                'Boil the pasta in well-salted water until al dente. Reserve a cup of pasta water, then drain.',
                'Meanwhile, heat olive oil in a large pan. Sauté the garlic for 30 seconds until fragrant.',
                'Add the mixed vegetables and capsicum. Cook on medium-high for 4-5 minutes until just tender.',
                'Pour in the tomato passata, chilli flakes, oregano and salt. Simmer for 5 minutes.',
                'Toss in the drained pasta with a splash of pasta water until the sauce clings to every piece.',
                'Adjust seasoning, top with cheese if using, and garnish before serving.',
            ],
            'tags' => 'dinner, pasta, italian, vegetarian, weeknight',
            'days_ago' => 19,
        ],
        [
            'name' => 'Mango Lassi',
            'description' => 'Thick, creamy and sunshine-sweet — the classic Indian mango lassi, blended in 5 minutes flat.',
            'prep_time' => '5 mins', 'cook_time' => '0 mins', 'total_time' => '5 mins',
            'servings' => '2', 'difficulty' => 'Easy', 'cuisine' => 'Indian',
            'ingredients' => [
                '2 ripe mangoes (about 2 cups chopped)',
                '1 cup thick curd (yogurt)',
                '1/2 cup cold milk',
                '2-3 tbsp sugar (adjust to mango sweetness)',
                '1/4 tsp cardamom powder',
                '4-5 ice cubes',
                'Chopped pistachios, to garnish (optional)',
            ],
            'instructions' => [
                'Peel and chop the mangoes, discarding the seed.',
                'Add mango, curd, milk, sugar, cardamom and ice cubes to a blender.',
                'Blend on high for 60-90 seconds until completely smooth and frothy.',
                'Taste and adjust sweetness or thickness — add milk to thin, more mango to thicken.',
                'Pour into tall glasses, garnish with pistachios if using, and serve immediately.',
            ],
            'tags' => 'drinks, mango, summer, indian, no-cook',
            'days_ago' => 16,
        ],
    ];

    $stmt = $pdo->prepare('INSERT INTO recipes(name, slug, description, image, image_alt,
            prep_time, cook_time, total_time, servings, difficulty, cuisine,
            ingredients, instructions, tags, status, publish_at, created_at, updated_at,
            seo_title, seo_description)
        VALUES(:name, :slug, :description, :image, :image_alt,
            :prep_time, :cook_time, :total_time, :servings, :difficulty, :cuisine,
            :ingredients, :instructions, :tags, \'published\', :publish_at, :created_at, :updated_at,
            :seo_title, :seo_description)
        ON CONFLICT(slug) DO NOTHING');

    $now = gmdate('Y-m-d H:i:s');
    foreach ($recipes as $r) {
        $slug = slugify($r['name']);
        $publish_at = gmdate('Y-m-d H:i:s', time() - ((int) $r['days_ago']) * 86400 - 8 * 3600);
        $stmt->execute([
            ':name' => $r['name'],
            ':slug' => $slug,
            ':description' => $r['description'],
            ':image' => 'uploads/seed/' . $slug . '.jpg',
            ':image_alt' => $r['name'],
            ':prep_time' => $r['prep_time'],
            ':cook_time' => $r['cook_time'],
            ':total_time' => $r['total_time'],
            ':servings' => $r['servings'],
            ':difficulty' => $r['difficulty'],
            ':cuisine' => $r['cuisine'],
            ':ingredients' => json_encode($r['ingredients'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':instructions' => json_encode($r['instructions'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':tags' => $r['tags'],
            ':publish_at' => $publish_at,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':seo_title' => $r['name'] . ' Recipe',
            ':seo_description' => $r['description'],
        ]);
    }
}

/* ------------------------------------------------------------------ */
/* Demo videos                                                         */
/* ------------------------------------------------------------------ */

function foodiie_seed_videos(PDO $pdo): void
{
    // IDs verified from real youtube.com watch URLs (Sep 2026).
    $videos = [
        [
            'title' => '[Demo] How to Make Perfect Scrambled Eggs',
            'video_id' => 'eLkgILAkqVI',
            'description' => 'Gordon Ramsay turns an ordinary breakfast into something extraordinary in this classic MasterChef clip — creamy, restaurant-style scrambled eggs in minutes. A perfect reference for upgrading your own breakfast game.',
            'tags' => 'breakfast, eggs, cooking video, demo',
            'days_ago' => 26,
        ],
        [
            'title' => '[Demo] 5 Easy Indian Curries for the Week',
            'video_id' => 'jGYOk1vQEdo',
            'description' => 'Hebbar’s Kitchen walks through five simple weekday curries — aloo chole, corn capsicum masala, aloo tamatar, palak paneer and tawa paneer — each designed for busy weeknight cooking with everyday ingredients.',
            'tags' => 'curry, indian, weeknight, cooking video, demo',
            'days_ago' => 20,
        ],
        [
            'title' => '[Demo] Creamy Paneer Veggie Masala',
            'video_id' => 'r6wIFt_-x8U',
            'description' => 'A restaurant-style creamy paneer curry loaded with vegetables — rich, comforting and surprisingly easy to make at home. Great inspiration for your next dinner.',
            'tags' => 'paneer, curry, indian, dinner, demo',
            'days_ago' => 14,
        ],
        [
            'title' => '[Demo] How to Make Paneer Butter Masala',
            'video_id' => 'lkOAdeV5DbU',
            'description' => 'The classic Punjabi paneer butter masala — soft paneer cubes in a smooth tomato-cashew gravy, finished with butter and cream. Watch the technique, then try it in your own kitchen.',
            'tags' => 'paneer, punjabi, curry, cooking video, demo',
            'days_ago' => 8,
        ],
        [
            'title' => '[Demo] How to Make Perfect Non-Sticky Poha',
            'video_id' => 'o_aPkRy8Aa4',
            'description' => 'Learn the secrets to soft, fluffy, non-sticky poha — the right way to wash flattened rice, a flavourful tempering, and a street-style finish with peanuts and lemon.',
            'tags' => 'poha, breakfast, indian, cooking video, demo',
            'days_ago' => 2,
        ],
    ];

    $videos_category = foodiie_category_id($pdo, 'videos');

    $stmt = $pdo->prepare('INSERT INTO videos(title, slug, youtube_url, video_id, description,
            category_id, tags, status, publish_at, created_at, updated_at, seo_title, seo_description)
        VALUES(:title, :slug, :youtube_url, :video_id, :description,
            :category_id, :tags, \'published\', :publish_at, :created_at, :updated_at, :seo_title, :seo_description)
        ON CONFLICT(slug) DO NOTHING');

    $now = gmdate('Y-m-d H:i:s');
    foreach ($videos as $v) {
        $slug = slugify($v['title']);
        $publish_at = gmdate('Y-m-d H:i:s', time() - ((int) $v['days_ago']) * 86400 - 8 * 3600);
        $stmt->execute([
            ':title' => $v['title'],
            ':slug' => $slug,
            ':youtube_url' => 'https://www.youtube.com/watch?v=' . $v['video_id'],
            ':video_id' => $v['video_id'],
            ':description' => $v['description'],
            ':category_id' => $videos_category,
            ':tags' => $v['tags'],
            ':publish_at' => $publish_at,
            ':created_at' => $now,
            ':updated_at' => $now,
            ':seo_title' => $v['title'],
            ':seo_description' => $v['description'],
        ]);
    }
}

/* ------------------------------------------------------------------ */
/* Demo image files                                                    */
/* ------------------------------------------------------------------ */

/**
 * Create real image files for every demo row that references
 * uploads/seed/{slug}.jpg, so the generated site has working images.
 * Uses GD for a simple branded placeholder; falls back to copying the
 * static placeholder.jpg when GD is unavailable.
 */
function foodiie_seed_images(PDO $pdo): void
{
    $dir = FOODIIE_ROOT . '/storage/uploads/seed';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $jobs = [];
    foreach ($pdo->query("SELECT slug, title FROM articles WHERE image LIKE 'uploads/seed/%'")->fetchAll() as $r) {
        $jobs[] = [(string) $r['slug'], (string) $r['title']];
    }
    foreach ($pdo->query("SELECT slug, name AS title FROM recipes WHERE image LIKE 'uploads/seed/%'")->fetchAll() as $r) {
        $jobs[] = [(string) $r['slug'], (string) $r['title']];
    }
    $fallback = FOODIIE_ROOT . '/public/assets/images/placeholder.jpg';
    foreach ($jobs as [$slug, $title]) {
        $dest = $dir . '/' . $slug . '.jpg';
        if (is_file($dest)) {
            continue;
        }
        if (!foodiie_seed_draw_placeholder($dest, $title) && is_file($fallback)) {
            copy($fallback, $dest);
        }
    }
}

/** Draw a 1200x630 branded placeholder with GD. Returns false when GD is missing. */
function foodiie_seed_draw_placeholder(string $dest, string $title): bool
{
    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }
    $w = 1200;
    $h = 630;
    $img = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($img, 255, 247, 237);   // warm cream
    $accent = imagecolorallocate($img, 245, 158, 11); // brand amber
    $dark = imagecolorallocate($img, 34, 34, 34);
    $muted = imagecolorallocate($img, 120, 110, 100);
    imagefilledrectangle($img, 0, 0, $w, $h, $bg);
    // Amber top + bottom bands.
    imagefilledrectangle($img, 0, 0, $w, 26, $accent);
    imagefilledrectangle($img, 0, $h - 26, $w, $h, $accent);
    // Brand mark.
    imagestring($img, 5, 60, 70, 'FOODIIE', $accent);
    // Title, wrapped.
    $title = trim(preg_replace('/^\[Demo\]\s*/i', '', $title));
    $words = preg_split('/\s+/', $title);
    $lines = [];
    $line = '';
    foreach ($words as $word) {
        $try = trim($line . ' ' . $word);
        if (strlen($try) > 38) {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $try;
        }
        if (count($lines) >= 3) {
            break;
        }
    }
    if ($line !== '') {
        $lines[] = $line;
    }
    $lines = array_slice($lines, 0, 4);
    $y = 200;
    foreach ($lines as $ln) {
        imagestring($img, 5, 60, $y, $ln, $dark);
        $y += 52;
    }
    imagestring($img, 4, 60, $h - 90, 'Demo image - replace with your own photo', $muted);
    $ok = imagejpeg($img, $dest, 82);
    imagedestroy($img);
    return (bool) $ok;
}
