# Post Calendar

Post Calendar is a WordPress plugin that lets you display your content as a calendar in Bricks Builder, using [react-big-calendar](https://github.com/jquense/react-big-calendar) for the calendar UI.

## Quick start

1. [Download and install the plugin ZIP from GitHub Releases.](https://github.com/achtender/post-calendar/releases)
2. Open `Settings > Post Calendar` and choose the post types you want to use as events.
3. Place the calendar either with the Bricks element or with the shortcode.

| ![](.github/Month.png) | ![](.github/Week.png)   |
| ---------------------- | ----------------------- |
| ![](.github/Day.png)   | ![](.github/Agenda.png) |

## Display options

### Use with the Bricks Builder

1. Open Bricks Builder on your page/template.
2. Add the `Post Calendar` element.
3. Configure the element options as needed (for example view mode, toolbar, and labels).
4. Publish your page.

### Use as a shortcode

You can also render a calendar without Bricks:

```php
[post_calendar]
```

Example with custom options:

```php
[post_calendar post_types="post,page" default_view="week" enabled_views="month,week,agenda" show_toolbar="1" agenda_range_mode="upcoming-window" agenda_range_months="3"]
```

Shortcode attributes:

- `post_types`: Comma-separated list of post types. Leave empty to use the allowed post types from `Settings > Post Calendar`.
- `default_view`: `month`, `week`, `day`, or `agenda`.
- `enabled_views`: Comma-separated views from `month`, `week`, `day`, `agenda`.
- `show_toolbar`: `1`/`0` (also supports `true`/`false`, `yes`/`no`, `on`/`off`).
- `agenda_range_mode`: `visible-range` or `upcoming-window`.
- `agenda_range_months`: Positive integer, used for `upcoming-window`.

## Event data model

Post Calendar only reads values from post meta. It does not require a specific editor workflow.

That means you can populate event data in different ways:

- with Post Calendar related fields
- with another plugin (for example ACF or a custom post type plugin)
- with your own PHP code

As long as these post meta keys exist, the calendar can read the event:

- `_post_is_event` (`1` for event, `0` or missing for non-event)
- `_post_is_allday` (`1` for all-day, `0` for timed event)
- `_post_start_date` (`Y-m-d H:i:s`, for example `2026-03-13 09:00:00`)
- `_post_end_date` (`Y-m-d H:i:s`, optional; when missing, start date is used)

Example using PHP:

```php
update_post_meta( $post_id, '_post_is_event', '1' );
update_post_meta( $post_id, '_post_is_allday', '0' );
update_post_meta( $post_id, '_post_start_date', '2026-03-13 09:00:00' );
update_post_meta( $post_id, '_post_end_date', '2026-03-13 11:00:00' );
```

## Developer workflow

If you are developing this plugin locally:

1. Run `npm install`.
2. Run `npm run dev` (watch build) or `npm run dev:preview` (preview mode).
3. Run `npm run build` for production assets.
4. Run `npm run build:zip` to create a release ZIP in `.release/`.
