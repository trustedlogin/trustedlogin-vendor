import React from "react";
import { render } from "@wordpress/element";
import App from "./App";
window.addEventListener("load", function () {
  render(<App />, document.getElementById("settings"));
});
