(function() {
    var SESIJOS_LAIKAS = 30 * 60 * 1000;
    var ISPEJIMO_LAIKAS = 2 * 60 * 1000;
    var sesijosTimer = null;
    var ispejimoTimer = null;
    var ispejimoElementas = null;

    function resetuotiLaikmati() {
        if (sesijosTimer) clearTimeout(sesijosTimer);
        if (ispejimoTimer) clearTimeout(ispejimoTimer);
        pasleptiIspejima();

        ispejimoTimer = setTimeout(function() {
            rodytiIspejima();
        }, SESIJOS_LAIKAS - ISPEJIMO_LAIKAS);

        sesijosTimer = setTimeout(function() {
            window.location.href = '/login.php?sesija_pasibaige=1';
        }, SESIJOS_LAIKAS);
    }

    function rodytiIspejima() {
        if (ispejimoElementas) return;
        ispejimoElementas = document.createElement('div');
        ispejimoElementas.id = 'sesijos-ispejimas';
        ispejimoElementas.style.cssText = 'position:fixed;top:0;left:0;right:0;background:#fff3cd;color:#856404;border-bottom:2px solid #ffc107;padding:12px 20px;text-align:center;z-index:99999;font-family:Inter,sans-serif;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.15);';
        ispejimoElementas.innerHTML = 'Jūsų sesija baigsis po 2 minučių dėl neaktyvumo. Pajudinkite pelę arba paspauskite klavišą, kad pratęstumėte.';
        document.body.appendChild(ispejimoElementas);
    }

    function pasleptiIspejima() {
        if (ispejimoElementas) {
            ispejimoElementas.remove();
            ispejimoElementas = null;
        }
    }

    var aktyvumoIvykiai = ['mousedown', 'mousemove', 'keydown', 'scroll', 'touchstart', 'click'];
    var paskutinisAtnaujinimas = 0;

    function aktyvumoHandler() {
        var dabar = Date.now();
        if (dabar - paskutinisAtnaujinimas < 30000) return;
        paskutinisAtnaujinimas = dabar;
        resetuotiLaikmati();
        fetch('/sesijos_atnaujinimas.php', { method: 'POST', credentials: 'same-origin' })
            .then(function(r) { if (r.status === 401) window.location.href = '/login.php?sesija_pasibaige=1'; })
            .catch(function() {});
    }

    var yraLoginPuslapis = window.location.pathname === '/login.php' || window.location.pathname === '/slaptazodis_atstatymas.php' || window.location.pathname === '/slaptazodis_keitimas.php';

    if (!yraLoginPuslapis) {
        aktyvumoIvykiai.forEach(function(ivykis) {
            document.addEventListener(ivykis, aktyvumoHandler, { passive: true });
        });
        resetuotiLaikmati();
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    // Elementų paieška: meniu mygtukas, šoninė juosta, uždarymo mygtukas
    var menuToggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('sidebar');
    var sidebarClose = document.getElementById('sidebarClose');

    // Šoninės juostos atidarymas paspaudus meniu mygtuką
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.add('open');
        });
    }

    // Šoninės juostos uždarymas paspaudus uždarymo mygtuką
    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', function() {
            sidebar.classList.remove('open');
        });
    }

    // Šoninės juostos uždarymas paspaudus bet kur už jos ribų
    document.addEventListener('click', function(e) {
        if (sidebar && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && e.target !== menuToggle) {
                sidebar.classList.remove('open');
            }
        }
    });
});

/**
 * Atidaro modalinį langą pagal jo ID
 * @param {string} id - Modalinio lango elemento identifikatorius
 */
function openModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
}

/**
 * Uždaro modalinį langą pagal jo ID
 * @param {string} id - Modalinio lango elemento identifikatorius
 */
function closeModal(id) {
    var modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
}

/**
 * Ištrynimo patvirtinimo funkcija - rodo patvirtinimo dialogą ir
 * siunčia POST užklausą su 'delete' veiksmu, jei vartotojas patvirtina
 * @param {string} url - Veiksmo URL, į kurį siunčiama ištrynimo užklausa
 * @param {string} name - Elemento pavadinimas, rodomas patvirtinimo dialoge
 */
function confirmDelete(url, name) {
    if (confirm('Ar tikrai norite ištrinti: ' + name + '?')) {
        // Dinamiškai sukuriama ir pateikiama forma su POST metodu
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'action';
        input.value = 'delete';
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}
