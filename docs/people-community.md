# People & Community

People are long-term private community records in TAKA Platform. Phase 9
extends the existing People and Registration model into lightweight community
management without turning TAKA into a generic CRM.

## Scope in Phase 9

- People dashboard with community metrics.
- Profile tabs for overview, participation history, orders, payments, products,
  vouchers, activities, notes and future document/certificate modules.
- Chronological participation history grouped by year.
- Extensible relationships such as dojo membership, instructor, organizer,
  volunteer, speaker, sponsor, press and VIP.
- Tags for operational/community segments.
- Duplicate merge that keeps registration history and order references.
- Privacy actions for personal-data export, anonymization and safe deletion of
  empty profiles.

## Data Ownership

People records are the durable community identity. Registrations and orders
reference People, but financial records remain owned by Ticketing. Merge actions
move person references; they do not delete orders.

## Privacy

People data is private and hidden from public queries and REST. If a person has
financial or registration history, deletion is intentionally blocked and
anonymization is offered instead.

## Future Extensions

The profile tab shell reserves room for documents, certificates, memberships,
newsletter preferences, instructor portals and national association modules.
