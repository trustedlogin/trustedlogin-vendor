import { __ } from "@wordpress/i18n";
import { useMemo, useState, useEffect } from "react";
import { Notice, BigButton } from "./components";
import TrustedLoginSettings from "./TrustedLoginSettings";

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

export default function App({ getSettings, updateSettings }) {
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
    updateSettings({ teams: settings.teams });
  };

  useEffect(() => {
    getSettings().then(({ teams }) => {
      setSettings({
        ...settings,
        teams,
      });
    });
  }, [getSettings]);
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
        setSettings={setSettings}
        setTeam={setTeam}
        canSave={canSave}
        onSave={onSave}
      />
    </div>
  );
}
