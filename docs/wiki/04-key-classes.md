# 04 — Key classes and functions

Line-level anchors are kept in the source files; this page explains the contract of each class.

## 1. HTTP layer

### 1.1 Middleware

| Class | File | Contract |
|---|---|---|
| `CheckAccessControl` | `app/Http/Middleware/CheckAccessControl.php` | The single ACL gate. Resolves module from the first route-name segment, permission from the last segment + HTTP verb, allows Admin, allows unknown modules, returns 403 JSON or flash redirect on denial. |

### 1.2 Controllers

61 concrete controllers. Most follow the same shape:

```
index()   → render list page (server-side DataTables)
data()    → JSON rows for DataTables (search, filters, pagination)
create()  → form
store()   → validate + DB::transaction + Log::record
show()    → detail page
edit()    → form
update()  → validate + transaction + log
destroy() → delete/cancel with state checks
```

Workflow endpoints are action methods (`submit`, `approve`, `reject`, `revise`, `unlock`, `void`,
`cancel`, `post`, `ship`, `receive`, `settle`, `pay`, `finalize`).

Representative controllers:

| Controller | Notable methods / behaviour |
|---|---|
| `AuthController` | `showLogin`, `login`, `logout`, `noAccess`; username login, session regeneration |
| `NotificationController` | `count`, `index`, `all`, `markAsRead`, `markAllRead`; bell polling |
| `UserManagementController` | `data` + resource CRUD; saves user ACL flags |
| `ConfigurationController` | Config-driven generic CRUD: `index`, `list`, `store`, `update`, `destroy`; `$config` maps table slug → model, columns, validation |
| `LeadsManagementController` | `data`, `downloadTemplate`, `import`, `storeActivity`, `fetchActivities`, `markQualified`, `markUnqualified`, `markConverted`, `storeLeadTask` |
| `OpportunityManagementController` | Deal CRUD, activity endpoints, negotiation/close-loss approval, task creation |
| `TaskPlannerController` | `data`, `kanban`, `export`, `import`, `downloadTemplate`, assignee pickers, visit/proposal/activity endpoints, approve/reject/cancel |
| `QuotationController` | `data`, `fetchTask`, `fetchTemplate`, `fetchCostTemplate`, `submit`, `approve`, `reject`, `unlock`, `revise`, `versions`, `pdf`, `pdfCost`, `updateNotes`, `viewPo` |
| `ProfitEstimateController` | Estimate CRUD, `sync` source refresh, outdated detection, PDF |
| `PosController` | `index`, `data`, POS CRUD/submit/approve/reject; requires quotation with profit estimate |
| `ProjectController` | `store`, `show`, billing-term validation `validatedBillingTerms`, manual-value parsing, achievement/warranty/document tabs |
| `ProjectTrackingController` | List + detail built from `ProjectTracker::forProject()`/`track()` |
| `PoSupplierApprovalController` | Per-project 2-approver quorum; closes opportunity on full approval |
| `GoodsRequestController` | Visibility-scoped list, approval via `GoodsRequestApproval`, close-remaining, fulfilment from stock |
| `PurchaseOrderController` | CRUD from approved PR items, `approve`, `void`, `revise`, PDF |
| `DirectPurchaseController` | Purpose stock/project/direct-use, approval, auto supplier bill, add-from-MR suggestion |
| `GoodsReceiptController` | Receipt from PO/DP, stock creation, incomplete-SN filters |
| `StockOutController` | Fulfil approved PR from free stock; project allocation |
| `PackingListController` | Per-box editing, ready/reopen, PDF with optional SN/batch appendix |
| `DeliveryOrderController` | Direct vs packing-list path, posting stock-out, PDF |
| `StockTransferController` | Warehouse/ownership move inside one branch |
| `BranchTransferController` | `ship`, per-item inspection, `receive` |
| `StockEntryController` | Opening balance / grants / other inbound stock |
| `StockOpnameController` | Physical count and `StockService::adjust` |
| `StockDetailController` | SN/batch/expiry/warranty corrections and per-SN history |
| `StockReportController` | Balance, valuation, in-transit, stock card |
| `MaterialRequestController` | Project/branch MR, approval, completion/close |
| `MaterialHandoverController` | Handover approved MR stock; allocation before consumption |
| `ProductionController` | Consume handed-over material, create finished goods, cancel |
| `MaterialReturnController` | Return leftover material; release free-stock allocation |
| `InvoiceController` | `data`, `pdf`, `fetchProject`, `submit`, `approve`, `reject`, `cancel`, `tax-invoice`, document endpoints |
| `CustomerReceiptController` | `openInvoices`, approve/void, documents |
| `SupplierBillController` | `billableLines`, approve/void, `pph-slip`, `exchange-rate`, documents |
| `SupplierPaymentController` | `openBills`, approve/void, documents |
| `SupplierRefundController` | Refund of PO advances / short delivery |
| `ReceivableReportController` / `PayableReportController` | Report pages, data, forecast/export |
| `EmployeeManagementController` | Employee CRUD, family endpoints, terminate/reactivate, salary components tab |
| `AttendanceController` | `data`, office/shift CRUD, `manual`, `importDevice`, approve/reject, corrections |
| `LeaveRequestController` | `data`, `balances`, approve/reject/revise/cancel |
| `OvertimeRequestController` | `fetchAttendance`, approve/reject/cancel |
| `LoanController` | approve/reject, `settle`, `loan.installments.pay` |
| `PayrollController` | `generateDraft`, `finalize`, component edits, Excel/PDF export |
| `DashboardVisitPlannerController` | Visit schedule grid (7 working days, per salesperson/division) |
| `DashboardAchievementController` | Achievement totals per division/brand |
| `ImsProjectController` | Hybrid project without quotation (0 value, no sales) |
| `WaterConfigurationController` etc. | Shared technical-config workflow + `ConfigurationTemplateHandling` |
| `SupplierController`, `WarehouseController`, `CurrencyController`, `ProductManagementController` | Master-data CRUD, import/export where applicable |
| `FinanceNotificationController` | Save payable/receivable reminder settings; manual send |
| `ProfileController` | Edit profile, change password, remove photo |

