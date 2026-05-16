# Starter kit contributing

## JS dependency hardening

This fork uses Bun as the only JavaScript package manager. `package.json` pins the toolchain with `"packageManager": "bun@1.3.13"`, and CI reads that pin through `oven-sh/setup-bun@v2`.

Do not use `npm`, `pnpm`, `yarn`, `npx`, `git://`, `git+ssh://`, `git+https://`, `git@...`, `github:`, `file:`, or `link:` package specs for JavaScript dependencies. Use registry versions from npm and commit the resulting `bun.lock` changes.

CI and review validation must install with `bun install --frozen-lockfile`; a PR that changes `package.json` must include the matching `bun.lock` change. Do not regenerate `bun.lock` as a cleanup step.

`bunfig.toml` enforces a 7 day `install.minimumReleaseAge` gate for newly resolved npm package versions. Existing versions already recorded in `bun.lock` are not re-resolved by that gate.

### trustedDependencies: [] is load-bearing

The explicit `"trustedDependencies": []` in `package.json` is not decorative. It is the mechanism that disables Bun's built-in curated trust list — when the field is **absent**, Bun falls back to a built-in default allowlist and may run install scripts for packages on that list. When the field is **present and empty**, no install scripts run unless a package is explicitly added.

- Never remove the `trustedDependencies` field, even when its array is empty.
- To allow a package's install scripts: add the package name to the array with a one-line justification in the PR description, then re-run `bun install --frozen-lockfile`.
- Do NOT run `bun pm trust <pkg>` — it writes to `package.json` without code review and runs scripts immediately.

### Adding a package to trustedDependencies

If `bun install --frozen-lockfile` fails because a dependency needs lifecycle scripts:

1. Identify the exact package name from the failure output.
2. Add the package name to the `trustedDependencies` array in `package.json`.
3. Include a one-line justification in your PR description (e.g. "esbuild — native binary requires postinstall to download platform-specific executable").
4. Re-run `bun install --frozen-lockfile`. Repeat for each additional failure.
5. Never add a package from a `git://`, `git+ssh://`, `git+https://`, `git@`, `github:`, `file:`, or `link:` source — Step 0 policy bans non-registry JS deps.

### Exemptions and escalation

If you believe a deviation from this policy is warranted (e.g. a critical dependency available only via a git source), escalate to the repo maintainer before merging. Do not merge non-registry JS dependency specs without explicit written approval in the PR. Document any approved exemption in this file under a `### Approved exemptions` subsection with the date, package name, source spec, and justification.

### Lockfile integrity

Bun verifies downloaded package integrity by default. Do not use `--no-verify` during installs. The `bun.lock` file must be committed; CI frozen installs fail if it is absent or stale.

Do not run `bun update` without a deliberate dependency upgrade PR — it regenerates `bun.lock` and may silently pull in breaking or malicious versions.
