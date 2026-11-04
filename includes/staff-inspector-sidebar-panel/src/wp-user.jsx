/**
 * WordPress Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useDebounce } from '@wordpress/compose';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';
import {
	Button,
	ComboboxControl,
	__experimentalHStack as HStack, // eslint-disable-line
	__experimentalVStack as VStack, // eslint-disable-line
} from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

const MIN_SEARCH_LENGTH = 2;

function UserSearch({ onSelect }) {
	const [search, setSearch] = useState('');
	const debouncedSetSearch = useDebounce(setSearch, 300);

	const { users, isSearching } = useSelect(
		(select) => {
			if (search.trim().length < MIN_SEARCH_LENGTH) {
				return { users: [], isSearching: false };
			}
			const query = { search, per_page: 20, _fields: 'id,name,slug' };
			return {
				users:
					select(coreStore).getEntityRecords('root', 'user', query) ??
					[],
				isSearching: select(coreStore).isResolving('getEntityRecords', [
					'root',
					'user',
					query,
				]),
			};
		},
		[search]
	);

	return (
		<ComboboxControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			label={__('Link a WordPress user', 'prc-staff-bylines')}
			help={
				isSearching
					? __('Searching…', 'prc-staff-bylines')
					: __('Search by name or username.', 'prc-staff-bylines')
			}
			value={null}
			options={users.map((user) => ({
				value: String(user.id),
				label: `${user.name} (${user.slug})`,
			}))}
			onFilterValueChange={debouncedSetSearch}
			onChange={(value) => {
				const user = users.find(({ id }) => String(id) === value);
				if (user) {
					onSelect({ id: user.id, name: user.name });
				}
			}}
		/>
	);
}

export default function WPUserPanel() {
	const { postType, postId } = useSelect(
		(select) => ({
			postType: select('core/editor').getCurrentPostType(),
			postId: select('core/editor').getCurrentPostId(),
		}),
		[]
	);
	const [wpUser, setWpUser] = useEntityProp(
		'postType',
		postType,
		'wpUser',
		postId
	);
	const canLink = true === window.prcStaffUserLink?.canLink;
	const linkedId = wpUser?.id ?? 0;

	let body;
	if (linkedId > 0) {
		body = (
			<HStack justify="space-between" alignment="center">
				<span className="prc-staff-wp-user__linked">
					{wpUser.name ||
						sprintf(
							/* translators: %d: user ID. */
							__('User #%d', 'prc-staff-bylines'),
							linkedId
						)}
				</span>
				{canLink && (
					<Button
						variant="link"
						isDestructive
						onClick={() => setWpUser({ id: 0, name: '' })}
					>
						{__('Unlink', 'prc-staff-bylines')}
					</Button>
				)}
			</HStack>
		);
	} else if (canLink) {
		body = <UserSearch onSelect={setWpUser} />;
	} else {
		body = (
			<p>
				{__(
					'Not linked. An administrator can link this staff record to a WordPress user.',
					'prc-staff-bylines'
				)}
			</p>
		);
	}

	return (
		<PluginDocumentSettingPanel
			name="prc-staff-wp-user"
			title={__('WordPress User', 'prc-staff-bylines')}
		>
			<VStack spacing="2">
				<p>
					{__(
						'The WordPress user this staff record belongs to. A linked user can authenticate their ORCID iD from the welcome screen.',
						'prc-staff-bylines'
					)}
				</p>
				{body}
			</VStack>
		</PluginDocumentSettingPanel>
	);
}