Shared controller concerns:

| Concern | Purpose |
|---|---|
| `ManagesPurchaseDocuments` | Upload/view/delete archived purchase documents; requires `documentOwner`, `documentRoutePrefix`, `documentModuleCode` |
| `ConfigurationTemplateHandling` | Template list/fetch/store/delete/PDF for technical-config modules; requires `templateDivisionId`, `templateViewRoot` |

### 1.3 Provider

`AppServiceProvider::boot()`:

- Forces HTTPS only when `FORCE_HTTPS` is on and the request is already secure.
- Registers `TaskObserver`, `TaskActivityObserver`, `ActivityObserver`.
- Registers the global view composer that injects ACL and product-price flags.

## 2. Services (`app/Services`)

| Service | Responsibility | Key methods |
|---|---|---|
| `StockService` | **Only** writer of stock quantities; appends `stock_logs` | `round`, `lock`, `parseSerials`, `receive`, `take`, `move`, `adjust`, `reactivate`, `arrive`, `balanceRow`, `updateDetails`, `assertPosition`, `label`, `available` |
| `StockException` | Domain exception for stock violations | — |
| `PayrollService` | Payroll calculation engine | `nextPeriodRange`, `compilePreview`, `generateDraft`, `composeComponents`, `attendanceSummary`, `countWorkingDays`, `unpaidLeaveDays`, `logPeriod` |
| `TaskAlertService` | H-1 / due-day WhatsApp reminders | `sendAlerts` |
| `TaskWhatsAppNotifier` | Task-specific WhatsApp messages | `notifyNewTask`, `notifyAssigneesAdded`, `notifyApprovalRequired` |
| `WhatsAppService` | waBaileys transport (no message composition) | `isConfigured`, `formatNumber`, `sendText`, `sendToGroup` |
| `MentionParser` | Extract `@mention` user ids from text | `extractMentionedIds` |
| `PayableDueNotifier` | Daily payable digest to group / personal numbers | `dueBills`, `buildMessage`, `personalRecipients`, `settings`, `send` |
| `ReceivableDueNotifier` | Daily receivable digest | Same contract as payable |
| `AccountImportService` / `ContactImportService` / `LeadImportService` / `ProductImportService` / `TaskImportService` | Row-by-row XLSX/CSV import, validation, upsert | Import entry points + reference-sheet/template helpers |
| `AccountExportService` / `ContactExportService` / `ProductExportService` / `TaskExportService` | XLSX export | Export builders |
| `*XlsxTemplateGenerator` | Generate downloadable import templates; base class `XlsxTemplateGenerator` | Template generation |

