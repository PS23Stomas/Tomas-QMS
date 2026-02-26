import { exec, ChildProcess } from "child_process";

function startPhp(): ChildProcess {
  const phpProcess = exec(
    "php -S 0.0.0.0:5000 -t public public/router.php",
    { cwd: process.cwd() }
  );

  phpProcess.stdout?.pipe(process.stdout);
  phpProcess.stderr?.pipe(process.stderr);

  phpProcess.on("exit", (code) => {
    console.error(`[PHP] Process exited with code ${code}, restarting in 1s...`);
    setTimeout(() => startPhp(), 1000);
  });

  return phpProcess;
}

startPhp();
