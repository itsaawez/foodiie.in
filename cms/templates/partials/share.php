<?php
/**
 * FOODIIE — share links partial. Lightweight links only, no SDKs.
 * Vars: $share_url (absolute URL), $share_title.
 */
$share_url = (string) ($share_url ?? '');
$share_title = (string) ($share_title ?? '');
$eu = urlencode($share_url);
$et = urlencode($share_title);
?>
<div class="share">
  <span class="share-label">Share:</span>
  <a href="https://wa.me/?text=<?= $et ?>%20<?= $eu ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp">WhatsApp</a>
  <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $eu ?>" target="_blank" rel="noopener" aria-label="Share on Facebook">Facebook</a>
  <a href="https://twitter.com/intent/tweet?text=<?= $et ?>&amp;url=<?= $eu ?>" target="_blank" rel="noopener" aria-label="Share on X">X</a>
  <a href="https://pinterest.com/pin/create/button/?url=<?= $eu ?>&amp;description=<?= $et ?>" target="_blank" rel="noopener" aria-label="Share on Pinterest">Pinterest</a>
  <button type="button" data-copy="<?= esc($share_url) ?>">Copy Link</button>
</div>
