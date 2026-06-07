# SIM-KK Premium Prototype Design

Source evidence:
- `CONTEXT.md`
- `ARCHITECTURE.md`
- `C:\Users\stefa\Downloads\Rancangan Sistem Informasi Klinik Kecantikan.pdf`

## Objective

Create an ultra-polished interactive frontend prototype for SIM-KK that proves the core clinic workflows with a clean, structured, high-trust UI. The prototype should feel premium, fast, and operational, not like a marketing landing page or a grid of generic cards.

## Design Direction

Recommended direction: **Clean Premium Operational App**.

Why: SIM-KK is a cashier, clinic record, inventory, and reporting system. Users need speed, clarity, accurate financial/commission state, and tablet-friendly treatment-room workflows. The UI should use premium motion, but motion must support orientation, hierarchy, and confidence.

Selected palette and type:
- Palette: `P08 Clean Academic Tech` adapted for clinic operations.
- Base: soft white `#FAFAF7`.
- Surface: cool porcelain `#EEF1EE`.
- Text: ink `#111827`.
- Accent: academic green `#1F5F50`.
- Micro accent: muted amber `#B98948`.
- Type: `T03 Instrument Premium`, with `Instrument Sans` for UI and `Instrument Serif` only for short editorial accents.

## Alternatives Considered

1. **Clean Premium Operational App**: best for real clinic staff. Dense, calm, precise, easy to scan. Recommended.
2. **Obsidian Brass Control Room**: darker and more cinematic. Strong for demos, but less comfortable for long cashier and tablet use.
3. **Luxury Clinic Editorial**: visually elegant and brand-heavy. Good for public-facing clinic pages, but too slow and decorative for internal operations.

## Product Scope

The prototype should be frontend-only and data-driven with realistic mock data. It should not claim that backend, authentication, database, S3 uploads, WhatsApp integration, or report generation are already implemented.

Core screens:
- Login and role entry.
- Kasir POS.
- Rekam Medis tablet view.
- Gudang inventory / FIFO.
- Manajer reports.

Secondary states:
- Empty state.
- Loading state.
- Saving state.
- Paid transaction success state.
- Export success state.
- Responsive mobile/tablet state.

## Information Architecture

Primary navigation:
- Kasir.
- Rekam Medis.
- Gudang.
- Laporan.

Global shell:
- Clinic identity area.
- Role switcher for prototype inspection.
- Current shift/date.
- Search.
- Notification/status chip.
- User menu.

The prototype should open directly into an authenticated operational shell after a short login flow. The first serious surface should be the Kasir POS because the DPPL emphasizes fast operational use.

## Screen Specifications

### Login

Purpose: communicate trust and role-based access without over-explaining.

Layout:
- Split visual composition.
- Left: refined clinic identity, small operational proof line, quiet motion background.
- Right: compact login form.
- Role chips for demo accounts: Kasir, Terapis, Gudang, Manajer.

Motion:
- Form fade-up.
- Background gradient drift.
- Role chip hover lift.

### Kasir POS

Purpose: fast service/product selection, therapist assignment, payment closure, commission lock.

Layout:
- Left zone: searchable service/product catalog with segmented filter.
- Center zone: selected patient and service context.
- Right zone: billing cart, therapist dropdown, payment summary, `Lunas` action.
- Top strip: shift status, queue count, daily revenue, pending commission.

Rules shown in UI:
- Therapist must be selected before payment.
- Commission snapshot locks at `Lunas`.
- Receipt and cash ledger are updated after payment.

Motion:
- Product tile press feedback.
- Cart item slide-in.
- Payment success confirmation with restrained reveal.

### Rekam Medis Tablet

Purpose: help therapist review patient history and add treatment notes/photos.

Layout:
- Patient summary rail.
- Chronological treatment timeline.
- Before/after photo lane.
- Complaint and action input zone.
- Upload drop zone / camera placeholder.

Rules shown in UI:
- Photos are represented as cloud object references.
- Timeline is chronological.
- Medical data requires careful access control.

Motion:
- Timeline reveal.
- Photo compare hover.
- Save state progress.

### Gudang Inventory

Purpose: make FIFO stock and HPP visible.

Layout:
- Product stock table.
- Batch cards only for stock lots, not decorative cards.
- Supplier purchase entry drawer.
- FIFO queue visualization.

Rules shown in UI:
- Earlier or nearer-expiry batches are prioritized.
- HPP is tied to supplier purchase records.
- Stock movement should be auditable.

Motion:
- FIFO queue reorder animation.
- Drawer slide-in.
- Low-stock status pulse kept subtle.

### Manajer Reports

Purpose: export trusted financial, stock, and commission reports.

Layout:
- Report switcher: Keuangan, Stok, Komisi Terapis.
- Preview table.
- Signature/approval preview for PDF financial report.
- Payroll-ready commission summary for Excel report.

Rules shown in UI:
- Values come from approved transaction history.
- Commission data is not manually edited in the report surface.
- Export actions are PDF and `.xlsx`.

Motion:
- Report tab crossfade.
- Export progress.
- Success toast with file type.

## Component System

Core components:
- `AppShell`
- `RoleSwitcher`
- `MetricStrip`
- `SegmentedControl`
- `CommandSearch`
- `StatusChip`
- `DataTable`
- `ActionDrawer`
- `Timeline`
- `PhotoCompare`
- `PaymentSummary`
- `ReportPreview`
- `ExportToast`

Component rules:
- Use 8px radius or less for repeated operational panels.
- Avoid nested cards.
- Prefer tables, rails, drawers, segmented controls, and toolbars over decorative boxes.
- Use icons only for concrete actions.
- Use concise labels instead of instructional paragraphs.

## Motion System

Motion should be premium but restrained:
- Page transition: 220-320ms fade and 8px vertical movement.
- Drawer transition: 260ms slide with opacity.
- Hover: 160-220ms lift or tone shift.
- Background drift: 18-30s loop.
- Success state: 600-800ms reveal.
- Respect reduced motion.

Motion must never block cashier speed, hide critical totals, or animate behind dense text.

## Prototype Data

Mock data should include:
- 4 users matching roles.
- 6 patients with medical record IDs.
- 10 services/products.
- 4 therapists.
- 3 pending and completed transactions.
- 8 inventory products with FIFO batch data.
- 3 report previews.

Use Indonesian labels and clinic-specific sample values.

## Acceptance Criteria

- First viewport clearly reads as SIM-KK clinic operations.
- Kasir POS is the strongest default screen.
- UI is clean, dense, structured, and not card-heavy.
- Core workflows are clickable with meaningful state transitions.
- No UI text claims backend features are live.
- Prototype is responsive for desktop and tablet.
- Motion feels expensive, useful, and calm.
- Visual QA includes desktop and mobile/tablet screenshots.
- Unknown backend choices stay documented, not hidden.

## Non-Goals

- Real authentication.
- Real database.
- Real S3 upload.
- Real WhatsApp integration.
- Real PDF or Excel file generation.
- Production deployment.

## Risks

- Too much motion can reduce operational speed.
- Too many rounded panels can turn the app into card soup.
- A dark cinematic theme can look premium in screenshots but tiring in daily clinic use.
- Fake reports can imply real accounting if labels are not clear.

## Recommendation

Build the prototype as a Vue 3 + TypeScript + Vite frontend using local fixture data. Keep the design system small, precise, and operational. Lead with Kasir POS, then prove Rekam Medis, Gudang FIFO, and Manajer reports as integrated workflows.
