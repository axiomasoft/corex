# DATA-004 exception for the cloud authorization schema

**Owner** · `axiomasoft/corex-access`

The tenant-cloud migration `packages/access/database/migrations/tenant-cloud/2026_10_01_030050_add_aut_role_assignments_cloud_fks.php` links `aut_role_assignments.workspace_id` to `wsp_workspaces.id` and `aut_role_assignments.granted_by` to `users.id`. Tenancy owns both referenced tables. Boxed installations can use Access without installing them.

The `axiomasoft/corex-access:tenant-cloud` schema set requires a registered tenancy schema and adds the constraints separately. The boxed profile installs the ordinary tenant set. This exception remains in effect until the data owner approves a replacement and supplies its migration.
