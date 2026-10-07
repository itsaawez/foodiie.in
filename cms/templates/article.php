<?php
/**
 * FOODIIE — article template.
 * Vars: $site,$page,$jsonld,$article,$category_name,$author_name,$related.
 */
partial('head', ['page' => $page ?? [], 'jsonld' => $jsonld ?? [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);

$article = $article ?? [];
$category_name = (string) ($category_name ?? '');
$author_name = (string) ($author_name ?? '');
$cat_href = !empty($article['category_url'])
    ? fd_public_url((string) $article['category_url'])
    : u('/');

$hero_img = trim((string) ($article['image'] ?? ''));
$hero_img = $hero_img !== '' ? public_image_url($hero_img) : u('assets/images/placeholder.jpg');

$pub_raw = $article['publish_at'] ?? $article['created_at'] ?? null;
$upd_raw = $article['updated_at'] ?? null;
$pub = fmt_date($pub_raw);
$upd = fmt_date($upd_raw);
$share_url = abs_url(ltrim((string) ($page['canonical'] ?? '/'), '/'));

/* Echo the (already sanitized) body, inserting the middle ad after ~half the paragraphs. */
if (!function_exists('fd_article_body')) {
function fd_article_body(string $body): void
{
    $chunks = explode('</p>', $body);
    $last = count($chunks) - 1;
    $total = 0;
    foreach ($chunks as $i => $c) {
        if ($i !== $last && trim(strip_tags($c)) !== '') {
            $total++;
        }
    }
    $target = (int) ceil($total / 2);
    $seen = 0;
    $inserted = false;
    foreach ($chunks as $i => $c) {
        echo $c;
        if ($i === $last) {
            break;
        }
        echo '</p>';
        if (!$inserted && trim(strip_tags($c)) !== '') {
            $seen++;
            if ($seen === $target) {
                echo ad_html('article_middle');
                $inserted = true;
            }
        }
    }
}
}

$tags = parse_tags((string) ($article['tags'] ?? ''));
?>
<main id="main-content">
  <div class="container article-wrap">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?= esc(u('/')) ?>">Home</a></li>
        <li><a href="<?= esc($cat_href) ?>"><?= esc($category_name) ?></a></li>
        <li aria-current="page"><?= esc($article['title'] ?? '') ?></li>
      </ol>
    </nav>

    <article>
      <header class="article-header">
        <?php if ($category_name !== ''): ?>
        <p><span class="card-tag"><?= esc($category_name) ?></span></p>
        <?php endif; ?>
        <h1><?= esc($article['title'] ?? '') ?></h1>
        <p class="byline">
          By <?= esc($author_name) ?>
          <?php if ($pub !== ''): ?> &middot; Published <time datetime="<?= esc((string) $pub_raw) ?>"><?= esc($pub) ?></time><?php endif; ?>
          <?php if ($upd !== '' && $upd !== $pub): ?> &middot; Updated <time datetime="<?= esc((string) $upd_raw) ?>"><?= esc($upd) ?></time><?php endif; ?>
          &middot; <?= reading_time((string) ($article['body'] ?? '')) ?> min read
        </p>
      </header>

      <figure class="article-hero">
        <img src="<?= esc($hero_img) ?>"
             alt="<?= esc($article['image_alt'] ?? $article['title'] ?? '') ?>"
             loading="eager"
             decoding="async"
             fetchpriority="high"
             width="820"
             height="461">
      </figure>

      <?= ad_html('article_top') ?>

      <div class="article-body">
        <?php fd_article_body((string) ($article['body'] ?? '')); ?>
      </div>

      <?php if ($tags): ?>
      <ul class="tags" aria-label="Tags">
        <?php foreach ($tags as $t): ?>
        <li class="tag"><?= esc($t) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php partial('share', ['share_url' => $share_url, 'share_title' => (string) ($article['title'] ?? '')]); ?>

      <aside class="author-box" aria-label="About the author">
        <div class="author-avatar" aria-hidden="true"><?= esc(mb_substr($author_name, 0, 1, 'UTF-8')) ?></div>
        <div>
          <h2><?= esc($author_name) ?></h2>
          <p>A FOODIIE contributor sharing practical food knowledge &mdash; recipes, facts and everyday cooking wisdom.</p>
        </div>
      </aside>

      <?= ad_html('article_bottom') ?>
    </article>

    <?php if (!empty($related)): ?>
    <section class="related" aria-labelledby="related-title">
      <h2 id="related-title">You may also like</h2>
      <div class="card-grid">
        <?php foreach ($related as $item) partial('card', ['item' => $item]); ?>
      </div>
    </section>
    <?php endif; ?>
  </div>
</main>
<?php partial('footer', ['site' => $site, 'nav_categories' => $nav_categories ?? []]); ?>
</body>
</html>
