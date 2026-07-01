# Documents, Certificates & Knowledge

Phase 10 adds a private document library and knowledge base to TAKA Platform.
The goal is to make reusable tour and seminar assets searchable and assignable
instead of burying them in email threads or notes.

## Scope in Phase 10

- Admin page under `TAKA Platform -> Documents`.
- Private document library.
- Private knowledge base.
- Certificate template scaffold.
- Global search across documents, knowledge articles and tags.
- Assignments to tours, events, people, organizers, venues, planning items,
  orders and volunteer roles.

## Data Model

The module stores private post types:

- `taka_document`
- `taka_knowledge`
- `taka_cert_tpl`

Documents reference WordPress attachment IDs instead of implementing a separate
upload system. This keeps media ownership inside WordPress while allowing TAKA
metadata and assignments around the file.

## Visibility

Visibility metadata is prepared for:

- public
- organizer only
- volunteer
- admin only
- specific users

Phase 10 keeps all records private in WordPress. Public rendering can be added
later where explicitly desired.

## Certificates

Certificate templates are metadata-only in this phase. Future phases can add:

- attendance certificates
- instructor certificates
- volunteer certificates
- speaker certificates
- QR verification
- digital signatures
- PDF generation

## Knowledge Base

Knowledge articles support reusable operational guidance such as seminar
checklists, travel guidelines, volunteer handbooks and emergency procedures.
Articles can be assigned to tours, events, planning items and volunteer roles.
