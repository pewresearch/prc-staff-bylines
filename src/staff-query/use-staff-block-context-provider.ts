/**
 * WordPress Dependencies
 */
import { useEntityRecords } from '@wordpress/core-data';
import {
	useMemo,
	useEffect,
} from '@wordpress/element';

interface StaffInfo {
	staffName: string;
	staffJobTitle: string;
	staffImage: false | Record<string, unknown>;
	staffTwitter: string | null;
	staffExpertise: Array<Record<string, unknown>>;
	staffBio: string;
	staffMiniBio: string;
	staffLink: string | false;
}

interface StaffPostRecord {
	staffInfo?: StaffInfo;
	[key: string]: unknown;
}

const placeholderStaff: StaffInfo[] = [
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
	},
];

interface UseStaffBlockContextProviderProps {
	attributes: {
		staffType?: { id?: number; slug?: string; name?: string };
		researchArea?: { id?: number; slug?: string; name?: string };
	};
	clientId: string;
}

interface UseStaffBlockContextProviderReturn {
	blockContexts: StaffInfo[];
	isResolving: boolean;
	hasResolved: boolean;
}

export default function useStaffBlockContextProvider({
	attributes,
	clientId,
}: UseStaffBlockContextProviderProps): UseStaffBlockContextProviderReturn {
	const { staffType, researchArea } = attributes;
	const staffTypeId = staffType ? staffType.id : null;
	const args: Record<string, unknown> = {
		per_page: 100,
		orderby: 'last_name',
		order: 'asc',
		fields: 'id,slug,title,type,name,staffInfo',
	};
	if (staffTypeId) {
		args['staff-type'] = staffTypeId;
	}
	const researchAreaId = researchArea ? researchArea.id : null;
	if (researchAreaId) {
		args['research-area'] = researchAreaId;
	}

	const { records, isResolving, hasResolved } = useEntityRecords(
		'postType',
		'staff',
		args
	);

	const blockContexts = useMemo(() => {
		if (!records || 0 === records.length) {
			return placeholderStaff;
		}
		return (records as StaffPostRecord[])
			?.map((staffPost) => {
				return staffPost?.staffInfo || ({} as StaffInfo);
			});
	}, [records]);

	useEffect(() => {
		// Debug logging for staff data updates
	}, [clientId, records, isResolving, staffType, researchArea]);

	return {
		blockContexts,
		isResolving,
		hasResolved: hasResolved ?? false,
	};
}
