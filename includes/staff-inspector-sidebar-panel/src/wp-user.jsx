/**
 * External Dependencies
 */

/**
 * WordPress Dependencies
 */

import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { __experimentalVStack as VStack } from '@wordpress/components';

export default function WPUserPanel() {
	return (
		<PluginDocumentSettingPanel
			name="prc-staff-wp-user"
			title="WordPress User"
		>
			<VStack spacing="2">
				<p>WIP</p>
				<p>
					Control to tie a wp_user profile to a staff profile will go
					here. This will enable slack notifications and the like in
					the future.
				</p>
			</VStack>
		</PluginDocumentSettingPanel>
	);
}
