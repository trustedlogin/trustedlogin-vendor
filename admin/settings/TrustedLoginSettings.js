import { Form, Submit, Input, FormTable } from "./components";
import { __ } from "@wordpress/i18n";
import TeamSettings from "./TeamSettings";
import { useMemo } from "react";

/**
 * Trusted Login Settings Form
 */
export default function ({ onSave, settings, canSave, setTeam, setSettings, }) {
  const helpscout = useMemo(() => {
    let defaults = {
      secret: "",
      callback: "",
    };
    return settings.hasOwnProperty("helpscout")
      ? Object.assign(defaults, settings.helpscout)
      : defaults;
  }, [settings]);
  return (
    <Form onSubmit={onSave}>
      {settings.teams
        ? settings.teams.map((team) => (
            <TeamSettings team={team} setTeam={setTeam} key={team.id} />
          ))
        : null}
      <FormTable>
        <Input
          name="helpscout[secret]"
          label={__("Help Scout Secret Key")}
          value={helpscout.secret}
          onChange={(value) => {
            setSettings({
              ...settings,
              helpscout: {
                ...helpscout,
                secret: value,
              },
            });
          }}
        />
        <Input
          name="helpscout[callback]"
          label={__("Help Scout Callback URL	")}
          value={helpscout.callback}
          type="url"
          onChange={(value) => {
            setSettings({
              ...settings,
              helpscout: {
                ...helpscout,
                callback: value,
              },
            });
          }}
        />
      </FormTable>

      <Submit
        onClick={onSave}
        variant={canSave ? "primary" : "secondary"}
        value={__("Save")}
        disabled={!canSave}
      />
    </Form>
  );
}
