# Events Manager Integration

TAKA Platform can export normalized event data for the WordPress Events Manager plugin. TAKA remains the source of truth; Events Manager is treated as an external consumer.

## Admin Page

Go to **TAKA Platform -> Events Manager**.

The page provides download links for:

- ICS calendar feed
- CSV export
- JSON export
- Events Manager compatible CSV

No destructive sync is performed. The integration does not delete, overwrite or automatically manage Events Manager events.

## REST Endpoint

The normalized public event feed is available at:

```text
/wp-json/taka-platform/v1/events
```

Optional language parameter:

```text
/wp-json/taka-platform/v1/events?lang=en
```

The feed includes normalized event data such as title, subtitle, description, source language, translations, program items, venue, organizer relationships, ticket provider, ticket URL, event URL, image URL and stable TAKA event IDs.

## Events Manager CSV Mapping

The Events Manager compatible CSV maps:

- event title -> `event_name`, `post_title`
- description -> `post_content`
- first program item start -> `event_start_date`, `event_start_time`
- last program item end -> `event_end_date`, `event_end_time`
- venue -> `location_name`, `location_address`, `location_town`, `location_country`
- ticket URL -> `taka_ticket_url`
- organizers -> `taka_organizers`
- TAKA event ID -> `_taka_platform_event_id`

The CSV also includes `external_id` for stable import/update workflows.

## Export Providers

The integration uses `TAKA_Platform_Event_Export_Provider_Interface`.

Built-in providers:

- `TAKA_Platform_ICS_Provider`
- `TAKA_Platform_JSON_Provider`
- `TAKA_Platform_CSV_Provider`
- `TAKA_Platform_Events_Manager_CSV_Provider`

Additional providers can be registered through the `taka_platform_event_export_providers` filter.

## Future Direct Sync

The provider layer is designed for a later non-destructive sync provider. A future implementation should match Events Manager records using `_taka_platform_event_id` and should never delete Events Manager events automatically without an explicit administrator action.
