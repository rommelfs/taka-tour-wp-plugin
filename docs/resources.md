# Resource Management

Resource Management tracks physical tour logistics for TAKA Platform. It is not
intended to be full inventory or ERP software. The module focuses on practical
event operations: what equipment exists, where it currently is, who is
responsible and which upcoming event requires it.

## Phase 8 Scope

- Private admin page under `TAKA Platform -> Resources`.
- Private resource records.
- Resource assignment to tours, events, planning items, vehicles and people.
- Movement history with current location, responsible person and expected
  return date.
- Event edit metabox for required resources.
- Dashboard metrics for missing, reserved, broken and overdue resources.

## Data Model

Resources are stored as the private post type `taka_resource`.

Movement history is stored as the private post type `taka_res_move`.

Event requirements are stored on events as private structured meta. They only
describe what an event needs; they do not change the resource ownership or
movement history by themselves.

## Resource Fields

Resources support:

- name
- category
- description
- serial number
- quantity
- current location
- responsible person
- condition
- status
- photo attachment ID
- notes
- assignment metadata

## Privacy

Resource data is internal tour logistics. It is not public, not queryable from
the frontend and not exposed through public REST endpoints.

## Future Phases

The model leaves room for QR labels, barcode scanning, maintenance schedules,
badge-printer integration and richer vehicle logistics.
