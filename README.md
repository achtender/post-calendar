# Post Calendar

Post Calendar adds structured event/date data to WordPress posts and makes those events queryable as individual occurrences.

## Event data model

Post Calendar stores event data on the original source post. A post becomes an event source when you save one or more event definitions in the `_post_events` meta key.

You can write that data in three ways:

- with the built-in post editor UI enabled in `Settings > Post Calendar`
- with ACF, as long as it saves the same `_post_events` meta structure
- with your own PHP code

The built-in editor writes the `_post_events` array and keeps the derived meta keys in sync, so a single post can define multiple calendar events and stay quarriable.

If you update `_post_events` outside the normal post save flow, you also need to keep the derived meta keys in sync so queries stay accurate.

Derived meta keys:

- `_post_events`: event-definition array data stored on the source post
- `_post_has_events`: derived `1`/missing summary flag used for coarse event-source queries
- `_post_event_range_start`: canonical post-summary start for the full set of event definitions on this source post
- `_post_event_range_end`: canonical post-summary end for the full set of event definitions on this source post; it may be missing for open-ended recurring definitions

The query loop exposes occurrence-specific values for each expanded event row. These are not the same as the source-post summary keys:

- `_post_start_date`: occurrence start for the current loop row
- `_post_end_date`: occurrence end for the current loop row
- `_post_event_label`: label for the current loop row, falling back to the post title when the event row leaves its label empty
- `post_calendar_occurrence_start`, `post_calendar_occurrence_end`, `post_calendar_occurrence_label`, and `post_calendar_occurrence_event_index` on the loop post object

This distinction matters when a single source post contains multiple event rows: the summary range describes the whole post, while each occurrence row has its own start/end/label values.

Example `_post_events` value:

```php
update_post_meta( $post_id, '_post_events', array(
  array(
    'label'           => '',
    'all_day'         => 1,
    'start_date'      => '2026-03-13 00:00:00',
    'end_date'        => '2026-03-20 23:59:59',
    'repeat'          => 'none',
    'repeat_interval' => 1,
    'repeat_byday'    => array(),
    'repeat_until'    => '',
  ),
  array(
    'label'           => 'Team Meeting',
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

Each event definition in `_post_events` uses this shape:

- `label`: optional string label for the event (falls back to post title if empty)
- `all_day`: `1` or `0`
- `start_date`: start datetime in `Y-m-d H:i:s`
- `end_date`: end datetime in `Y-m-d H:i:s`
- `repeat`: `none`, `weekly`, `monthly`, or `yearly`
- `repeat_interval`: positive integer interval between repeats
- `repeat_byday`: array of weekday codes for weekly recurrence, such as `MO` or `WE`
- `repeat_until`: end datetime in `Y-m-d H:i:s`, or an empty string for no recurrence limit

## Bricks Dynamic Data Tags

Post Calendar registers dynamic data tags for use in Bricks builder elements. These tags allow you to display event information (start date, end date, label) with custom date formatting in any Bricks text, rich text, or dynamic content element.

### Available Tags

- `{postcal_event_start_date}` – Event start date (raw format: `Y-m-d H:i:s`)
- `{postcal_event_end_date}` – Event end date (raw format: `Y-m-d H:i:s`)
- `{postcal_event_label}` – Event title/label
- `{postcal_has_events}` – Boolean flag indicating post has events

### Date Formatting

The date tags (`{postcal_event_start_date}` and `{postcal_event_end_date}`) support custom formatting using PHP `DateTime::format()` syntax. Pass a format string after the tag name with a colon:

```
{postcal_event_start_date:Y-m-d}               → 2026-08-17
{postcal_event_start_date:F j, Y}              → August 17, 2026
{postcal_event_start_date:g:i A}               → 2:30 PM
{postcal_event_start_date:l, F j \a\t g:i A}  → Sunday, August 17 at 2:30 PM
{postcal_event_end_date:Y-m-d H:i}             → 2026-08-17 16:00
```

### Usage in Bricks

Use these tags in any Bricks element that supports dynamic data:

- In Text or Heading elements
- In Rich Text fields
- In any field that accepts dynamic content
- Combined with other dynamic tags

The tags are context-aware and automatically access the current post's event meta during both builder preview and frontend rendering.

## Querying events in templates and page builders

Post Calendar registers a virtual post type called `post_calendar_event`. Use it as a query target only: you do not create or edit `post_calendar_event` posts in WordPress admin. When you query it in `WP_Query` or a builder loop, the plugin resolves it to matching source posts with the derived event-source constraint applied (`_post_has_events = 1`), then expands the results into per-occurrence loop items.

### Use with a builder loop or WP_Query

Set the query post type to `post_calendar_event`. Most query options (pagination, filters, sorting) work normally. The plugin rewrites `post_type` to source types and adds the derived event-source filter. The loop renders the actual source posts, so field access, permalink, excerpt, and featured image all work without extra steps.

Recurring posts are expanded into repeated loop rows. Each row keeps the source post content, permalink, excerpt, featured image, and taxonomy data, but carries occurrence-specific event dates.

When the current loop item is an occurrence instance, the plugin exposes occurrence-specific virtual meta for that loop row:

- `get_post_meta( get_the_ID(), '_post_start_date', true )` returns the occurrence start for the current loop row
- `get_post_meta( get_the_ID(), '_post_end_date', true )` returns the occurrence end for the current loop row
- `get_post_meta( get_the_ID(), '_post_event_label', true )` returns the event label for the current loop row, falling back to the source post title when the row label is empty
- the loop post object exposes `post_calendar_occurrence_id`, `post_calendar_occurrence_start`, `post_calendar_occurrence_end`, `post_calendar_occurrence_label`, `post_calendar_occurrence_event_index`, `post_calendar_occurrence_index`, and `post_calendar_occurrence_source_id`

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

## Display and query options

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
    <td>
      <img src=".github/Query-loop.png" alt="Use as a source for a query loop" width="260"><br>
      <strong>Query loop</strong>
    </td>
  </tr>
</table>

### Shortcode

```php
[post_calendar]
```

```php
[post_calendar post_types="post,page" default_view="month" enabled_views="year,month,agenda" show_toolbar="1" agenda_range_mode="upcoming-window" agenda_range_months="12"]
```

Shortcode attributes:

- `post_types`: Comma-separated list of source post types to include for this calendar instance. Leave empty to use the post types enabled in `Settings > Post Calendar`.
- `default_view`: `month`, `week`, `day`, `agenda`, or `year`.
- `enabled_views`: Comma-separated views from `month`, `week`, `day`, `agenda`, `year`. Invalid or empty values fall back to all views.
- `show_toolbar`: `1`/`0` (also supports `true`/`false`, `yes`/`no`, `on`/`off`).
- `agenda_range_mode`: `visible-range` or `upcoming-window`.
- `agenda_range_months`: Positive integer, used for `upcoming-window`. Invalid values fall back to `3`.
