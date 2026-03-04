/**
 * External Dependencies
 */
import { WPEntitySearch } from '@prc/components';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';

interface ControlsProps {
	staffId: number | null;
	setAttributes: (attrs: Record<string, unknown>) => void;
}

export default function Controls({ setAttributes }: ControlsProps) {
	return (
		<InspectorControls>
			<PanelBody title={__('Staff Context Provider')}>
				<WPEntitySearch
					placeholder="Search for Staff"
					searchLabel="Search for Staff"
					entityType="postType"
					entitySubType="staff"
					onSelect={(entity: any) => {
						setAttributes({
							staffSlug: entity.slug,
						});
					}}
					onKeyEnter={() => {}}
					onKeyESC={() => {}}
					perPage={5}
					showExcerpt={false}
				/>
			</PanelBody>
		</InspectorControls>
	);
}
