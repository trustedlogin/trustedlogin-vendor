import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useEffect, useMemo, useState } from "react";
const getSettings = async () => {
  apiFetch({ path: "/wp/v2/posts" }).then((posts) => {
    console.log(posts);
  });
};

const Notice = ({ heading, link, description, type }) => (
  <div className={`notice notice-success`}>
    <h2>{heading}</h2>
    <h3 className="description">
      <a href={link} rel="noopener" target="_blank">
        {description}
      </a>
    </h3>
  </div>
);

const Form = ({ children, onSubmit }) => {
  return <form onSubmit={onSubmit}>{children}</form>;
};

const FormTable = ({ children, title }) => {
  return (
    <>
      {title ? <h2 className="title">{title}</h2> : null}
      <table class="form-table" role="presentation">
        <tbody>{children}</tbody>
      </table>
    </>
  );
};

const Select = ({ name, label, value, options, help, onChange }) => {
  const attrs = useMemo(() => {
    let a = {
      name,
      label,
      value,
      className: "postform",
    };
    if (help) {
      a["aria-describedby"] = `${name}-description`;
    }
  }, [name, label, value, help]);
  return (
    <tr>
      <th scope="row">
        <label for={name}>{label}</label>
        {help ? (
          <span id={`${name}-description`} className="description">
            {help}
          </span>
        ) : null}
      </th>
      <td data-children-count="1">
        <select {...attrs} onChange={onChange}>
          {options.map(({ value, label }) => (
            <option className="level-0" value={value} key={value}>
              {label}
            </option>
          ))}
        </select>
      </td>
    </tr>
  );
};

const Input = ({ name, label, value, type, onChange }) => {
  return (
    <tr>
      <th scope="row">
        <label for={name}>{label}</label>
      </th>
      <td>
        <input
          name={name}
          type={type ? type : "input"}
          id={name}
          value={value}
          className="regular-text ltr"
          onChange={onChange}
        />
      </td>
    </tr>
  );
};

const SaveButton = ({ name, variant, value, disabled }) => (
  <p class="submit">
    <input
      type="submit"
      name={name}
      id={name}
      className={`button button-${variant ? variant : "secondary"} button-hero`}
      value={value}
      disabled={disabled}
    />
  </p>
);

const BigButton = ({ children, variant, onClick }) => (
  <p class="big-button">
    <button
      className={`button button-${variant ? variant : "secondary"} button-hero`}
      onClick={onClick}
    >
      {children}
    </button>
  </p>
);
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
        <SaveButton
          variant={canSave ? "primary" : "secondary"}
          value={__("Save Setting")}
          disabled={!canSave}
        />
      </Form>
    </div>
  );
}
