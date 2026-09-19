# P8 sealed tool receipts

**Schema:** p8-tool-receipts/1
**Item:** P8.7

Seven branch reports and cold installed consumer evidence are green. This seal pins the immutable
tool-surface graph for downstream P9/P10/P11 without claiming hosted release, live MCP endpoints,
or central control-plane deployment completion.

## Requirements

| R | Items | Local case receipts | Installed receipts | Pending effective gates | Owner | Resume |
|---|---|---|---|---|---|---|
| R08 | P8.1/P8.6/P8.7 | QUAL-01..05; CONTRIB-01..08 | `delivery/tool-install-qualification-20260915/receipt.md` | live control-plane MCP/central handlers | P10 | external map source-port only |
| R36 | P8.2/P8.3/P8.4/P8.6/P8.7 | DESC/CAT/INVOKE/CONTRIB/INST | `delivery/tool-install-qualification-20260915/receipt.md` | transport/live endpoint proof | P9/P10/P11 | mcp-tools-projection/1 parity only |
| R50 | P8.1/P8.5/P8.7 | QUAL/INTRO/INST | `delivery/tool-install-qualification-20260915/receipt.md` | compatibility passport | P9 | registry why remains read-only |

## Producer edges

| Producer | Contract/version | Consumer | Required receipt | Failure model |
|---|---|---|---|---|
| P8.1–P8.6 | p8-tool-receipts/1 branch reports | P8.7 | seven branch findings | missing branch blocks seal |
| P8.7 | tool-install-qualification/1 | P9 | `delivery/tool-install-qualification-20260915/receipt.md` | hosted matrix separately owned |
| P8.7 | tool-install-qualification/1 | P10 | `delivery/tool-install-qualification-20260915/receipt.md` | central deployment separately owned |
| P8.7 | p8-tool-receipts/1 | P11 | this seal | consumer acceptance separately owned |

## Contributions

