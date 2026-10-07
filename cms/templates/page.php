<?php
/**
 * FOODIIE — static page template.
 * Vars: $site,$page,$jsonld,$page_row. Body is already sanitized on save.
 * Renders the contact form when $page_row['slug'] === 'contact'.
 */
partial('head', ['page' => $page ?? [], 'jsonld' => $jsonld ?? [], 'site' => $site]);
partial('header', ['site' => $site, 'nav_categories' => $nav_categories ?? []]);

$page_row = $page_row ?? [];
$is_contact = ($page_row['slug'] ?? '') === 'contact';
?>
<main id="main-content">
  <div class="container page-wrap">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <ol>
        <li><a href="<?= esc(u('/')) ?>">Home</a></li>
        <li aria-current="page"><?= esc($page_row['title'] ?? '') ?></li>
      </ol>
    </nav>

    <h1><?= esc($page_row['title'] ?? '') ?></h1>

    <div class="article-body">
      <?= (string) ($page_row['body'] ?? '') ?>
    </div>

    <?php if ($is_contact): ?>
    <form class="contact-form" data-contact action="<?= esc(u('api/contact.php')) ?>" method="post" style="margin-top:2rem">
      <div class="form-group">
        <label for="contact-name">Name</label>
        <input type="text" id="contact-name" name="name" required autocomplete="name" maxlength="100">
      </div>
      <div class="form-group">
        <label for="contact-email">Email</label>
        <input type="email" id="contact-email" name="email" required autocomplete="email" maxlength="160">
      </div>
      <div class="form-group">
        <label for="contact-subject">Subject</label>
        <input type="text" id="contact-subject" name="subject" required maxlength="160">
      </div>
      <div class="form-group">
        <label for="contact-message">Message</label>
        <textarea id="contact-message" name="message" required maxlength="5000"></textarea>
      </div>
      <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
      <button class="btn btn-primary" type="submit">Send Message</button>
      <p class="form-msg" aria-live="polite"></p>
    </form>
    <?php endif; ?>
  </div>
</main>
<?php partial('footer', ['site' => $site, 'nav_categories' => $nav_categories ?? []]); ?>
</body>
</html>
