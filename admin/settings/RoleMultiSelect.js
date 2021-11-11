import Multiselect from 'multiselect-react-dropdown';
import { useMemo } from 'react';
import { FieldTr } from './components';

/**
 * MutliSelect component for WordPress Roles
 */
const RoleMultiSelect = ({
	approvedRoles,
	help,
	label,
	onChange
}) => {

	const rolesOptions = useMemo(() => {
		let tl = (window.tlVendor || {
		  roles: {},
		});
		return Object.keys(tl.roles).map(role => {
			return {
				name: tl.roles[role],
				id: role,
			};
		});

	}, [window.tlVendor]);


	function handleChange(selectedList) {
		onChange(selectedList.map(item => item.id));
	}

	const currentValues = useMemo( () => {
		return approvedRoles.map(value => {
				return rolesOptions.find(item => item.id === value);
		});
	}, [approvedRoles, rolesOptions]);
	return (
		<FieldTr
			label={ label }
			help={ help }
		>

			<Multiselect
				options={rolesOptions} // Options to display in the dropdown
				selectedValues={currentValues} // Preselected value to persist in dropdown
				onSelect={handleChange} // Function will trigger on select event
				onRemove={handleChange} // Function will trigger on remove event
				displayValue="name" // Property name to display in the dropdown options
			/>
		</FieldTr>

	);
}

export default RoleMultiSelect;