| R | Tool tuple | Owner | Plane | Producer path | Producer hash | Contract hash | Case evidence | Disposition |
|---|---|---|---|---|---|---|---|---|
| R08 | `files.read_retained@storage-context/1` | P3.1 | tenant-local | `findings/P3-storage-context.md` | `7ef4c868f1ad94685c9a07fe3eaed5c5251a1377cf0694c586c03d7fd191635d` | `edfceeeb9be0b5e2f6dc612fb5e1d9f117091b6f0cba4b19a7b77affb729a12c` | archive-lab-storage | PASS |
| R08 | `maintenance.export_account@claim-maintenance/1` | P3.2 | tenant-local | `findings/P3-claim-maintenance.md` | `d5e7bee1c11437de215435b942b47661269a61a6e2c7f2146cd73487822bca3a` | `4b4266140de7d3c9dcd0bf5c31350f79d2ab0594fa5119fd539fbde43c842d74` | archive-lab-maintenance | PASS |
| R08 | `identity.read_context@identity-transport/1` | P3.3 | tenant-local | `findings/P3-identity-transport.md` | `a35089c5b27e4e596779d902037bc498da21bbb30a9f65f4f953bca0dc1ae1ae` | `30f2a753bc8b01e382136a8cb469b7d53a91eecae676cd816e2e85d7a1c17713` | archive-lab-identity | PASS |
| R08 | `accounts.list_menu@account-list/1` | P3.6 | tenant-local | `findings/P3-account-list.md` | `f0b05bfd78f1abb899effb7a16929caffd21e33db1c7738f088534999fa77a24` | `67ed7c5eaa67ba405e30c38e3af99cbb5c4f5c5ce65d589afc776d85ccd1a5af` | archive-lab-account-list | PASS |
| R08 | `panels.render_slot@panel/1` | P4.1 | tenant-local | `findings/P4-panels.md` | `6b976a29ec7a21dfaa4ce253d10a081ff8448d577dc6202f70a137cfa068a945` | `fa0b072c00a9c292a904dda10f59a96759825c3f42cdb7fa08c36c25ab48b4cb` | PAN-01 | PASS |
| R08 | `workflow.wakeup_local@wakeup/1` | P5.1 | tenant-local | `findings/P5-wakeup-receipts.md` | `a88145a6cf68a8357b739a0a4b5e7dda181162e9b136ca4dbc505c1ac66c8f03` | `c78a772d6004150bec882c6abc053f552d37895fa70a640b1066a2bdf60b6533` | WAK-01 | PASS |
| R36 | `search.query@search/1` | P6.6 | tenant-local | `findings/P6-data-receipts.md` | `3168ed89da964064ff6c7f6556673405a98df692da1e8ff46462ad43713970e3` | `8b1895b26d7cd7503797aefd1e24ac23e429148500206d14a38127cf3940af6d` | SRCH-01 | PASS |
| R36 | `locale.format@locale/1` | P7.5 | tenant-local | `findings/P7-utility-receipts.md` | `279163d4d9bed471ef8fbb25465897307c4f5ae8144303b862312d6e549df036` | `9f14410ce59bf6a8a360cb2e2aa512d2a9bea98a32d620476cc1d52adba81ef6` | LOC-01 | PASS |
| R36 | `fx.convert@fx/1` | P7.6 | tenant-local | `findings/P7-utility-receipts.md` | `279163d4d9bed471ef8fbb25465897307c4f5ae8144303b862312d6e549df036` | `5be9e5c26f17cd9cbd0ecde2ac9ae76d0bee83935035b50991aa50be3cf43d44` | FX-01 | PASS |
| R36 | `geo.resolve@geo/1` | P7.7 | tenant-local | `findings/P7-utility-receipts.md` | `279163d4d9bed471ef8fbb25465897307c4f5ae8144303b862312d6e549df036` | `afbc2fb402979aa07952f8a3f5a50d2f560fc8a5f0547700bcd0b1e78908d7a1` | GEO-01 | PASS |
| R36 | `tasks.complete@recurrence/1` | P7.1 | tenant-local | `findings/P7-utility-receipts.md` | `279163d4d9bed471ef8fbb25465897307c4f5ae8144303b862312d6e549df036` | `ca0b71a6f4c5b8142f32c410039b1582be1f636f9e8312bacc1144c500743a4f` | REC-01 | PASS |
| R08 | `tenancy.get_account_status` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `tenancy.retry_provisioning` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `tenancy.replenish_pool` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `tenancy.list_cells` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `tenancy.get_cell_health` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `tenancy.start_migration_batch` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `tenancy.get_migration_status` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `tenancy.move_account` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `storage.get_tenant_usage` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `storage.start_export` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `tls.get_certificates_expiry` | Provisioner / storage / TLS ops owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `identity.get_identity` | Control-plane operator / central identity owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `identity.list_memberships` | Control-plane operator / central identity owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `identity.grant_membership` | Control-plane operator / central identity owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `identity.revoke_membership` | Control-plane operator / central identity owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `identity.resync_account_users` | Control-plane operator / central identity owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `identity.get_sync_status` | Control-plane operator / central identity owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `identity.invalidate_switcher_cache` | Control-plane operator / central identity owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |
| R08 | `identity.list_invitations` | Control-plane operator / central identity owner | control-plane | `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` | CONTRIB-01 | external-owner |


## Sealed inputs

