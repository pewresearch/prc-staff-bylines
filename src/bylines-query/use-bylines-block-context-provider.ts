/**
 * WordPress Dependencies
 */
import { useMemo, useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { useEntityProp } from '@wordpress/core-data';

interface StaffInfo {
	staffName: string;
	staffJobTitle: string;
	staffImage: false | Record<string, unknown>;
	staffTwitter: string | null;
	staffExpertise: Array<Record<string, unknown>>;
	staffBio: string;
	staffMiniBio: string;
	staffLink: string | false;
	staffJobTitleExtended: string;
	staffBioShort: string;
}

const placeholderBylines: StaffInfo[] = [
	{
		staffName: 'John Doe',
		staffJobTitle: 'Associate Researcher',
		staffImage: false,
		staffTwitter: 'johndoe',
		staffExpertise: [],
		staffBio:
			'Cupidatat minim amet labore esse adipisicing. Exercitation duis culpa do incididunt cillum Lorem dolor. Et irure non veniam amet deserunt officia aute do qui. Voluptate anim in duis.',
		staffMiniBio:
			'Cillum dolor nisi exercitation nostrud anim non ea deserunt deserunt ut tempor ut eiusmod',
		staffLink: false,
		staffJobTitleExtended: 'an Associate Researcher focusing on XYZ',
		staffBioShort:
			'<a href="#">John Doe</a> is an Associate Researcher focusing on XYZ.',
	},
	{
		staffName: 'Jane Doe',
		staffJobTitle: 'VP of Research Methods',
		staffImage: false,
		staffTwitter: 'janedoe',
		staffExpertise: [],
		staffBio:
			'Proident sit magna ullamco commodo esse duis labore. Consequat sint dolor incididunt id dolor laboris duis nulla pariatur. Consequat pariatur et ex. Dolore velit non deserunt. Dolore esse commodo deserunt magna quis irure. Ipsum id occaecat ea labore ipsum et proident culpa ullamco amet pariatur consequat elit ullamco mollit.',
		staffMiniBio:
			'Magna reprehenderit cupidatat magna elit do excepteur minim velit ex culpa nostrud voluptate laborum enim nulla amet laborum occaecat incididunt',
		staffLink: false,
		staffJobTitleExtended: 'an Associate Researcher focusing on XYZ',
		staffBioShort:
			'<a href="#">Jane Doe</a> is VP of Research Methods focusing on XYZ.',
	},
];

const getBylineNameAsync = (termId: number): Promise<StaffInfo> =>
	new Promise((resolve, reject) => {
		apiFetch({
			path: `/wp/v2/bylines/${termId}`,
		})
			.then((byline: any) => {
				const { staffInfo } = byline;
				return resolve(staffInfo);
			})
			.catch((err: Error) => {
				return reject(err);
			});
	});

async function getBlockBylineContexts(
	bylineTermIds: number[] | undefined
): Promise<StaffInfo[]> {
	if (!bylineTermIds) {
		return placeholderBylines;
	}
	return await Promise.all(
		bylineTermIds.map((termId) => getBylineNameAsync(termId))
	);
}

interface UseBylinesContextProviderProps {
	postId?: number;
	postType?: string;
}

interface UseBylinesContextProviderReturn {
	isResolving: boolean;
	bylinesContext: StaffInfo[];
}

/**
 * Returns an object containing the bylines context and a contextId.
 * The bylines context is an array of staffInfo objects matching each bylineTermId passed in.
 * The contextId is a hash of the first staffInfo object in the bylines context array.
 */
export default function useBylinesContextProvider({
	postType,
}: UseBylinesContextProviderProps): UseBylinesContextProviderReturn {
	const [bylineTermIds] = useEntityProp(
		'postType',
		postType ?? 'post',
		'bylines'
	);
	const [bylinesContext, _setBylines] = useState<StaffInfo[]>([]);
	const isResolving = useMemo(
		() => null === bylinesContext,
		[bylinesContext]
	);

	useEffect(() => {
		getBlockBylineContexts(bylineTermIds as number[] | undefined).then(
			(bylines) => {
				_setBylines(bylines);
			}
		);
	}, [bylineTermIds]);

	return {
		isResolving,
		bylinesContext,
	};
}
