/**
 * WordPress Dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { resolveSelect, useDispatch, useSelect } from '@wordpress/data';
import { store as coreStore, useEntityProp } from '@wordpress/core-data';
import { store as noticesStore } from '@wordpress/notices';
import {
	BaseControl,
	Button,
	ToggleControl,
	TextControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { PluginDocumentSettingPanel } from '@wordpress/editor';

/**
 * Internal Dependencies
 */
import { parseOrcid } from './orcid';

function OrcidField({ postType, postId, meta, setMeta }) {
	const isNewPost = useSelect(
		(select) => select('core/editor').isEditedPostNew(),
		[]
	);
	const [staffInfo] = useEntityProp(
		'postType',
		postType,
		'staffInfo',
		postId
	);
	const { invalidateResolution } = useDispatch(coreStore);
	const { createSuccessNotice, createErrorNotice } =
		useDispatch(noticesStore);

	const orcid = meta?.orcid ?? '';
	const parsedOrcid = parseOrcid(orcid);
	const isInvalid = '' !== orcid.trim() && null === parsedOrcid;
	const isAuthenticated =
		true === staffInfo?.staffOrcidAuthenticated &&
		null !== parsedOrcid &&
		staffInfo?.staffOrcid === parsedOrcid;
	const config = window.prcStaffOrcid;
	const isConfigured = true === config?.configured;

	useEffect(() => {
		if (!config?.channel) {
			return;
		}
		const channel = new window.BroadcastChannel(config.channel);
		channel.onmessage = (event) => {
			if (![postId, 0].includes(event.data?.staffId)) {
				return;
			}
			if ('success' === event.data.status) {
				setMeta({ ...meta, orcid: event.data.orcid });
				invalidateResolution('getEntityRecord', [
					'postType',
					postType,
					postId,
				]);
				resolveSelect(coreStore).getEntityRecord(
					'postType',
					postType,
					postId
				);
				createSuccessNotice(
					sprintf(
						/* translators: %s: ORCID iD. */
						__(
							'ORCID iD %s is authenticated.',
							'prc-staff-bylines'
						),
						event.data.orcid
					),
					{ type: 'snackbar' }
				);
				return;
			}
			createErrorNotice(event.data.message, { type: 'snackbar' });
		};
		return () => channel.close();
	}, [
		config?.channel,
		meta,
		setMeta,
		postType,
		postId,
		invalidateResolution,
		createSuccessNotice,
		createErrorNotice,
	]);

	const authenticate = () => {
		const popup = window.open(
			`${config.authorizeUrl}&staffId=${postId}&_wpnonce=${config.nonce}`,
			'prc-orcid-auth',
			'width=520,height=720'
		);
		if (!popup) {
			createErrorNotice(
				__(
					'The browser blocked the ORCID window. Allow pop-ups for this site and try again.',
					'prc-staff-bylines'
				),
				{ type: 'snackbar' }
			);
		}
	};

	let authenticateHelp = __(
		'The staff member signs in to ORCID to confirm the iD, so do this with them or from their browser.',
		'prc-staff-bylines'
	);
	if (!isConfigured) {
		authenticateHelp = __(
			'ORCID API credentials are not configured.',
			'prc-staff-bylines'
		);
	} else if (isNewPost) {
		authenticateHelp = __(
			'Save this staff profile before you authenticate the ORCID iD.',
			'prc-staff-bylines'
		);
	}

	return (
		<>
			<TextControl
				__next40pxDefaultSize
				label={__('ORCID iD', 'prc-staff-bylines')}
				value={orcid}
				onChange={(value) => {
					setMeta({ ...meta, orcid: value });
				}}
				placeholder="0000-0002-1825-0097"
				help={
					isInvalid
						? __(
								'This is not a valid ORCID iD. It will be cleared when you save.',
								'prc-staff-bylines'
							)
						: null
				}
			/>
			<BaseControl
				id="prc-staff-orcid-authenticate"
				help={authenticateHelp}
				__nextHasNoMarginBottom
			>
				<p>
					{isAuthenticated
						? __('Authenticated with ORCID', 'prc-staff-bylines')
						: __('Not authenticated', 'prc-staff-bylines')}
				</p>
				<Button
					__next40pxDefaultSize
					id="prc-staff-orcid-authenticate"
					variant="secondary"
					disabled={!isConfigured || isNewPost}
					onClick={authenticate}
				>
					{__('Authenticate with ORCID', 'prc-staff-bylines')}
				</Button>
			</BaseControl>
		</>
	);
}

export default function StaffInfoPanel() {
	const { postType, postId } = useSelect(
		(select) => ({
			postType: select('core/editor').getCurrentPostType(),
			postId: select('core/editor').getCurrentPostId(),
		}),
		[]
	);

	const [meta, setMeta] = useEntityProp('postType', postType, 'meta', postId);
	const { jobTitle, jobTitleExtended, bylineLinkEnabled } = meta;

	return (
		<PluginDocumentSettingPanel
			name="prc-staff-info"
			title="Staff Information"
		>
			<VStack spacing="2">
				<ToggleControl
					label={__('Display Byline Link')}
					help={__(
						'All staff are assigned a byline tem, however not all staff have a link to a byline archive "staff bio" page. If this staff member has a bio page, enable this option to link their byline to their bio page.'
					)}
					checked={bylineLinkEnabled}
					onChange={() => {
						setMeta({
							...meta,
							bylineLinkEnabled: !bylineLinkEnabled,
						});
					}}
				/>
				<TextControl
					__next40pxDefaultSize
					label={__('Job Title')}
					value={jobTitle}
					onChange={(value) => {
						setMeta({ ...meta, jobTitle: value });
					}}
					placeholder="Research Assistant"
				/>
				<TextControl
					__next40pxDefaultSize
					label={__('Job Title Extended')}
					help={__(
						'This extended job title appears under Short Read posts.'
					)}
					value={jobTitleExtended}
					onChange={(value) => {
						setMeta({
							...meta,
							jobTitleExtended: value,
						});
					}}
					placeholder="is a Research Assistant at Pew Research Center."
				/>
				<OrcidField
					postType={postType}
					postId={postId}
					meta={meta}
					setMeta={setMeta}
				/>
			</VStack>
		</PluginDocumentSettingPanel>
	);
}
