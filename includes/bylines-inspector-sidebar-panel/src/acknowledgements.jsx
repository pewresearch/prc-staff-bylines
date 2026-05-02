/**
 * External Dependencies
 */
import { WPEntitySearch } from '@prc/components';
import { List } from 'react-movable';
import styled from '@emotion/styled';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCallback } from '@wordpress/element';
import {
	__experimentalVStack as VStack,
	PanelRow,
} from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { randomId } from './utils';
import { useBylines } from './context';
import BylineItem from './byline-item';

const SearchContainer = styled.div`
	width: 100%;
	display: block;
	& > div:first-of-type {
		width: 100%;
	}
`;

const ListWrapper = styled.div`
	width: 100%;
	padding-top: 1em;
`;

function Acknowledgements() {
	const { acknowledgementItems, reorder, remove, append } = useBylines();

	const handleSelectAcknowledgement = useCallback(
		(entity) => {
			append(randomId(), entity.entityId, false);
		},
		[append]
	);

	return (
		<PanelRow>
			<VStack spacing="2">
				<p>
					{__(
						`Acknowledgements never appear directly on the post. Staff associated with this post will have this post listed on their staff bio page.`,
						'prc-platform-core'
					)}
				</p>
				<SearchContainer>
					<WPEntitySearch
						placeholder="Add new acknowledgement..."
						entityType="taxonomy"
						entitySubType="bylines"
						onSelect={handleSelectAcknowledgement}
						clearOnSelect={true}
						showUrl={false}
						showType={false}
					>
						<ListWrapper>
							<List
								lockVertically
								values={acknowledgementItems}
								onChange={({ oldIndex, newIndex }) =>
									reorder(oldIndex, newIndex, false)
								}
								renderList={({ children, props }) => (
									<div {...props}>{children}</div>
								)}
								renderItem={({ value, props, index }) => (
									<div {...props}>
										<BylineItem
											key={value.key}
											value={value}
											onRemove={() => {
												remove(index, false);
											}}
											lastItem={
												index ===
												acknowledgementItems.length - 1
											}
										/>
									</div>
								)}
							/>
						</ListWrapper>
					</WPEntitySearch>
				</SearchContainer>
			</VStack>
		</PanelRow>
	);
}

export default Acknowledgements;
