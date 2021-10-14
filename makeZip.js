var fs = require("fs");
var archiver = require("archiver");

var output = fs.createWriteStream("trusted-login.zip");
var archive = archiver("zip");

console.log("Zipping!");
output.on("close", function () {
  console.log("Zipped!");
  console.log(archive.pointer() + " total bytes");
});

archive.on("error", function (err) {
  throw err;
});

archive.pipe(output);

[
  "admin",
  "includes",
  "helpdesk",
  "vendor",
  "build",
  "languages",
  "license-generators",
].forEach((dir) => {
  if (fs.existsSync(`${__dirname}/${dir}`)) {
    archive.directory(`${dir}/`, dir);
  }
});

["trusted-login.php", "readme.txt"].forEach((name) => {
  archive.append(fs.createReadStream(`${__dirname}/${name}`), {
    name,
  });
});

archive.finalize();
