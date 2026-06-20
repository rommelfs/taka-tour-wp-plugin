# TAKA Platform Roadmap

This roadmap describes the planned evolution of TAKA Platform as a generic Open Source Event Management Platform.

It is intentionally product-focused. It describes the platform direction and expected capabilities, not implementation details. The guiding principle is the same as in `AGENTS.md`: TAKA should be suitable for many independent organizations, not only for the first Karate Tour deployment.

## Current Status

TAKA Platform is a WordPress-based event management platform with a mature first deployment for an international seminar tour.

Current capabilities include:

- event, organizer and venue management
- multi-organizer event relationships
- ticket panel rendering with Pretix support
- multilingual frontend labels and dynamic content
- reusable Content Sections
- reusable Content Blocks and Content References
- Translation Packages for provider-independent translation workflows
- configurable option lists for recurring event fields
- organizer access control and a frontend organizer dashboard
- normalized export feeds for external integrations
- import/export workflows for backup, migration and external processing

The current Karate Tour website remains a reference implementation and bundled fallback dataset, but it is not the product boundary.

## Current Active Milestone

### 2.1.x / 2.2.x: Code Audit, Security and Cleanup

The current stabilization milestone focuses on confidence rather than new capabilities.

Goals:

- audit security-sensitive flows
- reduce legacy risk
- document the architecture
- keep backwards compatibility
- protect translation and ticketing workflows
- preserve existing frontend behavior
- prepare the platform for broader extension

This phase should keep feature additions small and deliberate.

## Upcoming Milestones

### 2.2: Reusable Content Blocks and References

Reusable Content Blocks establish a single source of truth for shared editorial content.

Product outcomes:

- editors can reuse content across homepage sections, events and future object types
- shared content is translated once
- content updates propagate to all references
- local content remains available as fallback
- translation packages become smaller and clearer

### 2.3: Organizer Dashboard and Frontend Editing

The organizer experience should become less dependent on the WordPress backend.

Product outcomes:

- organizers can manage their own events from the frontend
- admins can keep sensitive WordPress areas hidden from non-admin users
- organizer editing flows become simpler and more focused
- permission behavior becomes more visible and predictable

### 2.4: Editorial Workflow

TAKA should support the real lifecycle of public content.

Product outcomes:

- draft content
- review states
- missing translation states
- publish readiness indicators
- clearer separation between internal notes and public content
- reduced risk of publishing incomplete or untranslated material

### 2.5: Collaboration

Events often involve more than one person or organization. The platform should support that without losing accountability.

Product outcomes:

- multiple organizers and contributors can collaborate safely
- permissions match real relationships
- editors can see change history
- teams can discuss content in context
- admins retain final control over public output

## Major Platform Milestones

### 3.0: Translation Center

Translation Packages are the foundation. The next step is a more complete Translation Center.

Product outcomes:

- overview of missing translations
- validation before import
- AI-assisted translation suggestions
- human translator workflows
- glossary and terminology checks
- source text change tracking
- safe import/export review

AI support should assist editors, not silently overwrite human work.

### 3.1: Integrations

TAKA should communicate cleanly with external systems without giving up source-of-truth ownership.

Product outcomes:

- stable REST APIs
- ICS calendar feeds
- Events Manager export workflows
- Pretix integration improvements
- additional ticket provider support
- WooCommerce and commerce-oriented extensions
- import/export profiles for common tools

TAKA remains the canonical event system unless an administrator explicitly chooses otherwise.

### 3.2: Program Engine

Events need richer schedules than a single date/time field.

Product outcomes:

- multi-day event programs
- program items
- timetables
- rooms
- tracks
- speakers or instructors
- breaks and social events
- printable and accessible schedules

### 3.3: Media Collections

Event platforms need structured media, not only individual images.

Product outcomes:

- reusable media collections
- event galleries
- organizer and venue galleries
- credits and licensing metadata
- image role definitions
- future support for video or document attachments

## Long-Term Milestone

### 4.0: White-label Platform

TAKA should become a reusable platform for many event organizations.

Product outcomes:

- configurable branding
- configurable content defaults
- reusable themes or design presets
- clean separation between platform logic and deployment-specific content
- onboarding flows for new organizations
- documentation suitable for external contributors

## Backlog

Near- and medium-term backlog themes:

- stronger permission model for organizers, editors and translators
- deeper Content Reference support for organizers, venues and ticket panels
- non-destructive sync providers
- richer translation validation
- accessibility review across admin and frontend
- better event import previews
- configurable ticket provider assets
- structured deprecation policy for legacy shims
- automated tests for security-sensitive workflows

## Future Ideas

Possible future directions:

- plugin ecosystem
- AI assistant workflows for editors
- QR check-in
- attendance tracking
- certificates
- newsletter integration
- analytics
- SEO assistant
- volunteer management
- sponsor management
- speaker management
- calendar subscriptions
- printable event packs
- mobile-friendly onsite operations

These ideas should be evaluated through the platform-first lens: they should serve many event organizations, not only one deployment.
