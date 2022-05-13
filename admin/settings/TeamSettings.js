import { useMemo, useState } from "react";
import { __ } from "@wordpress/i18n";
import { FormTable, Input, Select } from "./components";
import RoleMultiSelect from "./RoleMultiSelect";

/**
 * Settings for one single team
 */
const TeamSettings = ({ team, setTeam }) => {
  const teamId = useMemo(() => {
    return team.id;
  }, [team]);


  const helpDeskOptions = [
    {
      label: __("Helpscout"),
      value: "helpscout",
    },
  ];
  return (
    <FormTable title={__("Team")}>
      <Input
        label={__("TrustedLogin Account ID")}
        name={`team-${teamId}[account_id]`}
        value={team.account_id}
        onChange={(value) =>
          setTeam({
            ...team,
            account_id: value,
          })
        }
      />
      <Input
        label={__("TrustedLogin API Key")}
        name={`team-${teamId}[api_key]`}
        value={team.api_key}
        onChange={(value) =>
          setTeam({
            ...team,
            api_key: value,
          })
        }
      />
      <Input
        label={__("TrustedLogin Private Key")}
        name={`team-${teamId}[private_key]`}
        value={team.private_key}
        onChange={(value) =>
          setTeam({
            ...team,
            private_key: value,
          })
        }
      />
      <RoleMultiSelect
        label={__("What user roles provide support?")}
        help={__(
          "Which users should be able to log into customers’ sites if they have an Access Key?"
        )}
        name={`team-${teamId}[approved_roles]`}
        approvedRoles={team.approved_roles}
        onChange={(value) =>
          setTeam({
            ...team,
            approved_roles: value,
          })
        }
      />
      <Select
        label={__("Which helpdesk software are you using?")}
        name={`team-${teamId}[helpdesk]`}
        value={team.helpdesk}
        options={helpDeskOptions}
        onChange={(value) =>
          setTeam({
            ...team,
            helpdesk: value,
          })
        }
      />
    </FormTable>
  );
};

export default TeamSettings;
