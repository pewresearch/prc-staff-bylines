/**
 * External Dependencies
 */
import { TermSelect } from '@prc/components';

/**
 * WordPress Dependencies
 */

import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import {
	ToggleControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

export default function MaelstromPanel() {
	const { postType, postId } = useSelect(
		(select) => ({
			postType: select('core/editor').getCurrentPostType(),
			postId: select('core/editor').getCurrentPostId(),
		}),
		[]
	);
	const [meta, setMeta] = useEntityProp('postType', postType, 'meta', postId);
	const { _maelstrom } = meta;
	const { enabled, restricted } = useMemo(() => {
		if (_maelstrom && _maelstrom.enabled && _maelstrom.restricted) {
			return {
				enabled: true,
				restricted: _maelstrom.restricted,
			};
		}
		return {
			enabled: false,
			restricted: [],
		};
	}, [_maelstrom]);

	const updateRestricted = (value) => {
		const newValue = restricted;
		// if the value is not in the array, add it
		if (!newValue.includes(value)) {
			newValue.push(value);
		} else {
			// if the value is in the array, remove it
			newValue.splice(newValue.indexOf(value), 1);
		}
		setMeta({
			_maelstrom: {
				enabled,
				restricted: newValue,
			},
		});
	};

	const updateEnabled = (value) => {
		setMeta({
			_maelstrom: {
				enabled: value,
				restricted,
			},
		});
	};

	return (
		<PluginDocumentSettingPanel
			name="prc-staff-info-safety"
			title="Staff Safety (Maelstrom)"
		>
			<VStack spacing="2">
				<ToggleControl
					label="Enable Maelstrom"
					checked={enabled}
					onChange={() => {
						updateEnabled(!enabled);
					}}
				/>
				{enabled && (
					<TermSelect
						{...{
							onChange: (x) => {
								if (x?.name) {
									updateRestricted(x.name);
								}
							},
							// @TODO: Further obfuscate this naming
							taxonomy: 'regions-countries',
							value: restricted,
							maxTerms: 5,
							label: 'Select a region to restrict',
						}}
					/>
				)}
			</VStack>
		</PluginDocumentSettingPanel>
	);
}
