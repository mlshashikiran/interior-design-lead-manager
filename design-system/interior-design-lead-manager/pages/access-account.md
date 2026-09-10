# Access and Account — Page Rules

Phase 5 is implemented: sign-in, reset, and account password controls expose labelled visibility toggles; new-password fields show requirements and nearby matching errors; redirect notices receive focus; submissions expose busy feedback; profile fields have complete autofill metadata; and the workspace skip destination is programmatically focusable.

- Login uses the split layout on desktop and one focused panel on mobile. Form title, error, fields, submit, recovery, and access help follow reading order.
- Authentication errors are specific enough to recover but preserve account-enumeration protections.
- Password fields offer a labelled reveal control and expose requirements before submission.
- My Account separates identity summary from profile/security tasks. Success moves focus to the status message after navigation.
- Add a skip link before workspace navigation and restore the active page heading as the main landmark target.
