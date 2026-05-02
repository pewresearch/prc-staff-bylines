/* eslint-disable camelcase */

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Icon, Spinner } from '@wordpress/components';
import { dragHandle } from '@wordpress/icons';
import { useEntityRecord } from '@wordpress/core-data';

const ICON_SIZE = 20;

function BylineItem({ value, onRemove, lastItem = false }) {
	const { termId } = value;
	const numericTermId = Number(termId);
	const isValidTermId =
		termId !== false && Number.isFinite(numericTermId) && numericTermId > 0;

	const { record, isResolving, hasResolved } = useEntityRecord(
		'taxonomy',
		'bylines',
		isValidTermId ? numericTermId : 0,
		{ enabled: isValidTermId }
	);

	const staffInfo = record?.staffInfo;
	const bylineName = staffInfo?.staffName ?? '';
	const bylineJobTitle = staffInfo?.staffJobTitle ?? '';

	const loading = isValidTermId && (isResolving || !hasResolved);

	if (!isValidTermId) {
		return (
			<div
				className="prc-byline-item"
				style={{
					background: 'white',
					paddingBottom: '0.5em',
					marginBottom: '0.5em',
					borderBottom: lastItem ? 'none' : '1px solid #EAEAEA',
				}}
			>
				<p style={{ color: 'red' }}>Term not found</p>
			</div>
		);
	}

	return (
		<div
			className="prc-byline-item"
			style={{
				background: 'white',
				paddingBottom: '0.5em',
				marginBottom: '0.5em',
				borderBottom: lastItem ? 'none' : '1px solid #EAEAEA',
			}}
		>
			<div
				style={{
					display: 'flex',
					flexDirection: 'row',
					width: '100%',
					alignItems: 'center',
				}}
			>
				<div style={{ display: 'flex', cursor: 'grab' }}>
					<Icon icon={dragHandle} size={ICON_SIZE} />
				</div>
				<div
					style={{
						display: 'flex',
						flexGrow: '1',
						paddingLeft: '1em',
						cursor: 'grab',
					}}
				>
					{loading ? (
						<div
							style={{
								display: 'flex',
								alignItems: 'center',
							}}
						>
							<Spinner /> <span>Loading...</span>
						</div>
					) : (
						<span>
							<strong>{`${bylineName}`}</strong>
							{!!bylineJobTitle && 0 < bylineJobTitle.length && (
								<span
									style={{
										display: 'block',
										fontSize: '0.8em',
										color: '#666',
									}}
								>
									{`${bylineJobTitle}`}
								</span>
							)}
						</span>
					)}
				</div>
				<div style={{ display: 'flex' }}>
					<Button
						icon="no-alt"
						label={__('Remove byline', 'prc-platform-core')}
						onClick={onRemove}
						size="small"
					/>
				</div>
			</div>
		</div>
	);
}

export default BylineItem;
