import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useMemo, useState } from "react";
import { Notice, BigButton } from "./components";
import TrustedLoginSettings from "./TrustedLoginSettings";
const getSettings = async () => {
  apiFetch({ path: "/wp/v2/posts" }).then((posts) => {
    console.log(posts);
  });
};

const defaultSettings = {
  isConnected: false,
  teams: [],
};

const addEmptyTeam = (teams) => {
  return [
    ...teams,
    {
      id: teams.length + 1,
      account_id: "",
      private_key: "",
      api_key: "",
      helpdesk: "",
      approved_roles: [],
    },
  ];
};

export default function App() {
  const [settings, setSettings] = useState(() => {
    return defaultSettings;
  });

  const addTeam = () => {
    setSettings({
      ...settings,
      teams: addEmptyTeam(settings.teams),
    });
  };

  const setTeam = (team) => {
    setSettings({
      ...settings,
      teams: settings.teams.map((t) => {
        if (t.id === team.id) {
          return team;
        }
        return t;
      }),
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
      <TrustedLoginSettings
        settings={settings}
        setTeam={setTeam}
        canSave={canSave}
      />
    </div>
  );
}
