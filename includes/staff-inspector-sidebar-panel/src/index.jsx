/**
 * WordPress Dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal Dependencies
 */
import MaelstromPanel from './maelstrom';
import StaffInfoPanel from './staff-info';
import WPUserPanel from './wp-user';

registerPlugin('prc-staff-info', {
	render: () => {
		return (
			<>
				<StaffInfoPanel />
				<MaelstromPanel />
				<WPUserPanel />
			</>
		);
	},
	icon: null,
});
