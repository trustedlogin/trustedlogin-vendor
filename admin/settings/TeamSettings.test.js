import { render, fireEvent, cleanup, act } from "@testing-library/react";
import TeamSettings from "./TeamSettings";

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
