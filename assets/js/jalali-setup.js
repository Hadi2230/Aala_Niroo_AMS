// Requires: jQuery, persian-date, persian-datepicker, jalaali-js
(function() {
  function toJalaliStr(g) {
    if (!g || !/^\d{4}-\d{2}-\d{2}$/.test(g)) return '';
    var parts = g.split('-');
    var gy = parseInt(parts[0],10), gm = parseInt(parts[1],10), gd = parseInt(parts[2],10);
    var j = jalaali.toJalaali(gy, gm, gd);
    var pad = function(n){ return (n<10?'0':'')+n; };
    return j.jy + '/' + pad(j.jm) + '/' + pad(j.jd);
  }
  function toGregorianStr(j) {
    if (!j || !/^\d{4}\/(\d{1,2})\/(\d{1,2})$/.test(j)) return '';
    var parts = j.split('/');
    var jy = parseInt(parts[0],10), jm = parseInt(parts[1],10), jd = parseInt(parts[2],10);
    var g = jalaali.toGregorian(jy, jm, jd);
    var pad = function(n){ return (n<10?'0':'')+n; };
    return g.gy + '-' + pad(g.gm) + '-' + pad(g.gd);
  }

  function initPickers(context) {
    var $ctx = context ? $(context) : $(document);
    $ctx.find('input.jalali-date').each(function(){
      var $inp = $(this);
      // If initial value is Gregorian, convert to Jalali for display
      var v = ($inp.val() || '').trim();
      if (/^\d{4}-\d{2}-\d{2}$/.test(v)) {
        var jv = toJalaliStr(v);
        if (jv) $inp.val(jv);
      }
      try {
        $inp.pDatepicker({
          format: 'YYYY/MM/DD',
          autoClose: true,
          persianDigit: true,
        });
      } catch(e) {}
    });

    // On form submit, convert all jalali inputs back to Gregorian
    $ctx.find('form').each(function(){
      var form = this;
      if (form.__jalaliBind) return; // prevent duplicate bind
      form.__jalaliBind = true;
      form.addEventListener('submit', function(){
        $(form).find('input.jalali-date').each(function(){
          var $i = $(this), val = ($i.val()||'').trim();
          if (val.includes('/')) {
            var g = toGregorianStr(val);
            if (g) $i.val(g);
          }
        });
      });
    });
  }

  if (window.jQuery) {
    $(function(){ initPickers(); });
  } else {
    document.addEventListener('DOMContentLoaded', function(){ initPickers(); });
  }
})();

