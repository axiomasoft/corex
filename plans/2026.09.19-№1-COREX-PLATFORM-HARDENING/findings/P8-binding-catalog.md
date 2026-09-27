# Каталог биндингов «до» — P8.1

`RAG:—` Срез после P7, 2026-09-23. Таблицы §2.1–§2.4 из
`research/P8-design.md` перенесены без смены lifetime, concrete и модуля.
Строки, которых не было в замере 2026-09-19, дописаны в §5. Пустой
Membership-модуль не заводится.

Сверка:

```bash
rg -n '->(singleton|scoped|bind|bindIf)\(' \
  packages/tenancy/src/TenancyServiceProvider.php \
  packages/modules/src/ModulesServiceProvider.php
```

Tenancy: 44 совпадения = 29 строк §1 + 13 строк §5 + 1 строка boot §2.
Modules `register`: 12 совпадений = §3. `instance(ModulesOptions)` в шаблон
`rg` не входит и записан отдельно в §5.

## 1. Tenancy — register()

| Abstract | Lifetime | Concrete | Модуль |
|:--|:--|:--|:--|
| `StanclTenantDatabaseProvisioner` | singleton | self | Provisioning |
| `SchemaCatalogueSerializer` | singleton | self | Database |
| `TemplateIntegrityLock` | singleton | self | Database |
| `DatabaseTemplateIntegrityVerifier` | singleton | self | Database |
| `TemplateIntegrityVerifier` | singleton | `DatabaseTemplateIntegrityVerifier` | Database |
| `DomainTrustStateWriter` | scoped | self | DomainTrust |
| `DomainTrustCoordinator` | scoped | self | DomainTrust |
| `PendingPool` | singleton | self | Provisioning |
| `StanclTenantDatabaseLifecycle` | singleton | self | Provisioning |
| `TenantDatabaseLifecycle` | singleton | `StanclTenantDatabaseLifecycle` | Provisioning |
| `AccountLifecycle` | scoped | self | Provisioning |
| `MigrationWaveOrchestrator` | singleton | self | Provisioning |
| `StanclTenancyManager` | singleton | self | Runtime |
| `TenancyManager` | singleton | `StanclTenancyManager` | Runtime |
| `TenantContextResolver` | singleton | `TenancyManagerContextResolver` | Runtime |
| `TenantStorage` | scoped | `ContextTenantStorage` | Runtime |
| `DatabaseIdentitySyncer` | singleton | self | Identity |
| `IdentitySyncer` | singleton | `DatabaseIdentitySyncer` | Identity |
| `WorkspaceResolver` | singleton | `DatabaseWorkspaceResolver` | Workspace |
| `PrincipalWorkspaceResolver` | bindIf | `DatabaseWorkspaceResolver` | Workspace |
| `RootRegistryWakeupHint` | singleton | self | Wakeup, optional |
| `RootWakeupReconciler` | singleton | self | Wakeup, optional |
| `OidcDiscovery` | singleton | self | Authentication, optional |
| `JwksKeySet` | singleton | self | Authentication, optional |
| `IdTokenVerifier` | singleton | self | Authentication, optional |
| `OidcClient` | singleton | self | Authentication, optional |
| `LogoutController` | singleton | self | Authentication, optional |
| `ImpersonationService` | scoped | `DatabaseImpersonationService` | Authentication, optional |
| каждый класс из `bootstrapperOrder` | singleton | class-string | Runtime |

Дополнительно, не lifetime-строка: `tag(RootRegistryWakeupHint, 'corex.wakeup.hints')`;
команда `SweepRootWakeups` при wakeup; `WorkspaceContextBootstrapper::__constructStatic`
ровно один раз. Цикл bootstrappers — одна строка `->singleton($bootstrapper)`.

## 2. Tenancy — boot()

| Abstract | Lifetime | Concrete | Модуль |
|:--|:--|:--|:--|
| `UniqueIdentifierGenerator` | bind | `$options->idGenerator` | Runtime |

Команды: `PendingCreateCommand`, `PendingClearCommand`,
`IdentitiesReconcileCommand`, `TenantsMigrateCommand`.
События: `TenantCreated` → JobPipeline; `TenancyInitialized` →
`BootstrapTenancy`; `TenancyEnded` → `RevertToCentralContext`;
`JobExceptionOccurred` → `RevertTenancyOnJobException`; три wildcard
`eloquent.*` при impersonation.
Middleware-алиасы: `tenancy.workspace` всегда; `tenancy.host`,
`tenancy.status` при host identification; `tenancy.oidc.session_lifetime`
при OIDC; `tenancy.impersonation.allow` при impersonation.
Группа `tenant` мержится, не перезаписывается.
Маршруты: `routes/oidc.php` при OIDC, с охраной route cache.

