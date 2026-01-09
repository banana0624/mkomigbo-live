(function () {
  const qs = (sel, root) => (root || document).querySelector(sel);
  const qsa = (sel, root) => Array.from((root || document).querySelectorAll(sel));

  function checks() { return qsa('.js-rowcheck'); }

  const btnAll  = qs('[data-js="select-all"]');
  const btnNone = qs('[data-js="select-none"]');
  const btnInv  = qs('[data-js="select-invert"]');
  const btnNorm = qs('[data-js="normalize"]');

  if (btnAll)  btnAll.addEventListener('click', () => checks().forEach(c => c.checked = true));
  if (btnNone) btnNone.addEventListener('click', () => checks().forEach(c => c.checked = false));
  if (btnInv)  btnInv.addEventListener('click', () => checks().forEach(c => c.checked = !c.checked));

  if (btnNorm) btnNorm.addEventListener('click', () => {
    if (!confirm('Normalize selected rows to 10,20,30...?\nThen click “Save order” to persist.')) return;

    const rows = qsa('#pagesTable tbody tr');
    let v = 10, count = 0;

    rows.forEach(tr => {
      const cb = qs('.js-rowcheck', tr);
      if (!cb || !cb.checked) return;
      const inp = qs('.js-order', tr);
      if (!inp) return;
      inp.value = String(v);
      v += 10;
      count++;
    });

    if (count === 0) alert('Select at least one row to normalize.');
  });
})();
