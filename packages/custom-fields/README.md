# CoreX Custom Fields

Custom Fields provides versioned definitions, typed values and field-use policy checks for an entity such as equipment. It stores each record's values in a PostgreSQL JSONB document.

## Installation

Install the package with Core, configure the host's trusted scope and policy bindings, then migrate the package's tenant schema set. A standalone installation requires no Search package.

## Configuration

`corex-custom-fields.ddl` defaults to `true`. Set it to `false` to disable concurrent index DDL. When DDL is enabled, the host's `AccountConnectionResolver` must verify a direct PostgreSQL writer, the current physical scope, and the managed PostgreSQL schema.

## Values

Resolve FieldCatalogue to get the effective schema for an EntityHandle. A FieldDefinition has a stable UUID. A rename changes the machine key while retaining that identity. Use the schema revision and the current document revision to construct a FieldPatch. SetField accepts FieldInput, including explicit JSON null. RemoveField erases the slot. DocumentRevision::absent() is the expectation for a new document. FieldValues::update verifies each field and the record, commits one document revision, and returns a durable receipt. Use the patch's token method with the same scope, record and operation key when retrying the same request.

FieldValues::read and readMany take Core's FieldSelection of field UUIDs and return authorized snapshots. FieldFilter::compile checks the capability, current filter grant and schema revision before producing a typed predicate. A field-use grant carries its own policy witness.

## Extending Custom Fields

Extensions register FieldType, optional FieldConverterProvider, and DeclaredFields implementations as Laravel services tagged by their respective interface names. Each type declares its codec identity and version, normalization, supported uses and operators. Builtin decimal and money use exact amounts encoded as strings.

## Schema changes

A runtime change is planned against the current schema revision and applied by immutable plan reference, digest and operation key. The schema worker stages pending slots, backfills existing documents, verifies, switches the head, and drains old mappings before cleanup. Run `corex:custom-fields:work --limit=1` in the verified worker scope to process one bounded phase or batch.

A value update emits fields.values.changed.v2. An admitted schema operation emits fields.schema.requested.v2 and its published head emits fields.schema.changed.v2. These facts carry field IDs, refs or digests. Outbox append and the corresponding owner mutation share the caller's transaction.

## Failure and recovery

Inspect the operation reference for its phase, blockers and historical conversion failures. A failed conversion blocks the switch. After correcting the source value, retry the immutable plan using the current claim generation. A change to the plan's inputs requires a new plan. Index work waits for a verified direct writer and checks the physical index before acknowledging a reclaimed operation.