| Path | SHA256 |
|---|---|
| `findings/P8-source-qualification.md` | `9bacfc2e8f3a69504afe93e1b9bbd3a7b0c65ff0e98e0763f46b704ce07abe1f` |
| `findings/P8-descriptors.md` | `e02d0ce4c1e7d4eafa53755fcfae59e1158d405d582589313397de0a7b25a5cd` |
| `findings/P8-catalogue-policy.md` | `b795d425b0be00db79f00889af91059b561ec1a4d7c56d931a0b6554c725870a` |
| `findings/P8-invocation.md` | `4d8c6636dd5b70775d9f011464086baf4c08147c50752e4bffd92c8c805d5441` |
| `findings/P8-introspection.md` | `43b3ccaf4a36bcef93af4206f5df177cb85ce45341afaf05d524c44263652742` |
| `findings/P8-contributions.md` | `e8a80e771ede2dff9c63d7b7debd4f0d64cd7d875e68110ec2b25a15b294e623` |
| `findings/P8-installed.md` | `7b474af1804f37b59b4b3fc62b3fb8bbb0d6af64729a1a1ec412297658b043e7` |
| `research/P8-tool-design.md` | `ded33e03fc260e632c06a4fec0e66e404cb29e6383e921e24c679ffadd8802ca` |
| `research/P8-design-rag.md` | `d1a2cbfe4dee9fe0c48b013cc746b195a43a68bf6d4f17f11ca024245d2796e3` |
| `decisions/D13-tool-introspection.md` | `4470953848bfbe2c24b54782db1fed20ec90a2cc95b9d1d0c04b517cecc02fb6` |
| `research/05-integrated-design.md` | `6aca22518b510e17d108e0878baaa512b152596b56d75b185adc73494c117e9e` |
| `research/03-gates.md` | `b9a2ad93007d76152c25dbf2f4c6b908c2182482900c1f7f144c6ffb5857db3f` |
| `phases/P8/P8.md` | `dd4609e9e73414ed7f40d39aab20a8ea1b74788c630d5aa26775ef9ad2b2b22f` |
| `findings/P1-requirements-lock.md` | `6c2fd0af8072fd681eb095c748fa67d9fc5aab5dad252cefbf06c6597b36e0c2` |
| `findings/P1-acceptance-map.md` | `682e74f02a34abe361be072a22e0e980cb6a8b9f4e0039ab93d8041fe2d4d7b9` |
| `findings/P1-evidence-ledger.md` | `ef089764dbdb6f85e0aaf54c318acf8405a279259bed5cfd16331c88fc3ccf87` |
| `findings/P2-contract-catalogue.md` | `ee24d2dd236b776ccf14027e7731921d419adb1be2cf39728a2d7ccb0bfb2573` |
| `findings/P2-contract-proposals.md` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` |
| `findings/P2-consumer-cases.md` | `2e8cdc24b0a9193d418aa2e17e53ad4c9fff48c893d279b4e1d8f7740a61f760` |
| `findings/P2-gate-requests.md` | `77b763002a9509b5ab995046ed838bbcc19f9e6c1786cc6da6a6c994172e5cdd` |
| `delivery/tool-install-qualification-20260915/receipt.md` | `d58eb7e3631af26e52e1753b7ec4602ff011734244b2ba60b10826fa66e641e1` |
| `findings/P3-storage-context.md` | `7ef4c868f1ad94685c9a07fe3eaed5c5251a1377cf0694c586c03d7fd191635d` |
| `findings/P3-claim-maintenance.md` | `d5e7bee1c11437de215435b942b47661269a61a6e2c7f2146cd73487822bca3a` |
| `findings/P3-identity-transport.md` | `a35089c5b27e4e596779d902037bc498da21bbb30a9f65f4f953bca0dc1ae1ae` |
| `findings/P3-account-list.md` | `f0b05bfd78f1abb899effb7a16929caffd21e33db1c7738f088534999fa77a24` |
| `findings/P4-panels.md` | `6b976a29ec7a21dfaa4ce253d10a081ff8448d577dc6202f70a137cfa068a945` |
| `findings/P5-wakeup-receipts.md` | `a88145a6cf68a8357b739a0a4b5e7dda181162e9b136ca4dbc505c1ac66c8f03` |
| `findings/P6-data-receipts.md` | `3168ed89da964064ff6c7f6556673405a98df692da1e8ff46462ad43713970e3` |
| `findings/P7-utility-receipts.md` | `279163d4d9bed471ef8fbb25465897307c4f5ae8144303b862312d6e549df036` |
| `delivery/tool-contribution-qualification-20260915/fixtures/external-control-plane-map.json` | `c62b9f685e61f55e0d1dfaf3d0cb3878f7eeaf11e922752725427322397a7ab9` |
| `phases/P8/P8.1.md` | `ba5fac92cfc5420376ad6b7044dd987973aec29b91ab382fee62284244c0151f` |
| `phases/P8/P8.2.md` | `18909044e5d3234480a4bae08e950c02562a0422dee37fca0c0f01b81f9034fb` |
| `phases/P8/P8.3.md` | `010e6166076f3cab8425d533ba06e8e067c50593c68607cebd32a5bc6f311b0e` |
| `phases/P8/P8.4.md` | `91967e0432eb5d921a5f3688e977aff29b7c9f65147c77357352a3f30ce65257` |
| `phases/P8/P8.5.md` | `5cbc88adbaf7d49f5a516d4c9214673b9123daa7789fe6ed5925c09c4056eec2` |
| `phases/P8/P8.6.md` | `57f66238ac62c3e3e38275d1dbec5bf17a5b4da4ce5c83a5fbc653a3bf067db7` |
| `phases/P8/P8.7.md` | `670863303d0ce78451e6f5796d5d639c8a8882ba8ce6abaff1c61415d388c127` |
