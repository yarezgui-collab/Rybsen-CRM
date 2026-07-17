<?php // Indicateur de force du mot de passe — attend #pw1, #pw2, #pw-bar, #pw-hint, #pw2-hint ?>
<script>
(function() {
  var pw1 = document.getElementById('pw1');
  var pw2 = document.getElementById('pw2');
  var bar = document.getElementById('pw-bar');
  var hint = document.getElementById('pw-hint');
  var hint2 = document.getElementById('pw2-hint');
  if (!pw1 || !bar) return;

  function score(pw) {
    var s = 0;
    if (pw.length >= 8)  s++;
    if (pw.length >= 12) s++;
    if (/[A-Z]/.test(pw)) s++;
    if (/[0-9]/.test(pw)) s++;
    if (/[^A-Za-z0-9]/.test(pw)) s++;
    return s;
  }

  pw1.addEventListener('input', function() {
    var s = score(this.value);
    var colors = ['#f87171','#fb923c','#facc15','#34d399','#34d399'];
    var labels = ['Très faible','Faible','Moyen','Fort','Très fort'];
    bar.style.width = this.value ? Math.min(100, s * 20) + '%' : '0';
    bar.style.background = colors[Math.max(0, s - 1)] || colors[0];
    hint.textContent = this.value ? labels[Math.max(0, s - 1)] : 'Min. 8 caractères';
    hint.style.color = this.value ? (colors[Math.max(0, s - 1)] || colors[0]) : 'var(--muted)';
    checkMatch();
  });

  if (pw2) pw2.addEventListener('input', checkMatch);

  function checkMatch() {
    if (!pw2 || !hint2) return;
    if (!pw2.value) { hint2.style.color = 'transparent'; return; }
    if (pw1.value === pw2.value) {
      hint2.textContent = '✓ Mots de passe identiques';
      hint2.style.color = '#34d399';
    } else {
      hint2.textContent = '✗ Ne correspondent pas';
      hint2.style.color = '#f87171';
    }
  }
})();
</script>
