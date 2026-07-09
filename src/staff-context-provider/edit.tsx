/**
 * External Dependencies
 */
import { InnerBlocksAsContextTemplate } from '@prc/components';

/**
 * WordPress Dependencies
 */
import { useBlockProps, Warning } from '@wordpress/block-editor';
import { useEntityRecord } from '@wordpress/core-data';
import { __ } from '@wordpress/i18n';
import { Fragment, useEffect, useState, useMemo } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal Dependencies
 */
import Controls from './controls';

const ALLOWED_BLOCKS = [
	'core/group',
	'core/paragraph',
	'core/heading',
	'core/list-item',
	'core/button',
	'prc-block/staff-info',
];

interface EditProps {
	attributes: {
		allowedBlocks?: string[];
		staffSlug?: string;
		staffId?: number;
	};
	setAttributes: (attrs: Partial<EditProps['attributes']>) => void;
	context: {
		postId?: number;
		postType?: string;
	};
	clientId: string;
	isSelected: boolean;
}

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 */
export default function Edit({
	attributes,
	setAttributes,
	context,
	clientId,
}: EditProps) {
	const { allowedBlocks, staffSlug, staffId: savedStaffId } = attributes;
	const { postId, postType } = context;
	const [staffId, setStaffId] = useState<number | null>(savedStaffId ?? null);
	const [isFetchingStaffId, setIsFetchingStaffId] = useState(
		!savedStaffId && !(postId && postType === 'staff')
	);

	useEffect(() => {
		let cancelled = false;

		if (postId && postType === 'staff') {
			setStaffId(postId);
			setIsFetchingStaffId(false);
			return () => {
				cancelled = true;
			};
		}

		setIsFetchingStaffId(true);
		const slugToSearch = staffSlug || 'michael-dimock';

		const fetchStaffId = async () => {
			try {
				const staff = (await apiFetch({
					path: `/wp/v2/staff?slug=${slugToSearch}&_fields=id`,
				})) as Array<{ id?: number }>;

				if (cancelled) {
					return;
				}

				if (
					staff?.length &&
					Object.prototype.hasOwnProperty.call(staff[0], 'id')
				) {
					setStaffId(staff[0].id ?? null);
				} else {
					setStaffId(null);
				}
			} catch {
				if (!cancelled) {
					setStaffId(null);
				}
			} finally {
				if (!cancelled) {
					setIsFetchingStaffId(false);
				}
			}
		};

		fetchStaffId();

		return () => {
			cancelled = true;
		};
	}, [postId, postType, staffSlug]);

	useEntityRecord('postType', 'staff', staffId ?? undefined);

	useEffect(() => {
		const resolvedStaffId = staffId ?? undefined;
		if (savedStaffId !== resolvedStaffId) {
			setAttributes({ staffId: resolvedStaffId });
		}
	}, [staffId, savedStaffId, setAttributes]);

	const blockContexts = useMemo(() => {
		return [
			{
				staffId: staffId ?? undefined,
			},
		];
	}, [staffId]);

	const blockProps = useBlockProps();
	const staffLookupFailed = !isFetchingStaffId && !staffId;

	return (
		<Fragment>
			<Controls {...{ staffId, setAttributes }} />
			{staffLookupFailed && (
				<Warning>
					{__(
						'Staff member not found. Placeholder preview is shown for bound blocks.',
						'prc-staff-bylines'
					)}
				</Warning>
			)}
			<div {...blockProps}>
				<InnerBlocksAsContextTemplate
					{...{
						clientId,
						allowedBlocks: allowedBlocks || ALLOWED_BLOCKS,
						blockContexts,
						isResolving: isFetchingStaffId,
						loadingLabel: __(
							'Loading staff member…',
							'prc-staff-bylines'
						),
					}}
				/>
			</div>
		</Fragment>
	);
}
