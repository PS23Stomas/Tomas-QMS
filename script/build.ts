import { execSync } from "child_process";
import fs from "fs";
import path from "path";

const distDir = path.resolve("dist");

if (fs.existsSync(distDir)) {
  fs.rmSync(distDir, { recursive: true });
}
fs.mkdirSync(distDir, { recursive: true });

import { build } from "esbuild";

await build({
  entryPoints: ["server/index.ts"],
  outfile: "dist/index.cjs",
  platform: "node",
  format: "cjs",
  bundle: true,
  minify: true,
  external: [],
});

console.log("Build completed successfully.");
