import React from "react";
import ReactDOM from "react-dom";
import App from "./App";
it("renders without crashing", () => {
  const div = document.createElement("div");
  const getSettings = async () => {
    return new Promise((resolve) => {
      setTimeout(() => {
        resolve({ teams: [] });
      }, 1000);
    });
  };
  ReactDOM.render(
    <App getSettings={getSettings} updateSettings={jest.fn()} />,
    div
  );
  ReactDOM.unmountComponentAtNode(div);
});
