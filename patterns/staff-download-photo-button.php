<?php
/**
 * Title: Staff Download Photo Button
 * Slug: prc-staff-bylines/staff-download-photo-button
 * Categories: prc-staff-bylines
 * Description: Button bound to staff full photo download text and url.
 * Block Types: prc-block/staff-context-provider
 *
 * @package PRC\Platform\Staff_Bylines
 */

?>
<!-- wp:buttons {} -->
<div class="wp-block-buttons">
<!-- wp:button {"url":"#","metadata":{"bindings":{"text":{"source":"prc-platform/staff-info","args":{"valueToFetch":"photo-full-download-text"}},"url":{"source":"prc-platform/staff-info","args":{"valueToFetch":"photo-full"}}}}} -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button"></a></div>
<!-- /wp:button -->

</div>
<!-- /wp:buttons -->
