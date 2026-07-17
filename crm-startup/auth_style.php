<?php // Styles communs aux pages publiques (login, register, forgot, verify) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0d1117;--surface:#161b27;--card:#1c2333;--border:#2a3349;
  --accent:#38bdf8;--accent2:#818cf8;--text:#f0f4f8;--muted:#A8B8CC;--label:#D2DFED;
  --error:#f87171;--success:#34d399;
  --font:'Inter',-apple-system,'Segoe UI',sans-serif;--mono:'DM Mono','Courier New',monospace;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--bg);color:var(--text);min-height:100vh;padding:20px;line-height:1.6}
.auth-center{display:flex;align-items:center;justify-content:center;min-height:calc(100vh - 40px)}
.wrap{width:100%;max-width:420px;margin:0 auto}
.wrap-lg{max-width:680px}
.logo-block{text-align:center;margin-bottom:28px}
.logo-icon{font-size:38px;margin-bottom:10px}
.logo-title{font-family:var(--mono);font-size:23px;font-weight:700;color:var(--accent);letter-spacing:-1px}
.logo-title span{color:var(--text)}
.logo-sub{font-size:13px;color:var(--muted);margin-top:5px}
.card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:32px;margin-bottom:16px}
.card h2{font-size:18px;font-weight:600;margin-bottom:8px;color:#fff}
.card > p{font-size:13px;color:var(--muted);line-height:1.6;margin-bottom:20px}
.section-label{font-size:11px;color:var(--accent);text-transform:uppercase;letter-spacing:2px;font-family:var(--mono);margin-bottom:16px;padding-bottom:8px;border-bottom:1px solid var(--border)}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.field{margin-bottom:16px}
.field label{display:block;font-size:12px;color:var(--label);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;font-family:var(--mono)}
.req{color:var(--accent)}
.field input,.field select,.field textarea{
  width:100%;padding:11px 14px;
  background:var(--surface);border:1px solid var(--border);border-radius:8px;
  color:var(--text);font-family:var(--font);font-size:14px;outline:none;transition:border-color .2s;
}
.field textarea{resize:vertical;min-height:72px}
.field select{-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg width='10' height='6' viewBox='0 0 10 6' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23A8B8CC' stroke-width='1.5' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:34px}
.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--accent)}
.field input::placeholder,.field textarea::placeholder{color:var(--muted)}
.field small{display:block;font-size:11px;color:var(--muted);margin-top:4px}
.btn-auth{
  width:100%;padding:13px;
  background:var(--accent);color:#000;
  border:none;border-radius:8px;
  font-family:var(--font);font-size:15px;font-weight:700;
  cursor:pointer;transition:opacity .15s;margin-top:4px;min-height:48px
}
.btn-auth:hover{opacity:.85}
.btn-ghost{
  width:100%;padding:10px;
  background:none;color:var(--muted);
  border:1px solid var(--border);border-radius:8px;
  font-family:var(--font);font-size:13px;font-weight:500;
  cursor:pointer;transition:all .15s;min-height:44px
}
.btn-ghost:hover{border-color:var(--accent);color:var(--accent)}
.error-msg{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171;padding:11px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.info-msg{background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.2);color:var(--accent);padding:11px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.success-msg{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#34d399;padding:11px 14px;border-radius:8px;font-size:13px;margin-bottom:16px}
.back-link{text-align:center;margin-top:16px;font-size:13px;color:var(--muted)}
.back-link a{color:var(--accent);text-decoration:none}
.back-link a:hover{text-decoration:underline}
.divider{text-align:center;color:var(--muted);font-size:12px;margin:20px 0;position:relative}
.divider::before,.divider::after{content:'';position:absolute;top:50%;width:40%;height:1px;background:var(--border)}
.divider::before{left:0}.divider::after{right:0}
/* Barre de force du mot de passe */
.pw-strength{margin-top:6px;height:4px;border-radius:2px;background:var(--border);overflow:hidden}
.pw-strength-bar{height:100%;border-radius:2px;width:0;transition:width .3s,background .3s}
.pw-hint{font-size:11px;color:var(--muted);margin-top:4px}
/* Pills multi-sélection */
.toggle-group{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.toggle-item{position:relative}
.toggle-item input[type="checkbox"]{position:absolute;opacity:0;width:0;height:0}
.toggle-item label{
  display:flex;align-items:center;gap:6px;
  padding:8px 14px;min-height:38px;
  background:var(--surface);border:1.5px solid var(--border);border-radius:20px;
  font-size:13px;font-weight:500;color:var(--label);
  cursor:pointer;transition:all .15s;
  white-space:nowrap;user-select:none;-webkit-user-select:none;
  text-transform:none;letter-spacing:0
}
.toggle-item label::before{content:'';width:14px;height:14px;border:1.5px solid #344060;border-radius:3px;background:var(--bg);flex-shrink:0;transition:all .15s}
.toggle-item input:checked+label{background:rgba(56,189,248,.1);border-color:var(--accent);color:var(--accent)}
.toggle-item input:checked+label::before{background:var(--accent);border-color:var(--accent);background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 10 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 4L3.5 6.5L9 1' stroke='%230d1117' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:center;background-size:10px}
.toggle-item label:hover{border-color:var(--accent);color:var(--accent)}
.check-row{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--label);margin-bottom:8px;cursor:pointer}
.check-row input{accent-color:var(--accent);width:16px;height:16px}
@media(max-width:560px){
  .grid2{grid-template-columns:1fr}
  .card{padding:24px 20px}
  .field input,.field select,.field textarea{font-size:16px;min-height:48px} /* iOS zoom fix */
}
</style>
