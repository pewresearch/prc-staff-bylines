/**
 * External Dependencies
 */
import { InnerBlocksAsContextTemplate } from '@prc/components';

/**
 * WordPress Dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Fragment, useEffect, useState, useMemo } from 'react';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal Dependencies
 */
import Controls from './controls';

const ALLOWED_BLOCKS = [
	'core/group',
	'core/paragraph',
	'core/button',
	'prc-block/staff-info',
];

interface EditProps {
	attributes: {
		allowedBlocks?: string[];
		staffSlug?: string;
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
	const { allowedBlocks, staffSlug } = attributes;
	const { postId, postType } = context;
	const [staffId, setStaffId] = useState<number | null>(null);

	useEffect(() => {
		if (postId && postType === 'staff') {
			setStaffId(postId);
		}
		let slugToSearch = '';
		if (staffSlug) {
			slugToSearch = staffSlug;
		} else {
			slugToSearch = 'michael-dimock';
		}
		// If the staffSlug is set, we need to fetch the staff ID from the API.
		const fetchStaffId = async () => {
			await apiFetch({
				path: `/wp/v2/staff?slug=${slugToSearch}&_fields=id`,
			})
				.then((staff: any) => {
					if (
						staff &&
						staff.length &&
						Object.prototype.hasOwnProperty.call(staff[0], 'id')
					) {
						setStaffId(staff[0].id);
					} else {
						setStaffId(null);
					}
				})
				.catch(() => {
					setStaffId(null);
				});
		};

		fetchStaffId();
	}, [postId, postType, staffSlug]);

	const blockContexts = useMemo(() => {
		return [
			{
				staffId,
			},
		];
	}, [staffId]);

	const blockProps = useBlockProps();

	// To avoid flicker when switching active block contexts, a preview is rendered
	// for each block context, but the preview for the active block context is hidden.
	// This ensures that when it is displayed again, the cached rendering of the
	// block preview is used, instead of having to re-render the preview from scratch.
	return (
		<Fragment>
			<Controls {...{ staffId, setAttributes }} />
			<div {...blockProps}>
				<InnerBlocksAsContextTemplate
					{...{
						clientId,
						allowedBlocks: allowedBlocks || ALLOWED_BLOCKS,
						blockContexts,
						isResolving: !staffId,
					}}
				/>
			</div>
		</Fragment>
	);
}
