import {
  Input,
  Notice,
  Select,
  Form,
  FormTable,
  BigButton,
  Submit,
} from "./components";
import { render, fireEvent, cleanup } from "@testing-library/react";

describe("Input", () => {
  afterEach(cleanup);

  it("renders", () => {
    const onChange = jest.fn();
    const { container } = render(
      <FormTable>
        <Input
          label={"Label"}
          name={"name"}
          value={"value"}
          onChange={onChange}
        />
      </FormTable>
    );

    expect(container).toMatchSnapshot();
  });
  it("changes", () => {
    const onChange = jest.fn();
    const { getByLabelText } = render(
      <FormTable>
        <Input
          label={"Label"}
          name={"name"}
          value={"value"}
          onChange={onChange}
        />
      </FormTable>
    );
    fireEvent.change(getByLabelText("Label"), {
      target: { value: "New Value" },
    });
    expect(onChange).toHaveBeenCalledTimes(1);
    expect(onChange).toHaveBeenCalledWith("New Value");
  });
});

describe("Select", () => {
  afterEach(cleanup);

  it("renders", () => {
    const onChange = jest.fn();
    const { container } = render(
      <FormTable>
        <Select
          label={"Label"}
          name={"name"}
          value={"value"}
          onChange={onChange}
          options={[
            { value: "one", label: "One" },
            { value: "two", label: "Two" },
          ]}
        />
      </FormTable>
    );
    expect(container).toMatchSnapshot();
  });
  it("changes", () => {
    const onChange = jest.fn();
    const { container } = render(
      <FormTable>
        <Select
          label={"Label"}
          name={"name"}
          value={"value"}
          onChange={onChange}
          options={[
            { value: "one", label: "One" },
            { value: "two", label: "Two" },
          ]}
        />
      </FormTable>
    );
  });
});

describe("BigButton", () => {
  afterEach(cleanup);

  it("renders", () => {
    const { container } = render(
      <BigButton onClick={jest.fn()}>Text</BigButton>
    );
    expect(container).toMatchSnapshot();
  });
  it("click", () => {});
});

describe("Form", () => {
  afterEach(cleanup);

  it("renders", () => {
    const { container } = render(
      <Form>
        <Submit name="s" disabled={false} value={"Save"} />
      </Form>
    );
    expect(container).toMatchSnapshot();
  });
  it("renders with title", () => {
    const { container } = render(
      <Form title="Spaces">
        <Submit name="s" disabled={false} value={"Save"} />
      </Form>
    );
    expect(container).toMatchSnapshot();
  });
  it("submits", () => {
    const onSubmit = jest.fn();
    const { getByTitle } = render(
      <Form title="Spaces" onSubmit={onSubmit}>
        <Submit name="s" disabled={false} value={"Save"} />
      </Form>
    );
    fireEvent.submit(getByTitle("Spaces"));
  });
});

describe("FormTable", () => {
  afterEach(cleanup);

  it("renders", () => {
    const { container } = render(
      <Form>
        <FormTable>
          <tr>
            <td>1</td>
          </tr>
        </FormTable>
      </Form>
    );
    expect(container).toMatchSnapshot();
  });

  it("renders with title", () => {
    const { container } = render(
      <Form>
        <FormTable title="One">
          <tr>
            <td>1</td>
          </tr>
        </FormTable>
      </Form>
    );
    expect(container).toMatchSnapshot();
  });
});

describe("Submit", () => {
  afterEach(cleanup);

  it("renders", () => {
    const { container } = render(<Submit value={"Save"} name="r" />);
    expect(container).toMatchSnapshot();
  });
});
