<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<style>
/* ============================================================
   Partyline admin design system ("plg") — shared by the
   Main, Settings, Partyliners and How-To screens. Namespaced
   under .plg so it sits cleanly on top of wp-admin + the older
   broadstreet.css (which scopes to #main, avoided here).
   ============================================================ */
.plg { --ink:#18181b; --muted:#6b7280; --line:#e7e7ea; --surface:#f6f6f8; --accent:#7c3aed; }
.plg { max-width: 880px; margin: 18px auto 64px; color: var(--ink);
       font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
       -webkit-font-smoothing: antialiased; }
.plg *, .plg *::before, .plg *::after { box-sizing: border-box; }
.plg-wrap { background:#fff; border:1px solid var(--line); border-radius:22px; overflow:hidden;
            box-shadow:0 4px 24px rgba(24,24,27,.06); }

/* Hero */
.plg-hero { text-align:center; padding:44px 28px 40px; color:#fff;
            background:linear-gradient(135deg,#4f46e5 0%,#9333ea 52%,#ec4899 100%); }
.plg-hero-logo { display:block; width:230px; max-width:70%; height:auto; margin:0 auto 18px;
                 filter:brightness(0) invert(1); }
.plg-hero .plg-lead { color:#fff; text-align:center; margin:0 auto; max-width:52ch; font-size:16px; line-height:1.55; }

/* TOC / quick nav pills */
.plg-toc { display:flex; flex-wrap:wrap; justify-content:center; gap:8px; padding:16px 24px;
           background:var(--surface); border-bottom:1px solid #f1f1f3; }
.plg-toc a { font-size:13px; font-weight:700; color:var(--ink); background:#fff; border:1px solid var(--line);
             border-radius:999px; padding:7px 14px; text-decoration:none; }
.plg-toc a:hover { border-color:var(--accent); color:var(--accent); }

/* Sections */
.plg-section { padding:32px 44px; border-top:1px solid #f1f1f3; scroll-margin-top:40px; }
.plg-toc + .plg-section, .plg-hero + .plg-section { border-top:none; }
.plg-eyebrow { font-size:12px; font-weight:800; letter-spacing:.11em; text-transform:uppercase; color:var(--accent); margin-bottom:7px; }
.plg-section h2 { margin:0 0 6px; font-size:24px; font-weight:800; letter-spacing:-.01em; padding:0; }
.plg-section h3 { margin:24px 0 8px; font-size:16px; font-weight:800; }
.plg-section > .plg-intro { font-size:15px; line-height:1.65; color:var(--muted); margin:0 0 20px; }
.plg p { font-size:15px; line-height:1.7; color:#3f3f46; margin:0 0 14px; }
.plg a { color:var(--accent); text-decoration:none; font-weight:600; }
.plg a:hover { text-decoration:underline; }
.plg-note { background:var(--surface); border-radius:12px; padding:14px 16px; font-size:14px; margin-top:4px; color:#3f3f46; }

/* Feature rows (icon + label + desc) */
.plg-features { display:flex; flex-direction:column; gap:14px; }
.plg-feature { display:flex; gap:14px; align-items:flex-start; }
.plg-feature .ico { flex:0 0 auto; width:42px; height:42px; border-radius:11px; background:var(--surface);
                    display:flex; align-items:center; justify-content:center; font-size:21px; }
.plg-feature strong { font-size:15px; display:block; }
.plg-feature span { font-size:14px; color:var(--muted); line-height:1.55; }

/* ---- Form controls (Settings) ---- */
.plg-field { padding:20px 0; border-top:1px solid #f1f1f3; }
.plg-section > .plg-field:first-of-type { border-top:none; padding-top:6px; }
.plg-field-label { font-size:15px; font-weight:800; color:var(--ink); margin-bottom:4px; }
.plg-field-desc { font-size:13.5px; line-height:1.6; color:var(--muted); margin-bottom:12px; }
.plg-field-desc:last-child { margin-bottom:0; }
.plg input[type="text"], .plg input[type="password"], .plg input[type="email"],
.plg select, .plg textarea {
    width:100%; max-width:560px; box-sizing:border-box; padding:10px 12px;
    border:1px solid #d4d4d8; border-radius:10px; font-size:14px; color:var(--ink);
    background:#fff; font-family:inherit; line-height:1.5; box-shadow:none; }
.plg textarea { min-height:96px; resize:vertical; }
.plg textarea.tall { min-height:190px; }
.plg input:focus, .plg select:focus, .plg textarea:focus {
    outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(124,58,237,.14); }

/* Toggle rows */
.plg-check { display:inline-flex; align-items:center; gap:10px; font-size:14px; font-weight:600; color:var(--ink); cursor:pointer; }
.plg-check input[type="checkbox"] { width:18px; height:18px; margin:0; accent-color:var(--accent); cursor:pointer; }

/* Copyable link box */
.plg-linkbox { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:14px 0 2px;
               background:var(--surface); border:1px solid var(--line); border-radius:12px; padding:12px 14px; }
.plg-linkbox .lbl { font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:var(--muted); }
.plg-linkbox .url { font-family:Menlo,Consolas,monospace; font-size:13.5px; color:var(--ink); word-break:break-all; flex:1 1 auto; }
.plg-copy { border:none; background:var(--ink); color:#fff; font-size:12px; font-weight:700; padding:8px 13px;
            border-radius:9px; cursor:pointer; white-space:nowrap; }
.plg-copy:hover { background:#000; }

/* Callouts */
.plg-callout { border-radius:14px; padding:15px 17px; font-size:14.5px; line-height:1.6; margin-top:14px; }
.plg-callout.warn { background:#fff7ed; border:1px solid #fdba74; color:#9a3412; }
.plg-callout.pink { background:linear-gradient(135deg,#fff1e6,#ffe0ec); border:1px solid #fbd6c8; color:#7c2d3a; }

/* Buttons */
.plg-btn { display:inline-block; background:var(--ink); color:#fff; border:0; border-radius:11px;
           padding:11px 22px; font-size:14px; font-weight:700; cursor:pointer; text-decoration:none; line-height:1.2; }
.plg-btn:hover { background:#000; color:#fff; }
.plg-btn.ghost { background:#fff; color:var(--ink); border:1px solid var(--line); }
.plg-btn.ghost:hover { border-color:var(--accent); color:var(--accent); background:#fff; }

/* Save bar */
.plg-savebar { display:flex; align-items:center; justify-content:space-between; gap:16px;
               padding:22px 44px; background:var(--surface); border-top:1px solid #f1f1f3; flex-wrap:wrap; }
.plg-savebar .hint { font-size:13px; color:var(--muted); }
.plg-saving { display:inline-flex; align-items:center; gap:9px; font-size:13px; color:var(--muted); }
.plg-saving img { height:14px; }

/* Search */
.plg-search { margin:0 0 16px; }
.plg-search input { width:340px; max-width:100%; }

/* Tables */
.plg-table { width:100%; border-collapse:collapse; font-size:14px; }
.plg-table th { text-align:left; padding:9px 10px; border-bottom:2px solid var(--line); color:var(--muted);
                font-size:11.5px; text-transform:uppercase; letter-spacing:.04em; font-weight:800; }
.plg-table td { padding:12px 10px; border-bottom:1px solid #f2f2f4; vertical-align:top; color:#3f3f46; }
.plg-table tbody tr:last-child td, .plg-table tbody tr:last-child td { border-bottom:none; }
.plg-table tr:hover td { background:#fafafa; }
.plg-table .name { font-weight:700; color:var(--ink); }
.plg-muted { color:#a1a1aa; }
.plg-count { display:inline-block; min-width:24px; text-align:center; background:var(--surface); border:1px solid var(--line);
             border-radius:999px; padding:2px 9px; font-weight:800; font-size:13px; color:var(--ink); }
.plg-actions a { text-decoration:none; margin-right:12px; font-weight:600; }
.plg-actions a.del { color:#b32020; }

/* Add-grid (Partyliners) */
.plg-grid { display:grid; grid-template-columns:1fr 1fr; gap:14px 18px; margin-bottom:16px; }
.plg-grid .full { grid-column:1 / -1; }
.plg-grid label { display:block; font-weight:800; font-size:12px; color:var(--muted); margin-bottom:5px;
                  text-transform:uppercase; letter-spacing:.03em; }

/* Notices */
.plg-flash { padding:13px 16px; border-radius:12px; margin:0 0 20px; font-size:14px; font-weight:600; }
.plg-flash.is-success { background:#edfaef; border:1px solid #b7e4c0; color:#1a7431; }
.plg-flash.is-error   { background:#fdecec; border:1px solid #f3bcbc; color:#b32020; }

/* Submission list (Main page) */
.plg-list { display:flex; flex-direction:column; gap:10px; }
.plg-list-item { display:block; border:1px solid var(--line); border-radius:14px; padding:15px 17px;
                 text-decoration:none; transition:border-color .12s, box-shadow .12s; background:#fff; }
.plg-list-item:hover { border-color:var(--accent); box-shadow:0 3px 14px rgba(124,58,237,.09); text-decoration:none; }
.plg-list-item .t { font-size:15.5px; font-weight:800; color:var(--ink); margin-bottom:5px; }
.plg-list-item .m { display:flex; align-items:center; gap:10px; font-size:12.5px; color:var(--muted); }
.plg-pill { display:inline-block; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em;
            padding:2px 9px; border-radius:999px; background:#fef3c7; color:#92600a; }

/* Empty state */
.plg-empty { padding:36px 20px; text-align:center; color:var(--muted); border:1px dashed var(--line); border-radius:14px; }
.plg-empty .big { font-size:34px; margin-bottom:8px; }

/* Footer / tag */
.plg-tag { text-align:center; font-weight:800; letter-spacing:.22em; font-size:14px; color:#fff; padding:22px; background:var(--ink); }
.plg-footer { text-align:center; padding:20px; font-size:13px; color:var(--muted); }

@media (max-width:600px){
  .plg-section, .plg-savebar { padding-left:22px; padding-right:22px; }
  .plg-hero { padding:38px 20px; }
  .plg-grid { grid-template-columns:1fr; }
}
</style>