## 3. Modules — register()

| Abstract | Lifetime | Concrete | Модуль |
|:--|:--|:--|:--|
| `ArrayEntityRegistry` | singleton | self | Registry |
| `EntityRegistry` | singleton | `ArrayEntityRegistry` | Registry |
| `ModuleDiscovery` | singleton | self | Registry |
| `ModuleActivationGate` | singleton | `AllowAllActivationGate` | Registry |
| `ModuleStateReader` | singleton | `DatabaseModuleStateReader` | Lifecycle |
| `RecordsRegistrar` | singleton | `DatabaseRecordsRegistrar` | Lifecycle |
| `corex-modules.wiring` | singleton | array | Lifecycle |
| `ModuleLifecycle` | singleton | `DatabaseModuleLifecycle` | Lifecycle |
| `ModuleRegistryCache` | singleton | `RepositoryModuleRegistryCache` | Registry |
| `ModuleRegistry` | singleton | `CompiledModuleRegistry` | Registry |
| `RegistryIntrospection` | singleton | `ReadRegistryIntrospection` | Registry |
| `SettingDefaultsProvider` | singleton | `CompiledSettingDefaultsProvider` | Settings |

## 4. Modules — boot()

Команды `CompileModulesCommand`, `WhyCommand`, closure
`corex:modules:repair`. Publish tags без изменения. `JobProcessing` →
`CompiledModuleRegistry::flush`. `optimizes(corex:modules:compile)`.
`booted`: проекция compiled → EntityRegistry, `Relation::morphMap`,
`AuditCapabilityWiring::wire`. `registryVersion()` остаётся с
`Schema::hasTable` — ленивый epoch, не boot-time обход схемы всего
приложения.

## 5. Строки после P7

`RAG:—` В замере 2026-09-19 этих call site не было. Первые четыре —
`TenancyOptions` и три policy: в дереве это `bind`, не `instance`.
`ModulesOptions` — единственный `instance()`. Остальные девять `bind`
тоже есть в `rg` и поэтому дописаны; строки §1–§4 не переписаны.
В гейт D45 `instance` Options не входит. После декомпозиции
`$app->make(TenancyOptions::class)` и `$app->make(ModulesOptions::class)`
обязаны разрешаться.

| Abstract | Lifetime | Concrete | Модуль |
|:--|:--|:--|:--|
| `TenancyOptions` | bind | self | composition |
| `ImpersonationPolicy` | bind | `TenancyOptions->impersonation` | composition |
| `AccountLifecyclePolicy` | bind | `TenancyOptions->lifecycle` | composition |
| `StatusGatePolicy` | bind | `TenancyOptions->statusGate` | composition |
| `ImpersonationGrant` | bind | self | Authentication |
| `ImpersonationSessionGuard` | bind | self | Authentication |
| `DenyImpersonatedWrites` | bind | self | Authentication |
| `AccountStatusGate` | bind | self | Authentication |
| `EnforceSessionLifetime` | bind | self | Http |
| `HostTenantResolver` | bind | self | Http |
| `TemplateClonePostgreSQLManager` | bind | self | Database |
| `RequireActiveMembership` | bind | self | Http |
| `ResolveWorkspace` | bind | self | Http |
| `ModulesOptions` | instance | self | composition |

## 6. Когда composition-модуль оправдан

`RAG:✅` P8-Q2. Модуль оправдан, если истинен хотя бы один пункт:

1. Его можно не включить, и пакет остаётся поддерживаемым (optional
   integration: Wakeup, OIDC).
2. Это связная возможность с одним владельцем и одним ответом на
   «что пропадёт, если убрать вызов» (Runtime, Database, Provisioning,
   Registry, Lifecycle).
3. Это отдельная фаза lifecycle фреймворка (Console, Http, Events).

Модуль запрещён, если:

- в провайдере нет ни одного биндинга или хука этого имени
  (Membership);
- единственный мотив — уложиться в порог гейта;
- два биндинга одного владельца разносятся «для красоты».
