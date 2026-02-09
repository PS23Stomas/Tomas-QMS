import { exec } from "child_process";

const phpProcess = exec(
  "php -S 0.0.0.0:5000 -t public public/router.php",
  { cwd: process.cwd() }
);

phpProcess.stdout?.pipe(process.stdout);
phpProcess.stderr?.pipe(process.stderr);

phpProcess.on("exit", (code) => {
  process.exit(code ?? 1);
});
