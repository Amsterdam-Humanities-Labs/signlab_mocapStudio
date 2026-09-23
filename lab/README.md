# mocapStudio/lab (was signlab_mocap_lab)
PHP endpoints and reference media for the mocapStudio recording page. The page calls them as `../mocap_lab/`.

## What it does
- `fetch_images.php` lists `meta_images/`. `fetch_topics.php` returns `topics.json`, a set of Dutch conversation prompts.
- `generate_video_files.php` lists `lsc_videos/`. That folder is not in git and exists only on the server. The studio plays gloss videos from it.
- `save_fbx_studio.php` marks a gloss as recorded in `mocap_data` and `captures`.
- `upload_video.php` stores a camera video under `../gebarenoverleg_media/mocap_video/<camera>/`. `test_fbx_sql.php` is a one-off test script.
- Media in git: `meta_images/` (58 photos), `csl_images/` (41 diagrams), `mocap_videos/` (18 clips, the fallback for playback).
- There is no index page, so the bare folder URL returns an error (500 on the core server, 403 on demo hosts). The studio also fetches `helpScripts/emptyVideoTop.php`, which no repo contains.

## Where it runs
Core server: `/web/mocap_lab`, https://signcollect.nl/mocap_lab/.
Demo hosts (dev2 `/web/mocap_lab`, dev-1 `/srv/signcollect/web/mocap_lab`): a symlink to `mocapStudio/lab`.

## Status
Production.

## How to run / deploy
It deploys with mocapStudio; see `../README.md`. The stack's `host-bootstrap.sh` makes the `mocap_lab` symlink and replaces an old signlab_mocap_lab checkout.
There is no build step. The listing endpoints read paths relative to the working folder, so call them over HTTP at `/mocap_lab/`.

## Configuration
- `../mysql_config.php` at the docroot (not in git).
- `sc_paths.php` is copied from signcollect-lib. Edit it there, not here.
- `SC_LEGACY_WEB_ROOT` (environment or `/web/.env`, default `/var/www/html`): where `save_fbx_studio.php` looks for GLB files.

## Dependencies
- MySQL `admin_gebarenoverleg`: `mocap_data`, `captures`, `mocap_files`.
- `gebarenoverleg_media` next to it in the docroot (on demo hosts: [signlab_demo-media](https://github.com/Amsterdam-Humanities-Labs/signlab_demo-media)). [signlab_signcollect-lib](https://github.com/Amsterdam-Humanities-Labs/signlab_signcollect-lib) is optional.
- The only caller is the mocapStudio page.
