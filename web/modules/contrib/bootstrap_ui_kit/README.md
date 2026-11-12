# Bootstrap UI Kit

<div align="center">
  <kbd>
    <img src="https://www.drupal.org/files/project-images/bsui-color.png" />
  </kbd>
</div>

## Description

Provides a built-in User Interface Kit for your bootstrap enabled theme.

The goal is to provide a clear representation of how all the elements within your site look/feel/behave in one centralized place located at `/ui-kit`.

### Features

- Minimal setup required. Uses your custom theme but removes the header/footer.
- Customize the UI Kit frontend using CSS3 variables. (ie. --bootstrap-ui-kit--sidebar-bg-color: var(--bs-primary, #180733);)
- Glossary override. Add, remove, rearrange sections within the sidebar.
- Auto-discovery for custom sections.
- "Dev Mode", gives additional information for developers which often includes example code snippets.
- Deep linking to sections within the UI kit. /ui-kit#your-custom-section
- Copy current section link to users clipboard.
- Color: Automatic color contrast checker using https://www.aremycolorsaccessible.com/api-page
- Bootstrap Icons (SVG Sprites works too)
- ...Many more, but the idea is to customize this per project (hence all the auto-discovery functionality)

## Getting started

### Prerequisites

0 dependencies, your theme just needs to have bootstrap 5 enabled.

### Install

Require it: `composer require drupal/bootstrap_ui_kit`

Enable the module: `drush en bootstrap_ui_kit -y`

### Configure

At some point you may want to turn on the developer mode, or adjust the iconset loaded in the `iconography` section. To do so visit the Bootstrap UI Kit configuration page.

`Configuration -> User interface -> Bootstrap UI Kit`

`/admin/appearance/bootstrap_ui_kit/settings`

### Found a bug?

Submit an issue through the drupal.org at https://www.drupal.org/node/add/project-issue/bootstrap_ui_kit
