import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useEffect, useMemo, useState } from "react";
import {
  Input,
  Notice,
  Select,
  Form,
  FormTable,
  BigButton,
  Submit,
} from "./components";
const getSettings = async () => {
  apiFetch({ path: "/wp/v2/posts" }).then((posts) => {
    console.log(posts);
  });
};

const TeamSettings = (props) => {
  const [team, setTeam] = useState(props.team);
  const teamId = useMemo(() => {
    return team.id;
  }, [props.team]);
  const rolesOptions = [];
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
        onChange={(e) =>
          setTeam({
            ...team,
            account_id: e.target.value,
          })
        }
      />
      <Input
        label={__("TrustedLogin API Key")}
        name={`team-${teamId}[api_key]`}
        value={team.api_key}
        onChange={(e) =>
          setTeam({
            ...team,
            api_key: e.target.value,
          })
        }
      />
      <Input
        label={__("TrustedLogin Private Key")}
        name={`team-${teamId}[private_key]`}
        value={team.private_key}
        onChange={(e) =>
          setTeam({
            ...team,
            private_key: e.target.value,
          })
        }
      />
      <Select
        label={__("What user roles provide support?")}
        help={__(
          "Which users should be able to log into customers’ sites if they have an Access Key?"
        )}
        name={`team-${teamId}[approved_roles]`}
        value={team.approved_roles}
        options={rolesOptions}
        onChange={(e) =>
          setTeam({
            ...team,
            approved_roles: e.target.value,
          })
        }
      />
      <Select
        label={__("Which helpdesk software are you using?")}
        name={`team-${teamId}[helpdesk]`}
        value={team.helpdesk}
        options={helpDeskOptions}
        onChange={(e) =>
          setTeam({
            ...team,
            helpdesk: e.target.value,
          })
        }
      />
    </FormTable>
  );
};

const defaultSettings = {
  isConnected: false,
  teams: [],
};

const emptyTeam = {
  account_id: "",
  private_key: "",
  api_key: "",
  helpdesk: "",
  approved_roles: [],
};

export default function App() {
  const [settings, setSettings] = useState(() => {
    return defaultSettings;
  });

  const addTeam = () => {
    setSettings({
      ...settings,
      teams: [...settings.teams, emptyTeam],
    });
  };

  const canSave = useMemo(() => {
    return settings.teams.length > 0;
  }, [settings.teams]);

  const onSave = (e) => {
    e.preventDefault();
  };
  return (
    <div>
      {!settings.isConnected ? (
        <Notice
          heading={__("Connect your site to the TrustedLogin service.")}
          description={__("Sign up at TrustedLogin.com")}
          link="https://trustedlogin.com"
        />
      ) : null}
      <BigButton
        onClick={addTeam}
        variant={!settings.teams.length ? "primary" : "secondary"}
      >
        {__("Add Team")}
      </BigButton>
      <Form onSubmit={onSave}>
        {settings.teams
          ? settings.teams.map((team) => (
              <TeamSettings team={team} key={team.id} />
            ))
          : null}
        <Submit
          variant={canSave ? "primary" : "secondary"}
          value={__("Save Setting")}
          disabled={!canSave}
        />
      </Form>
    </div>
  );
}
