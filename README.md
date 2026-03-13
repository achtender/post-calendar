# Post Calendar

Post Calendar is a WordPress plugin that lets you display your content as a calendar in Bricks Builder, using [react-big-calendar](https://github.com/jquense/react-big-calendar) for the calendar UI.

## Quick start

1. [Download the plugin ZIP from GitHub Releases.](https://github.com/achtender/post-calendar/releases)
2. In your WordPress admin, go to `Plugins > Add New > Upload Plugin`, select the ZIP, and activate it.
3. Open `Settings > Post Calendar` and choose the post types you want to use as events.
4. Add the calendar to a page or page template, either with the Bricks `Post Calendar` element or with the `[post_calendar]` shortcode.

## Display options

| ![](.github/Month.png) | ![](.github/Week.png)   |
| ---------------------- | ----------------------- |
| ![](.github/Day.png)   | ![](.github/Agenda.png) |

## Event data model

Post Calendar only reads values from post meta. It does not require a specific editor workflow, and as long as the expected post meta keys exist, the calendar can read a post as an event. That means you can populate event data in different ways:

- by enabling the fields on a post type with the included options page
- with another plugin (for example ACF or a other custom post type plugin)
- with your own PHP code

Example using PHP:

```php
update_post_meta( $post_id, '_post_is_event', '1' );                      // `1` for event, `0` or missing for non-event
update_post_meta( $post_id, '_post_is_allday', '0' );                     // `1` for all-day, `0` for timed event
update_post_meta( $post_id, '_post_start_date', '2026-03-13 09:00:00' );  // `Y-m-d H:i:s`, for example `2026-03-13 09:00:00`
update_post_meta( $post_id, '_post_end_date', '2026-03-13 11:00:00' );    // `Y-m-d H:i:s`, optional; when missing, start date is used
```

## Shortcode

```php
[post_calendar]
```

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

## Querying events in templates and page builders

Post Calendar registers a virtual post type called `post_calendar_event`. It acts as a single query target for all event posts from every source post type enabled under `Settings > Post Calendar`.

This type is intended as a virtual query target and is not created through normal admin workflows. Any `WP_Query` or builder loop targeting `post_calendar_event` is automatically resolved to the matching source posts, with the event-enabled meta constraint applied.

### Use with a builder loop or WP_Query

Set the query post type to `post_calendar_event`. Most query options (pagination, filters, sorting) work normally. The plugin always rewrites `post_type` to enabled source types and adds the event-enabled filter. The loop renders the actual source posts, so field access, permalink, excerpt, and featured image all work without extra steps.

```php
$events = new WP_Query( [
    'post_type'      => 'post_calendar_event',
    'posts_per_page' => 10,
] );
```

Events are ordered by start date ascending by default. You can override it:

```php
$events = new WP_Query( [
    'post_type'      => 'post_calendar_event',
    'posts_per_page' => -1,
    'meta_key'       => '_post_start_date',
    'orderby'        => 'meta_value',
    'meta_type'      => 'DATETIME',
    'order'          => 'DESC',
] );
```

A custom `meta_query` is merged with the event-enabled constraint automatically:

```php
$events = new WP_Query( [
    'post_type'      => 'post_calendar_event',
    'posts_per_page' => 10,
    'meta_query'     => [
        [
            'key'     => '_post_start_date',
            'value'   => date( 'Y-m-d H:i:s' ),
            'compare' => '>=',
            'type'    => 'DATETIME',
        ],
    ],
] );
```

## Developer workflow

If you are developing this plugin locally:

1. Run `npm install`.
2. Run `npm run dev` (watch build) or `npm run dev:preview` (preview mode).
3. Run `npm run build` for production assets.
4. Run `npm run build:zip` to create a release ZIP in `.release/`.
