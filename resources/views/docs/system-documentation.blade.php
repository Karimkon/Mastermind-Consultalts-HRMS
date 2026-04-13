<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<title>Mastermind Consultants HRMS — System Documentation</title>
<style>
    /* ── Base ─────────────────────────────────────────── */
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:10pt; color:#1e293b; background:#fff; line-height:1.65; }

    /* ── Page breaks ──────────────────────────────────── */
    .page-break { page-break-after: always; }
    .no-break   { page-break-inside: avoid; }

    /* ── Cover page ───────────────────────────────────── */
    .cover {
        width:100%; height:100%;
        background:#0f172a;
        color:#fff;
        padding: 0;
    }
    .cover-top-bar {
        background:#1d4ed8;
        height: 8px;
        width: 100%;
    }
    .cover-accent-bar {
        background:#3b82f6;
        height: 4px;
        width: 60%;
    }
    .cover-body {
        padding: 70px 60px 50px 60px;
    }
    .cover-logo-box {
        width: 64px; height: 64px;
        background: #1d4ed8;
        border-radius: 12px;
        display: inline-block;
        text-align: center;
        line-height: 64px;
        font-size: 30pt;
        color: #fff;
        font-weight: bold;
        margin-bottom: 36px;
    }
    .cover-tag {
        font-size: 8pt;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: #60a5fa;
        font-weight: bold;
        margin-bottom: 16px;
    }
    .cover-title {
        font-size: 34pt;
        font-weight: bold;
        color: #fff;
        line-height: 1.2;
        margin-bottom: 10px;
    }
    .cover-subtitle {
        font-size: 16pt;
        color: #94a3b8;
        margin-bottom: 48px;
    }
    .cover-divider {
        border: none;
        border-top: 1px solid #334155;
        margin: 40px 0;
    }
    .cover-meta-row {
        margin-bottom: 10px;
        font-size: 10pt;
    }
    .cover-meta-label {
        color: #64748b;
        display: inline-block;
        width: 140px;
    }
    .cover-meta-value {
        color: #e2e8f0;
        font-weight: bold;
    }
    .cover-modules-heading {
        font-size: 8pt;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: #475569;
        margin-top: 50px;
        margin-bottom: 16px;
    }
    .cover-module-pills table { border-collapse: collapse; }
    .cover-pill {
        display: inline-block;
        background: #1e293b;
        border: 1px solid #334155;
        color: #94a3b8;
        font-size: 8pt;
        padding: 5px 14px;
        border-radius: 4px;
        margin: 3px 4px 3px 0;
    }
    .cover-footer {
        position: fixed;
        bottom: 0; left: 0; right: 0;
        background: #1e293b;
        padding: 14px 60px;
    }
    .cover-footer-inner { color: #475569; font-size: 8pt; }
    .cover-footer-inner span { color: #60a5fa; font-weight: bold; }
    .cover-version-badge {
        float: right;
        background: #1d4ed8;
        color: #fff;
        font-size: 7.5pt;
        padding: 3px 10px;
        border-radius: 20px;
        font-weight: bold;
    }

    /* ── TOC ──────────────────────────────────────────── */
    .toc-header {
        background: #f8fafc;
        border-left: 5px solid #1d4ed8;
        padding: 18px 24px;
        margin-bottom: 28px;
    }
    .toc-header h2 { font-size: 16pt; color: #0f172a; }
    .toc-entry {
        display: table;
        width: 100%;
        margin-bottom: 6px;
        font-size: 9.5pt;
    }
    .toc-num   { display: table-cell; width: 36px; color: #1d4ed8; font-weight: bold; }
    .toc-text  { display: table-cell; color: #334155; }
    .toc-dots  { display: table-cell; border-bottom: 1px dotted #cbd5e1; }
    .toc-page  { display: table-cell; width: 30px; text-align: right; color: #64748b; font-size: 8.5pt; }
    .toc-sub   { padding-left: 36px; color: #64748b; font-size: 8.5pt; margin-bottom: 3px; }
    .toc-group { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.12em;
                 color: #94a3b8; margin: 18px 0 8px 0; font-weight: bold; }

    /* ── Section layout ───────────────────────────────── */
    .section { padding: 0 0 32px 0; }
    .section-header {
        background: linear-gradient(90deg, #0f172a 0%, #1e293b 100%);
        color: #fff;
        padding: 18px 28px;
        margin-bottom: 24px;
        border-radius: 6px;
    }
    .section-number {
        font-size: 8pt;
        letter-spacing: 0.15em;
        text-transform: uppercase;
        color: #60a5fa;
        margin-bottom: 4px;
    }
    .section-title { font-size: 15pt; font-weight: bold; }
    .section-desc  { font-size: 8.5pt; color: #94a3b8; margin-top: 4px; }

    /* ── Sub-section ──────────────────────────────────── */
    .sub-section { margin-bottom: 24px; }
    .sub-title {
        font-size: 11pt;
        font-weight: bold;
        color: #1d4ed8;
        border-bottom: 2px solid #dbeafe;
        padding-bottom: 5px;
        margin-bottom: 12px;
    }
    .sub-sub-title {
        font-size: 9.5pt;
        font-weight: bold;
        color: #0f172a;
        margin: 12px 0 6px 0;
    }

    /* ── Callout boxes ────────────────────────────────── */
    .callout {
        border-left: 4px solid #3b82f6;
        background: #eff6ff;
        padding: 12px 16px;
        border-radius: 0 6px 6px 0;
        margin: 12px 0;
        font-size: 9pt;
        color: #1e40af;
    }
    .callout-green {
        border-left-color: #16a34a;
        background: #f0fdf4;
        color: #166534;
    }
    .callout-amber {
        border-left-color: #d97706;
        background: #fffbeb;
        color: #92400e;
    }
    .callout-red {
        border-left-color: #dc2626;
        background: #fef2f2;
        color: #991b1b;
    }

    /* ── Tables ───────────────────────────────────────── */
    table.doc-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
        margin-bottom: 16px;
    }
    table.doc-table thead tr {
        background: #1d4ed8;
        color: #fff;
    }
    table.doc-table thead th {
        padding: 9px 12px;
        text-align: left;
        font-weight: bold;
        font-size: 8.5pt;
        letter-spacing: 0.03em;
    }
    table.doc-table tbody tr:nth-child(even) { background: #f8fafc; }
    table.doc-table tbody tr:nth-child(odd)  { background: #fff; }
    table.doc-table tbody td {
        padding: 8px 12px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: top;
    }
    table.doc-table tbody tr:last-child td { border-bottom: none; }

    /* ── Feature cards ────────────────────────────────── */
    .feature-grid { width: 100%; }
    .feature-card {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 14px 16px;
        margin-bottom: 10px;
    }
    .feature-card-header {
        margin-bottom: 6px;
    }
    .feature-icon {
        display: inline-block;
        width: 28px; height: 28px;
        background: #dbeafe;
        border-radius: 6px;
        text-align: center;
        line-height: 28px;
        font-size: 12pt;
        vertical-align: middle;
        margin-right: 8px;
    }
    .feature-name {
        font-size: 10.5pt;
        font-weight: bold;
        color: #0f172a;
        vertical-align: middle;
    }
    .feature-desc {
        font-size: 8.5pt;
        color: #64748b;
        margin-top: 4px;
    }
    .feature-list {
        margin-top: 8px;
        padding-left: 0;
        list-style: none;
    }
    .feature-list li {
        font-size: 8.5pt;
        color: #334155;
        padding: 2px 0 2px 14px;
        position: relative;
    }
    .feature-list li:before {
        content: "•";
        color: #3b82f6;
        position: absolute;
        left: 0;
    }

    /* ── Badge ────────────────────────────────────────── */
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 7.5pt;
        font-weight: bold;
    }
    .badge-blue   { background: #dbeafe; color: #1d4ed8; }
    .badge-green  { background: #dcfce7; color: #166534; }
    .badge-amber  { background: #fef9c3; color: #854d0e; }
    .badge-red    { background: #fee2e2; color: #991b1b; }
    .badge-purple { background: #f3e8ff; color: #6b21a8; }
    .badge-slate  { background: #f1f5f9; color: #475569; }

    /* ── Architecture diagram ──────────────────────────── */
    .arch-box {
        border: 2px solid #1d4ed8;
        border-radius: 8px;
        padding: 16px;
        text-align: center;
        background: #eff6ff;
        margin: 8px;
    }
    .arch-box-dark {
        border-color: #0f172a;
        background: #0f172a;
        color: #fff;
    }
    .arch-box-green {
        border-color: #16a34a;
        background: #f0fdf4;
    }
    .arch-box-amber {
        border-color: #d97706;
        background: #fffbeb;
    }
    .arch-label { font-size: 8pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; }
    .arch-sub   { font-size: 7.5pt; margin-top: 4px; color: #64748b; }

    /* ── Flow arrows ──────────────────────────────────── */
    .flow-arrow { text-align: center; font-size: 14pt; color: #1d4ed8; margin: 4px 0; }

    /* ── Two-col layout ───────────────────────────────── */
    .two-col { width: 100%; border-collapse: collapse; }
    .two-col td { width: 50%; vertical-align: top; padding: 0 8px 0 0; }
    .two-col td:last-child { padding: 0 0 0 8px; }

    /* ── Page header/footer ───────────────────────────── */
    @page {
        margin: 18mm 16mm 20mm 16mm;
    }
    .page-header {
        position: running(header);
        font-size: 7.5pt;
        color: #94a3b8;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 6px;
    }
    .page-footer {
        position: running(footer);
        font-size: 7.5pt;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        padding-top: 6px;
    }

    /* ── Misc ─────────────────────────────────────────── */
    p { margin-bottom: 8px; font-size: 9.5pt; color: #334155; }
    .text-muted { color: #94a3b8; }
    .text-blue  { color: #1d4ed8; }
    .text-bold  { font-weight: bold; }
    .mt-8  { margin-top: 8px; }
    .mt-16 { margin-top: 16px; }
    .mb-8  { margin-bottom: 8px; }
    .mb-16 { margin-bottom: 16px; }
    .mb-24 { margin-bottom: 24px; }
    .mono  { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; background:#f8fafc; padding:1px 5px; border-radius:3px; }
</style>
</head>
<body>

{{-- ═══════════════════════════════════════════════════════
     COVER PAGE
══════════════════════════════════════════════════════════ --}}
<div class="cover">
    <div class="cover-top-bar"></div>
    <div class="cover-accent-bar"></div>

    <div class="cover-body">
        <div class="cover-logo-box">M</div>

        <div class="cover-tag">System Documentation &nbsp;·&nbsp; Version 1.0</div>
        <div class="cover-title">Mastermind Consultants<br>HRMS</div>
        <div class="cover-subtitle">Human Resource Management System</div>

        <hr class="cover-divider">

        <div class="cover-meta-row">
            <span class="cover-meta-label">Prepared by</span>
            <span class="cover-meta-value">Higeni Abdulkarim</span>
        </div>
        <div class="cover-meta-row">
            <span class="cover-meta-label">Company</span>
            <span class="cover-meta-value">Ehsan Developers</span>
        </div>
        <div class="cover-meta-row">
            <span class="cover-meta-label">Client</span>
            <span class="cover-meta-value">Mastermind Consultants</span>
        </div>
        <div class="cover-meta-row">
            <span class="cover-meta-label">Document Type</span>
            <span class="cover-meta-value">Technical &amp; Functional Documentation</span>
        </div>
        <div class="cover-meta-row">
            <span class="cover-meta-label">Date</span>
            <span class="cover-meta-value">{{ \Carbon\Carbon::now()->format('F j, Y') }}</span>
        </div>
        <div class="cover-meta-row">
            <span class="cover-meta-label">Status</span>
            <span class="cover-meta-value" style="color:#4ade80;">&#10003; Production Ready</span>
        </div>

        <div class="cover-modules-heading">System Modules Covered</div>
        <div>
            <span class="cover-pill">Dashboard &amp; Analytics</span>
            <span class="cover-pill">Employee Management</span>
            <span class="cover-pill">Attendance</span>
            <span class="cover-pill">Leave Management</span>
            <span class="cover-pill">Payroll</span>
            <span class="cover-pill">Recruitment</span>
            <span class="cover-pill">Performance &amp; BSC</span>
            <span class="cover-pill">Training</span>
            <span class="cover-pill">Meetings</span>
            <span class="cover-pill">Onboarding &amp; Exit</span>
            <span class="cover-pill">RBAC &amp; Security</span>
            <span class="cover-pill">PWA / Mobile</span>
        </div>
    </div>

    <div class="cover-footer">
        <div class="cover-footer-inner">
            <span class="cover-version-badge">v1.0 — April 2026</span>
            &copy; 2026 <span>Ehsan Developers</span> &nbsp;|&nbsp; Confidential &amp; Proprietary
        </div>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     TABLE OF CONTENTS
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="toc-header">
        <h2>Table of Contents</h2>
        <p style="color:#64748b;font-size:8.5pt;margin-top:4px;margin-bottom:0;">Mastermind Consultants HRMS &nbsp;·&nbsp; Complete System Documentation</p>
    </div>

    <div class="toc-group">Overview &amp; Architecture</div>

    <div class="toc-entry no-break">
        <span class="toc-num">1.</span>
        <span class="toc-text">System Overview</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">4</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">2.</span>
        <span class="toc-text">Technical Architecture</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">5</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">3.</span>
        <span class="toc-text">User Roles &amp; Permissions</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">6</span>
    </div>

    <div class="toc-group">Core HR Modules</div>

    <div class="toc-entry no-break">
        <span class="toc-num">4.</span>
        <span class="toc-text">Employee Management</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">7</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">5.</span>
        <span class="toc-text">Attendance Management</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">8</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">6.</span>
        <span class="toc-text">Leave Management</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">9</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">7.</span>
        <span class="toc-text">Payroll Processing</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">10</span>
    </div>

    <div class="toc-group">Talent Management</div>

    <div class="toc-entry no-break">
        <span class="toc-num">8.</span>
        <span class="toc-text">Recruitment &amp; Hiring</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">12</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">9.</span>
        <span class="toc-text">Performance Management &amp; BSC</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">13</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">10.</span>
        <span class="toc-text">Training &amp; Development</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">14</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">11.</span>
        <span class="toc-text">Employee Lifecycle (Onboarding &amp; Exit)</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">15</span>
    </div>

    <div class="toc-group">Collaboration &amp; Platform</div>

    <div class="toc-entry no-break">
        <span class="toc-num">12.</span>
        <span class="toc-text">Meetings &amp; Calendar</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">16</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">13.</span>
        <span class="toc-text">Reports &amp; Exports</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">17</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">14.</span>
        <span class="toc-text">Security &amp; Audit</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">18</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">15.</span>
        <span class="toc-text">PWA &amp; Mobile Capabilities</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">19</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">16.</span>
        <span class="toc-text">Notifications &amp; Email System</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">20</span>
    </div>
    <div class="toc-entry no-break">
        <span class="toc-num">17.</span>
        <span class="toc-text">System Configuration &amp; Deployment</span>
        <span class="toc-dots">&nbsp;</span>
        <span class="toc-page">21</span>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     1. SYSTEM OVERVIEW
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 01</div>
        <div class="section-title">System Overview</div>
        <div class="section-desc">Introduction, objectives, and high-level capabilities of the HRMS</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">1.1 Introduction</div>
        <p>
            The <strong>Mastermind Consultants HRMS</strong> is a comprehensive, web-based Human Resource
            Management System purpose-built for Mastermind Consultants. It consolidates all HR operations —
            from hiring to retirement — into a single, secure, and intuitive platform. The system is
            developed and delivered by <strong>Ehsan Developers</strong>.
        </p>
        <p>
            The platform is built on <strong>Laravel 12</strong> (PHP) with a responsive Tailwind CSS +
            Alpine.js frontend, and is deployable on any standard LAMP/LEMP stack. It is also installable
            as a <strong>Progressive Web App (PWA)</strong> for mobile usage without requiring native app
            development.
        </p>
        <div class="callout">
            <strong>Mission Statement:</strong> To digitise and streamline every HR touchpoint — reducing
            administrative overhead, enforcing compliance, and providing real-time workforce intelligence
            to leadership at Mastermind Consultants.
        </div>
    </div>

    <div class="sub-section">
        <div class="sub-title">1.2 Key Objectives</div>
        <table class="doc-table">
            <thead>
                <tr><th>#</th><th>Objective</th><th>Addressed By</th></tr>
            </thead>
            <tbody>
                <tr><td>1</td><td>Centralise employee data and records</td><td>Employee Management Module</td></tr>
                <tr><td>2</td><td>Automate payroll calculations (UGX, PAYE, NSSF)</td><td>Payroll Processing Module</td></tr>
                <tr><td>3</td><td>Enforce attendance discipline and remote clock-in</td><td>Attendance Module</td></tr>
                <tr><td>4</td><td>Manage leave entitlements with carry-forward logic</td><td>Leave Management Module</td></tr>
                <tr><td>5</td><td>Streamline recruitment from vacancy to offer</td><td>Recruitment Module</td></tr>
                <tr><td>6</td><td>Drive performance accountability via BSC goals</td><td>Performance &amp; Goals Modules</td></tr>
                <tr><td>7</td><td>Standardise training and certification tracking</td><td>Training &amp; Development Module</td></tr>
                <tr><td>8</td><td>Enforce role-based access with audit trails</td><td>RBAC, MFA &amp; Audit Module</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">1.3 System Statistics</div>
        <table class="doc-table">
            <thead>
                <tr><th>Metric</th><th>Value</th></tr>
            </thead>
            <tbody>
                <tr><td>Total Functional Modules</td><td><strong>12</strong></td></tr>
                <tr><td>User Roles</td><td><strong>6</strong> (super-admin, hr-admin, manager, payroll-officer, recruiter, employee)</td></tr>
                <tr><td>Database Tables</td><td><strong>40+</strong> (including all migration tables)</td></tr>
                <tr><td>Automated Scheduled Jobs</td><td><strong>4</strong> (leave seeding, status resume, cert alerts, recurring meetings)</td></tr>
                <tr><td>Email Notification Types</td><td><strong>6</strong> (leave, payroll, meeting, training, certification expiry)</td></tr>
                <tr><td>Currency</td><td>Ugandan Shilling (UGX) with local PAYE tax brackets</td></tr>
                <tr><td>MFA Support</td><td>TOTP (Google Authenticator / Authy compatible)</td></tr>
                <tr><td>PWA Support</td><td>Installable on Android, iOS, Windows desktop</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     2. TECHNICAL ARCHITECTURE
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 02</div>
        <div class="section-title">Technical Architecture</div>
        <div class="section-desc">Framework, database, deployment stack, and design patterns</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">2.1 Technology Stack</div>
        <table class="doc-table">
            <thead><tr><th>Layer</th><th>Technology</th><th>Version / Notes</th></tr></thead>
            <tbody>
                <tr><td>Backend Framework</td><td><strong>Laravel</strong></td><td>12.x (PHP 8.2+)</td></tr>
                <tr><td>Frontend CSS</td><td><strong>Tailwind CSS</strong></td><td>CDN (v3) + custom config</td></tr>
                <tr><td>Frontend JS</td><td><strong>Alpine.js</strong></td><td>v3.x — reactive UI without build step</td></tr>
                <tr><td>Charts</td><td><strong>ApexCharts</strong></td><td>CDN — dashboard analytics</td></tr>
                <tr><td>Database</td><td><strong>MySQL</strong></td><td>8.0+</td></tr>
                <tr><td>Auth &amp; RBAC</td><td><strong>Spatie Laravel Permission</strong></td><td>v6.x</td></tr>
                <tr><td>MFA</td><td><strong>PragmaRX Google2FA</strong></td><td>TOTP-based</td></tr>
                <tr><td>PDF Generation</td><td><strong>barryvdh/laravel-dompdf</strong></td><td>v3.x</td></tr>
                <tr><td>Excel / CSV Export</td><td><strong>Maatwebsite Excel</strong></td><td>Bank payment exports</td></tr>
                <tr><td>Queue Driver</td><td><strong>Database</strong> (configurable)</td><td>Email notifications</td></tr>
                <tr><td>Web Server</td><td><strong>Apache / XAMPP</strong></td><td>Local &amp; production</td></tr>
                <tr><td>PWA</td><td><strong>Service Worker + Web App Manifest</strong></td><td>Cache-first strategy</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">2.2 Application Architecture</div>
        <p>The system follows the <strong>MVC (Model-View-Controller)</strong> pattern native to Laravel,
           extended with Service classes for complex business logic (e.g., Payroll), Observer classes for
           event-driven side effects (audit logging, emails), and dedicated Command classes for
           scheduled background tasks.</p>

        <table class="doc-table">
            <thead><tr><th>Pattern</th><th>Used For</th><th>Examples</th></tr></thead>
            <tbody>
                <tr><td>Service Layer</td><td>Complex business logic isolated from controllers</td><td><span class="mono">PayrollService</span></td></tr>
                <tr><td>Observer Pattern</td><td>Decoupled side effects (emails, audit logs)</td><td><span class="mono">LeaveRequestObserver</span>, <span class="mono">PayslipObserver</span></td></tr>
                <tr><td>Mailable Classes</td><td>Queued transactional emails</td><td><span class="mono">MeetingInviteMail</span>, <span class="mono">LeaveStatusMail</span></td></tr>
                <tr><td>Artisan Commands</td><td>Cron-triggered background jobs</td><td><span class="mono">hrms:seed-leave-balances</span></td></tr>
                <tr><td>Resource Controllers</td><td>Standard CRUD operations</td><td>All module controllers</td></tr>
                <tr><td>Middleware</td><td>Route-level guards</td><td><span class="mono">RequireMfa</span>, Spatie roles</td></tr>
                <tr><td>Blade Components</td><td>Reusable UI elements</td><td><span class="mono">x-page-header</span>, <span class="mono">x-data-table</span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">2.3 Directory Structure (Key Paths)</div>
        <table class="doc-table">
            <thead><tr><th>Path</th><th>Contents</th></tr></thead>
            <tbody>
                <tr><td><span class="mono">app/Http/Controllers/</span></td><td>All web controllers, grouped by domain</td></tr>
                <tr><td><span class="mono">app/Models/</span></td><td>Eloquent models with relationships, casts, accessors</td></tr>
                <tr><td><span class="mono">app/Services/Payroll/</span></td><td>PayrollService — pro-rata, PAYE, NSSF, overtime</td></tr>
                <tr><td><span class="mono">app/Observers/</span></td><td>Model observers for audit logging &amp; email triggers</td></tr>
                <tr><td><span class="mono">app/Mail/</span></td><td>6 Mailable classes for transactional email</td></tr>
                <tr><td><span class="mono">app/Console/Commands/</span></td><td>4 scheduled Artisan commands</td></tr>
                <tr><td><span class="mono">database/migrations/</span></td><td>All schema migrations (versioned)</td></tr>
                <tr><td><span class="mono">resources/views/</span></td><td>Blade templates organised by module</td></tr>
                <tr><td><span class="mono">public/</span></td><td>manifest.json, sw.js, offline.html (PWA)</td></tr>
                <tr><td><span class="mono">routes/web.php</span></td><td>All web routes with middleware groups</td></tr>
                <tr><td><span class="mono">routes/console.php</span></td><td>Laravel scheduler definitions</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     3. USER ROLES & PERMISSIONS
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 03</div>
        <div class="section-title">User Roles &amp; Permissions</div>
        <div class="section-desc">Role-based access control powered by Spatie Laravel Permission</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">3.1 Role Definitions</div>
        <table class="doc-table">
            <thead><tr><th>Role</th><th>Slug</th><th>Description</th><th>Access Level</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>Super Administrator</strong></td>
                    <td><span class="mono">super-admin</span></td>
                    <td>Full unrestricted access to all modules, settings, audit logs, and role management</td>
                    <td><span class="badge badge-red">Full</span></td>
                </tr>
                <tr>
                    <td><strong>HR Administrator</strong></td>
                    <td><span class="mono">hr-admin</span></td>
                    <td>Manages employees, leaves, attendance, payroll, training, and recruitment</td>
                    <td><span class="badge badge-purple">High</span></td>
                </tr>
                <tr>
                    <td><strong>Manager</strong></td>
                    <td><span class="mono">manager</span></td>
                    <td>Views and approves leave for their team, accesses performance and meeting modules</td>
                    <td><span class="badge badge-blue">Medium</span></td>
                </tr>
                <tr>
                    <td><strong>Payroll Officer</strong></td>
                    <td><span class="mono">payroll-officer</span></td>
                    <td>Processes payroll runs, manages salary grades, exports bank payment files</td>
                    <td><span class="badge badge-amber">Scoped</span></td>
                </tr>
                <tr>
                    <td><strong>Recruiter</strong></td>
                    <td><span class="mono">recruiter</span></td>
                    <td>Manages job postings, candidates, interviews, scorecards, and offer letters</td>
                    <td><span class="badge badge-amber">Scoped</span></td>
                </tr>
                <tr>
                    <td><strong>Employee</strong></td>
                    <td><span class="mono">employee</span></td>
                    <td>Access to own profile, attendance clock-in/out, leave requests, and training</td>
                    <td><span class="badge badge-slate">Personal</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">3.2 Permission Matrix</div>
        <table class="doc-table">
            <thead>
                <tr>
                    <th>Module</th>
                    <th style="text-align:center;">Super Admin</th>
                    <th style="text-align:center;">HR Admin</th>
                    <th style="text-align:center;">Manager</th>
                    <th style="text-align:center;">Payroll</th>
                    <th style="text-align:center;">Recruiter</th>
                    <th style="text-align:center;">Employee</th>
                </tr>
            </thead>
            <tbody>
                <tr><td>Dashboard</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td></tr>
                <tr><td>Employee CRUD</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">View</td><td style="text-align:center;">View</td><td style="text-align:center;">—</td><td style="text-align:center;">Own</td></tr>
                <tr><td>Attendance</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">Own</td></tr>
                <tr><td>Leave Approvals</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">Request</td></tr>
                <tr><td>Payroll</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">Payslip</td></tr>
                <tr><td>Recruitment</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td></tr>
                <tr><td>Performance</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">Own</td></tr>
                <tr><td>Training</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">Enroll</td></tr>
                <tr><td>Meetings</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td></tr>
                <tr><td>Reports</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">Payroll</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td></tr>
                <tr><td>Audit Logs</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td></tr>
                <tr><td>Role Management</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">&#10003;</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td><td style="text-align:center;">—</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     4. EMPLOYEE MANAGEMENT
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 04</div>
        <div class="section-title">Employee Management</div>
        <div class="section-desc">Core workforce registry — profiles, designations, departments, and documents</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">4.1 Module Features</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Employee Registry</td><td>Full profile: personal info, contact, emergency contact, bank details, photo upload</td></tr>
                <tr><td>Department &amp; Designation</td><td>Hierarchical departments with assigned designations per employee</td></tr>
                <tr><td>Employment Status</td><td>Active, On Leave, Suspended, Terminated, Resigned — with status history</td></tr>
                <tr><td>Contract Types</td><td>Full-time, Part-time, Contract, Intern, Casual</td></tr>
                <tr><td>Document Management</td><td>Upload and track IDs, contracts, certificates against expiry dates</td></tr>
                <tr><td>Certification Tracking</td><td>Professional certifications with expiry alerts (30-day email warning)</td></tr>
                <tr><td>Employee Number</td><td>Auto-generated unique identifier (e.g., EMP-0001)</td></tr>
                <tr><td>Search &amp; Filter</td><td>Filter by department, status, employment type, date range</td></tr>
                <tr><td>Bulk Import</td><td>CSV import for bulk employee onboarding</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">4.2 Employee Profile Data Model</div>
        <table class="doc-table">
            <thead><tr><th>Field Group</th><th>Fields Captured</th></tr></thead>
            <tbody>
                <tr><td>Personal</td><td>First name, last name, date of birth, gender, national ID, photo</td></tr>
                <tr><td>Contact</td><td>Personal email, phone, physical address, city, country</td></tr>
                <tr><td>Employment</td><td>Hire date, department, designation, salary grade, employment type, manager</td></tr>
                <tr><td>Bank Details</td><td>Bank name, account number, branch code</td></tr>
                <tr><td>Emergency Contact</td><td>Name, relationship, phone number</td></tr>
                <tr><td>System</td><td>Employee number, status, created_by, last updated</td></tr>
            </tbody>
        </table>
    </div>

    <div class="callout callout-green">
        <strong>Onboarding Integration:</strong> Every new employee automatically gets an onboarding task
        checklist created upon profile creation. HR can add custom tasks and mark each as complete,
        providing a full audit trail of the onboarding process.
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     5. ATTENDANCE MANAGEMENT
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 05</div>
        <div class="section-title">Attendance Management</div>
        <div class="section-desc">Clock-in/out, daily logs, overtime tracking, and attendance reports</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">5.1 Features</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Clock-In / Clock-Out</td><td>One-click web-based time recording; timestamps saved to second precision</td></tr>
                <tr><td>Hours Calculation</td><td>Automatic daily hours computation with decimal precision</td></tr>
                <tr><td>Overtime Detection</td><td>Hours exceeding 8/day flagged as overtime; fed into payroll</td></tr>
                <tr><td>Date Filter</td><td>HR can filter attendance by employee, department, or date range</td></tr>
                <tr><td>Manual Correction</td><td>Admins can add/edit attendance records for corrections</td></tr>
                <tr><td>Monthly Summary</td><td>Per-employee monthly attendance summary with total hours</td></tr>
                <tr><td>Export</td><td>CSV export of attendance records for payroll processing</td></tr>
                <tr><td>Status Scope</td><td>Employees see only their own records; managers see all</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">5.2 Clock-In Flow</div>
        <div class="callout">
            Employee navigates to Attendance → Clicks "Clock In" → System records timestamp and IP
            address → Displays elapsed time on screen → Employee clicks "Clock Out" →
            System calculates total hours and marks record complete. All operations use CSRF-protected
            POST requests with AJAX support for seamless UX.
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     6. LEAVE MANAGEMENT
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 06</div>
        <div class="section-title">Leave Management</div>
        <div class="section-desc">Multi-type leave, balances, carry-forward, approvals, and auto-status updates</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">6.1 Features</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Leave Types</td><td>Annual, Sick, Maternity, Paternity, Compassionate, Unpaid, Study Leave — HR-configurable</td></tr>
                <tr><td>Leave Balances</td><td>Per-employee, per-type balances with used/remaining tracking</td></tr>
                <tr><td>Annual Balance Seeding</td><td>Automated January 1st command seeds new balances with carry-forward enforcement</td></tr>
                <tr><td>Carry-Forward</td><td>Configurable per leave type — maximum carry-forward days capped at <span class="mono">max_carry_forward</span></td></tr>
                <tr><td>Document Upload</td><td>Employees can attach supporting documents (e.g., medical certificate)</td></tr>
                <tr><td>Approval Workflow</td><td>Submitted → Pending → Approved / Rejected (with rejection reason)</td></tr>
                <tr><td>Email Notifications</td><td>HR/manager notified on submission; employee notified on status change</td></tr>
                <tr><td>Auto Status Resume</td><td>Daily command resets employees from "on_leave" to "active" when leave ends</td></tr>
                <tr><td>Reporting</td><td>Leave utilisation report with department and type breakdown</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">6.2 Leave Request States</div>
        <table class="doc-table">
            <thead><tr><th>Status</th><th>Triggered By</th><th>Effect</th></tr></thead>
            <tbody>
                <tr><td><span class="badge badge-amber">Pending</span></td><td>Employee submits request</td><td>Email sent to HR/manager; no balance deducted yet</td></tr>
                <tr><td><span class="badge badge-green">Approved</span></td><td>HR/manager approves</td><td>Leave days deducted from balance; employee status set to "on_leave" on start date</td></tr>
                <tr><td><span class="badge badge-red">Rejected</span></td><td>HR/manager rejects with reason</td><td>Email with rejection reason sent to employee; balance unchanged</td></tr>
                <tr><td><span class="badge badge-slate">Cancelled</span></td><td>Employee cancels before start</td><td>Balance restored if previously deducted</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     7. PAYROLL PROCESSING
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 07</div>
        <div class="section-title">Payroll Processing</div>
        <div class="section-desc">Uganda PAYE, NSSF, pro-rata, overtime, allowances, and bank export</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">7.1 Payroll Features</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Payroll Run</td><td>Monthly payroll execution for all active employees in one click</td></tr>
                <tr><td>Salary Grades</td><td>Grade-based salary structure (Basic, Housing, Transport allowances)</td></tr>
                <tr><td>Pro-Rata Calculation</td><td>Automatically prorates salary for mid-month hires and exits using working days</td></tr>
                <tr><td>Overtime Pay</td><td>Overtime hours × hourly rate × 1.5 multiplier, pulled from attendance</td></tr>
                <tr><td>PAYE Tax</td><td>Uganda Revenue Authority PAYE brackets (monthly) with correct threshold steps</td></tr>
                <tr><td>NSSF Deduction</td><td>5% employee + 10% employer NSSF contributions</td></tr>
                <tr><td>Leave Deductions</td><td>Unpaid leave days deducted proportionally from gross pay</td></tr>
                <tr><td>Payslip PDF</td><td>Professional per-employee payslip PDF generation via DomPDF</td></tr>
                <tr><td>Bank Payment Export</td><td>CSV export of net pay with bank account details for direct bank upload</td></tr>
                <tr><td>Email Notification</td><td>Employee receives payslip notification email when payroll is processed</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">7.2 Uganda PAYE Tax Brackets (Monthly)</div>
        <table class="doc-table">
            <thead><tr><th>Monthly Income (UGX)</th><th>Tax Rate</th></tr></thead>
            <tbody>
                <tr><td>0 – 235,000</td><td>0% (Exempt)</td></tr>
                <tr><td>235,001 – 335,000</td><td>10%</td></tr>
                <tr><td>335,001 – 410,000</td><td>20%</td></tr>
                <tr><td>410,001 – 10,000,000</td><td>30%</td></tr>
                <tr><td>Above 10,000,000</td><td>40%</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">7.3 Payslip Components</div>
        <table class="doc-table">
            <thead><tr><th>Component</th><th>Type</th><th>Calculation</th></tr></thead>
            <tbody>
                <tr><td>Basic Salary</td><td><span class="badge badge-green">Earning</span></td><td>From salary grade × pro-rata factor</td></tr>
                <tr><td>Housing Allowance</td><td><span class="badge badge-green">Earning</span></td><td>From salary grade × pro-rata factor</td></tr>
                <tr><td>Transport Allowance</td><td><span class="badge badge-green">Earning</span></td><td>From salary grade × pro-rata factor</td></tr>
                <tr><td>Overtime Pay</td><td><span class="badge badge-green">Earning</span></td><td>Overtime hours × (Basic/176) × 1.5</td></tr>
                <tr><td>PAYE Tax</td><td><span class="badge badge-red">Deduction</span></td><td>URA progressive brackets on gross</td></tr>
                <tr><td>NSSF (Employee)</td><td><span class="badge badge-red">Deduction</span></td><td>5% of basic salary</td></tr>
                <tr><td>Leave Deduction</td><td><span class="badge badge-red">Deduction</span></td><td>Unpaid leave days × daily rate</td></tr>
                <tr><td>Net Pay</td><td><span class="badge badge-blue">Result</span></td><td>Gross Earnings − All Deductions</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     8. RECRUITMENT
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 08</div>
        <div class="section-title">Recruitment &amp; Hiring</div>
        <div class="section-desc">End-to-end talent acquisition from job posting to offer letter</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">8.1 Recruitment Pipeline</div>
        <table class="doc-table">
            <thead><tr><th>Stage</th><th>Features</th></tr></thead>
            <tbody>
                <tr><td><strong>1. Job Posting</strong></td><td>Create vacancies with title, department, type (full-time/contract), salary range, deadline, description</td></tr>
                <tr><td><strong>2. Candidate Applications</strong></td><td>Candidate profiles with CV upload, source tracking (LinkedIn, referral, walk-in, etc.)</td></tr>
                <tr><td><strong>3. Screening</strong></td><td>Status pipeline: Applied → Shortlisted → Interview → Offered → Hired / Rejected</td></tr>
                <tr><td><strong>4. Interview Scheduling</strong></td><td>Schedule interviews with date/time/type, linked interviewers</td></tr>
                <tr><td><strong>5. Structured Feedback</strong></td><td>Per-interviewer scorecards with weighted criteria scoring</td></tr>
                <tr><td><strong>6. Offer Management</strong></td><td>Issue formal offer letter with salary details; candidate accepts/rejects via HRMS</td></tr>
                <tr><td><strong>7. Hire</strong></td><td>Accepted offer triggers employee profile creation workflow</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">8.2 Interview Scoring</div>
        <p>The system uses a <strong>weighted scoring model</strong> for structured candidate evaluation. Each
           interview scorecard contains multiple criteria (Technical Skills, Communication, Cultural Fit, etc.),
           each with a configurable weight. The system computes a weighted average score per candidate and
           displays a ranked comparison across all interviewers.</p>

        <div class="callout callout-amber">
            <strong>Offer Workflow:</strong> Once an offer is issued, the candidate receives a notification
            email. The candidate can accept or decline directly from the HRMS. If accepted, the recruiter is
            prompted to initiate the employee onboarding checklist.
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     9. PERFORMANCE MANAGEMENT
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 09</div>
        <div class="section-title">Performance Management &amp; BSC</div>
        <div class="section-desc">Goal setting, PIPs, 360-degree reviews, and Balanced Scorecard categories</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">9.1 Performance Features</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>360° Reviews</td><td>Multi-rater evaluations — self, peer, manager, and subordinate ratings per cycle</td></tr>
                <tr><td>BSC Categories</td><td>Goals categorised by: Financial, Customer, Internal Process, Learning &amp; Growth</td></tr>
                <tr><td>Goal Setting</td><td>Employee or manager creates SMART goals with weight, target, deadline, and BSC category</td></tr>
                <tr><td>Goal Progress</td><td>Inline progress slider (0–100%) with automatic status derivation</td></tr>
                <tr><td>Performance Cycles</td><td>Annual/semi-annual review cycles with configurable periods</td></tr>
                <tr><td>Overall Score</td><td>Weighted aggregate of all goals within a cycle</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">9.2 Performance Improvement Plans (PIPs)</div>
        <table class="doc-table">
            <thead><tr><th>Field</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Employee</td><td>Target employee placed on PIP</td></tr>
                <tr><td>Initiated By</td><td>HR or manager who created the PIP</td></tr>
                <tr><td>Objectives</td><td>JSON array of measurable improvement objectives</td></tr>
                <tr><td>Duration</td><td>Start date and end date</td></tr>
                <tr><td>Status</td><td>Active, Completed, Extended, Terminated</td></tr>
                <tr><td>Notes</td><td>Ongoing check-in notes from the reviewing manager</td></tr>
                <tr><td>Notification</td><td>Employee receives in-app notification when PIP is created</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     10. TRAINING & DEVELOPMENT
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 10</div>
        <div class="section-title">Training &amp; Development</div>
        <div class="section-desc">Programme catalogue, enrolments, assessments, and certification alerts</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">10.1 Features</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Training Catalogue</td><td>Create training programmes with title, type, provider, dates, capacity, cost</td></tr>
                <tr><td>Self-Enrolment</td><td>Employees browse catalogue and enrol directly; HR approves if capacity limited</td></tr>
                <tr><td>Progress Tracking</td><td>Enrolment statuses: Enrolled → In Progress → Completed; completion date recorded</td></tr>
                <tr><td>Assessments</td><td>Post-training assessments with configurable pass score (default 70%)</td></tr>
                <tr><td>Results</td><td>Pass/fail recorded with score; failed assessments can be retaken</td></tr>
                <tr><td>Certification Management</td><td>Upload certificates against employee profiles with expiry dates</td></tr>
                <tr><td>Expiry Alerts</td><td>Daily command emails employees and HR 30 days before certificate expiry</td></tr>
                <tr><td>Training Report</td><td>Training completion rate by department and programme type</td></tr>
            </tbody>
        </table>
    </div>

    <div class="callout callout-green">
        <strong>Assessment Pass Logic:</strong> A score of 70% or above marks the assessment as passed
        and automatically updates the enrolment status to "Completed". Scores below 70% are marked
        as failed and the employee may be permitted additional attempts at the trainer's discretion.
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     11. EMPLOYEE LIFECYCLE
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 11</div>
        <div class="section-title">Employee Lifecycle</div>
        <div class="section-desc">Structured onboarding checklists and exit/offboarding workflows</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">11.1 Onboarding</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Task Checklist</td><td>HR creates per-employee onboarding tasks (ID submission, contract signing, IT setup, etc.)</td></tr>
                <tr><td>Template Tasks</td><td>Quick-add buttons for common tasks: System Access, Welcome Email, Desk Setup, Policy Review</td></tr>
                <tr><td>Completion Toggle</td><td>Each task can be marked complete with timestamp and completing user recorded</td></tr>
                <tr><td>Progress Indicator</td><td>Visual progress bar showing X of N tasks completed</td></tr>
                <tr><td>Custom Tasks</td><td>HR can add any custom task not covered by templates</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">11.2 Exit / Offboarding Workflow</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Exit Initiation</td><td>HR initiates exit with: exit date, reason (resignation/dismissal/retirement), exit interview notes</td></tr>
                <tr><td>Exit Checklist</td><td>Structured offboarding: Equipment Return, Access Revocation, Clearance, Final Settlement</td></tr>
                <tr><td>Settlement Amount</td><td>Gratuity / terminal benefit amount recorded</td></tr>
                <tr><td>Status Transition</td><td>Employee status set to "terminated" automatically on exit date</td></tr>
                <tr><td>Audit Trail</td><td>Full history of exit actions saved in audit logs</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     12. MEETINGS & CALENDAR
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 12</div>
        <div class="section-title">Meetings &amp; Calendar</div>
        <div class="section-desc">Meeting scheduling, recurring meetings, RSVP, and interactive calendar</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">12.1 Features</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Meeting Scheduling</td><td>Create meetings with title, date/time, location, description, and participant list</td></tr>
                <tr><td>Participant Management</td><td>Multi-select employee participants from the full employee directory</td></tr>
                <tr><td>Invite Emails</td><td>Queued email invites sent to all participants immediately on scheduling</td></tr>
                <tr><td>RSVP</td><td>Participants can Accept or Decline from within the HRMS; status shown on meeting page</td></tr>
                <tr><td>Recurrence</td><td>Daily, Weekly, Bi-weekly, Monthly recurrence with configurable end date</td></tr>
                <tr><td>Auto Instance Generation</td><td>Daily scheduled command spawns next recurring meeting instance 24h in advance</td></tr>
                <tr><td>Meeting Status</td><td>Scheduled, In Progress, Completed, Cancelled</td></tr>
                <tr><td>Cancel Meeting</td><td>Organiser can cancel a meeting; participants are notified</td></tr>
                <tr><td>Calendar View</td><td>Interactive full-month calendar (FullCalendar.js) with colour-coded meeting status</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">12.2 Recurring Meeting Flow</div>
        <div class="callout">
            When a recurring meeting is created, the system stores the recurrence pattern on the parent
            record. The scheduled Artisan command <span class="mono">hrms:generate-recurring-meetings</span>
            runs daily at 01:00 and computes the next occurrence date. If the next occurrence falls
            tomorrow and no instance exists yet, a child meeting record is created, participants are
            copied, and invite emails are queued.
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     13. REPORTS & EXPORTS
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 13</div>
        <div class="section-title">Reports &amp; Exports</div>
        <div class="section-desc">Analytics dashboards, module-specific reports, and data export</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">13.1 Available Reports</div>
        <table class="doc-table">
            <thead><tr><th>Report</th><th>Access</th><th>Formats</th></tr></thead>
            <tbody>
                <tr><td>Payroll Summary Report</td><td>HR Admin, Payroll Officer</td><td>On-screen, PDF, CSV</td></tr>
                <tr><td>Attendance Report</td><td>HR Admin, Manager</td><td>On-screen, CSV export</td></tr>
                <tr><td>Leave Utilisation Report</td><td>HR Admin, Manager</td><td>On-screen, CSV export</td></tr>
                <tr><td>Employee Directory Export</td><td>HR Admin</td><td>CSV</td></tr>
                <tr><td>Bank Payment File</td><td>Payroll Officer</td><td>CSV (bank-ready format)</td></tr>
                <tr><td>Training Completion Report</td><td>HR Admin</td><td>On-screen</td></tr>
                <tr><td>Recruitment Pipeline Summary</td><td>Recruiter, HR Admin</td><td>On-screen</td></tr>
                <tr><td>Performance Score Summary</td><td>Manager, HR Admin</td><td>On-screen</td></tr>
                <tr><td>Payslips (per employee)</td><td>Employee, HR Admin</td><td>PDF</td></tr>
                <tr><td>System Documentation</td><td>Super Admin</td><td>PDF (this document)</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">13.2 Dashboard Analytics</div>
        <p>The main dashboard presents real-time KPI cards and ApexCharts visualisations:</p>
        <table class="doc-table">
            <thead><tr><th>Widget</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>Total Employees</td><td>Active headcount with trend vs last month</td></tr>
                <tr><td>Present Today</td><td>Employees who have clocked in today</td></tr>
                <tr><td>Pending Leave</td><td>Leave requests awaiting approval</td></tr>
                <tr><td>Monthly Payroll</td><td>Total net pay for current payroll run</td></tr>
                <tr><td>Attendance Trend</td><td>7-day line chart of daily attendance count</td></tr>
                <tr><td>Department Headcount</td><td>Donut/bar chart by department</td></tr>
                <tr><td>Recent Activities</td><td>Live feed of recent system events</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     14. SECURITY & AUDIT
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 14</div>
        <div class="section-title">Security &amp; Audit</div>
        <div class="section-desc">MFA, rate limiting, RBAC, audit logging, and data protection measures</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">14.1 Security Features</div>
        <table class="doc-table">
            <thead><tr><th>Feature</th><th>Implementation</th><th>Standard</th></tr></thead>
            <tbody>
                <tr>
                    <td><strong>Multi-Factor Authentication (MFA)</strong></td>
                    <td>TOTP via Google Authenticator / Authy; QR code setup; session gate</td>
                    <td><span class="badge badge-blue">TOTP / RFC 6238</span></td>
                </tr>
                <tr>
                    <td><strong>Login Rate Limiting</strong></td>
                    <td>5 failed attempts per minute per IP; 60-second lock-out with user feedback</td>
                    <td><span class="badge badge-blue">OWASP A07</span></td>
                </tr>
                <tr>
                    <td><strong>Role-Based Access Control</strong></td>
                    <td>Spatie Laravel Permission; middleware-level route guards per role</td>
                    <td><span class="badge badge-blue">RBAC</span></td>
                </tr>
                <tr>
                    <td><strong>CSRF Protection</strong></td>
                    <td>Laravel's built-in CSRF tokens on all state-changing requests</td>
                    <td><span class="badge badge-blue">OWASP A01</span></td>
                </tr>
                <tr>
                    <td><strong>SQL Injection Prevention</strong></td>
                    <td>Eloquent ORM with parameterised queries throughout</td>
                    <td><span class="badge badge-blue">OWASP A03</span></td>
                </tr>
                <tr>
                    <td><strong>XSS Prevention</strong></td>
                    <td>Blade's <span class="mono">&#123;&#123; &#125;&#125;</span> auto-escaping; no raw HTML from user input</td>
                    <td><span class="badge badge-blue">OWASP A03</span></td>
                </tr>
                <tr>
                    <td><strong>Password Hashing</strong></td>
                    <td>bcrypt via Laravel's Hash facade; never stored in plaintext</td>
                    <td><span class="badge badge-blue">bcrypt</span></td>
                </tr>
                <tr>
                    <td><strong>Session Security</strong></td>
                    <td>MFA session gate (<span class="mono">mfa_verified</span> flag); session regeneration on login</td>
                    <td><span class="badge badge-blue">OWASP A07</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">14.2 MFA Setup Process</div>
        <div class="callout">
            1. User navigates to <strong>Profile → Two-Factor Auth</strong><br>
            2. System generates a unique TOTP secret and displays a QR code<br>
            3. User scans with Google Authenticator or Authy<br>
            4. User submits a 6-digit verification code to confirm setup<br>
            5. MFA is activated — subsequent logins require the 6-digit code<br>
            6. User can disable MFA by confirming their password
        </div>
    </div>

    <div class="sub-section">
        <div class="sub-title">14.3 Audit Log</div>
        <p>Every data-changing action in the system is recorded in the <span class="mono">audit_logs</span>
           table via Model Observers. The audit log captures:</p>
        <table class="doc-table">
            <thead><tr><th>Field</th><th>Description</th></tr></thead>
            <tbody>
                <tr><td>User</td><td>The authenticated user who performed the action</td></tr>
                <tr><td>Action</td><td>created, updated, or deleted</td></tr>
                <tr><td>Model Type</td><td>The Eloquent model class affected</td></tr>
                <tr><td>Model ID</td><td>The primary key of the affected record</td></tr>
                <tr><td>Old Values</td><td>JSON snapshot of values before the change</td></tr>
                <tr><td>New Values</td><td>JSON snapshot of values after the change</td></tr>
                <tr><td>IP Address</td><td>Client IP address at time of action</td></tr>
                <tr><td>Timestamp</td><td>Exact date and time of the event</td></tr>
            </tbody>
        </table>
        <div class="callout callout-amber">
            The audit log is filterable by model type, action, user, and date range. Changes are displayed
            as a collapsible diff panel in the admin interface. Only Super Admins and HR Admins can access
            the audit log.
        </div>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     15. PWA & MOBILE
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 15</div>
        <div class="section-title">PWA &amp; Mobile Capabilities</div>
        <div class="section-desc">Progressive Web App — installable, offline-capable, mobile-first</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">15.1 PWA Features</div>
        <table class="doc-table">
            <thead><tr><th>Capability</th><th>Implementation</th><th>Status</th></tr></thead>
            <tbody>
                <tr><td>Installable on Home Screen</td><td>Web App Manifest with name, icons, theme colour</td><td><span class="badge badge-green">Active</span></td></tr>
                <tr><td>App-like Standalone Display</td><td><span class="mono">display: standalone</span> in manifest</td><td><span class="badge badge-green">Active</span></td></tr>
                <tr><td>Offline Fallback Page</td><td>Service worker serves <span class="mono">/offline.html</span> when network unavailable</td><td><span class="badge badge-green">Active</span></td></tr>
                <tr><td>Static Asset Caching</td><td>CDN fonts/scripts cached via cache-first strategy</td><td><span class="badge badge-green">Active</span></td></tr>
                <tr><td>App Shortcuts</td><td>Dashboard, Attendance, Leave quick-launch shortcuts</td><td><span class="badge badge-green">Active</span></td></tr>
                <tr><td>Theme Colour</td><td>Blue (#1d4ed8) status bar on mobile</td><td><span class="badge badge-green">Active</span></td></tr>
                <tr><td>Background Sync</td><td>Not implemented (network-dependent operations)</td><td><span class="badge badge-slate">Roadmap</span></td></tr>
                <tr><td>Push Notifications</td><td>Browser push API integration</td><td><span class="badge badge-slate">Roadmap</span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">15.2 Service Worker Strategy</div>
        <table class="doc-table">
            <thead><tr><th>Request Type</th><th>Strategy</th><th>Rationale</th></tr></thead>
            <tbody>
                <tr><td>HTML navigation (GET)</td><td>Network-first → Offline fallback</td><td>Always fresh content; graceful degradation</td></tr>
                <tr><td>CDN assets (CSS/JS/fonts)</td><td>Cache-first → Network update</td><td>Fast load; CDN assets rarely change</td></tr>
                <tr><td>AJAX / API calls</td><td>Network-only</td><td>Data must always be real-time; never cached</td></tr>
                <tr><td>POST / non-GET</td><td>Network-only (pass-through)</td><td>State-changing operations cannot be intercepted</td></tr>
            </tbody>
        </table>
    </div>

    <div class="callout callout-green">
        <strong>Installation (Android/Chrome):</strong> Navigate to the HRMS URL in Chrome → tap the
        install prompt or use the browser menu "Add to Home Screen" → the app launches in standalone
        mode with no browser chrome, indistinguishable from a native app.
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     16. NOTIFICATIONS & EMAIL
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 16</div>
        <div class="section-title">Notifications &amp; Email System</div>
        <div class="section-desc">Queued email notifications triggered by system events via Model Observers</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">16.1 Email Notification Catalogue</div>
        <table class="doc-table">
            <thead><tr><th>Event</th><th>Mailable Class</th><th>Recipients</th><th>Trigger</th></tr></thead>
            <tbody>
                <tr><td>Leave Submitted</td><td><span class="mono">LeaveSubmittedMail</span></td><td>HR Admin, Managers</td><td>Employee submits leave request</td></tr>
                <tr><td>Leave Status Changed</td><td><span class="mono">LeaveStatusMail</span></td><td>Employee</td><td>Request approved or rejected</td></tr>
                <tr><td>Payroll Processed</td><td><span class="mono">PayrollProcessedMail</span></td><td>Employee</td><td>Payslip created by payroll run</td></tr>
                <tr><td>Meeting Invitation</td><td><span class="mono">MeetingInviteMail</span></td><td>All participants</td><td>Meeting scheduled or recurring instance created</td></tr>
                <tr><td>Training Enrollment</td><td><span class="mono">TrainingEnrollmentMail</span></td><td>Employee</td><td>Enrolled in a training programme</td></tr>
                <tr><td>Certification Expiry</td><td><span class="mono">CertificationExpiryMail</span></td><td>Employee + HR</td><td>Daily command — 30 days before expiry</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">16.2 In-App Notifications</div>
        <p>
            In addition to email, the HRMS provides a real-time in-app notification bell (top navigation bar).
            Notifications are fetched via <span class="mono">/ajax/notifications</span> and display unread
            count. Users can mark all as read.
        </p>
        <div class="callout">
            <strong>Queue Configuration:</strong> All email notifications use Laravel's <span class="mono">Queue</span>
            system (<span class="mono">Mail::to()->queue()</span>). Ensure the queue worker is running
            in production: <span class="mono">php artisan queue:work --daemon</span>
        </div>
    </div>

    <div class="sub-section">
        <div class="sub-title">16.3 Scheduled Commands Reference</div>
        <table class="doc-table">
            <thead><tr><th>Command</th><th>Schedule</th><th>Action</th></tr></thead>
            <tbody>
                <tr><td><span class="mono">hrms:seed-leave-balances</span></td><td>Jan 1 at 00:30</td><td>Seeds new-year leave balances for all employees with carry-forward</td></tr>
                <tr><td><span class="mono">hrms:auto-resume-leave-status</span></td><td>Daily at 00:05</td><td>Resets on_leave employees to active when leave period ends</td></tr>
                <tr><td><span class="mono">hrms:cert-expiry-alert</span></td><td>Daily at 08:00</td><td>Emails employees and HR about certificates expiring within 30 days</td></tr>
                <tr><td><span class="mono">hrms:generate-recurring-meetings</span></td><td>Daily at 01:00</td><td>Creates next recurring meeting instance and sends invite emails</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="page-break"></div>

{{-- ═══════════════════════════════════════════════════════
     17. CONFIGURATION & DEPLOYMENT
══════════════════════════════════════════════════════════ --}}
<div class="section">
    <div class="section-header">
        <div class="section-number">Section 17</div>
        <div class="section-title">System Configuration &amp; Deployment</div>
        <div class="section-desc">Environment setup, credentials, scheduler, and production checklist</div>
    </div>

    <div class="sub-section">
        <div class="sub-title">17.1 Environment Variables (.env)</div>
        <table class="doc-table">
            <thead><tr><th>Variable</th><th>Description</th><th>Example Value</th></tr></thead>
            <tbody>
                <tr><td><span class="mono">APP_NAME</span></td><td>Application display name</td><td>Mastermind HRMS</td></tr>
                <tr><td><span class="mono">APP_URL</span></td><td>Full public URL</td><td>https://hrms.mastermind.co.ug</td></tr>
                <tr><td><span class="mono">DB_HOST</span></td><td>MySQL server host</td><td>127.0.0.1</td></tr>
                <tr><td><span class="mono">DB_DATABASE</span></td><td>Database name</td><td>mastermind_hrms</td></tr>
                <tr><td><span class="mono">MAIL_MAILER</span></td><td>Email driver</td><td>smtp</td></tr>
                <tr><td><span class="mono">MAIL_HOST</span></td><td>SMTP server</td><td>mail.mastermind.co.ug</td></tr>
                <tr><td><span class="mono">MAIL_FROM_ADDRESS</span></td><td>Sender email</td><td>noreply@mastermind.co.ug</td></tr>
                <tr><td><span class="mono">QUEUE_CONNECTION</span></td><td>Queue driver</td><td>database</td></tr>
                <tr><td><span class="mono">SESSION_DRIVER</span></td><td>Session storage</td><td>file (prod: redis)</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">17.2 Initial Setup Commands</div>
        <table class="doc-table">
            <thead><tr><th>#</th><th>Command</th><th>Purpose</th></tr></thead>
            <tbody>
                <tr><td>1</td><td><span class="mono">composer install</span></td><td>Install PHP dependencies</td></tr>
                <tr><td>2</td><td><span class="mono">cp .env.example .env</span></td><td>Create environment file</td></tr>
                <tr><td>3</td><td><span class="mono">php artisan key:generate</span></td><td>Generate application encryption key</td></tr>
                <tr><td>4</td><td><span class="mono">php artisan migrate --seed</span></td><td>Run all migrations and seed default data</td></tr>
                <tr><td>5</td><td><span class="mono">php artisan queue:table &amp;&amp; migrate</span></td><td>Create queue jobs table</td></tr>
                <tr><td>6</td><td><span class="mono">php artisan storage:link</span></td><td>Link public storage for file uploads</td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">17.3 Production Checklist</div>
        <table class="doc-table">
            <thead><tr><th>Item</th><th>Check</th></tr></thead>
            <tbody>
                <tr><td>Set <span class="mono">APP_ENV=production</span> and <span class="mono">APP_DEBUG=false</span></td><td><span class="badge badge-green">Required</span></td></tr>
                <tr><td>Configure real SMTP credentials in .env</td><td><span class="badge badge-green">Required</span></td></tr>
                <tr><td>Run queue worker as a daemon or supervisor process</td><td><span class="badge badge-green">Required</span></td></tr>
                <tr><td>Schedule <span class="mono">php artisan schedule:run</span> via cron every minute</td><td><span class="badge badge-green">Required</span></td></tr>
                <tr><td>Configure HTTPS (SSL certificate)</td><td><span class="badge badge-green">Required</span></td></tr>
                <tr><td>Serve icons at <span class="mono">/public/icons/icon-192.png</span> and <span class="mono">icon-512.png</span></td><td><span class="badge badge-amber">PWA</span></td></tr>
                <tr><td>Run <span class="mono">php artisan config:cache</span> and <span class="mono">route:cache</span></td><td><span class="badge badge-blue">Performance</span></td></tr>
                <tr><td>Configure daily database backups</td><td><span class="badge badge-blue">Recommended</span></td></tr>
            </tbody>
        </table>
    </div>

    <div class="sub-section">
        <div class="sub-title">17.4 Default Administrator Account</div>
        <div class="callout callout-red">
            <strong>Security Notice:</strong> Change the default administrator password immediately after
            first login in any production environment.
        </div>
        <table class="doc-table">
            <thead><tr><th>Field</th><th>Value</th></tr></thead>
            <tbody>
                <tr><td>Email</td><td><span class="mono">admin@mastermind.co.za</span></td></tr>
                <tr><td>Default Password</td><td><span class="mono">Admin@1234</span></td></tr>
                <tr><td>Role</td><td>super-admin</td></tr>
            </tbody>
        </table>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════
     BACK COVER / SIGNATURE PAGE
══════════════════════════════════════════════════════════ --}}
<div class="page-break"></div>
<div style="background:#0f172a; color:#e2e8f0; padding:60px; min-height:600px;">
    <div style="border-top:4px solid #1d4ed8; padding-top:40px;">
        <div style="font-size:8pt; letter-spacing:0.15em; text-transform:uppercase; color:#60a5fa; margin-bottom:12px;">
            Document Certification
        </div>
        <h2 style="font-size:20pt; color:#fff; margin-bottom:6px;">Mastermind Consultants HRMS</h2>
        <p style="color:#64748b; font-size:10pt; margin-bottom:40px;">System Documentation — Version 1.0</p>

        <table style="width:100%; border-collapse:collapse;">
            <tr>
                <td style="width:50%; vertical-align:top; padding-right:40px;">
                    <div style="border-top:1px solid #334155; padding-top:16px; margin-top:60px;">
                        <p style="color:#94a3b8; font-size:8pt; margin-bottom:4px;">Prepared by</p>
                        <p style="color:#f1f5f9; font-size:13pt; font-weight:bold; margin-bottom:2px;">Higeni Abdulkarim</p>
                        <p style="color:#60a5fa; font-size:9pt;">Lead Developer &amp; Technical Architect</p>
                        <p style="color:#64748b; font-size:8.5pt; margin-top:8px;">Ehsan Developers</p>
                    </div>
                </td>
                <td style="width:50%; vertical-align:top; padding-left:40px; border-left:1px solid #1e293b;">
                    <div style="border-top:1px solid #334155; padding-top:16px; margin-top:60px;">
                        <p style="color:#94a3b8; font-size:8pt; margin-bottom:4px;">Prepared for</p>
                        <p style="color:#f1f5f9; font-size:13pt; font-weight:bold; margin-bottom:2px;">Mastermind Consultants</p>
                        <p style="color:#60a5fa; font-size:9pt;">Client Organisation</p>
                        <p style="color:#64748b; font-size:8.5pt; margin-top:8px;">{{ \Carbon\Carbon::now()->format('F j, Y') }}</p>
                    </div>
                </td>
            </tr>
        </table>

        <div style="margin-top:60px; padding:20px; background:#1e293b; border-radius:8px; border:1px solid #334155;">
            <p style="font-size:8pt; color:#475569; margin:0; text-align:center;">
                This document is <strong style="color:#64748b;">confidential and proprietary</strong> to Mastermind Consultants and Ehsan Developers.
                It may not be reproduced, distributed, or disclosed to any third party without prior written consent.
                &copy; 2026 Ehsan Developers. All rights reserved.
            </p>
        </div>
    </div>
</div>

</body>
</html>
