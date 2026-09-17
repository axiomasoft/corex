# Concrete neutral schemas: ordered columns, key constraints, checks. Not applied to any project DB.
# K references follow current IdColumns profile; u UUID explicit existing P4/P5 design, c counter uint64.
Z={
4:{
'corex_membership_pairs':('account_id:u;identity_id:u;product:s64;membership_id:u?;state:s16;revision:c;generation:c;lease_until:t?;snapshot_digest:h;restore_epoch:h;witness_digest:h;read_started_at:t;observed_at:t;updated_at:t', ['PRIMARY KEY(account_id,identity_id,product)'],["state IN ('active','removed','suspended','unknown')","(state <> 'active' OR (membership_id IS NOT NULL AND lease_until IS NOT NULL))"]),
'corex_membership_inbox':('id:k;account_id:u;identity_id:u;product:s64;event_id:s128;request_digest:h;revision:c;generation:c;restore_epoch:h;state:s16;committed_at:t?', ['UNIQUE(account_id,identity_id,product,event_id)'],["state IN ('pending','applied','conflict','unknown')"]),
},
8:{
'med_files':('id:u!;request_key:s128;request_digest:h;state:s16;version:c;object_key:s1024;mime_type:s128;kind:s64;bytes:c;checksum:h;uploaded_actor:s128;workspace_scope_key:s255?;created_at:t;updated_at:t;deleted_at:t?', ['UNIQUE(request_key)'],["state IN ('staged','ready','deleting','deleted','quarantined')"]),
'med_relations':('id:u!;file_id:u;entity_handle:s128;entity_id:s128;slot:s64',['UNIQUE(file_id,entity_handle,entity_id,slot)','FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT'],[]),
'med_operations':('id:u!;operation_key:h;kind:s16;file_id:u;state:s16;source_version:c;profile_digest:h?;claim_epoch:c;attempts:i;next_attempt:t?;lease_until:t?;attempt_output_key:s1024?',['UNIQUE(operation_key)','FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT'],["kind IN ('conversion','purge')","state IN ('pending','claimed','ready','failed','unknown','cancelled')","attempts >= 0"]),
'med_conversions':('id:u!;file_id:u;profile_key:s128;profile_digest:h;source_version:c;state:s16;output_key:s1024?;mime_type:s128?;bytes:c?;checksum:h?;width:i?;height:i?',['UNIQUE(file_id,profile_key,profile_digest,source_version)','FOREIGN KEY(file_id) REFERENCES med_files(id) ON DELETE RESTRICT'],["state IN ('pending','ready','failed')"])
},
9:{
'ntf_notifications':('id:u!;recipient_kind:s64;recipient_id:s128;request_key:s128;request_digest:h;event_type:s128;template_key:s128;payload:j;related:j?;read_at:t?;dismissed_at:t?;cancelled_at:t?;revision:c;created_at:t;updated_at:t',['UNIQUE(recipient_kind,recipient_id,request_key)'],[]),
'ntf_recipient_state':('kind:s64;id:s128;preference_revision:c',['PRIMARY KEY(kind,id)'],[]),
'ntf_deliveries':('id:u!;notification_id:u?;digest_run_id:u?;channel:s64;delivery_key:h;state:s16;claim_epoch:c;attempts:i;next_attempt:t?;lease_until:t?;admitted_revision:c;provider_ref:s255?;error:j?;sent_at:t?;message:j?;message_hash:h?;template_version:s128?;policy_version:s128?;driver_version:s128?',['UNIQUE(delivery_key)','FOREIGN KEY(notification_id) REFERENCES ntf_notifications(id) ON DELETE RESTRICT'],["((notification_id IS NULL) <> (digest_run_id IS NULL))","state IN ('pending','claimed','sent','failed','skipped','cancelled','unknown')","attempts >= 0"])
},
10:{
'ntf_digest_runs':('id:u!;recipient_kind:s64;recipient_id:s128;period:s16;period_start:t;period_end:t;batch_seq:i;membership_hash:h;state:s16;created_at:t',['UNIQUE(recipient_kind,recipient_id,period,period_start,batch_seq)'],["period IN ('hourly','daily')","period_start < period_end","batch_seq >= 0","state IN ('frozen','claimed','sent','unknown','cancelled')"]),
'ntf_digest_items':('id:u!;run_id:u;notification_id:u;channel:s64',['UNIQUE(notification_id,channel)','FOREIGN KEY(run_id) REFERENCES ntf_digest_runs(id) ON DELETE RESTRICT','FOREIGN KEY(notification_id) REFERENCES ntf_notifications(id) ON DELETE RESTRICT'],[])
},
11:{
'sys_saved_view_scopes':('entity_code:s128;scope_key:s1024',['PRIMARY KEY(entity_code,scope_key)'],[]),
'sys_saved_views':('id:u!;entity_code:s128;workspace_id:s255?;owner_kind:s64?;owner_id:s255?;scope_key:s1024;kind:s64;name:j;config:j;schema_version:s128;revision:c;is_default:b;default_scope_key:s1024?;position:i;created_by:s128;created_at:t;updated_at:t',['UNIQUE(entity_code,default_scope_key)','FOREIGN KEY(entity_code,scope_key) REFERENCES sys_saved_view_scopes(entity_code,scope_key) ON DELETE RESTRICT'],["((owner_kind IS NULL) = (owner_id IS NULL))","((is_default = TRUE AND default_scope_key = scope_key AND default_scope_key IS NOT NULL) OR (is_default = FALSE AND default_scope_key IS NULL))"])
},
12:{
'sys_wakeup_requests':('id:u!;workspace_scope_key:s255;kind:s128;request_key:s128;request_digest:h;fire_at:t;window_start:t?;window_end:t?;period:s16?;payload:j;status:s16;generation:c;attempt:i;lease_until:t?;retry_at:t?;failure_code:s64?;created_at:t;updated_at:t;completed_at:t?',['UNIQUE(workspace_scope_key,kind,request_key)','UNIQUE(workspace_scope_key,request_key)'],["status IN ('pending','running','completed','cancelled','failed')","((window_start IS NULL AND window_end IS NULL AND period IS NULL) OR (window_start IS NOT NULL AND window_end IS NOT NULL AND window_start < window_end AND period IN ('hourly','daily','custom')))","((status = 'running') = (lease_until IS NOT NULL))","attempt >= 0"])
},
13:{
'corex_workflow_intents':('id:k;request_key:s128;payload_hash:h;context_hash:h;restore_epoch:h;module:s128;kind:s16;definition_ref:s255?;version_ref:s255?;subject_ref:s255;payload:j;state:s16;provider_instance:s255?;claim_epoch:c;created_at:t;updated_at:t',['UNIQUE(request_key)'],["kind IN ('trigger','signal','transition')","state IN ('pending','claimed','started','noop','conflict','unknown','failed')"])
},
17:{
'corex_transfer_jobs':('id:k;source_manifest:j;source_hash:h;mapping_snapshot:j;mapping_hash:h;schema_hash:h;restore_epoch:h;mode:s24;state:s24;checkpoint_seq:c;cursor:j;stats:j;claim_epoch:c;created_at:t;updated_at:t',[''],["state IN ('pending','running','unknown','completed','cancelled','failed')","mode IN ('import','dryRun','export')"]),
'corex_transfer_rows':('id:k;job_id:K;ordinal:c;external_identity_hash:h;operation_key:h;payload_hash:h;result:s24;error:j?;request:j?;provider_task:s255?;epoch:c',['UNIQUE(job_id,ordinal)','FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT'],["result IN ('applied','updated','noop','invalid','conflict','unknown','unsupported')"]),
'corex_transfer_refs':('id:k;entity:s128;external_system:s128;external_id:s255;target_id:s255?;source_revision:s255;source_updated_at:t?;payload_hash:h;record_version:c',['UNIQUE(entity,external_system,external_id)'],[]),
'corex_transfer_outbox':('id:k;job_id:K;operation_key:h;payload_hash:h;request:j;state:s24;claim_epoch:c;provider_task:s255?;dedup_deadline:t?',['UNIQUE(operation_key)','FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT'],["state IN ('pending','submitted','committed','failed','unknown')"])
},
18:{
'corex_export_manifests':('id:k;job_id:K;capture_attempt:h;schema_hash:h;restore_epoch:h;roster:j;parts_hash:h?;snapshot_mode:s24;state:s24',['UNIQUE(capture_attempt)','FOREIGN KEY(job_id) REFERENCES corex_transfer_jobs(id) ON DELETE RESTRICT'],["snapshot_mode IN ('coherent','liveBounded')","state IN ('capturing','sealed','emitting','completed','aborted')"]),
'corex_export_parts':('id:k;manifest_id:K;part_no:c;immutable_ref:s1024;hash:h;byte_length:c;row_counts:j;state:s24',['UNIQUE(manifest_id,part_no)','FOREIGN KEY(manifest_id) REFERENCES corex_export_manifests(id) ON DELETE RESTRICT'],["state IN ('staged','verified','emitted')"])
},
20:{
'corex_search_dirty':('id:k;entity:s128;record_id:s255;revision:c;schema_hash:h;restore_epoch:h;payload_hash:h;deleted:b;committed_event_ref:s255',['UNIQUE(entity,record_id,revision,restore_epoch)'],[]),
'corex_search_lanes':('id:k;scope_hash:h;generation:h;index_uid:s255;restore_epoch:h;state:s24;claim_epoch:c;active_task:s255?;request_hash:h?',['UNIQUE(scope_hash,generation)'],["state IN ('idle','claimed','submitted','unknown','quarantined','retired')"]),
'corex_search_rebuilds':('id:k;scope_hash:h;generation:h;restore_epoch:h;schema_hash:h;old_uid:s255;new_uid:s255;state:s24;cursor:j;committed_watermark:j;swap_task:s255?;marker_hash:h',['UNIQUE(scope_hash,generation)'],["state IN ('capturing','catchingUp','fenced','swapping','verified','unknown','failed')"])
},
21:{'corex_task_operations':('id:k;request_key:s128;payload_hash:h;kind:s32;result_ref:j;restore_epoch:h;created_at:t',['UNIQUE(request_key)'],["kind IN ('createProject','createSprint','recordTime','materialize','completionCapture')"])},
23:{
'corex_task_series':('id:k;series_key:s128;immutable_revision:s128;definition_hash:h;definition:j;template:j;actor:j;context_hash:h;restore_epoch:h',['UNIQUE(series_key,immutable_revision)'],[]),
'corex_task_series_state':('series_key:s128;active_revision:s128;retired:b;complete_through:s128?;cursor:j?;restore_epoch:h;generation:c',['PRIMARY KEY(series_key)','FOREIGN KEY(series_key,active_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT'],[]),
'corex_task_occurrences':('id:k;series_key:s128;immutable_revision:s128;occurrence_key:h;original_identity:j;effective_at:s128;task_ref:j?;operation_key:s128;state:s24',['UNIQUE(series_key,immutable_revision,occurrence_key)','UNIQUE(operation_key)','FOREIGN KEY(series_key,immutable_revision) REFERENCES corex_task_series(series_key,immutable_revision) ON DELETE RESTRICT'],["state IN ('pending','committed','cancelled','retired','unknown')"])
},
25:{
'corex_rate_snapshots':('id:k;schema_version:s32;base:s3;quote:s3;rate_decimal:s64;valid_on:s10;observed_at:t;source:s128;publication_id:s128;publication_revision:s128;payload_hash:h',['UNIQUE(source,publication_id,publication_revision,base,quote,valid_on)'],[]),
'corex_rate_batches':('id:k;operation_key:h;payload_hash:h;format_hash:h;source_manifest:j;generation:c;state:s16;created_at:t',['UNIQUE(operation_key)'],["state IN ('staged','published','conflict','failed')"]),
'corex_rate_current':('base:s3;quote:s3;source:s128;snapshot_id:K;generation:c',['PRIMARY KEY(base,quote,source)','FOREIGN KEY(snapshot_id) REFERENCES corex_rate_snapshots(id) ON DELETE RESTRICT'],[])
},
26:{
'corex_reference_datasets':('snapshot_id:s128;manifest:j;raw_hash:h;normalized_hash:h;state:s16;created_at:t',['PRIMARY KEY(snapshot_id)'],["state IN ('staged','published','retired')"]),
'corex_reference_records':('snapshot_id:s128;namespace:s128;source_id:s255;kind:s64;parent_namespace:s128?;parent_source_id:s255?;names:j;normalized_name:s255;country_key:j?;coordinates:j?;timezones:j;provenance:j',['PRIMARY KEY(snapshot_id,namespace,source_id)','FOREIGN KEY(snapshot_id) REFERENCES corex_reference_datasets(snapshot_id) ON DELETE RESTRICT','FOREIGN KEY(snapshot_id,parent_namespace,parent_source_id) REFERENCES corex_reference_records(snapshot_id,namespace,source_id) ON DELETE RESTRICT'],["((parent_namespace IS NULL) = (parent_source_id IS NULL))"]),
'corex_reference_aliases':('snapshot_id:s128;namespace:s128;source_id:s255;locale:s128;alias:s255;normalized_alias:s255',['PRIMARY KEY(snapshot_id,namespace,source_id,locale,alias)','FOREIGN KEY(snapshot_id,namespace,source_id) REFERENCES corex_reference_records(snapshot_id,namespace,source_id) ON DELETE RESTRICT'],[]),
'corex_reference_current':('dataset_key:s128;snapshot_id:s128;generation:c',['PRIMARY KEY(dataset_key)','FOREIGN KEY(snapshot_id) REFERENCES corex_reference_datasets(snapshot_id) ON DELETE RESTRICT'],[])
}}
