import { Form, Submit } from "./components";
import { __ } from "@wordpress/i18n";
import TeamSettings from "./TeamSettings";

/**
 * Trusted Login Settings Form
 */
export default function ({ onSave, settings, canSave, setTeam }) {
  return (
    <Form onSubmit={onSave}>
      {settings.teams
        ? settings.teams.map((team) => (
            <TeamSettings team={team} setTeam={setTeam} key={team.id} />
          ))
        : null}
      <Submit
        onClick={onSave}
        variant={canSave ? "primary" : "secondary"}
        value={__("Save")}
        disabled={!canSave}
      />
    </Form>
  );
}
