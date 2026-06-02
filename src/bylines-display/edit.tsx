/**
 * External Dependencies
 */
import classNames from 'classnames';

/**
 * WordPress Dependencies
 */
import { Fragment, useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal Dependencies
 */

const DEFAULT_BYLINES = ['Person A', 'Person B', 'Person C'];

interface EditProps {
	attributes: {
		prefix: string;
		className?: string;
	};
	setAttributes: (attrs: Partial<EditProps['attributes']>) => void;
	context: {
		postId?: number;
	};
	clientId: string;
	isSelected: boolean;
	__unstableLayoutClassNames: string;
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
	__unstableLayoutClassNames: layoutClassNames,
}: EditProps) {
	const { postId } = context;
	const { prefix, className } = attributes;

	const [bylines, setBylines] = useState<string[]>(DEFAULT_BYLINES);
	const bylineRows = useSelect(
		(select: any) =>
			select('core/editor').getEditedPostAttribute('bylinesOrdered'),
		[]
	);
	const blockProps = useBlockProps({
		className: classNames(className, layoutClassNames),
	});

	useEffect(() => {
		const getBylineNameAsync = (termId: number): Promise<string> =>
			new Promise((resolve) => {
				apiFetch({
					path: `/wp/v2/bylines/${termId}`,
				})
					.then((byline: any) => {
						const { name } = byline;
						return resolve(name);
					})
					.catch(() => {
						resolve('');
					});
			});

		const termIds = Array.isArray(bylineRows)
			? bylineRows
					.map((row: { termId?: number }) => Number(row?.termId))
					.filter(
						(termId: number) =>
							Number.isFinite(termId) && termId > 0
					)
			: [];

		if (termIds.length > 0) {
			Promise.all(
				termIds.map((termId: number) => getBylineNameAsync(termId))
			).then((data) => {
				setBylines(data.filter((name) => name !== ''));
			});
		} else {
			setBylines([...DEFAULT_BYLINES]);
		}
	}, [bylineRows]);

	return (
		<div {...blockProps}>
			<RichText
				tagName="span"
				placeholder="By"
				className="prc-block-bylines__prefix"
				value={prefix}
				onChange={(value: string) => setAttributes({ prefix: value })}
				allowedFormats={[]}
				style={{
					marginRight: '4px',
				}}
			/>

			{bylines.map((b, index) => {
				const total = bylines.length;
				const name = b;
				let r: JSX.Element;
				let Sep = (): JSX.Element | null => null;
				if (1 < total && index + 1 === total) {
					Sep = () => (
						<span className="prc-platform-staff-bylines__separator">
							{' '}
							and{' '}
						</span>
					);
				} else if (1 < total && index !== 0) {
					Sep = () => (
						<span className="prc-platform-staff-bylines__separator">
							,{' '}
						</span>
					);
				}
				if (index === 0 && !postId) {
					r = <span role="link">{name}</span>;
				} else {
					r = (
						<Fragment>
							<Sep />
							<span>{name}</span>
						</Fragment>
					);
				}
				return r;
			})}
		</div>
	);
}
