## Business Logic Questions Log

### 1. What "published" means for recipes
- Question: The prompt requires that only Approved recipes can be published, but does not specify whether publishing targets an external public site or an internal application surface. Which is it?
- My Understanding: The prompt never required an external website, and the system is designed as a fully offline local-network deployment. Publishing should stay internal.
- Solution: Treat recipe publishing as an internal system state plus visible published views in the same application, with Draft/In Review/Rejected items excluded from published browsing.

### 2. Who performs final settlement approval
- Question: Finance users generate statements, reconcile variances, and route settlements for approval, but the prompt does not explicitly name the approving role. Who approves?
- My Understanding: Separation of duties requires that the preparer and the approver be distinct roles. Finance Clerks prepare; a higher authority approves.
- Solution: Use Finance Clerk for statement preparation, reconciliation, and submission; use Administrator for final approval and lock transitions.

### 3. How analytics/search features stay reviewable in a fully offline build
- Question: The prompt requires dashboards, report generation, and full-text/multi-dimensional search over local records, but does not define the initial local data shape or whether reviewable demo data is available. How do we make this reviewable?
- My Understanding: Without representative seed data the system cannot be meaningfully demonstrated or tested in an offline environment.
- Solution: Provide local seeded records covering recipes, review history, communities, group leaders, participants, orders, analytics snapshots, freight rules, statements, and audit events.

### 4. How the multi-site organization scope should behave
- Question: The prompt describes a multi-site organization but does not spell out how site boundaries affect permissions, analytics visibility, reporting scope, or settlement ownership. What are the rules?
- My Understanding: Sites should be first-class organizational partitions, with role-based access determining cross-site versus single-site visibility.
- Solution: Model site as a core dimension for recipes, communities, orders, analytics snapshots, reports, settlements, and audit views. Administrators and Read-Only Auditors can review cross-site data; other roles are constrained to their permitted site scope.

### 5. What the named searchable record examples must cover
- Question: The prompt explicitly calls out searches across positions, companies, participants, and workflow status, but does not explain how those examples map onto the rest of the community-commerce domain model. Are they optional examples or hard requirements?
- My Understanding: The prompt named them explicitly, so they should be treated as required searchable dimensions rather than optional examples.
- Solution: Ensure the offline search/reporting model includes searchable records and indexed fields covering participant identity, company/organizational affiliation, position/title, and workflow status alongside the commerce/content/settlement entities.
