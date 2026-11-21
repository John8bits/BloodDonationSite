# BLife PH

A web-based community for connecting blood donors with blood requesters.

## Database update for the message inbox

After applying the existing member-panel and donation-offer migrations, run
`database_sql/messages_inbox_migration.sql` against the configured database. It
adds inbox read state, message reports, optional donor availability times, and
an administrator-controlled donor verification flag. Existing members are
unverified by default; administrators can manage verification from Member
Management.
