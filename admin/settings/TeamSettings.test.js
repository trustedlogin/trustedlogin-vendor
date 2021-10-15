import { render, fireEvent, cleanup, act } from "@testing-library/react";
import TeamSettings from "./TeamSettings";
import TrustedLoginSettings from "./TrustedLoginSettings";
describe("TeamSettings", () => {
  afterEach(cleanup);
  let team = {
    id: 0,
    account_id: "7",
    private_key: "pk_1",
    api_key: "ak_1",
    helpdesk: "helpscout",
    approved_roles: ["editor"],
  };
  it("renders", () => {
    const { container } = render(<TeamSettings team={team} />);
    expect(container).toMatchSnapshot();
  });

  it("updates", () => {
    const setTeam = jest.fn();
    const { container, getByLabelText } = render(
      <TeamSettings team={team} setTeam={setTeam} />
    );
    //Change an input
    act(() => {
      fireEvent.change(getByLabelText("TrustedLogin Account ID"), {
        target: { value: "42" },
      });
    });

    expect(setTeam).toBeCalledWith({
      ...team,
      account_id: "42",
    });
  });
});
describe("TrustedLoginSettings", () => {
  afterEach(cleanup);
  let settings = {
    isConnected: false,
    teams: [
      {
        id: 0,
        account_id: "7",
        private_key: "pk_1",
        api_key: "ak_1",
        helpdesk: "helpscout",
        approved_roles: ["editor"],
      },
    ],
    helpscout: {
      callback: "",
      secret: "",
    },
  };
  it("renders with save enabled", () => {
    const setTeam = jest.fn();
    const onSave = jest.fn();

    const { container } = render(
      <TrustedLoginSettings
        settings={settings}
        setTeam={setTeam}
        canSave={false}
        onSave={onSave}
      />
    );
    expect(container).toMatchSnapshot();
  });
  it("renders with save disabled", () => {
    const setTeam = jest.fn();
    const onSave = jest.fn();

    const { container } = render(
      <TrustedLoginSettings
        settings={settings}
        setTeam={setTeam}
        canSave={false}
        onSave={onSave}
      />
    );
    expect(container).toMatchSnapshot();
  });

  it("updates team settings", () => {
    const setTeam = jest.fn();
    const onSave = jest.fn();

    const { getByLabelText } = render(
      <TrustedLoginSettings
        onSave={onSave}
        settings={settings}
        setTeam={setTeam}
        canSave={true}
      />
    );
    //Change an input
    act(() => {
      fireEvent.change(getByLabelText("TrustedLogin Account ID"), {
        target: { value: "42" },
      });
    });

    expect(setTeam).toBeCalledWith({
      ...settings.teams[0],
      account_id: "42",
    });
  });

  it("updates helpscout settings", () => {
    const setTeam = jest.fn();
    const onSave = jest.fn();
    const setSettings = jest.fn();
    const { getByLabelText } = render(
      <TrustedLoginSettings
        onSave={onSave}
        settings={settings}
        setTeam={setTeam}
        canSave={true}
        setSettings={setSettings}
      />
    );
    //Change secret
    act(() => {
      fireEvent.change(getByLabelText("Help Scout Secret Key"), {
        target: { value: "42" },
      });
    });

    expect(setSettings).toBeCalledWith({
      ...settings,
      helpscout: {
        ...settings.helpscout,
        secret: "42",
      },
    });

    //Change callback
    act(() => {
      fireEvent.change(getByLabelText("Help Scout Callback URL"), {
        target: { value: "https://happy.dog" },
      });
    });

    expect(setSettings).toBeCalledWith({
      ...settings,
      helpscout: {
        ...settings.helpscout,
        callback: "https://happy.dog",
      },
    });
  });
});
