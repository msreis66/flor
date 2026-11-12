# Upgrading SWAT

In general, SWAT is meant to be a "one and done" solution for setting up projects. As such, there is no upgrade path
from one version to the next; however, below are some version specific notes on upgrades that may benefit older builds.

## 6.x to 7.0.x

### Frontend Hooks.

The frontent commands have been changed to use hooks. you will
need to add the following to your `swat/swat.yml` file, note that if upgrading to the Vite setup, this will be the same
as swat's defaults.

```yaml
command-hooks:
  frontend-install:
    dir: <your-command-dir>
    command: <your install command> # e.g. yarn install
  frontend-build:
    dir: <your-command-dir>
    command: <your build command> # e.g. yarn build:production
```

## 5.x to 6.0.0

### Frontend upgrade (Docksal)
The FE process between these versions has significantly changed. Follow the steps below to update.

1. Delete your `scripts` folder (check for anything custom here first)
2. Copy the files from `vendor/imagex/swat/defaults/frontend` to your repo root, overwriting existing. You will want to check your package.json for any previous modifications.
    - Delete `blt/deploy/rsync-exclude.txt` and replace with `blt/deploy-exclude-additions.txt` from above, **but check for any custom settings here.**
3. Copy the files from `vendor/imagex/swat/defaults/docksal/commands/frontend` to your `.docksal/commands/frontend` folder.
4. Copy `vendor/imagex/swat/defaults/theme/package.json` to your theme dir, note that you will need to match your Bootstrap version from your previous root package.json and modify as neccessary.
5. Add `public` to your theme's `.gitignore`
6. Rename `assets` to `src` in your theme
7. Add necessary `global.js` and any other custom libs you might have in your theme, following `vendor/imagex/swat/defaults/theme/src/global.js` as a reference.
8. Update your libraries to use `public` instead of `dist`
9. Repeat 4-8 for any components, use `vendor/imagex/swat/defaults/bootstrap/components/tooltip` as reference.
9. Run `fin blt frontend/build` and confirm everything works
10. Run an artifact build to simulate a deployment and confirm ignore/exclude is correct

## 6.0.0 to 6.1.x

**Note:** If you currently are using an NPM-based frontend, you might want to consider running the upgrade steps above before continuing.

### BLT Removal

6.1.x is the first version to remove BLT as a dependency, see the [Upgrade Steps](#6.1.1) below. The following BLT command changes are notable:

| BLT Command                           | Replacement                  | Notes                                          |
| ------------------------------------- | ---------------------------- | ---------------------------------------------- |
| `source:build`                        | N/A                          | `composer install` and `swat frontend` suffice |
| `drupal:deployment-identifier:init`   | `swat:deployment-identifier` |                                                |
| `drupal:sync:default:site`            | N/A                          | Use Drush                                      |
| `source:build:frontend`               | `frontend`                   |                                                |
| `drupal:hash-salt:init`               | N/A                          | Exists outside BLT                             |
| `artifact:build:simplesamlphp-config` | N/A                          | Manual Setup                                   |
| `blt:init:git-h`ooks                  | N/A                          | GrumPHP                                        |
| `blt:init:settings`                   | N/A                          | acquia/drupal-recommended-settings             |
| `drupal:sync`                         | N/A                          | Use Drush                                      |
| `drupal:update`                       | N/A                          | Use Drush                                      |
| `frontend`                            | `frontend`                   |                                                |
| `recipes:cloud-hooks:init`            | N/A                          | Manual                                         |
| `validate`                            | `validate`                   | Also separated frontend/backend                |

### 6.1.1

The `swat upgrade` command has been introduced.
See https://imagex.atlassian.net/wiki/spaces/DEV/pages/2400354310/BLT+Removal+SWAT+6.1.0 for directions.

## 6.1.x to 6.1.10

- New PHPCS, ESLint, and Stylelint rulesets
- Upgraded from Yarn 1.x to 4.x and switched from Lerna to Turborepo for monorepo management
- Enabled development JavaScript and Sass source maps

### Breaking Changes

- Projects using a `.yarnrc` file must convert to the new `.yarnrc.yml` format
  - Must include `nodeLinker: node-modules` to disable PnP loader
  - See https://bitbucket.org/ixm/swat/src/7.0.x/defaults/frontend/.yarnrc.yml
- Projects using Lerna must create `turbo.json` and remove `nx.json` and `lerna.json`
  - Add the `.turbo` cache/log directory to the root `.gitignore` file
  - See https://bitbucket.org/ixm/swat/src/7.0.x/defaults/frontend/turbo.json
- Projects using `.eslintrc.json` must replace it with the new `.eslintrc` file
  - Be sure to remove the old `.eslintrc.json` file
  - See https://bitbucket.org/ixm/swat/src/7.0.x/defaults/frontend/.eslintrc
- Projects using `.stylelintrc.json` must replace it with the new `.stylelintrc` file
  - Be sure to remove the old `.stylelintrc.json` file
  - See https://bitbucket.org/ixm/swat/src/7.0.x/defaults/frontend/.stylelintrc
- Projects using Sass functions must be imported using the `@use` rule
  - Examples:
    - `map-get()` changes to `@use "sass:map"; map.get($map, 'value')`
    - `lighten()` changes to `@use "sass:color"; color.adjust($color, $lightness: + 5%)`
