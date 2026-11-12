# Frontend Tools


Install the tools by running the `swat frontend:setup` or `swat theme:setup`
command. The default Bootstrap theme includes an example global theme and
component example. Happy building!

## Standards & Formatting

Included are the recommended code standards and formatting for JavaScript,
Sass/CSS, and Yaml. All projects should be using these standards for development
and production builds. Code standards are comprised of Airbnb, Node Security,
Drupal, and custom rules. Formatting is controlled by Prettier across all
supported languages.

> **⚠️ IMPORTANT** - Modifying or disabling rules is allowed, but strongly
> discouraged. The default rules attempt to cover most use cases for consistency
> and reliability. Please reach out to discuss changes that could potentially be
> added to the default rules.

Use any of the following commands to validate the codebase. To autofix issues,
when possible, add `:fix` at the end of the command. When building deployment
artifacts the `swat validate` command should be used instead.

```shell
npm run lint
npm run lint:fix
```

## Package Builds

Projects using DDEV for local development may use the following commands to
start a build within the container. When building deployment artifacts
the `ddev frontend-build` command should be used instead.

```shell
# Build all packages.
ddev frontend-build
# Build single package.
ddev frontend-build @theme/component.example
# Watch all packages.
ddev frontend-watch
# Watch single package.
ddev frontend-watch @theme/component.example
```

Builds may also be initiated directly with the npm scripts provided in the
default `package.json` configuration. Usually these scripts can run locally,
outside of a container, with improved performance. If running locally is causing
issues, add `ddev exec` at the start of the command to run it inside the
container.

```shell
# Build all packages.
npm run build
# Build all packages, ignoring local disk cache.
npm run build @theme/example -- --force
# Build all packages in production mode.
npm run build:production
# Watch for changes in packages.
npm run watch @theme/example
```

## Package & Component Structure

All packages must adhere to the established monorepo structure to properly use
these frontend tools. There are no limitations to the number of packages a
project may contain. That said, keep in mind that each new package could add to
the overall build time.

### Monorepo structure

```shell
┌── components/
│   │
│   └── example-component/
│       │ # Public build output.
│       ├── public/
│       │   └── assets/ js/ css/
│       │
│       │ # Source code used to build public output.
│       ├── src/
│       │   ├── icons/
│       │   │   │ # Vector images to create an icons sprite.
│       │   │   └── *.svg
│       │   ├── js/
│       │   │   │ # Entry points (any .ts or .js file).
│       │   │   └── *.ts / *.js
│       │   └── scss/
│       │       │ # Styles written in Sass (automatically compiled).
│       │       └── *.scss
│       │
│       │ # Static assets including but not limited to images, videos, and web fonts.
│       ├── static/
│       │   └── assets/
│       │
│       │ # Mandatory package definition.
│       ├── package.json
│       │ # Optional Vite configuration overrides.
│       └── vite.config.js
│
├── example.info.yml
└── example.libraries.yml
```

Entry points depend on the `src/` layout:
- If `src/` contains top-level `.ts`, `.js`, or `.scss` files, those top-level files are treated as entry points.
- Else, if `src/` contains second-level files like `src/js/*.js`, `src/ts/*.ts`, or `src/scss/*.scss`, those second-level files are treated as entry points.
TypeScript is preferred, but JavaScript is accepted. SCSS is automatically compiled and does not need to be imported from JavaScript.

Example: `src/example.ts` imports a library and defines a Drupal behavior.
```ts
import ExampleLibrary from "example-library";

((Drupal) => {
  Drupal.behaviors.exampleComponent = {
    ...
  };
})(Drupal);
```

### Package definition

At minimum, each `package.json` definition must include the following
configuration. Name needs to be unique to the codebase and is also referred to
as workspace when using npm commands. The `build` script allows npm scripts to
run builds in any of the defined workspaces. Modifying the script could
cause unexpected behaviors or build errors.

> **⚠️ IMPORTANT** - Package dependencies should be added to the workspace
> instead of the project root. See [Workspaces](#workspaces) for more
> information on using npm to manage dependencies and perform other workspace
> related operations.

```json
{
  "name": "@{module,theme}/{package_name}",
  "type": "module",
  "scripts": {
    "build": "swat build"
  }
}
```

Although package name formatting is not strictly enforced, it's recommended to
adhere to the following nomenclature.

- Theme: `@theme/global`
- Theme component: `@theme/component.{component_name}`
- Module: `@module/{module_name}`
- Module component: `@module/{module_name}.{component_name}`

## Build Technology

Packages are built using [Turborepo](https://turbo.build/)
and [Vite](https://vite.dev/). The project root `vite.config.js`
attempts to cover most use cases. Including a Sass, JavaScript, and SVG compiler
out of the box. Adhering to the [[Frontend Tools#Monorepo structure]] ensures
the default compilers are made available without the need to create Vite
configurations in each package. In some cases it may be necessary to extend the
project root configuration by adding `vite.config.js` to the custom package
folder in the following format.

```js
import { setConfig } from '@imagexmedia/vite'

export default setConfig(
  {
    // Overriden options.
  },
  (env) => {
    // Advanced overriden config. This is similar to configuring Vite manually, except swat defaults are applied first.
  }
)
```

## Customizing Configurations

The project root configuration files (`eslint.config.js`, `stylelint.config.js`,
`vite.config.js`) use a `setConfig()` method from their respective `@imagexmedia`
packages. This method accepts an optional configuration object to extend or
override the defaults.

### ESLint
See [ESLint Config documentation](https://github.com/antfu/eslint-config)

```js
import { setConfig } from '@imagexmedia/eslint'

const config = setConfig({
  // Overridden options.
})

// Modify the final flat config object.
export default config
```

### Stylelint
Refer to existing options at [Swat Stylint](https://bitbucket.org/ixm/swat-frontend/src/main/packages/stylelint/src/index.ts)

```js
import { setConfig } from '@imagexmedia/stylelint'

const config = setConfig({
  // Overridden options.
})

// Modify the final config object.
export default config
```

### Vite
Refer to existing options at [Swat Vite](https://bitbucket.org/ixm/swat-frontend/src/main/packages/vite/src/index.ts)

```js
import { setConfig } from '@imagexmedia/vite'

export default setConfig(
  {
    // Overriden options.
  },
  (env) => {
    // Advanced overriden config. This is similar to configuring Vite manually, except swat defaults are applied first.
  }
)
```

## Tips & Tricks

### Imports

Each custom package can be imported to another custom package in both JavaScript
and Sass. For example, import theme scaffolding into a component
with `@import '@theme/global/src/scss/abstracts/scaffold';` at the top of the
Sass file.

To import static assets in SCSS, reference them using the package path:

```scss
background: url("@theme/global/static/assets/ixm.png");
```

### Workspaces

npm workspaces allow custom packages to be managed from the project root. For
example, `ddev npm install -D react -w @theme/global` adds a react dependency to
the global theme. See https://docs.npmjs.com/cli/using-npm/workspaces for more examples.

### Icons

Vector icons placed in the `src/icons/` folder are compiled into a single
sprite. Use the provided `icon.html.twig` template to insert icons into a page.
Be sure to replace `{theme_name}` with the Drupal theme name and `{icon_name}`
with the icon filename.

```twig
{% include '@{theme_name}/components/icon.html.twig' with {'name': '{icon_name}', 'classes': ['icon-sm']} %}
```
