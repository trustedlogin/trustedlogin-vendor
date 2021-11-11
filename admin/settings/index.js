import React from "react";
import { render } from "@wordpress/element";
import App from "./App";
import { getSettings, updateSettings } from "./api";
import './style.css';
window.addEventListener("load", function () {
  render(
    <App getSettings={getSettings} updateSettings={updateSettings} />,
    document.getElementById("tl-vendor-settings-app")
  );
});