`StockService` rules:

- Callers lock rows first (`lock()` orders by id to avoid deadlocks) and wrap work in a transaction.
- `receive()`: material adds to the weighted-average balance row; serial goods create one row per unit.
- `take()`: material may be partial; serial goods are whole units and require SN unless explicitly allowed.
- `move()` / `arrive()`: ownership/warehouse changes, including branch-transfer in-transit.
- `adjust()`: opname delta.
- `updateDetails()`: SN/batch/expiry/warranty corrections with a `detail_update` log.
- `available()`: subtracts ready-Packing-List reservations.

### Service concern

`SendsWhatsAppDigest` implements the shared finance digest lifecycle: settings from `app_settings`,
enable/time/last-sent checks, group + personal recipients, one send per day, result statuses
(`disabled`, `already_sent`, `not_yet`, `nothing_due`, `not_configured`, `no_recipient`, `sent`,
`partial`).

## 3. Support helpers (`app/Support`)

| Class | Responsibility | Key API |
|---|---|---|
| `ModuleAccess` | Read ACL flags for UI and notification targets (never a gate) | `for`, `module`, `canRead/Create/Update/Delete/Approve/Manage`, `crossModuleCanRead`, `approverUserIds` |
| `SidebarMenu` | Data-driven menu collection for a user | `forUser`, `firstModuleFor` |
| `NumberSequence` | Transaction document numbering | `make`, `prefix` |
| `Number` | Server-side number formatting (US style) | `format`, `money`, `moneyWithCode`, `qty`, `percent`, `rate` |
| `StockCost` | Cost normalization/formatting | `standardCost`, `toBase`, `format`, `qty` |
| `StockPicker` | Selectable stock rows with reservation-aware availability | `query`, `options`, `row` |
| `ShipmentLines` | Validate/build PL/DO lines from quotation or POS, defaults, project warehouses | `assertStocks`, `quotationItems`, `pos`, `posItems`, `text`, `projectDefaults`, `projectWarehouses` |
| `ProjectTracker` | Compute seven project stages + warnings from existing documents | `forProject`, `track`, `alertDays`; constants `STAGES`, `DONE/RUN/LATE/IDLE/NA` |
| `ReceivableReport` | Term positions, aging, forecast, customer/project summaries | `openInvoices`, `daysOverdue`, `projects`, `termPosition`, `termAmount`, `termExpectedDate`, `isLateBilling`, `customerSummary`, `forecast`, `projectSummary` |
| `PayableReport` | Payable aging, schedule, FX, project cost vs estimate | `openBills`, `daysOverdue`, `agingBucket`, `supplierByCurrency`, `projectBills`, `aging`, `schedule`, `projectCosts`, `fx` |
| `ProductPricing` | Normalize product price/currency pairs | `price` |
| `TaskWorkflowLogger` | Standard task audit entries | `forTask` |
| `ConfigurationTemplateHandling` | Technical-config template CRUD/PDF trait | See concern above |

## 4. Models (`app/Models`)

Key models and their code-level responsibilities:

