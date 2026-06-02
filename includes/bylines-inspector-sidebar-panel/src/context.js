/* eslint-disable camelcase */

/**
 * WordPress Dependencies
 */
import {
	useContext,
	createContext,
	useRef,
	useCallback,
} from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

const bylinesContext = createContext();

const useProvideBylines = () => {
	const { editPost } = useDispatch('core/editor');

	const bylinesOrdered = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('bylinesOrdered'),
		[]
	);
	const acknowledgementsOrdered = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute(
				'acknowledgementsOrdered'
			),
		[]
	);
	const rawMeta = useSelect(
		(select) => select('core/editor').getEditedPostAttribute('meta'),
		[]
	);
	const meta = rawMeta ?? {};

	const bylinesRef = useRef(bylinesOrdered);
	bylinesRef.current = bylinesOrdered;
	const acksRef = useRef(acknowledgementsOrdered);
	acksRef.current = acknowledgementsOrdered;
	const metaRef = useRef(meta);
	metaRef.current = meta;

	const reorder = useCallback(
		(oldIndex, newIndex, isBylines = true) => {
			const currentBylines = Array.isArray(bylinesRef.current)
				? [...bylinesRef.current]
				: [];
			const currentAcks = Array.isArray(acksRef.current)
				? [...acksRef.current]
				: [];
			const source = isBylines ? currentBylines : currentAcks;
			const item = source[oldIndex];
			source.splice(oldIndex, 1);
			source.splice(newIndex, 0, item);

			if (isBylines) {
				editPost({ bylinesOrdered: source });
			} else {
				editPost({ acknowledgementsOrdered: source });
			}
		},
		[editPost]
	);

	const append = useCallback(
		(key, termId, isBylines = true) => {
			const currentBylines = Array.isArray(bylinesRef.current)
				? [...bylinesRef.current]
				: [];
			const currentAcks = Array.isArray(acksRef.current)
				? [...acksRef.current]
				: [];
			if (isBylines) {
				const next = [...currentBylines, { key, termId }];
				editPost({ bylinesOrdered: next });
			} else {
				const next = [...currentAcks, { key, termId }];
				editPost({ acknowledgementsOrdered: next });
			}
		},
		[editPost]
	);

	const remove = useCallback(
		(index, isBylines = true) => {
			const currentBylines = Array.isArray(bylinesRef.current)
				? [...bylinesRef.current]
				: [];
			const currentAcks = Array.isArray(acksRef.current)
				? [...acksRef.current]
				: [];
			if (isBylines) {
				const next = [...currentBylines];
				next.splice(index, 1);
				editPost({ bylinesOrdered: next });
			} else {
				const next = [...currentAcks];
				next.splice(index, 1);
				editPost({ acknowledgementsOrdered: next });
			}
		},
		[editPost]
	);

	const updateItem = useCallback(
		(index, key, value, isBylines = true) => {
			const currentBylines = Array.isArray(bylinesRef.current)
				? [...bylinesRef.current]
				: [];
			const currentAcks = Array.isArray(acksRef.current)
				? [...acksRef.current]
				: [];
			if (isBylines) {
				const next = [...currentBylines];
				next[index] = { ...next[index], [key]: value };
				editPost({ bylinesOrdered: next });
			} else {
				const next = [...currentAcks];
				next[index] = { ...next[index], [key]: value };
				editPost({ acknowledgementsOrdered: next });
			}
		},
		[editPost]
	);

	const toggleBylinesDisplay = useCallback(() => {
		const m = metaRef.current || {};
		const current = m.displayBylines ?? true;
		// Only patch displayBylines — spreading full meta reintroduces stale bylines/acks rows.
		editPost({
			meta: {
				displayBylines: !current,
			},
		});
	}, [editPost]);

	if (undefined === rawMeta) {
		// eslint-disable-next-line no-console -- warn developers when meta cannot be loaded
		console.warn(
			'Bylines will not work correctly until post meta can be loaded, ensure this post type supports `custom-fields`.'
		);
		return {
			displayBylines: false,
			bylineItems: [],
			acknowledgementItems: [],
			reorder: null,
			append: null,
			remove: null,
			updateItem: null,
			toggleBylinesDisplay: null,
		};
	}

	const displayBylines = meta.displayBylines ?? true;
	const bylineItems = Array.isArray(bylinesOrdered) ? bylinesOrdered : [];
	const acknowledgementItems = Array.isArray(acknowledgementsOrdered)
		? acknowledgementsOrdered
		: [];

	return {
		displayBylines,
		bylineItems,
		acknowledgementItems,
		reorder,
		append,
		remove,
		updateItem,
		toggleBylinesDisplay,
	};
};

// Hook for child components to get the context object ...
// ... and re-render when it changes.
const useBylines = () => useContext(bylinesContext);

// Provider component that wraps the facets app, you must hydrate the bylines with data from post meta.
// Available to any child component that calls useBylines()
function ProvideBylines({ children }) {
	const provider = useProvideBylines();
	return (
		<bylinesContext.Provider value={provider}>
			{children}
		</bylinesContext.Provider>
	);
}

export { ProvideBylines, useBylines };
export default ProvideBylines;
