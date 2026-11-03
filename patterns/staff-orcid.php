<?php
/**
 * Title: Staff ORCID iD
 * Slug: prc-staff-bylines/staff-orcid
 * Categories: prc-staff-bylines
 * Description: ORCID iD mark (grayscale when unauthenticated) beside a paragraph bound to the staff ORCID iD. The paragraph renders nothing when the staff member has no ORCID iD.
 * Block Types: prc-block/staff-context-provider
 *
 * @package PRC\Platform\Staff_Bylines
 */

?>
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"var:preset|spacing|20"}}} -->
<div class="wp-block-group"><!-- wp:icon {"metadata":{"bindings":{"icon":{"source":"prc-platform/staff-info","args":{"valueToFetch":"orcid-icon"}}}},"style":{"dimensions":{"width":"16px"}}} /-->

<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"prc-platform/staff-info","args":{"valueToFetch":"orcid"}}}}} -->
<p></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->

