const defaultConfig = require("@wordpress/scripts/config/webpack.config");
const path = require("path");
const isProduction = "production" === process.env.NODE_ENV;

let entry = {};
["settings"].forEach((entryPoint) => {
  entry[`admin-page-${entryPoint}`] = path.resolve(
    process.cwd(),
    `admin/${entryPoint}/index.js`
  );
});

module.exports = {
  mode: isProduction ? "production" : "development",
  ...defaultConfig,
  module: {
    ...defaultConfig.module,
    rules: [
      ...defaultConfig.module.rules,
      {
        test: /\.css$/,
        use: ["style-loader", "css-loader"],
      },
    ],
  },
  entry,
  output: {
    filename: "[name].js",
    path: path.join(__dirname, "./build"),
  },
};
