<?php
/** RYBSEN DATA ROOM — Signature électronique du NDA */
require_once __DIR__ . '/_dr.php';
require_once __DIR__ . '/_layout.php';

$db  = getDB();
$acc = drRequireLogin($db);

// Déjà signé → salle
if (intval($acc['nda_signe'])) { header('Location: /dataroom/room.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = hash_equals($_SESSION['dr_csrf'] ?? '', $_POST['csrf'] ?? '');
    $nom    = trim($_POST['fullname'] ?? '');
    $org    = trim($_POST['organisation'] ?? '');
    $agree  = !empty($_POST['agree']);

    if (!$csrfOk || !$nom || !$org || !$agree) {
        $error = t('nda_required');
    } else {
        $db->prepare("UPDATE dataroom_acces
                      SET nda_signe=1, nda_date=CURRENT_TIMESTAMP, nda_ip=?, nda_nom_signe=?, nda_organisation=?
                      WHERE id=?")
           ->execute([clientIp(), substr($nom, 0, 200), substr($org, 0, 200), $acc['id']]);
        drLog($db, intval($acc['id']), 'nda_signe', null, $nom . ' — ' . $org);
        header('Location: /dataroom/room.php');
        exit;
    }
}

drLog($db, intval($acc['id']), 'nda_vue');
drHead(t('nda_title'));
?>
<div style="max-width:820px;margin:0 auto">
  <div class="dr-card" style="padding:34px 38px;margin-bottom:22px">
    <div style="font-size:22px;font-weight:800;color:var(--navy-2);margin-bottom:8px">📜 <?= e(t('nda_title')) ?></div>
    <p style="font-size:13.5px;color:var(--muted)"><?= e(t('nda_intro')) ?></p>
  </div>

  <?php if ($error): ?><div class="dr-alert err"><?= e($error) ?></div><?php endif; ?>

  <!-- Texte du NDA (défilement obligatoire) -->
  <div class="dr-card" style="padding:0;overflow:hidden;margin-bottom:22px">
    <div id="nda-scroll" style="max-height:52vh;overflow-y:auto;padding:34px 40px;font-size:13.5px;line-height:1.75;color:#25333E">
      <div style="text-align:center;margin-bottom:22px">
        <div style="font-size:17px;font-weight:800;letter-spacing:2px;color:var(--navy-2)">RYBSEN SARL</div>
        <div style="font-size:11px;color:var(--muted)">www.rybsen.fr · yrezgui@rybsen.fr · Patent FR3070137</div>
        <div style="font-size:16px;font-weight:800;margin-top:16px">NON-DISCLOSURE AGREEMENT</div>
        <div style="font-size:12px;color:var(--muted)">(Unilateral Confidentiality Agreement)</div>
      </div>

      <p>This Non-Disclosure Agreement (the "Agreement") is entered into as of the date of electronic signature indicated below (the "Effective Date"), by and between:</p>
      <p style="margin-top:10px"><strong>RYBSEN SARL</strong>, a company duly incorporated under the laws of Tunisia, with its registered office at Centre Millenium, Sidi Daoud, Tunisia, represented by Mr. Yassine Rezgui, Co-founder (hereinafter the "Disclosing Party"),</p>
      <p style="margin-top:10px;text-align:center"><strong>AND</strong></p>
      <p style="margin-top:10px">The undersigned investor / prospective partner identified by the electronic signature below (hereinafter the "Receiving Party").</p>
      <p style="margin-top:10px">The Disclosing Party and the Receiving Party are hereinafter individually referred to as a "Party" and collectively as the "Parties".</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">1. PURPOSE</h3>
      <p>The Receiving Party wishes to review certain confidential business, technical, financial, and strategic information of RYBSEN in connection with a potential investment, partnership, or business relationship relating to RYBSEN's proprietary water recycling technology known as AquaClean (the "Purpose"). This Agreement sets forth the terms and conditions under which such information will be disclosed and protected.</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">2. DEFINITION OF CONFIDENTIAL INFORMATION</h3>
      <p>"Confidential Information" means any and all non-public information disclosed by the Disclosing Party to the Receiving Party, whether oral, written, electronic, or in any other form, including but not limited to:</p>
      <ul style="margin:8px 0 0 22px">
        <li>Technical data, patents, patent applications, engineering specifications, filtration processes, and any information relating to Patent FR3070137 and any related or subsequent patents (including AquaClean V2 developments);</li>
        <li>Financial information, including capitalization tables, valuation models, fundraising terms, revenue figures, and projections;</li>
        <li>Business plans, pitch decks, investor materials, commercial contracts, and partnership agreements;</li>
        <li>Client lists, supplier information, manufacturing processes, and pricing structures;</li>
        <li>Any other information that a reasonable person would understand to be confidential given the nature of the information and the circumstances of disclosure.</li>
      </ul>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">3. OBLIGATIONS OF THE RECEIVING PARTY</h3>
      <p>The Receiving Party undertakes to:</p>
      <ul style="margin:8px 0 0 22px">
        <li>Hold all Confidential Information in strict confidence and not disclose it to any third party without the prior written consent of the Disclosing Party;</li>
        <li>Use the Confidential Information solely for the Purpose described in Section 1, and for no other purpose whatsoever;</li>
        <li>Take all reasonable precautions to protect the confidentiality of the information, using at minimum the same degree of care it uses to protect its own confidential information, and in no event less than a reasonable standard of care;</li>
        <li>Limit access to the Confidential Information to its own employees, advisors, or representatives who have a strict need to know such information for the Purpose, and ensure such persons are bound by confidentiality obligations at least as protective as those set forth herein;</li>
        <li>Not reproduce, copy, or reverse-engineer any technical information, prototype, or process disclosed, in particular any element relating to AquaClean's filtration media, technical parameters, or patented processes;</li>
        <li>Promptly notify the Disclosing Party in writing in the event of any unauthorized use or disclosure of the Confidential Information.</li>
      </ul>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">4. EXCLUSIONS</h3>
      <p>This Agreement shall not apply to information that:</p>
      <ul style="margin:8px 0 0 22px">
        <li>Was already known to the Receiving Party prior to disclosure, free of any confidentiality obligation, as evidenced by written records;</li>
        <li>Is or becomes publicly available through no breach of this Agreement by the Receiving Party;</li>
        <li>Is independently developed by the Receiving Party without use of or reference to the Confidential Information;</li>
        <li>Is rightfully obtained by the Receiving Party from a third party without breach of any confidentiality obligation;</li>
        <li>Is required to be disclosed by law, regulation, or court order, provided that the Receiving Party gives the Disclosing Party prompt written notice prior to such disclosure, to the extent legally permissible.</li>
      </ul>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">5. NO LICENSE OR TRANSFER OF RIGHTS</h3>
      <p>Nothing in this Agreement shall be construed as granting the Receiving Party any license, right, title, or interest in or to the Confidential Information, including any intellectual property rights, patents, trademarks, or trade secrets of the Disclosing Party. All Confidential Information remains the exclusive property of RYBSEN SARL.</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">6. TERM AND DURATION</h3>
      <p>This Agreement shall take effect on the Effective Date and shall remain in force for a period of five (5) years from the date of signature. The confidentiality obligations set forth herein shall survive the termination or expiration of this Agreement and shall remain binding upon the Receiving Party for the entire duration stated above, regardless of whether any business relationship between the Parties materializes.</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">7. RETURN OR DESTRUCTION OF MATERIALS</h3>
      <p>Upon written request of the Disclosing Party, or upon termination of discussions between the Parties, the Receiving Party shall promptly return or destroy all documents, materials, and copies containing Confidential Information, and shall certify such return or destruction in writing if requested.</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">8. NO OBLIGATION</h3>
      <p>This Agreement does not obligate either Party to enter into any further agreement, investment, partnership, or business relationship. Either Party may terminate discussions at any time, for any reason, without liability, subject to the surviving confidentiality obligations set forth in Section 6.</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">9. REMEDIES</h3>
      <p>The Receiving Party acknowledges that any breach of this Agreement may cause irreparable harm to the Disclosing Party for which monetary damages alone may not be an adequate remedy. Accordingly, the Disclosing Party shall be entitled to seek injunctive relief, in addition to any other remedies available at law or in equity, without the necessity of posting a bond.</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">10. GOVERNING LAW AND JURISDICTION</h3>
      <p>This Agreement shall be governed by and construed in accordance with general principles of international commercial law, in particular the UNIDROIT Principles of International Commercial Contracts, without regard to conflict of law provisions. Any dispute arising out of or in connection with this Agreement shall be finally settled by binding arbitration administered under the Rules of Arbitration of the International Chamber of Commerce (ICC), by a single arbitrator, with the seat of arbitration in Paris, France, and the proceedings conducted in English.</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">11. ELECTRONIC SIGNATURE</h3>
      <p>The Parties agree that this Agreement may be executed electronically and that electronic signatures shall have the same legal validity and binding effect as handwritten signatures, in accordance with applicable electronic signature laws and regulations (including, without limitation, the UNCITRAL Model Law on Electronic Commerce and the eIDAS Regulation, as applicable). This Agreement becomes effective and binding upon completion of electronic signature by the Receiving Party, prior to which no access to Confidential Information shall be granted.</p>

      <h3 style="margin:22px 0 8px;font-size:14px;color:var(--navy-2)">12. ENTIRE AGREEMENT</h3>
      <p>This Agreement constitutes the entire agreement between the Parties with respect to the subject matter hereof and supersedes all prior discussions, negotiations, and agreements, whether oral or written, relating to the confidentiality of the information exchanged between the Parties.</p>

      <div style="margin-top:28px;padding-top:18px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)">
        <strong>DISCLOSING PARTY</strong> — RYBSEN SARL, Yassine Rezgui, Co-founder<br>
        <strong>RECEIVING PARTY</strong> — Electronic signature below
      </div>
      <div id="nda-end"></div>
    </div>
    <div id="scroll-hint" style="background:#FFF8EC;border-top:1px solid #F0D9AE;padding:10px 20px;font-size:12.5px;color:#8a5e1e;text-align:center">
      ⬇ <?= e(t('nda_scroll')) ?>
    </div>
  </div>

  <!-- Signature -->
  <form method="POST" class="dr-card" style="padding:30px 38px;margin-bottom:30px">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['dr_csrf'] ?? '') ?>">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px">
      <div class="dr-field" style="margin-bottom:0">
        <label><?= e(t('nda_fullname')) ?> *</label>
        <input type="text" name="fullname" required value="<?= e(trim(($acc['prenom'] ?? '') . ' ' . $acc['nom'])) ?>">
      </div>
      <div class="dr-field" style="margin-bottom:0">
        <label><?= e(t('nda_org')) ?> *</label>
        <input type="text" name="organisation" required value="<?= e($acc['societe']) ?>">
      </div>
    </div>
    <label style="display:flex;gap:12px;align-items:flex-start;margin:22px 0;font-size:13px;cursor:pointer">
      <input type="checkbox" name="agree" id="agree-box" required disabled style="margin-top:3px;width:17px;height:17px;accent-color:var(--cyan)">
      <span id="agree-text" style="color:var(--muted)"><?= e(t('nda_check')) ?></span>
    </label>
    <button type="submit" class="btn-dr" id="sign-btn" disabled style="width:100%">✍️ <?= e(t('nda_sign')) ?></button>
  </form>
</div>

<script>
(function(){
  const box   = document.getElementById('nda-scroll');
  const agree = document.getElementById('agree-box');
  const btn   = document.getElementById('sign-btn');
  const hint  = document.getElementById('scroll-hint');
  const text  = document.getElementById('agree-text');

  function unlock() {
    agree.disabled = false;
    hint.style.display = 'none';
    text.style.color = 'var(--ink)';
  }
  function check() {
    if (box.scrollTop + box.clientHeight >= box.scrollHeight - 40) unlock();
  }
  box.addEventListener('scroll', check);
  check(); // contenu court sur grand écran

  agree.addEventListener('change', () => { btn.disabled = !agree.checked; });
})();
</script>
<?php drFoot(); ?>
