/**
 * External Dependencies
 */
import { InnerBlocksAsContextTemplate } from '@prc/components';
import { getBlockGapSupportValue } from '@prc/block-utils';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Fragment } from 'react';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal Dependencies
 */
import Controls from './controls';
import useStaffBlockContextProvider from './use-staff-block-context-provider';

const ALLOWED_BLOCKS = ['prc-block/staff-info', 'core/group'];

interface EditProps {
	clientId: string;
	attributes: {
		allowedBlocks?: string[];
		staffType?: Record<string, unknown>;
		researchArea?: Record<string, unknown>;
		style?: Record<string, unknown>;
	};
	setAttributes: (attrs: Partial<EditProps['attributes']>) => void;
}

/**
 * The edit function describes the structure of your block in the context of the
 * editor. This represents what the editor will render when the block is used.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-edit-save/#edit
 */
export default function Edit({ clientId, attributes, setAttributes }: EditProps) {
	const { allowedBlocks } = attributes;

	const {
		blockContexts,
		isResolving,
	} = useStaffBlockContextProvider({
		attributes,
		clientId,
	});

	const blockProps = useBlockProps({
		style: {
			'gap': getBlockGapSupportValue(attributes),
		},
	});

	return (
		<Fragment>
			<Controls
				{...{
					attributes,
					setAttributes,
					clientId,
				}}
			/>
			<div {...blockProps}>
				<InnerBlocksAsContextTemplate {...{
					clientId,
					allowedBlocks: allowedBlocks || ALLOWED_BLOCKS,
					blockContexts,
					isResolving,
					loadingLabel: __('Loading Staff Members...'),
				}}/>
			</div>
		</Fragment>
	);
}
