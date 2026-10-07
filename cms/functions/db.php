<?php
/**
 * FOODIIE — SQLite storage via PDO.
 *
 * Single database file: cms/data/foodiie.sqlite (created by installer).
 * All queries use prepared statements. No raw interpolation of user input.
 */

require_once __DIR__ . '/config.php';

function db_path(): string
{
    $override = (string) env('DATABASE_PATH', '');
    if ($override !== '') {
        return $override;
    }
    return FOODIIE_ROOT . '/cms/data/foodiie.sqlite';
}

/** @return PDO shared connection */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $file = db_path();
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    db_migrate($pdo);
    return $pdo;
}

/** Full schema. Executed by the installer. Idempotent. */
function foodiie_schema_sql(): string
{
    return <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'admin',
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    image TEXT NOT NULL DEFAULT '',
    seo_title TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT '',
    sort INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE IF NOT EXISTS articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL DEFAULT '',
    image TEXT NOT NULL DEFAULT '',
    image_alt TEXT NOT NULL DEFAULT '',
    author_id INTEGER,
    category_id INTEGER,
    tags TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft',
    publish_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    reading_time INTEGER NOT NULL DEFAULT 0,
    seo_title TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT '',
    canonical TEXT NOT NULL DEFAULT '',
    og_image TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS recipes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    image TEXT NOT NULL DEFAULT '',
    image_alt TEXT NOT NULL DEFAULT '',
    prep_time TEXT NOT NULL DEFAULT '',
    cook_time TEXT NOT NULL DEFAULT '',
    total_time TEXT NOT NULL DEFAULT '',
    servings TEXT NOT NULL DEFAULT '',
    difficulty TEXT NOT NULL DEFAULT '',
    cuisine TEXT NOT NULL DEFAULT '',
    ingredients TEXT NOT NULL DEFAULT '[]',
    instructions TEXT NOT NULL DEFAULT '[]',
    calories TEXT NOT NULL DEFAULT '',
    protein TEXT NOT NULL DEFAULT '',
    carbs TEXT NOT NULL DEFAULT '',
    fat TEXT NOT NULL DEFAULT '',
    tags TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft',
    publish_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    seo_title TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT '',
    youtube_video_url TEXT NOT NULL DEFAULT '',
    youtube_video_id TEXT NOT NULL DEFAULT '',
    youtube_video_title TEXT NOT NULL DEFAULT '',
    youtube_video_description TEXT NOT NULL DEFAULT '',
    youtube_channel_name TEXT NOT NULL DEFAULT '',
    youtube_enabled INTEGER NOT NULL DEFAULT 1,
    youtube_position TEXT NOT NULL DEFAULT 'after_intro',
    youtube_video_type TEXT NOT NULL DEFAULT 'normal'
);
CREATE TABLE IF NOT EXISTS videos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    youtube_url TEXT NOT NULL DEFAULT '',
    video_id TEXT NOT NULL DEFAULT '',
    thumbnail TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    category_id INTEGER,
    tags TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft',
    publish_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    seo_title TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS media (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    original_name TEXT NOT NULL,
    mime TEXT NOT NULL,
    size INTEGER NOT NULL DEFAULT 0,
    width INTEGER NOT NULL DEFAULT 0,
    height INTEGER NOT NULL DEFAULT 0,
    alt TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS ads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slot TEXT NOT NULL UNIQUE,
    enabled INTEGER NOT NULL DEFAULT 0,
    code TEXT NOT NULL DEFAULT '',
    device TEXT NOT NULL DEFAULT 'both',
    start_date TEXT NOT NULL DEFAULT '',
    end_date TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL DEFAULT ''
);
CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    body TEXT NOT NULL DEFAULT '',
    seo_title TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT '',
    updated_at TEXT NOT NULL
);
CREATE TABLE IF NOT EXISTS recipe_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id INTEGER DEFAULT NULL,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL DEFAULT 'category',
    description TEXT NOT NULL DEFAULT '',
    image TEXT NOT NULL DEFAULT '',
    seo_title TEXT NOT NULL DEFAULT '',
    seo_description TEXT NOT NULL DEFAULT '',
    sort INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (parent_id) REFERENCES recipe_categories(id)
);
CREATE TABLE IF NOT EXISTS recipe_category_map (
    recipe_id INTEGER NOT NULL,
    category_id INTEGER NOT NULL,
    PRIMARY KEY (recipe_id, category_id),
    FOREIGN KEY (recipe_id) REFERENCES recipes(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES recipe_categories(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS shorts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    youtube_url TEXT NOT NULL DEFAULT '',
    video_id TEXT NOT NULL DEFAULT '',
    thumbnail TEXT NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    tags TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft',
    publish_at TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_articles_status ON articles(status, publish_at);
CREATE INDEX IF NOT EXISTS idx_recipes_status ON recipes(status, publish_at);
CREATE INDEX IF NOT EXISTS idx_videos_status ON videos(status, publish_at);
CREATE INDEX IF NOT EXISTS idx_shorts_status ON shorts(status, publish_at);
CREATE INDEX IF NOT EXISTS idx_rc_type ON recipe_categories(type);
CREATE INDEX IF NOT EXISTS idx_rc_parent ON recipe_categories(parent_id);
SQL;
}

/** Run the schema (used by installer). */
function db_install_schema(PDO $pdo): void
{
    $pdo->exec(foodiie_schema_sql());
}

/**
 * Run safe migrations on an existing database.
 * Each ALTER is wrapped in a try/catch so it's idempotent — running it
 * twice on the same database does nothing harmful.
 */
function db_migrate(PDO $pdo): void
{
    // Ensure new tables exist (CREATE IF NOT EXISTS is already idempotent).
    $pdo->exec(foodiie_schema_sql());

    // Add new columns to existing tables (ignore "duplicate column" errors).
    $alters = [
        // recipes — new fields for cuisine/course/diet/method/skill/rating
        "ALTER TABLE recipes ADD COLUMN author_id INTEGER",
        "ALTER TABLE recipes ADD COLUMN category_id INTEGER",
        "ALTER TABLE recipes ADD COLUMN course TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN diet_type TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN cooking_method TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN skill_level TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN canonical TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN og_image TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN rating_sum INTEGER NOT NULL DEFAULT 0",
        "ALTER TABLE recipes ADD COLUMN rating_count INTEGER NOT NULL DEFAULT 0",
        // articles — sub-type for food-news, kitchen-hack, etc.
        "ALTER TABLE articles ADD COLUMN article_type TEXT NOT NULL DEFAULT 'article'",
        // recipes — YouTube recipe video integration
        "ALTER TABLE recipes ADD COLUMN youtube_video_url TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN youtube_video_id TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN youtube_video_title TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN youtube_video_description TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN youtube_channel_name TEXT NOT NULL DEFAULT ''",
        "ALTER TABLE recipes ADD COLUMN youtube_enabled INTEGER NOT NULL DEFAULT 1",
        "ALTER TABLE recipes ADD COLUMN youtube_position TEXT NOT NULL DEFAULT 'after_intro'",
        "ALTER TABLE recipes ADD COLUMN youtube_video_type TEXT NOT NULL DEFAULT 'normal'",
    ];
    foreach ($alters as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // "duplicate column name" — safe to ignore.
            if (strpos($e->getMessage(), 'duplicate column') === false) {
                error_log('FOODIIE migration warning: ' . $e->getMessage());
            }
        }
    }
}

/** Get a site setting. */
function setting(string $key, string $default = ''): string
{
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = db()->prepare('SELECT value FROM settings WHERE key = :k');
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch();
        $cache[$key] = $row ? (string) $row['value'] : $default;
    } catch (Throwable $e) {
        $cache[$key] = $default;
    }
    return $cache[$key];
}

/** Save a site setting. */
function save_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings(key, value) VALUES(:k, :v)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

/** Current UTC timestamp in SQLite-friendly format. */
function now_utc(): string
{
    return gmdate('Y-m-d H:i:s');
}

/** Is this row publicly visible? status=published AND publish_at <= now. */
function is_published(array $row): bool
{
    if (($row['status'] ?? '') !== 'published') {
        return false;
    }
    $pub = $row['publish_at'] ?? null;
    if ($pub === null || $pub === '') {
        return true;
    }
    return strtotime($pub) <= time();
}