| Model | Status / constants | Important behaviour |
|---|---|---|
| `User` | `PRICE_MANAGER_DIVISIONS`, `PRICE_DIVISION_SCOPED_MANAGERS`, `SELLING_PRICE_VIEWER_DIVISIONS` | `canAccessBranch`, `canViewProductPrice`, `canManageProductPrice`, `ownsProductDivision`, `canManageProduct`, `canViewPurchasePriceOf`, `canEditPurchasePriceOf`, `canViewSellingPrice`, `displayName`, `avatarUrl` |
| `Module` | — | `scopeAccessibleFor`, `firstAccessibleFor` |
| `Quotation` | `STATUS_*`, `PPN_RATE`, `DPP_TAX_BASE_RATE` | `items/configItems/costItems`, `salesUsers`, `isLocked`, `nextVersion`, `flattenTree`, `flattenCostTree`, `sanitizeDescription`, `renderDescription`, `calculateTotals`, `generateQuotationNumber`, `statusBadgeHtml` |
| `QuoteConfiguration` | `STATUS_*` | Revision chain (`parent`, `revisionChildren`, `groupVersions`), items, division/task/opportunity |
| `Pos` | `MODULE_CODE`, `STATUS_*` | Flat `items`, quotation/company/project, approver/rejecter |
| `Project` | `STATUS_*`, `TYPE_PROJECT`, `TYPE_RETAIL` | `billingTerms`, `invoices`, `deliveryOrders`, `poSupplierApprovals`, `billingBaseAmount`, `billingBaseBreakdown`, `saleValueBreakdown`, `scopeImsHybrid`, `isReadyForSupplierPo`, `generateProjectNumber`, `deletionBlockers` |
| `Invoice` | `STATUS_*`, `APPROVAL_*`, defaults for terms/percent/signer | Project/POS/term links, items, receipts, payer/final checker |
| `PurchaseOrder` | `STATUS_*`, `RECEIVABLE_STATUSES`, `TYPE_*`, `TYPE_SUFFIXES` | Items, supplier, receipts, bills, refunds, revision chain |
| `GoodsRequest` | `MODULE_CODE`, `PURPOSE_*`, `REQUIRED_APPROVALS`, `STATUS_*` | `scopeVisibleTo`, approvals, items, fulfilment labels |
| `GoodsRequestItem` | — | Remaining-quantity logic across stock/PO fulfilment |
| `PackingList` | `STATUS_DRAFT/READY/SHIPPED` | Boxes, items, delivery order, ready-by |
| `DeliveryOrder` | `STATUS_DRAFT/POSTED` | Items, packing lists, posting metadata |
| `Stock` | `SOURCE_*`, `EPSILON` | Balance row; `scopeOpen`, `scopePool`, `scopeMissingSerial`, relations to product/warehouse/project/supplier |
| `StockLog` | `TYPE_*`, `REF_*`, private `REF_MAP` | `value` accessor (`qty × unit_cost`), `scopeForProject`, `attachDocuments` |
| `Task` | `STATUS_CANCELLED`, `CANCELLABLE_STATUSES` | Assignees, activities, visits, proposals, quote configurations, quotations, `salesUsers`, `whatsappUrl`, account/contact resolution |
| `Notification` | — | `group_key` / `group_type` accessors for UI grouping |
| `Log` | — | Static `record(action, description, moduleCode, loggable, user)` |
| `Document` | Type labels, `MAX_FILES`, `VALIDATION_RULE` | Polymorphic document storage and inline response |
| `MasterProduct` | Material/SN type flags | Pricing/division helpers used by product ACL |

Traits:

| Trait | Provides |
|---|---|
| `Loggable` | `logs()` morph-many |
| `Notifiable` | `notifications()` morph-many + `notify()` helper |

Model concerns:

| Concern | Provides |
|---|---|
| `HasPurchaseDocuments` | `documents()` morph-many, `ownsDocument()`, declared `documentTypes()` and `canDeleteDocument()` per model |

## 5. Observers

| Observer | Trigger | Effect |
|---|---|---|
| `TaskObserver` | `Task.updated` when `status` changed | Notifies creator on `waiting_approval`, notifies assignees on approval, sends status-change notifications; triggers `TaskWhatsAppNotifier::notifyApprovalRequired` |
| `TaskActivityObserver` | `TaskActivity.created` | Touches last-activity timestamps on company/contact; notifies task creator and assignees (replies are handled by the controller) |
| `ActivityObserver` | `Activity.created` | Notifies lead owner or opportunity owner with polymorphic notification target |

Observers are side-effect only; explicit workflow transitions and audit logs stay in controllers.

## 6. Console commands

| Command | Class | Schedule |
|---|---|---|
| `task:send-alerts` | `SendTaskAlerts` | Daily 08:00 |
| `finance:send-due-reminders` | `SendPayableDueReminders` | Every 5 minutes |
| `finance:send-receivable-reminders` | `SendReceivableDueReminders` | Every 5 minutes |

The schedule entries in `routes/console.php` call the services directly; the Artisan commands
wrap the same services for manual execution. The finance commands accept `--force` (send despite
disabled/already-sent state) and `--preview` (print the message without sending).

## 7. Import/export extension points

To add a new import/export for a module, the existing pattern is:

1. `XxxImportService` — validate and upsert rows, return per-row errors.
2. `XxxXlsxTemplateGenerator` — generate a template with reference sheets.
3. `XxxExportService` — build the workbook (PhpSpreadsheet or OOXML writer).
4. Controller endpoints named `template`, `import`, `export`; ACL follows normal verb mapping
   (`template`/`export` GET = `can_read`, `import` POST = `can_create`).
