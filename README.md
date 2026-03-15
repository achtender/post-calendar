# Post Calendar

Post Calendar is a WordPress plugin that displays posts as events in a calendar via Bricks, shortcode, using [react-big-calendar](https://github.com/jquense/react-big-calendar) for the calendar UI.

## Quick start

1. [Download the plugin ZIP from GitHub Releases.](https://github.com/achtender/post-calendar/releases)
2. In your WordPress admin, go to `Plugins > Add New > Upload Plugin`, select the ZIP file, and activate it.
3. Open `Settings > Post Calendar` and choose which source post types should show the built-in event editor.
4. Edit a post in one of those source post types and add one or more event rows in the `Post Calendar` meta box.
5. Add the calendar to a page or page template, either with the Bricks `Post Calendar` element or with the `[post_calendar]` shortcode.

## Display options

<table>
  <tr>
    <td align="center">
      <img src=".github/Year.png" alt="Year view preview" width="260"><br>
      <strong>Year</strong>
    </td>
    <td align="center">
      <img src=".github/Month.png" alt="Month view preview" width="260"><br>
      <strong>Month</strong>
    </td>
    <td align="center">
      <img src=".github/Week.png" alt="Week view preview" width="260"><br>
      <strong>Week</strong>
    </td>
  </tr>
  <tr>
    <td align="center">
      <img src=".github/Day.png" alt="Day view preview" width="260"><br>
      <strong>Day</strong>
    </td>
    <td align="center">
      <img src=".github/Agenda.png" alt="Agenda view preview" width="260"><br>
      <strong>Agenda</strong>
    </td>
    <td></td>
  </tr>
</table>

## Event data model

Post Calendar stores and reads event data from Post Calendar meta on the original source post. A post becomes an event source when it contains one or more event definitions.

You can provide that event data in different ways:

- with the built-in post editor UI enabled in `Settings > Post Calendar`
- with your own PHP code

The built-in settings screen controls which source post types show the plugin's native event editor. That editor writes one `_post_events` array so a single post can define multiple calendar events. You can also write the same meta structure from your own PHP code.

Meta keys:

- `_post_events`: event-definition array data stored on the source post
- `_post_has_events`: derived `1`/missing summary flag used for coarse querying
- `_post_event_range_start`: derived earliest event-definition start on the post
- `_post_event_range_end`: derived latest bounded end on the post; may be missing for open-ended recurring definitions

Recurrence notes:

- the frontend calendar expands recurring events inside the requested visible date range
- weekly recurrence can target one or more weekdays
- monthly and yearly recurrence repeat from the original start date pattern
- a single post can contribute multiple event definitions and many occurrences
- builder loops and direct `WP_Query` usage expand matching posts into per-occurrence loop items

Example using PHP:

```php
update_post_meta( $post_id, '_post_events', array(
  array(
    'all_day'         => 1,
    'start_date'      => '2026-03-13 00:00:00',
    'end_date'        => '2026-03-20 23:59:59',
    'repeat'          => 'none',
    'repeat_interval' => 1,
    'repeat_byday'    => array(),
    'repeat_until'    => '',
  ),
  array(
    'all_day'         => 0,
    'start_date'      => '2026-03-15 09:00:00',
    'end_date'        => '2026-03-15 11:00:00',
    'repeat'          => 'weekly',
    'repeat_interval' => 1,
    'repeat_byday'    => array( 'MO', 'WE' ),
    'repeat_until'    => '2026-06-30 23:59:59',
  ),
) );
```

When `_post_events` changes through custom code outside the normal post editor save flow, the derived summary meta should also be regenerated so queries stay accurate.

## Shortcode

```php
[post_calendar]
```

```php
[post_calendar post_types="post,page" default_view="month" enabled_views="year,month,agenda" show_toolbar="1" agenda_range_mode="upcoming-window" agenda_range_months="12"]
```

Shortcode attributes:

- `post_types`: Comma-separated list of source post types to include for this calendar instance. Leave empty to include events from all post types.
- `default_view`: `month`, `week`, `day`, `agenda`, or `year`.
- `enabled_views`: Comma-separated views from `month`, `week`, `day`, `agenda`, `year`.
- `show_toolbar`: `1`/`0` (also supports `true`/`false`, `yes`/`no`, `on`/`off`).
- `agenda_range_mode`: `visible-range` or `upcoming-window`.
- `agenda_range_months`: Positive integer, used for `upcoming-window`.

## Querying events in templates and page builders

Post Calendar registers a virtual post type called `post_calendar_event`. It acts as a single query target for event posts across all post types.

This type is intended as a virtual query target and is not created through normal admin workflows. Any `WP_Query` or builder loop targeting `post_calendar_event` is automatically resolved to matching source posts with the derived event-source constraint applied (`_post_has_events = 1`), then expanded into per-occurrence loop items.

### Use with a builder loop or WP_Query

Set the query post type to `post_calendar_event`. Most query options (pagination, filters, sorting) work normally. The plugin rewrites `post_type` to source types and adds the derived event-source filter. The loop renders the actual source posts, so field access, permalink, excerpt, and featured image all work without extra steps.

Recurring posts are expanded into repeated loop rows. Each row keeps the source post content, permalink, excerpt, featured image, and taxonomy data, but carries occurrence-specific event dates.

When the current loop item is an occurrence instance:

- `get_post_meta( get_the_ID(), '_post_start_date', true )` returns the occurrence start for the current loop row
- `get_post_meta( get_the_ID(), '_post_end_date', true )` returns the occurrence end for the current loop row
- the loop post object exposes `post_calendar_occurrence_id`, `post_calendar_occurrence_start`, `post_calendar_occurrence_end`, and `post_calendar_occurrence_source_id`

For recurring queries, date constraints should be explicit whenever possible. A `meta_query` on `_post_start_date` is treated as an occurrence-range filter, and the loop paginates after recurrence expansion. If no date window is supplied, the virtual query defaults to an upcoming one-year occurrence window.

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

You can also pass explicit occurrence window bounds through custom query vars:

```php
$events = new WP_Query( [
  'post_type'      => 'post_calendar_event',
  'posts_per_page' => 10,
  'start'          => current_time( 'mysql' ),
  'end'            => gmdate( 'Y-m-d H:i:s', strtotime( '+90 days' ) ),
] );
```

## Developer workflow

For developing locally you can:

1. Run `npm run dev` for a watch build, or `npm run dev:preview` for a standalone React preview.
2. Run `npm run dev:admin` when working on the native post editor bundle.
3. Run `npm run build` for production assets.
4. Run `npm run build:zip` to create a release ZIP in `.release/`.
