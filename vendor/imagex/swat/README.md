![SWAT 5 Logo](logo.png)
### (SUPER WICKED AWESOME TOOL)
SWAT is meant to be a helper for projects within ImageX. It provides a DDEV setup, some templated Bitbucket pipelines and deployment helpers. And it's super wicked awesome.

## Requirements

SWAT requires the following to run:

1. DDEV - <https://ddev.readthedocs.io/>
2. Composer - <https://getcomposer.org/>
3. PHP >=8.1

##### Available Commands
Run `ddev swat list` to get a listing of available commands.

## GETTING STARTED

New projects must use Drupal 11.

1. `git clone git@bitbucket.org:ixm/swat-project-template.git <project-name>`
    - **YUSA / OpenY**: `git clone --branch=yusa git@bitbucket.org:ixm/swat-project-template.git <project-name>`
2. `cd <project-name>`
3. `rm -rf .git`
4. `ddev config --docroot=docroot --project-type=drupal11 --php-version=8.3 --composer-version=2.8.12`
    - **Pantheon/Platform.sh**: Use `web` for `--docroot`
    - **YUSA / OpenY**: Use `drupal10` for `--project-type`
5. Modify PHP version to match your remote host
6. Initialize a new git repository
    - `git init`
    - `git add .`
    - `git commit -m "Initial commit"`
7. `ddev composer install`
8. `ddev swat setup`
9. Modify `ddev/config.yaml` for any stack changes
    - DB, NodeJS, composer, etc versions to match your remote host
    - Custom services, if needed
10. `ddev restart`
11. `ddev swat install`
12. Export your config.
    - ** Check/Setup config splits as well **
13. Setup your git remote in `swat/swat.yml` (see example below)
14. Push your code to Bitbucket:
    - `git remote add origin git@bitbucket.org:example/example.git`
    - `git add . && git commit -m "PROJ-1: Initial Setup for <project>."`
    - `git push -u origin master`

#### Git Remote Setup:

Example of Git config in `swat.yml` for remote:

```
    git:
      default_branch: develop
      remotes:
        cloud: 'ixmcomponents@svn-4707.devcloud.hosting.acquia.com:ixmcomponents.git'
      user:
        name: 'ImageX Media'
        email: no-reply@imagexmedia.com
```

### Hosting Provider

See our internal documentation: [Configuring your hosting provider files](https://imagex.atlassian.net/wiki/spaces/DEV/pages/2163114754/Project+Setup#%F0%9F%92%BB-Configuring-your-hosting-provider-files)
See DDEV documentation: [Hosting Provider Integration](https://ddev.readthedocs.io/en/stable/users/providers/)
---

## Existing Projects

Existing projects generally should be manually upgraded until such an update script exists.

---
